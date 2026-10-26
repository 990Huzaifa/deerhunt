<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEngagement;
use InvalidArgumentException;

class EngagementTrackingService
{
    public function ensureFor(User $user): UserEngagement
    {
        $engagement = $user->engagement;

        if ($engagement) {
            return $engagement;
        }

        return UserEngagement::create([
            'user_id' => $user->id,
            'feature_usage' => UserEngagement::defaultFeatureUsage(),
        ]);
    }

    public function recordLogin(User $user): UserEngagement
    {
        $engagement = $this->ensureFor($user);

        if ($engagement->first_login_at === null) {
            $engagement->first_login_at = now();
            $engagement->save();
        }

        return $engagement->fresh();
    }

    public function recordScore(User $user): UserEngagement
    {
        $engagement = $this->ensureFor($user);
        $count = (int) $user->analysis_count;
        $dirty = false;

        if ($count >= 1 && $engagement->first_score_at === null) {
            $engagement->first_score_at = now();
            $dirty = true;
        }

        if ($count >= 2 && $engagement->second_score_at === null) {
            $engagement->second_score_at = now();
            $dirty = true;
        }

        if ($dirty) {
            $engagement->save();
        }

        return $engagement->fresh();
    }

    public function recordPaywallDismiss(User $user): UserEngagement
    {
        $engagement = $this->ensureFor($user);

        if ($engagement->trial_paywall_dismissed_at === null) {
            $engagement->trial_paywall_dismissed_at = now();
            $engagement->save();
        }

        return $engagement->fresh();
    }

    public function recordFeatureUsed(User $user, string $feature): UserEngagement
    {
        if (!in_array($feature, UserEngagement::FEATURE_KEYS, true)) {
            throw new InvalidArgumentException("Unknown feature: {$feature}");
        }

        $engagement = $this->ensureFor($user);
        $usage = $engagement->feature_usage ?: UserEngagement::defaultFeatureUsage();

        foreach (UserEngagement::FEATURE_KEYS as $key) {
            if (!isset($usage[$key]) || !is_array($usage[$key])) {
                $usage[$key] = ['used' => false, 'at' => null];
            }
        }

        if (empty($usage[$feature]['used'])) {
            $usage[$feature] = [
                'used' => true,
                'at' => now()->toIso8601String(),
            ];
            $engagement->feature_usage = $usage;
            $engagement->save();
        }

        return $engagement->fresh();
    }

    public function getState(User $user): array
    {
        $engagement = $this->ensureFor($user);
        $usage = $engagement->feature_usage ?: UserEngagement::defaultFeatureUsage();

        foreach (UserEngagement::FEATURE_KEYS as $key) {
            if (!isset($usage[$key]) || !is_array($usage[$key])) {
                $usage[$key] = ['used' => false, 'at' => null];
            }
        }

        return [
            'first_login_at' => $engagement->first_login_at?->toIso8601String(),
            'first_score_at' => $engagement->first_score_at?->toIso8601String(),
            'second_score_at' => $engagement->second_score_at?->toIso8601String(),
            'trial_paywall_dismissed_at' => $engagement->trial_paywall_dismissed_at?->toIso8601String(),
            'is_first_score' => $engagement->first_score_at !== null,
            'is_second_score' => $engagement->second_score_at !== null,
            'is_trial_paywall_dismissed' => $engagement->trial_paywall_dismissed_at !== null,
            'feature_usage' => $usage,
        ];
    }
}
