<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserEngagement;
use App\Services\EngagementTrackingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class EngagementController extends Controller
{
    protected EngagementTrackingService $engagementTracking;

    public function __construct(EngagementTrackingService $engagementTracking)
    {
        $this->engagementTracking = $engagementTracking;
    }

    public function show(): JsonResponse
    {
        try {
            $user = Auth::user();
            $state = $this->engagementTracking->getState($user);

            return response()->json([
                'message' => 'Engagement state fetched successfully',
                'data' => $state,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function dismissPaywall(): JsonResponse
    {
        try {
            $user = Auth::user();
            $this->engagementTracking->recordPaywallDismiss($user);
            $state = $this->engagementTracking->getState($user);

            return response()->json([
                'message' => 'Trial paywall dismiss recorded',
                'data' => $state,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function featureUsed(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'feature' => 'required|string|in:' . implode(',', UserEngagement::FEATURE_KEYS),
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            $user = Auth::user();
            $this->engagementTracking->recordFeatureUsed($user, $request->feature);
            $state = $this->engagementTracking->getState($user);

            return response()->json([
                'message' => 'Feature usage recorded',
                'data' => $state,
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
