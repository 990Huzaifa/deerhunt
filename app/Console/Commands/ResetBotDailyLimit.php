<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BotConfig;

class ResetBotDailyLimit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-bot-daily-limit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset daily bot action counters';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $updated = BotConfig::query()->update([
            'total_actions_today' => 0,
        ]);

        $this->info("Bot daily limits reset successfully. Updated records: {$updated}");

        return 0;
    }
}