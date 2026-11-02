<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\UserEngagement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class EngagementEmailService
{
    /** Max emails per reminder type per cron run (keeps job under the 5-min schedule). */
    protected const BATCH_LIMIT = 20;

    /** Hours before the same user can receive the same reminder again. */
    protected const REMINDER_COOLDOWN_HOURS = 24;

    protected MailingApiService $mailingApi;

    public function __construct(MailingApiService $mailingApi)
    {
        $this->mailingApi = $mailingApi;
    }

    /**
     * Send score-reminder email for first (6h) or second (24h) score nudge.
     *
     * @param  'first'|'second'  $reminderType
     */
    public function sendScoreReminder(User $user, string $reminderType = 'first'): bool
    {
        if (empty($user->email)) {
            return false;
        }

        $subject = $reminderType === 'second'
            ? 'Ready for your next RackScore?'
            : 'Still waiting on your first RackScore';

        $bodyHtml = $this->mailingApi->renderTemplate(
            'score-reminder-email',
            $this->reminderTemplateData($user, $reminderType)
        );

        $this->mailingApi->send(
            $user->email,
            $subject,
            $bodyHtml,
            $user->full_name ?: $user->username ?: 'Hunter'
        );

        return true;
    }

    /**
     * Send score-ready (locked reveal) email after 2nd score without plan.
     */
    public function sendScoreReady(User $user): bool
    {
        if (empty($user->email)) {
            return false;
        }

        $bodyHtml = $this->mailingApi->renderTemplate(
            'score-ready-email',
            $this->scoreReadyTemplateData($user)
        );

        $this->mailingApi->send(
            $user->email,
            'Your RackScore is waiting 👀',
            $bodyHtml,
            $user->full_name ?: $user->username ?: 'Hunter'
        );

        return true;
    }

    /**
     * Case 1: login + 6h, no first score.
     * Resend only after 24h cooldown via first_score_reminder_sent_at.
     */
    public function processFirstScoreReminders(): int
    {
        $sent = 0;
        $cutoff = now()->subHours(6);

        $engagements = UserEngagement::query()
            ->with('user')
            ->whereNotNull('first_login_at')
            ->where('first_login_at', '<=', $cutoff)
            ->whereNull('first_score_at')
            ->tap(fn (Builder $q) => $this->whereReminderDue($q, 'first_score_reminder_sent_at'))
            ->orderByRaw('first_score_reminder_sent_at IS NOT NULL')
            ->orderBy('first_score_reminder_sent_at')
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        foreach ($engagements as $engagement) {
            if ($this->dispatchReminder($engagement, 'first')) {
                $engagement->first_score_reminder_sent_at = now();
                $engagement->save();
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Case 2: first score + 24h, no second score.
     * Resend only after 24h cooldown via second_score_reminder_sent_at.
     */
    public function processSecondScoreReminders(): int
    {
        $sent = 0;
        $cutoff = now()->subHours(24);

        $engagements = UserEngagement::query()
            ->with('user')
            ->whereNotNull('first_score_at')
            ->where('first_score_at', '<=', $cutoff)
            ->whereNull('second_score_at')
            ->tap(fn (Builder $q) => $this->whereReminderDue($q, 'second_score_reminder_sent_at'))
            ->orderByRaw('second_score_reminder_sent_at IS NOT NULL')
            ->orderBy('second_score_reminder_sent_at')
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        foreach ($engagements as $engagement) {
            if ($this->dispatchReminder($engagement, 'second')) {
                $engagement->second_score_reminder_sent_at = now();
                $engagement->save();
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Case 3: after second score, paywall not dismissed → subscribe / score-ready email.
     * Resend only after 24h cooldown via score_ready_email_sent_at.
     */
    public function processScoreReadyEmails(): int
    {
        $sent = 0;
        $cutoff = now()->subMinutes(15);

        $engagements = UserEngagement::query()
            ->with(['user.subscriptions', 'user.premium'])
            ->whereNotNull('second_score_at')
            ->where('second_score_at', '<=', $cutoff)
            ->whereNull('trial_paywall_dismissed_at')
            ->tap(fn (Builder $q) => $this->whereReminderDue($q, 'score_ready_email_sent_at'))
            ->orderByRaw('score_ready_email_sent_at IS NOT NULL')
            ->orderBy('score_ready_email_sent_at')
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        foreach ($engagements as $engagement) {
            $user = $engagement->user;

            if (!$user || empty($user->email) || $user->is_delete || !$user->is_active) {
                continue;
            }

            if ($this->userHasActivePlan($user)) {
                continue;
            }

            try {
                $this->sendScoreReady($user);
                $engagement->score_ready_email_sent_at = now();
                $engagement->save();
                $sent++;
            } catch (Throwable $e) {
                Log::error('Score ready / subscribe email failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    public function userHasActivePlan(User $user): bool
    {
        if ($user->is_premium) {
            return true;
        }

        $hasActiveSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->exists();

        if ($hasActiveSubscription) {
            return true;
        }

        return $user->premium()
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Never sent, or last sent at least REMINDER_COOLDOWN_HOURS ago.
     */
    protected function whereReminderDue(Builder $query, string $column): Builder
    {
        $cooldownBefore = now()->subHours(self::REMINDER_COOLDOWN_HOURS);

        return $query->where(function (Builder $q) use ($column, $cooldownBefore) {
            $q->whereNull($column)
                ->orWhere($column, '<=', $cooldownBefore);
        });
    }

    /**
     * @param  'first'|'second'  $reminderType
     */
    protected function dispatchReminder(UserEngagement $engagement, string $reminderType): bool
    {
        $user = $engagement->user;

        if (!$user || empty($user->email) || $user->is_delete || !$user->is_active) {
            return false;
        }

        try {
            $this->sendScoreReminder($user, $reminderType);

            return true;
        } catch (Throwable $e) {
            Log::error('Score reminder email failed', [
                'user_id' => $user->id,
                'type' => $reminderType,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  'first'|'second'  $reminderType
     */
    protected function reminderTemplateData(User $user, string $reminderType): array
    {
        return array_merge($this->commonMailData($user), [
            'reminderType' => $reminderType,
            'heroImageUrl' => null,
        ]);
    }

    protected function scoreReadyTemplateData(User $user): array
    {
        $post = Post::query()
            ->where('user_id', $user->id)
            ->where('is_delete', false)
            ->whereNull('ref_id')
            ->latest('created_at')
            ->first();

        $postImage = null;
        if ($post && !empty($post->image)) {
            $firstPath = explode(',', $post->image)[0];
            $postImage = asset(ltrim($firstPath, '/'));
        }

        $logoUrl = $this->commonMailData($user)['logoUrl'];

        $leaders = Post::query()
            ->select('posts.score', 'users.full_name', 'users.username', 'users.avatar')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->where('posts.is_delete', false)
            ->whereNull('posts.ref_id')
            ->where('posts.is_trophy', true)
            ->orderByDesc('posts.score')
            ->limit(3)
            ->get()
            ->map(function ($row) {
                $avatar = $row->avatar
                    ? asset(ltrim($row->avatar, '/'))
                    : asset('logo.png');

                return [
                    'name' => $row->full_name ?: $row->username ?: 'Hunter',
                    'avatar_url' => $avatar,
                    'score_display' => $row->score !== null ? (string) $row->score : '—',
                ];
            })
            ->all();

        return array_merge($this->commonMailData($user), [
            'hunterEmail' => $user->email,
            'species' => $user->species ?: 'Whitetail deer',
            'lockedResultImageUrl' => $postImage ?: $logoUrl,
            'deerImageUrl' => $postImage ?: $logoUrl,
            'rockEmojiUrl' => $logoUrl,
            'leaders' => $leaders,
        ]);
    }

    protected function commonMailData(User $user): array
    {
        $logoPath = public_path('logo.png');
        $appUrl = config('app.url');

        return [
            'hunterName' => $user->full_name ?: $user->username ?: 'there',
            'logoUrl' => file_exists($logoPath) ? asset('logo.png') : null,
            'appUrl' => $appUrl,
            'websiteUrl' => $appUrl,
            'supportUrl' => $appUrl,
            'unsubscribeUrl' => $appUrl,
            'year' => (int) date('Y'),
            'appName' => config('app.name'),
        ];
    }
}
