<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateBotsJob;
use App\Models\BotActivity;
use App\Models\User;
use App\Services\OpenAIService;
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
        try{
            $validator = Validator::make($request->all(),[
                'bot_count' => 'required|integer|max:50',
                'config' => 'required|array'
            ]);
            if($validator->fails()) return response()->json(['error' => $validator->errors()->first()], 422);
            // Job ko queue mein bhej dya
            GenerateBotsJob::dispatch($request->bot_count, $request->config);

            return response()->json([
                'status' => 'success',
                'message' => "Bot generation process started in background for {$request->bot_count} bots."
            ]);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
        // based on images in bot-avatar folder

        $files = glob(public_path('bot-avatar/*'));
        $count = count($files); // Number of files in the directory
        return response()->json(['max_bot_create' => $count]);
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
}
