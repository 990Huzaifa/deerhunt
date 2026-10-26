<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\UserEngagement;
use Illuminate\Support\Facades\Log;
use Throwable;

class EngagementEmailService
{
    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
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

        $bodyHtml = $this->mailService->renderTemplate(
            'score-reminder-email',
            $this->reminderTemplateData($user, $reminderType)
        );

        $this->mailService->sendEmail($user->email, $subject, $bodyHtml);

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

        $bodyHtml = $this->mailService->renderTemplate(
            'score-ready-email',
            $this->scoreReadyTemplateData($user)
        );

        $this->mailService->sendEmail(
            $user->email,
            'Your RackScore is waiting 👀',
            $bodyHtml
        );

        return true;
    }

    /**
     * Process due first-score reminders (login + 6h, no first score).
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
            ->whereNull('first_score_reminder_sent_at')
            ->limit(200)
            ->get();

        foreach ($engagements as $engagement) {
            if ($this->dispatchReminder($engagement, 'first')) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Process due second-score reminders (first score + 24h, no second score).
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
            ->whereNull('second_score_reminder_sent_at')
            ->limit(200)
            ->get();

        foreach ($engagements as $engagement) {
            if ($this->dispatchReminder($engagement, 'second')) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * After 2nd score + 15 min, if user still has no active plan → score-ready email.
     */
    public function processScoreReadyEmails(): int
    {
        $sent = 0;
        $cutoff = now()->subMinutes(15);

        $engagements = UserEngagement::query()
            ->with(['user.subscriptions', 'user.premium'])
            ->whereNotNull('second_score_at')
            ->where('second_score_at', '<=', $cutoff)
            ->whereNull('score_ready_email_sent_at')
            ->limit(200)
            ->get();

        foreach ($engagements as $engagement) {
            $user = $engagement->user;

            if (!$user || empty($user->email) || $user->is_delete || !$user->is_active) {
                continue;
            }

            if ($this->userHasActivePlan($user)) {
                // Mark so we don't keep re-checking forever
                $engagement->score_ready_email_sent_at = now();
                $engagement->save();
                continue;
            }

            try {
                $this->sendScoreReady($user);
                $engagement->score_ready_email_sent_at = now();
                $engagement->save();
                $sent++;

                Log::info('Score ready email sent', [
                    'user_id' => $user->id,
                ]);
            } catch (Throwable $e) {
                Log::error('Score ready email failed', [
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

            if ($reminderType === 'second') {
                $engagement->second_score_reminder_sent_at = now();
            } else {
                $engagement->first_score_reminder_sent_at = now();
            }
            $engagement->save();

            Log::info('Score reminder email sent', [
                'user_id' => $user->id,
                'type' => $reminderType,
            ]);

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
