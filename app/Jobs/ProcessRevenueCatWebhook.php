<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRevenueCatWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $event = $this->payload['event'] ?? $this->payload;

        if (!is_array($event) || empty($event['type'])) {
            Log::warning('RevenueCat webhook missing event type', ['payload' => $this->payload]);
            return;
        }

        $type = strtoupper((string) $event['type']);

        if ($type === 'TEST') {
            Log::info('RevenueCat TEST event received');
            return;
        }

        $user = $this->resolveUser($event);
        if (!$user) {
            Log::warning('RevenueCat webhook: user not found', [
                'app_user_id' => $event['app_user_id'] ?? null,
                'type' => $type,
            ]);
            return;
        }

        $productId = $event['product_id'] ?? null;
        $plan = $this->normalizePlan($productId);
        $transactionId = $event['original_transaction_id']
            ?? $event['transaction_id']
            ?? null;

        $expiresAt = null;
        if (!empty($event['expiration_at_ms'])) {
            $expiresAt = Carbon::createFromTimestampMs((int) $event['expiration_at_ms']);
        }

        $platform = $this->mapPlatform($event['store'] ?? null);
        $renewalPeriod = $this->mapRenewalPeriod($plan ?? $productId);

        $subscription = null;
        if ($transactionId) {
            $subscription = Subscription::where('transaction_id', $transactionId)->first();
        }
        if (!$subscription) {
            $subscription = Subscription::where('user_id', $user->id)->first();
        }

        switch ($type) {
            case 'INITIAL_PURCHASE':
            case 'RENEWAL':
            case 'UNCANCELLATION':
            case 'PRODUCT_CHANGE':
            case 'NON_RENEWING_PURCHASE':
                if (!$plan) {
                    Log::warning('RevenueCat webhook: unsupported plan', [
                        'product_id' => $productId,
                        'user_id' => $user->id,
                        'type' => $type,
                    ]);
                    return;
                }

                $data = [
                    'user_id' => $user->id,
                    'plan' => $plan,
                    'platform' => $platform,
                    'status' => 'active',
                    'renewal_period' => $renewalPeriod,
                    'transaction_id' => $transactionId,
                    'expires_at' => $expiresAt,
                    'canceled_at' => null,
                ];

                if ($subscription) {
                    $subscription->update($data);
                } else {
                    Subscription::create($data);
                }
                break;

            case 'CANCELLATION':
                if (!$subscription) {
                    Log::warning('RevenueCat CANCELLATION: subscription not found', [
                        'user_id' => $user->id,
                        'transaction_id' => $transactionId,
                    ]);
                    return;
                }
                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'expires_at' => $expiresAt ?? $subscription->expires_at,
                ]);
                break;

            case 'EXPIRATION':
                if (!$subscription) {
                    Log::warning('RevenueCat EXPIRATION: subscription not found', [
                        'user_id' => $user->id,
                        'transaction_id' => $transactionId,
                    ]);
                    return;
                }
                $subscription->update([
                    'status' => 'expired',
                    'expires_at' => $expiresAt ?? now(),
                ]);
                break;

            default:
                Log::info('RevenueCat event ignored', ['type' => $type]);
                break;
        }
    }

    private function resolveUser(array $event): ?User
    {
        $appUserId = $event['app_user_id'] ?? null;

        if ($appUserId !== null && $appUserId !== '' && strtolower((string) $appUserId) !== 'anonymous') {
            // 1) Try as Laravel users.id
            if (is_numeric($appUserId)) {
                $user = User::find((int) $appUserId);
                if ($user) {
                    return $user;
                }
            }

            // 2) Try as email (app_user_id itself)
            if (filter_var($appUserId, FILTER_VALIDATE_EMAIL)) {
                $user = User::where('email', $appUserId)->first();
                if ($user) {
                    return $user;
                }
            }
        }

        // 3) Fallback: subscriber email attribute / top-level email
        $email = $event['subscriber_attributes']['$email']['value']
            ?? $event['subscriber_attributes']['email']['value']
            ?? $event['email']
            ?? null;

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $email)->first();
        }

        return null;
    }

    private function normalizePlan(?string $productId): ?string
    {
        if (!$productId) {
            return null;
        }

        $allowed = [
            'elite-yearly',
            'elite-monthly',
            'pro-yearly',
            'pro-monthly',
            'elite_yearly',
            'elite_monthly',
            'pro_yearly',
            'pro_monthly',
        ];

        // Google/RC often sends "subscription_plans:elite-yearly"
        $candidate = $productId;
        if (str_contains($productId, ':')) {
            $candidate = substr($productId, strrpos($productId, ':') + 1);
        }

        $candidate = strtolower(trim($candidate));

        if (in_array($candidate, $allowed, true)) {
            return $candidate;
        }

        // Try hyphen <-> underscore variants
        $hyphen = str_replace('_', '-', $candidate);
        if (in_array($hyphen, $allowed, true)) {
            return $hyphen;
        }

        $underscore = str_replace('-', '_', $candidate);
        if (in_array($underscore, $allowed, true)) {
            return $underscore;
        }

        return null;
    }

    private function mapPlatform(?string $store): string
    {
        $store = strtoupper((string) $store);

        return match ($store) {
            'PLAY_STORE', 'GOOGLE', 'GOOGLE_PLAY' => 'google',
            'APP_STORE', 'APPLE', 'MAC_APP_STORE', 'IOS' => 'apple',
            default => 'apple',
        };
    }

    private function mapRenewalPeriod(?string $productId): string
    {
        $productId = strtolower((string) $productId);

        if (str_contains($productId, 'year') || str_contains($productId, 'annual')) {
            return 'yearly';
        }

        return 'monthly';
    }
}
