<?php

namespace App\Console\Commands;

use App\Services\EngagementPushService;
use Illuminate\Console\Command;

class ProcessEngagementPushNotifications extends Command
{
    protected $signature = 'app:process-engagement-push-notifications';

    protected $description = 'Send FCM engagement pushes: first-score (P1), paywall (P2), then feature screens';

    public function handle(EngagementPushService $engagementPushService): int
    {
        $first = $engagementPushService->processFirstScoreReminders();
        $paywall = $engagementPushService->processPaywallReminders();
        $features = $engagementPushService->processFeatureReminders();

        $this->info("P1 first-score pushes sent: {$first}");
        $this->info("P2 paywall pushes sent: {$paywall}");
        $this->info("Feature screen pushes sent: {$features}");

        return self::SUCCESS;
    }
}
