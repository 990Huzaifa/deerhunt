<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\EngagementPushTestService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;


class NotificationController extends Controller
{
    /**
     * Test endpoint: each call sends the next engagement push to the given FCM token.
     * Sequence: P1 first score → P2 paywall → each feature screen, then loops.
     * Optional query: ?reset=1 to start again from P1.
     */
    public function sendNotify(string $fcm, EngagementPushTestService $testService): JsonResponse
    {
        try {
            if ($fcm === '') {
                return response()->json(['error' => 'FCM token is required'], 422);
            }

            if (request()->boolean('reset')) {
                $testService->reset($fcm);
            }

            $result = $testService->sendNext($fcm);
            $ok = !empty($result['fcm_result']['success']);

            return response()->json([
                'message' => $ok
                    ? 'Test push sent: ' . $result['event']['key']
                    : 'FCM send failed for: ' . $result['event']['key'],
                'data' => $result,
            ], $ok ? 200 : 502);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();   
            $page = $request->query('page', 1);
            $perPage = $request->query('per_page', 200);
            $notifications =Notification::where('receiver_id', $user->id)->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['message' => 'Notifications listed successfully', 'data' => $notifications], 200);
        }catch(Exception $e){
            return response()->json(['DB error' => $e->getMessage()], 500);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function markAsRead($id): JsonResponse
    {
        try{
            $user = Auth::user();
            $notification = Notification::where('id', $id)->where('receiver_id', $user->id)->first();

            if (!$notification) {
                return response()->json(['message' => 'Notification not found'], 404);
            }

            $notification->update(['read_at' => now()]);

            return response()->json(['message' => 'Notification marked as read successfully'], 200);
        }catch(Exception $e){
            return response()->json(['DB error' => $e->getMessage()], 500);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try{
            $user = Auth::user();
            $notification = Notification::where('id', $id)->where('receiver_id', $user->id)->first();

            if (!$notification) {
                return response()->json(['message' => 'Notification not found'], 404);
            }

            $notification->delete();

            return response()->json(['message' => 'Notification deleted successfully'], 200);
        }catch(Exception $e){
            return response()->json(['DB error' => $e->getMessage()], 500);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
