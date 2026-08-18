<?php

namespace App\Jobs;

use App\Models\Premium;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AppStoreConnectAuth;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;



class ProcessAppleNotificationV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $signedPayload;
    /**
     * Create a new job instance.
     */
    public function __construct($signedPayload)
    {
        $this->signedPayload = $signedPayload;
    }

    /**
     * Execute the job.
     */
    public function handle(AppStoreConnectAuth $auth): void
    {
        $decodedNotification = $auth->JWSParse($this->signedPayload);
        
        // $decodedNotification = $this->signedPayload;
        $data = $decodedNotification['data'];
        $subtype = $decodedNotification['subtype'] ?? null;
        $notificationType = $decodedNotification['notificationType'];
        // this has to be pars from JWS
        $signedTransactionInfo = $data['signedTransactionInfo'];
        $decodedTransactionInfo = $auth->JWSParse($signedTransactionInfo);
        Log::info('Decoded Notification:', ['transactionInfo' => $decodedTransactionInfo,'notification' => $decodedNotification]);
        // update logic here

        $planConfig = [
                'premium_monthly'         => ['duration' => 'monthly', "is_premium" => true],
                'premium_yearly'          => ['duration' => 'yearly', "is_premium" => true],
                'unlimited_cred_monthly'  => ['duration' => 'monthly', "is_premium" => false],
                'unlimited_cred_yearly'   => ['duration' => 'yearly', "is_premium" => false],
            ];

        $productId = $decodedTransactionInfo['productId'];
        $plan = $planConfig[$productId] ?? null;
        // Log::error('App Store V2 Notification Job Start.');

        if($plan['is_premium']){
            $this->premium($notificationType, $subtype, $decodedTransactionInfo, $productId, $plan);
        }
        else{
            $this->subscription($notificationType, $subtype, $decodedTransactionInfo, $productId, $plan);
        }


    }


    private function subscription($notificationType, $subtype, $decodedTransactionInfo, $productId, $plan)
    {
        if($notificationType == "DID_RENEW"){
            $subscription = Subscription::where('transaction_id', $decodedTransactionInfo['originalTransactionId'])->where('platform', 'apple')->first();
            if($subscription){
                $subscription->update([
                    'plan'       => $productId,
                    'expires_at' => $decodedTransactionInfo['expireDateFormatted'],
                    'status'     => 'active',
                    'canceled_at' => null,
                ]);
            }
        }
        elseif($notificationType == "EXPIRED"){
            $subscription = Subscription::where('transaction_id', $decodedTransactionInfo['originalTransactionId'])->first();
            if($subscription){
                $subscription->update([
                    'status' => 'expired',
                ]);
            }
        }
        elseif($notificationType == "DID_CHANGE_RENEWAL_STATUS"){
            if($subtype && $subtype == "AUTO_RENEW_DISABLED"){
                $subscription = Subscription::where('transaction_id', $decodedTransactionInfo['originalTransactionId'])->first();
                if($subscription){
                    $subscription->update([
                        'canceled_at' => Carbon::now(),
                    ]);
                }
            }
        }
    }


    private function premium($notificationType, $subtype, $decodedTransactionInfo, $productId, $plan)
    {   
        $premium = Premium::where('transaction_id', $decodedTransactionInfo['originalTransactionId'])->where('platform', 'apple')->first();
        if($notificationType == "DID_RENEW"){
            if($premium){
                $premium->update([
                    'plan'              => $productId,
                    'expires_at'        => $decodedTransactionInfo['expireDateFormatted'],
                    'status'            => 'active',
                    'canceled_at'      => null,
                ]);

                $user = User::find($premium->user_id);
                if($user){
                    $user->update([
                        'is_premium' => true,
                    ]);
                }

            }
            
        }
        elseif($notificationType == "EXPIRED"){
            if($premium){
                $premium->update([
                    'status' => 'expired',
                ]);

                // Log::error('App Store V2 Notification Job Done. subscription Expired.');
            }
            // Log::error('App Store V2 Notification Job Done. Subscription not found.'); 
        }
        elseif($notificationType == "DID_CHANGE_RENEWAL_STATUS"){
            if($subtype && $subtype == "AUTO_RENEW_DISABLED"){
                if($premium){
                    $premium->update([
                        'canceled_at' => Carbon::now(),
                    ]);
                    
                }
            }
            
        }
        else{
            // Log::error('App Store V2 Notification Job Done. Subscription not found.');
        }
    }
}
