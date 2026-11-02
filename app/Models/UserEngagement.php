<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEngagement extends Model
{
    use HasFactory;

    public const FEATURE_KEYS = [
        'trophy_room',
        'leaderboard',
        'feed',
        'comments',
        'likes',
        'follow',
        'profile_edit',
        'subscribe',
    ];

    protected $fillable = [
        'user_id',
        'first_login_at',
        'first_score_at',
        'second_score_at',
        'trial_paywall_dismissed_at',
        'feature_usage',
    ];

    protected $casts = [
        'first_login_at' => 'datetime',
        'first_score_at' => 'datetime',
        'second_score_at' => 'datetime',
        'trial_paywall_dismissed_at' => 'datetime',
        'feature_usage' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasCompleted(string $event): bool
    {
        return match ($event) {
            'first_login' => $this->first_login_at !== null,
            'first_score' => $this->first_score_at !== null,
            'second_score' => $this->second_score_at !== null,
            'trial_paywall_dismissed' => $this->trial_paywall_dismissed_at !== null,
            default => false,
        };
    }

    public function hasUsedFeature(string $feature): bool
    {
        $usage = $this->feature_usage[$feature] ?? null;

        return is_array($usage) && !empty($usage['used']);
    }

    public function unusedFeatures(): array
    {
        $unused = [];

        foreach (self::FEATURE_KEYS as $key) {
            if (!$this->hasUsedFeature($key)) {
                $unused[] = $key;
            }
        }

        return $unused;
    }

    public static function defaultFeatureUsage(): array
    {
        $defaults = [];

        foreach (self::FEATURE_KEYS as $key) {
            $defaults[$key] = [
                'used' => false,
                'at' => null,
            ];
        }

        return $defaults;
    }
}
