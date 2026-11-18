<?php

namespace App\Services;

use App\Models\UserEngagement;
use Illuminate\Support\Facades\Cache;

class EngagementPushTestService
{
    protected const CACHE_PREFIX = 'engagement_push_test_index:';

    protected const CACHE_TTL_HOURS = 24;

    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send the next engagement push in sequence for this FCM token, then advance the cursor.
     *
     * @return array{event: array, index: int, total: int, next_index: int, fcm_result: array}
     */
    public function sendNext(string $fcmToken): array
    {
        $events = $this->events();
        $total = count($events);
        $index = $this->currentIndex($fcmToken);
        $event = $events[$index];

        $data = [];
        foreach ($event['data'] as $key => $value) {
            $data[$key] = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value);
        }

        $fcmResult = $this->firebaseService->sendToDevice(
            $fcmToken,
            $event['title'],
            $event['body'],
            $data
        );

        $nextIndex = ($index + 1) % $total;
        $this->setIndex($fcmToken, $nextIndex);

        return [
            'event' => $event,
            'index' => $index,
            'total' => $total,
            'next_index' => $nextIndex,
            'next_event' => $events[$nextIndex]['key'],
            'fcm_result' => $fcmResult,
        ];
    }

    /**
     * Reset sequence so the next call sends P1 again.
     */
    public function reset(string $fcmToken): void
    {
        Cache::forget($this->cacheKey($fcmToken));
    }

    /**
     * Full ordered catalog: P1 → P2 → feature screens.
     *
     * @return list<array{key: string, type: string, title: string, body: string, data: array}>
     */
    public function events(): array
    {
        $events = [
            [
                'key' => 'first_score_reminder',
                'type' => 'first_score_reminder',
                'title' => 'Still waiting on your first RackScore?',
                'body' => 'Got a buck photo? Open RackScore and see what that rack could score.',
                'data' => ['type' => 'first_score_reminder', 'screen' => 'score'],
            ],
            [
                'key' => 'paywall_reminder',
                'type' => 'paywall_reminder',
                'title' => 'Your RackScore is waiting 👀',
                'body' => 'Reveal your score with a free trial — pick up where you left off.',
                'data' => ['type' => 'paywall_reminder', 'screen' => 'subscribe'],
            ],
        ];

        foreach (UserEngagement::FEATURE_KEYS as $feature) {
            [$title, $body, $screen] = $this->featureCopy($feature);
            $events[] = [
                'key' => "feature_{$feature}",
                'type' => 'feature_reminder',
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'feature_reminder',
                    'feature' => $feature,
                    'screen' => $screen,
                ],
            ];
        }

        return $events;
    }

    protected function currentIndex(string $fcmToken): int
    {
        $total = count($this->events());
        $index = (int) Cache::get($this->cacheKey($fcmToken), 0);

        if ($index < 0 || $index >= $total) {
            return 0;
        }

        return $index;
    }

    protected function setIndex(string $fcmToken, int $index): void
    {
        Cache::put(
            $this->cacheKey($fcmToken),
            $index,
            now()->addHours(self::CACHE_TTL_HOURS)
        );
    }

    protected function cacheKey(string $fcmToken): string
    {
        return self::CACHE_PREFIX . hash('sha256', $fcmToken);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
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
