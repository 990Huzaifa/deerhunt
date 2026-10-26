<?php

namespace App\Console\Commands;

use App\Services\EngagementEmailService;
use Illuminate\Console\Command;

class ProcessScoreReminderEmails extends Command
{
    protected $signature = 'app:process-score-reminder-emails';

    protected $description = 'Send score reminder + score-ready (no plan) engagement emails';

    public function handle(EngagementEmailService $engagementEmailService): int
    {
        $first = $engagementEmailService->processFirstScoreReminders();
        $second = $engagementEmailService->processSecondScoreReminders();
        $ready = $engagementEmailService->processScoreReadyEmails();

        $this->info("First-score reminders sent: {$first}");
        $this->info("Second-score reminders sent: {$second}");
        $this->info("Score-ready emails sent: {$ready}");

        return self::SUCCESS;
    }
}
