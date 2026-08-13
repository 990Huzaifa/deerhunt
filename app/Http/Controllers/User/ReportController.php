<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\Report;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            // 1. Validate Input
            $request->validate([
                'reportable_type' => 'required|string|in:post,comment', // Enforce valid types
                'reportable_id' => 'required|integer|min:1',
                'reason' => 'nullable|string|max:255',
            ]);
            $reporterId = $user->id;

            // Dynamically determine the model class based on input type
            $modelClass = $request->reportable_type === 'post' ? Post::class : PostComment::class;
            $reportableId = $request->reportable_id;

            // Fetch the item to ensure it exists and is not already soft-deleted
            $item = $modelClass::where('id', $reportableId)->where('is_delete', false)->first();

            if (!$item) {
                return response()->json(['message' => 'The item you are trying to report does not exist or has already been removed.'], 404);
            }

            // 2. Check for Existing Report (Prevent duplicate reports from the same user)
            $existingReport = Report::where('user_id', $reporterId)
                ->where('reportable_type', $modelClass)
                ->where('reportable_id', $reportableId)
                ->exists();

            if ($existingReport) throw new Exception("You have already reported this item", 400);
            

            // 3. Create Report
            DB::beginTransaction();
            $report =Report::create([
                'user_id' => $reporterId,
                // Laravel stores the full class path in the database for polymorphic relations
                'reportable_type' => $modelClass,
                'reportable_id' => $reportableId,
                'reason' => $request->input('reason') ?? null,
            ]);
            $report->save();

            // 4. Crucial Logic: Count and Delete Check
            // Count all existing reports for this specific item (including the one just created)
            $reportCount = $item->reports()->count();

            // Check the threshold (3 reports)
            if ($reportCount >= 3) {
                // Soft-delete the item by setting the is_delete flag to true
                $item->is_delete = true;
                $item->save();

                // Notify the reporter that action was taken immediately
                return response()->json([
                    'message' => 'Report submitted successfully. The item has been automatically removed due to reaching the report threshold.',
                    'item_deleted' => true,
                ], 200);
            }
            DB::commit();
            // If threshold not reached, just confirm the report submission
            return response()->json(['message' => 'Report submitted successfully.'], 200);

        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(["DB error" => $e->getMessage()],500);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["error" => $e->getMessage()],$e->getCode() ?: 500);
        }
    }
}
