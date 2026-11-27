<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateBotsJob;
use App\Models\BotActivity;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BotController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        try{
            $perPage = $request->input('per_page', 15);
            $selectFields =['id', 'full_name', 'username', 'is_active', 'avatar', 'created_at'];
            $data = User::select($selectFields)->where('is_bot', true)->where('is_delete',0)->with('botConfig')
                ->orderBy('created_at', 'desc');

            $paginatedData = $data->paginate($perPage);
            return response()->json($paginatedData);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try{
            $selectFields =['id', 'full_name', 'username', 'is_active', 'avatar', 'created_at'];
            $bot = User::select($selectFields)->where('is_bot', true)->where('is_delete',0)->with('botConfig')->findOrFail($id);
            $botActivities = BotActivity::where('bot_id', $id)
                ->with(['post', 'targetUser'])
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json([
                'bot' => $bot,
                'activities' => $botActivities
            ]);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try{
            $bot = User::findOrFail($id);

            $validator = Validator::make($request->all(),[
                'username' => 'required|string|max:255',
                'full_name' => 'required|string|max:255',
                'bio' => 'nullable|string|max:500',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ],[
                'username.required' => 'Username is required',
                'username.string' => 'Username must be a string',
                'username.max' => 'Username must not exceed 255 characters',

                'full_name.required' => 'Full name is required',
                'full_name.string' => 'Full name must be a string',
                'full_name.max' => 'Full name must not exceed 255 characters',

                'bio.string' => 'Bio must be a string',
                'bio.max' => 'Bio must not exceed 500 characters',

                'avatar.image' => 'The file must be an image',
                'avatar.mimes' => 'Only jpeg, png, jpg, gif, svg images are allowed',
                'avatar.max' => 'Sorry! Maximum allowed size for an image is 2MB',
            ]);

            if($validator->fails()) return response()->json(['error' => $validator->errors()->first()], 422);
            // check if username is unique
            $check = User::where('username', $request->username)->where('id', '!=', $id)->first();
            if ($check) {
                return response()->json(['error' => 'Username already exists'], 422);
            }
            $bot->update([
                'username'  => $request->username ?? $bot->username,
                'full_name' => $request->full_name ?? $bot->full_name,
                'bio'       => $request->bio ?? $bot->bio
            ]);

            $avatar = $bot->avatar;
            if ($request->hasFile('avatar')) {
                //  first unlink the old one
                if ($bot->avatar && file_exists(public_path($bot->avatar))) {
                    @unlink(public_path($bot->avatar));
                }
                // upload new 
                $image = $request->file('avatar');
                $image_name = 'u-avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('user-avatar'), $image_name);
                $avatar = 'user-avatar/' . $image_name;
            }
            $bot->update([
                'avatar' => $avatar,
            ]);
            
            return response()->json($bot);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function generateBot(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'names' => 'required|file|mimes:csv,txt,text/plain,text/csv|max:5120',
                'daily_like_limit' => 'required|integer|min:0|max:1000',
                'daily_comment_limit' => 'required|integer|min:0|max:1000',
            ], [
                'names.required' => 'Please upload a CSV/TXT file with full names',
                'names.mimes' => 'Names file must be CSV or TXT',
                'daily_like_limit.required' => 'Daily like limit is required',
                'daily_comment_limit.required' => 'Daily comment limit is required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            $names = $this->parseNamesFile($request->file('names'));

            if (count($names) === 0) {
                return response()->json(['error' => 'No valid full names found in the uploaded file'], 422);
            }

            if (count($names) > 500) {
                return response()->json(['error' => 'Maximum 500 bots can be generated per upload'], 422);
            }

            $config = [
                'like_limit' => (int) $request->daily_like_limit,
                'comment_limit' => (int) $request->daily_comment_limit,
            ];

            GenerateBotsJob::dispatch($names, $config);

            $botCount = count($names);

            return response()->json([
                'status' => 'success',
                'message' => "Bot generation started in background for {$botCount} bots.",
                'bot_count' => $botCount,
                'config' => [
                    'daily_like_limit' => $config['like_limit'],
                    'daily_comment_limit' => $config['comment_limit'],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse uploaded CSV/TXT into unique trimmed full names.
     * Accepts one name per line, or first column of a CSV. Skips header rows like "name"/"full_name".
     *
     * @return list<string>
     */
    protected function parseNamesFile($file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return [];
        }

        $names = [];
        $isFirst = true;

        while (($row = fgetcsv($handle)) !== false) {
            if (!is_array($row) || count($row) === 0) {
                continue;
            }

            $name = trim((string) ($row[0] ?? ''));
            if ($name === '') {
                continue;
            }

            // Strip UTF-8 BOM
            $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);

            if ($isFirst) {
                $isFirst = false;
                $header = strtolower(str_replace([' ', '-'], '_', $name));
                if (in_array($header, ['name', 'full_name', 'fullname', 'bot_name'], true)) {
                    continue;
                }
            }

            $names[] = $name;
        }

        fclose($handle);

        return array_values(array_unique($names));
    }
    public function BotActivityLog(Request $request): JsonResponse
    {
        try{
            $perPage = $request->input('per_page', 15);
            $data = BotActivity::with(['bot', 'post', 'targetUser'])
                ->orderBy('created_at', 'desc');

            $paginatedData = $data->paginate($perPage);
            return response()->json($paginatedData);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadBotAvatars(Request $request): JsonResponse
    {
        try{
            $validator = Validator::make($request->all(),[
                'avatars' => 'required|array',
                'avatars.*' => 'image|max:2048' // 2MB max size per image
            ]);
            if($validator->fails()) return response()->json(['error' => $validator->errors()->first()], 422);

            $uploadedPaths = [];
            foreach($request->file('avatars') as $avatar){
                $image_name = 'bot-avatar' . time() . rand(1000, 999999) . '.' . $avatar->getClientOriginalExtension();
                $avatar->move(public_path('bot-avatar'), $image_name);
                $uploadedPaths[] = asset('bot-avatar/' . $image_name);
            }

            return response()->json([
                'status' => 'success',
                'paths' => $uploadedPaths
            ]);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function MaxBotCreate(): JsonResponse
    {
        // Bot count now comes from the uploaded names CSV (no avatar pool limit).
        return response()->json([
            'max_bot_create' => 500,
            'avatar_required' => false,
            'message' => 'Upload a CSV of full names; bot count = number of names (max 500).',
        ]);
    }

    public function deleteBot($id): JsonResponse
    {
        try{
            $bot = User::find($id);
            if (!$bot) return response()->json(['error' => 'Bot not found'], 404);
            $bot->update(['is_delete' => true]);
            return response()->json([
                'status' => 'success',
                'message' => 'Bot deleted successfully'
            ]);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateBotActiveStatus(Request $request, $id): JsonResponse
    {
        try {
            if (!$request->exists('is_active')) {
                return response()->json(['error' => 'is_active is required'], 422);
            }

            $raw = $request->input('is_active');
            $normalized = is_string($raw) ? strtolower(trim($raw)) : $raw;

            if (in_array($normalized, [true, 1, '1', 'true', 'active'], true)) {
                $status = 'active';
            } elseif (in_array($normalized, [false, 0, '0', 'false', 'inactive'], true)) {
                $status = 'inactive';
            } else {
                return response()->json(['error' => 'is_active must be true or false'], 422);
            }

            $bot = User::where('is_bot', true)->where('is_delete', 0)->find($id);
            if (!$bot) {
                return response()->json(['error' => 'Bot not found'], 404);
            }

            $bot->update(['is_active' => $status]);

            return response()->json([
                'status' => 'success',
                'message' => $status === 'active' ? 'Bot activated successfully' : 'Bot deactivated successfully',
                'bot' => [
                    'id' => $bot->id,
                    'username' => $bot->username,
                    'full_name' => $bot->full_name,
                    'is_active' => $bot->is_active,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
