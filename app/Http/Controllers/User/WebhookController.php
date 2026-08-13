<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAppleNotificationV2;
use App\Jobs\ProcessGoogleNotification;
use App\Services\AppStoreConnectAuth;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\CreditsWallet as Wallet;
use App\Models\Subscription;
use Carbon\Carbon;
use Exception;



class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signedPayload = $request->input('signedPayload');

        if (!$signedPayload) {
            // Log the error for missing payload but still return 200 to prevent retries
            Log::error('App Store V2 Notification received with missing signedPayload.');
            return response()->json(['status' => 'ok']);
        }
        // This is crucial: respond quickly (within a few seconds) and process asynchronously.
        ProcessAppleNotificationV2::dispatch($signedPayload)->onQueue('apple-webhooks');

        // 3. Respond with 200 OK to acknowledge receipt
        return response()->json(['status' => 'ok'], 200);
    }

    public function handleGoogle(Request $request)
    {

        $data = $request->input('message.data');
        // here ye need to set a job for better and background processing
        ProcessGoogleNotification::dispatch($data)->onQueue('google-webhooks');
        // Must return a 200 status code to acknowledge receipt
        return response()->json(['status' => 'ok'], 200);
    }

    public function decodeApple(Request $request): JsonResponse
    {
        $signedPayload = $request->input('signedPayload');
        if (!$signedPayload) {
            return response()->json(['error' => 'signedPayload is required'], 400);
        }
        $auth = new AppStoreConnectAuth();
        try {
            $decodedTransactionInfo = $auth->JWSParse($signedPayload);
            return response()->json(['decodedTransactionInfo' => $decodedTransactionInfo], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage() ], 500);
        }
        
    }
}
