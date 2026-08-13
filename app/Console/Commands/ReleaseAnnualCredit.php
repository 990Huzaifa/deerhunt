<?php

namespace App\Console\Commands;

use App\Models\CreditsWallet;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReleaseAnnualCredit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
     protected $signature = 'app:release-annual-credit';
    protected $description = 'Release 10 monthly credits for users on annual credit plan (runs daily but releases only every 30 days per user).';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $subscriptions = Subscription::whereIn('plan',['basic_cred_yearly','basic-credt-yearly'] )->where('status', 'active')->get();
        $count = 0;
        foreach ($subscriptions as $subscription) {

            if ($subscription->released_credits >= $subscription->total_credits) {
                continue;
            }

            $lastRelease = Carbon::parse($subscription->last_released_at);

            // ⏱ Check if 30+ days passed since last release
            $daysPassed = $lastRelease->diffInDays(Carbon::now());
            if ($daysPassed < 30) {
                continue; // skip — not yet 30 days
            }


            $count++;
            DB::transaction(function () use ($subscription) {
                // Wallet update
                $wallet = CreditsWallet::where('user_id', $subscription->user_id)->first();
                $wallet->update([
                    'paid_credits' => 10
                ]);


                // Subscription update
                $subscription->update([
                    'released_credits' => $subscription->released_credits + $subscription->credits_per_month,
                    'last_released_at' => Carbon::now(),
                ]);
            });
        }
        // with count
        $this->info('✅ Released monthly credits for eligible annual plans.' . $count);
    }
}
