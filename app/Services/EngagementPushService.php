<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEngagement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class EngagementPushService
{
    /** Max pushes per reminder type per cron run. */
    protected const BATCH_LIMIT = 50;

    /** P1: minutes after first login before first-score nudge. */
    protected const FIRST_SCORE_DELAY_MINUTES = 15;

    /** P2: minutes after paywall dismiss before subscribe nudge. */
    protected const PAYWALL_DELAY_MINUTES = 15;

    /** Hours between feature-screen nudges for the same user. */
    protected const FEATURE_COOLDOWN_HOURS = 24;

    /** Hours after first login before feature nudges start. */
    protected const FEATURE_START_DELAY_HOURS = 24;

    /**
     * Users registered before this cutoff were grandfathered via engagement backfill
     * and must not receive the new FCM engagement funnel.
     */
    protected const ENGAGEMENT_PUSH_USER_CUTOFF = '2026-11-16 00:00:00';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * P1: signed up / logged in, no first score within 15 minutes.
     */
    public function processFirstScoreReminders(): int
    {
        $sent = 0;
        $cutoff = now()->subMinutes(self::FIRST_SCORE_DELAY_MINUTES);

        $engagements = UserEngagement::query()
            ->with('user')
            ->whereNotNull('first_login_at')
            ->where('first_login_at', '<=', $cutoff)
            ->whereNull('first_score_at')
            ->whereNull('first_score_reminder_sent_at')
            ->whereHas('user', fn (Builder $q) => $this->wherePushableUser($q))
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        foreach ($engagements as $engagement) {
            if ($this->dispatchPush(
                $engagement,
                'first_score_reminder',
                'Still waiting on your first RackScore?',
                'Got a buck photo? Open RackScore and see what that rack could score.',
                ['type' => 'first_score_reminder', 'screen' => 'score']
            )) {
                $engagement->first_score_reminder_sent_at = now();
                $engagement->save();
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * P2: dismissed paywall without purchasing a subscription.
     */
    public function processPaywallReminders(): int
    {
        $sent = 0;
        $cutoff = now()->subMinutes(self::PAYWALL_DELAY_MINUTES);

        $engagements = UserEngagement::query()
            ->with(['user.subscriptions', 'user.premium'])
            ->whereNotNull('trial_paywall_dismissed_at')
            ->where('trial_paywall_dismissed_at', '<=', $cutoff)
            ->whereNull('paywall_reminder_sent_at')
            ->whereHas('user', fn (Builder $q) => $this->wherePushableUser($q))
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        foreach ($engagements as $engagement) {
            $user = $engagement->user;

            if (!$user || $this->userHasActivePlan($user)) {
                continue;
            }

            if ($this->dispatchPush(
                $engagement,
                'paywall_reminder',
                'Your RackScore is waiting 👀',
                'Reveal your score with a free trial — pick up where you left off.',
                ['type' => 'paywall_reminder', 'screen' => 'subscribe']
            )) {
                $engagement->paywall_reminder_sent_at = now();
                $engagement->save();
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Feature / screen nudges for screens the user has not opened yet.
     */
    public function processFeatureReminders(): int
    {
        $sent = 0;
        $loginCutoff = now()->subHours(self::FEATURE_START_DELAY_HOURS);
        $cooldownBefore = now()->subHours(self::FEATURE_COOLDOWN_HOURS);

        $engagements = UserEngagement::query()
            ->with('user')
            ->whereNotNull('first_login_at')
            ->where('first_login_at', '<=', $loginCutoff)
            ->where(function (Builder $q) use ($cooldownBefore) {
                $q->whereNull('feature_reminder_sent_at')
                    ->orWhere('feature_reminder_sent_at', '<=', $cooldownBefore);
            })
            ->whereHas('user', fn (Builder $q) => $this->wherePushableUser($q))
            ->orderByRaw('feature_reminder_sent_at IS NOT NULL')
            ->orderBy('feature_reminder_sent_at')
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        foreach ($engagements as $engagement) {
            $user = $engagement->user;
            $feature = $this->nextUnusedFeature($engagement, $user);

            if ($feature === null) {
                continue;
            }

            [$title, $body, $screen] = $this->featureCopy($feature);

            if ($this->dispatchPush(
                $engagement,
                'feature_reminder',
                $title,
                $body,
                ['type' => 'feature_reminder', 'feature' => $feature, 'screen' => $screen]
            )) {
                $this->markFeatureReminded($engagement, $feature);
                $sent++;
            }
        }

        return $sent;
    }

    public function userHasActivePlan(User $user): bool
    {
        if ($user->is_premium) {
            return true;
        }

        if ($user->subscriptions()->where('status', 'active')->exists()) {
            return true;
        }

        return $user->premium()->where('status', 'active')->exists();
    }

    protected function wherePushableUser(Builder $query): Builder
    {
        return $query
            ->where('created_at', '>=', self::ENGAGEMENT_PUSH_USER_CUTOFF)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('is_delete', false)
            ->where('is_active', 'active');
    }

    protected function dispatchPush(
        UserEngagement $engagement,
        string $type,
        string $title,
        string $body,
        array $data
    ): bool {
        $user = $engagement->user;

        if (!$user || empty($user->fcm_token) || $user->is_delete || $user->is_active !== 'active') {
            return false;
        }

        try {
            $payload = [];
            foreach ($data as $key => $value) {
                $payload[$key] = is_scalar($value) || $value === null
                    ? (string) $value
                    : json_encode($value);
            }

            $this->notificationService->send(
                $user->id,
                $user->id,
                $type,
                $title,
                $body,
                $payload
            );

            return true;
        } catch (Throwable $e) {
            Log::error('Engagement push notification failed', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function nextUnusedFeature(UserEngagement $engagement, ?User $user = null): ?string
    {
        $usage = $engagement->feature_usage ?: UserEngagement::defaultFeatureUsage();
        $skipSubscribe = $engagement->paywall_reminder_sent_at !== null
            || ($user && $this->userHasActivePlan($user));

        foreach (UserEngagement::FEATURE_KEYS as $key) {
            if ($key === 'subscribe' && $skipSubscribe) {
                continue;
            }

            $entry = $usage[$key] ?? null;

            if (!is_array($entry)) {
                $entry = ['used' => false, 'at' => null];
            }

            $used = !empty($entry['used']);
            $alreadyReminded = !empty($entry['reminded_at']);

            if (!$used && !$alreadyReminded) {
                return $key;
            }
        }

        return null;
    }

    protected function markFeatureReminded(UserEngagement $engagement, string $feature): void
    {
        $usage = $engagement->feature_usage ?: UserEngagement::defaultFeatureUsage();

        foreach (UserEngagement::FEATURE_KEYS as $key) {
            if (!isset($usage[$key]) || !is_array($usage[$key])) {
                $usage[$key] = ['used' => false, 'at' => null];
            }
        }

        $usage[$feature]['reminded_at'] = now()->toIso8601String();
        $engagement->feature_usage = $usage;
        $engagement->feature_reminder_sent_at = now();
        $engagement->save();
    }

    /**
     * @return array{0: string, 1: string, 2: string} title, body, screen
     */
    protected function featureCopy(string $feature): array
    {
        return match ($feature) {
            'trophy_room' => [
                'Check out your Trophy Room',
                'See your scored bucks in one place — open Trophy Room.',
                'trophy_room',
            ],
            'leaderboard' => [
                'How do you rank?',
                'Jump into the leaderboard and see where your rack stands.',
                'leaderboard',
            ],
            'feed' => [
                'See what hunters are scoring',
                'Open the feed for fresh bucks from the community.',
                'feed',
            ],
            'comments' => [
                'Join the conversation',
                'Leave a comment on a buck and connect with other hunters.',
                'comments',
            ],
            'likes' => [
                'Show some love',
                'Like a great rack in the feed — it only takes a tap.',
                'likes',
            ],
            'follow' => [
                'Follow fellow hunters',
                'Follow hunters you like and keep up with their scores.',
                'follow',
            ],
            'profile_edit' => [
                'Make your profile yours',
                'Add a photo and bio so other hunters know who you are.',
                'profile_edit',
            ],
            'buck_gallery' => [
                'Browse the Buck Gallery',
                'Explore bucks from around the map in Buck Gallery.',
                'buck_gallery',
            ],
            'scored_gallery' => [
                'See scored bucks',
                'Open Scored Gallery for racks with numbers attached.',
                'scored_gallery',
            ],
            'subscribe' => [
                'Unlock the full hunt',
                'Try RackScore premium and unlock scoring + extras.',
                'subscribe',
            ],
            default => [
                'Explore RackScore',
                'There’s more to check out in the app — take a look.',
                $feature,
            ],
        };
    }
}
