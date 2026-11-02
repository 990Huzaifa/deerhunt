<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\UserEngagement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class EngagementEmailService
{
    /** Max emails per reminder type per cron run (keeps job under the 5-min schedule). */
    protected const BATCH_LIMIT = 20;

    /** Min minutes between resends to the same user for the same reminder type. */
    protected const REMINDER_COOLDOWN_MINUTES = 60;

    /** Fetch more rows than BATCH_LIMIT so cooldown-skipped users don't block the queue. */
    protected const CANDIDATE_LIMIT = 100;

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
     * Process due first-score reminders (login + 6h, no first score).
     * Resends while first_score_at is null, with cooldown between sends.
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
            ->orderBy('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        foreach ($engagements as $engagement) {
            if ($sent >= self::BATCH_LIMIT) {
                break;
            }

            if (!$this->canSendEngagementEmail($engagement, 'first_score')) {
                continue;
            }

            if ($this->dispatchReminder($engagement, 'first')) {
                $this->markEngagementEmailSent($engagement, 'first_score');
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Process due second-score reminders (first score + 24h, no second score).
     * Resends while second_score_at is null, with cooldown between sends.
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
            ->orderBy('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        foreach ($engagements as $engagement) {
            if ($sent >= self::BATCH_LIMIT) {
                break;
            }

            if (!$this->canSendEngagementEmail($engagement, 'second_score')) {
                continue;
            }

            if ($this->dispatchReminder($engagement, 'second')) {
                $this->markEngagementEmailSent($engagement, 'second_score');
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Case 3: after second score is done, if trial paywall was never dismissed → subscribe email.
     * Resends while trial_paywall_dismissed_at is null, with cooldown between sends.
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
            ->orderBy('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        foreach ($engagements as $engagement) {
            if ($sent >= self::BATCH_LIMIT) {
                break;
            }

            if (!$this->canSendEngagementEmail($engagement, 'score_ready')) {
                continue;
            }

            $user = $engagement->user;

            if (!$user || empty($user->email) || $user->is_delete || !$user->is_active) {
                continue;
            }

            // Already subscribed — skip (paywall dismiss / plan will stop this case)
            if ($this->userHasActivePlan($user)) {
                continue;
            }

            try {
                $this->sendScoreReady($user);
                $this->markEngagementEmailSent($engagement, 'score_ready');
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
     * Cooldown tracker lives in feature_usage (no extra DB columns).
     *
     * @param  'first_score'|'second_score'|'score_ready'  $type
     */
    protected function canSendEngagementEmail(UserEngagement $engagement, string $type): bool
    {
        $lastAt = data_get($engagement->feature_usage, "email_reminders.{$type}.at");

        if (empty($lastAt)) {
            return true;
        }

        try {
            return Carbon::parse($lastAt)->lte(now()->subMinutes(self::REMINDER_COOLDOWN_MINUTES));
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param  'first_score'|'second_score'|'score_ready'  $type
     */
    protected function markEngagementEmailSent(UserEngagement $engagement, string $type): void
    {
        $usage = $engagement->feature_usage ?: UserEngagement::defaultFeatureUsage();
        $usage['email_reminders'][$type] = [
            'at' => now()->toIso8601String(),
        ];
        $engagement->feature_usage = $usage;
        $engagement->save();
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
