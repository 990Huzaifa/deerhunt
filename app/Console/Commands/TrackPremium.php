<?php

namespace App\Console\Commands;

use App\Models\Premium;
use App\Models\User;
use Illuminate\Console\Command;

class TrackPremium extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:track-premium';

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
        $premiums = Premium::where('status', 'active')->get();
        $expiry_count = 0;
        foreach ($premiums as $premium) {
            // check if the subscription is expired by expires_at date reached
            if(Now()->greaterThan($premium->expires_at)) {
                $expiry_count++;
                $premium->status = 'expired';
                $premium->save();

                // set user id_premium to zero
                $user = User::where('id', $premium->user_id)->update(['id_premium' => 0]);

            }
        }
    }
}
