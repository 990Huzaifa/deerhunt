<?php

namespace App\Console\Commands;

use App\Models\CreditsWallet;
use Illuminate\Console\Command;

class ResetCredit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-credit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // here we will reset the credit of all users to 10 every month(so check if updated_at is more than 30 days ago)
        
        $wallets = CreditsWallet::where('unlimited_active', false)->get();
        $count = 0;
        foreach ($wallets as $wallet) {
            if ($wallet->updated_at->diffInDays(now()) >= 30) {
                $wallet->free_credits = 5;
                $wallet->save();

                // log
            }
            $count++;
        }
        $this->info("Updated $count wallets");
    }
}
