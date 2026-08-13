<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\NotificationService;


class ProfileController extends Controller
{

    protected $notificationService;
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }


    
    public function profile(): JsonResponse
    {
        $user = Auth::user();
        $followersCount = $user->followers()->count();
        $followingCount = $user->followings()->count();
        $harvestCount = $user->post()->count();
        
        $user->followers_count = $followersCount;
        $user->following_count = $followingCount;
        $user->harvest_count = $harvestCount;
        // $likesCount = $user->like()->count();
        // $user->like_count = $likesCount;

        $premiumPlan = $user->premium()->where('status', 'active')->first();
        $user->premium_plan = $premiumPlan;

        // get likes count from posts
        $likeCount = Post::where('user_id', $user->id)->where('is_public', true)->where('is_delete', false)->sum('like_count');
        $user->like_count = $likeCount;

        return response()->json($user);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $validator = Validator::make($request->all(),[
                'full_name' => 'required|string|max:255',
                'username' =>'nullable|string|max:255|unique:users,username,'.$user->id,
                'bio' => 'nullable|string|max:500',
                'state' => 'nullable|string|max:255',
                'personal_site' => 'nullable|url|max:255',
                // outfitter validation

                'county' => 'nullable',
                'phone' => 'nullable',
                'species' => 'nullable|array',
                'hunt_type' => 'nullable|array',
                'starting_price' => 'nullable|numeric',
                'highlight_photos' => 'nullable|array',
                'highlight_photos.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
                'existing_photos' => 'nullable|array',
                'existing_photos.*' => 'nullable|string',
            ],[
                'full_name.required' => 'Full name is required',
                'full_name.string' => 'Full name must be a string',
                'full_name.max' => 'Full name must not exceed 255 characters',

                'username.string' => 'Username must be a string',
                'username.max' => 'Username must not exceed 255 characters',
                'username.unique' => 'Username already taken',

                'bio.string' => 'Bio must be a string',
                'bio.max' => 'Bio must not exceed 500 characters',

                'state.string' => 'State must be a string',
                'state.max' => 'State must not exceed 255 characters',

                'personal_site.url' => 'Personal site must be a valid URL',
                'personal_site.max' => 'Personal site must not exceed 255 characters',

                // outfitter validation
                'species.array' => 'Species must be an array',
                'hunt_type.array' => 'Hunt type must be an array',
                'starting_price.numeric' => 'Starting price must be a number',
                'highlight_photos.array' => 'Highlight photos must be an array',
                'highlight_photos.*.image' => 'Each highlight photo must be an image',
                'highlight_photos.*.mimes' => 'Each highlight photo must be a file of type: jpeg, png, jpg, gif, svg',
                'highlight_photos.*.max' => 'Each highlight photo must not exceed 4MB',
            ]);
            if($validator->fails()){
                return response()->json(["error" => $validator->errors()],422);
            }
            /*
            |--------------------------------------------------------------------------
            | HANDLE HIGHLIGHT PHOTOS (FULL NEW LOGIC)
            |--------------------------------------------------------------------------
            */

            $existingPhotosInDB = $user->highlight_photos ? explode(',', $user->highlight_photos) : [];
            $keepPhotos = $request->existing_photos ?? [];     // frontend submitted existing paths
            $newUploadedPhotos = [];

            // 1. DELETE OLD IMAGES THAT ARE NOT IN existing_photos
            if (!empty($existingPhotosInDB)) {
                foreach ($existingPhotosInDB as $old) {
                    if (!in_array($old, $keepPhotos)) {
                        if (file_exists(public_path($old))) {
                            // @unlink(public_path($old));
                        }
                        deleteImageFromSpaces($old);
                    }
                }
            }

            // 2. UPLOAD NEW IMAGES & APPEND
            if ($request->hasFile('highlight_photos')) {
                foreach ($request->file('highlight_photos') as $image) {
                        // upload to bucket before moving the uploaded temp file locally

                    $uploadedImage = uploadImageToSpaces(
                        $image,
                        'highlight-photos',
                        'highlight-photos'
                    );
                    $image_name = $uploadedImage['path']; // this is the path returned from the upload function, which includes the folder and filename

                    // $image->move(public_path('highlight-photos'), $image_name);

                    $newUploadedPhotos[] = $image_name;
                }
            }

            // 3. MERGE: existing_kept + new_uploaded
            $finalHighlightPhotos = array_merge($keepPhotos, $newUploadedPhotos);

            // 4. CONVERT ARRAY → CSV STRING FOR DATABASE
            $highlight_photos = implode(',', $finalHighlightPhotos);




            // handle species and hunt_type as json encoded string stored in longText fields
            $species = $request->has('species') ? json_encode($request->species) : null;
            $hunt_type = $request->has('hunt_type') ? json_encode($request->hunt_type) : null;

            $user->update([
                'full_name' => $request->full_name,
                'username' => $request->username,
                'bio' => $request->bio ?? null,
                'state' => $request->state ?? null,
                'personal_site' => $request->personal_site ?? null,
                // outfitter fields
                'county' => $request->county ?? null,
                'phone' => $request->phone ?? null,
                'species' => $species,
                'hunt_type' => $hunt_type,
                'starting_price' => $request->starting_price ?? null,
                'highlight_photos' => $highlight_photos,
            ]);
            return response()->json([
                'message' => 'Profile updated successfully',
                'data' => $user
            ]);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],500);
        }
    }

    public function updateProfilePicture(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $validator = Validator::make($request->all(),[
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ],[
                'avatar.required' => 'Please upload an image',
                'avatar.image' => 'The file must be an image',
                'avatar.mimes' => 'Only jpeg, png, jpg, gif, svg images are allowed',
                'avatar.max' => 'Sorry! Maximum allowed size for an image is 2MB',
            ]);

            if($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
            DB::beginTransaction();
            $avatar = $user->avatar;
            if ($request->hasFile('avatar')) {
                $image = $request->file('avatar');

                // upload to bucket before moving the uploaded temp file locally
                $uploadedImage = uploadImageToSpaces(
                    $image,
                    'user-avatar',
                    'u-avatar'
                );

                if ($user->avatar && file_exists(public_path($user->avatar))) {
                    // @unlink(public_path($user->avatar));

                    deleteImageFromSpaces($user->avatar);
                }

                $image_name = $uploadedImage['path']; // this is the path returned from the upload function, which includes the folder and filename
                // $image->move(public_path('user-avatar'), $image_name);
                $avatar = $image_name;
            }
            $user->update([
                'avatar' => $avatar,
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Profile picture updated successfully',
                'data' => $user
            ]);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["error" => $e->getMessage()],500);
        }
    }

    public function follow(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $validator = Validator::make($request->all(),[
                'follow_id' => 'required|exists:users,id',
            ],[
                'follow_id.required' => 'Follow ID is required',
                'follow_id.exists' => 'User not found',
            ]);
            if($validator->fails()){
                return response()->json(["error" => $validator->errors()],422);
            }

            $followUser = User::find($request->follow_id);

            // Check if already following
            if($user->isFollowing($followUser)){
                $user->unfollow($followUser);
                return response()->json([
                    'message' => 'User unfollowed successfully'
                ]);
            }
            

            $user->follow($followUser);
            // send notification
            $this->notificationService->send(
                $user->id,
                $followUser->id,
                'follow',
                "{$user->username} started following you.",
                'New Follower',
                ['type' => 'follow', 'follower_id' => $user->id, 'receiver_username' => $followUser->username]
            );
            return response()->json([
                'message' => 'User followed successfully'
            ]);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],500);
        }
    }

    public function preferences(): JsonResponse
    {
        try{
            $user = Auth::user();
            $preferences = $user->preferences;
            return response()->json([
                'data' => $preferences
            ]);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],500);
        }
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $validator = Validator::make($request->all(),[
                'is_profile_public' => 'required|boolean',
            ],[
                'is_profile_public.required' => 'Profile visibility is required',
                'is_profile_public.boolean' => 'Profile visibility must be true or false',
            ]);
            if($validator->fails()){
                return response()->json(["error" => $validator->errors()],422);
            }

            $user->preferences->update([
                    'is_profile_public' => $request->is_profile_public,
                ]);
            return response()->json([
                'message' => 'Preferences updated successfully'
            ]);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],500);
        }
    }

    public function deleteAccount(): JsonResponse
    {
        try{
            $user = Auth::user();
            $user->is_delete = true;
            $user->save();
            Auth::logout();
            return response()->json([
                'message' => 'Account marked for deletion successfully'
            ]);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],500);
        }
    }

    public function outfitters(Request $request): JsonResponse
    {
        try{
            $authUser = Auth::user();
            $authUserId = $authUser->id;
            $perPage = $request->query('per_page', 5);

            $search = $request->query('search');
            $sortBy = $request->query('sort_by');        // closest, most_followed, most_liked, highest_price, lowest_price
            $state = $request->query('state');
            $species = $request->query('species');
            $huntType = $request->query('hunt_type');



            $query = User::query()
            ->select(
                'users.id',
                'users.full_name',
                'users.username',
                'users.avatar',
                'users.phone',
                'users.county',
                'users.state',
                'users.starting_price',
                'users.hunt_type',
                'users.highlight_photos',
                'users.species',
                'users.is_featured',
                DB::raw("
                    CASE 
                        WHEN users.id = {$authUserId} THEN NULL
                        WHEN follows.id IS NULL THEN 0
                        ELSE 1
                    END as is_follow
                ")
            )
            ->leftJoin('follows', function ($join) use ($authUserId) {
                $join->on('follows.following_id', '=', 'users.id')
                    ->where('follows.follower_id', '=', $authUserId);
            })
            ->where('users.is_active', 1)
            ->where('users.is_premium', 1)
            ->where('users.is_delete', 0);

            $query->orderByDesc('users.is_featured');
            
            /**
             * Sorting logic
             */
            if($sortBy){
                switch ($sortBy) {

                    case 'most_followed':
                        $query->orderByDesc(
                            DB::raw(
                                "(SELECT COUNT(*) 
                                FROM follows 
                                WHERE follows.following_id = users.id)"
                            )
                        );
                        break;

                    case 'most_liked':
                        $query
                            ->leftJoin('posts', function ($join) {
                                $join->on('posts.user_id', '=', 'users.id')
                                    ->where('posts.is_public', 1)
                                    ->where('posts.is_delete', 0);
                            })
                            ->groupBy('users.id')
                            ->orderByRaw('COALESCE(SUM(posts.like_count), 0) DESC');
                        break;

                    case 'highest_price':
                        $query->orderBy('users.starting_price', 'DESC');
                        break;

                    case 'lowest_price':
                        $query->orderBy('users.starting_price', 'ASC');
                        break;

                    // case 'closest':
                    //     $query->where('users.state', "like", '%'.$authUser->state.'%');
                    //     break;
                    // default:
                    //     // Default sorting by closest
                    //     $query->where('users.state', "like", '%'.$authUser->state.'%');
                    //     break;
                }
            }


            // add extra filters

            if ($state) {
                $query->where('users.state', $state);
            }

            // these are stored as json encoded strings
            if ($species) {
                $query->where('users.species', 'like', '%' . $species . '%');
            }
            if ($huntType) {
                $query->where('users.hunt_type', 'like', '%' . $huntType . '%');
            }

            if($search){
                $query->where(function ($query) use ($search) {
                    $query->where('users.full_name', 'like', '%' . $search . '%')
                        ->orWhere('users.username', 'like', '%' . $search . '%')
                        ->orWhere('users.county', 'like', '%' . $search . '%')
                        ->orWhere('users.state', 'like', '%' . $search . '%');
                });
            }


            $users = $query->paginate($perPage);
            return response()->json([
                'message' => 'Users fetched successfully',
                'data' => $users
            ], 200);

        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // another user data
    public function userProfile($id): JsonResponse
    {
        try{
            $authUser = Auth::user();
            $user = User::find($id);
            
            $followersCount = $user->followers()->count();
            $followingCount = $user->followings()->count();
            $likeCount = $user->like()->count();
            $harvestCount = $user->post()->count();

            $user->followers_count = $followersCount;
            $user->following_count = $followingCount;
            $user->harvest_count = $harvestCount;
            
            $likeCount = Post::where('user_id', $user->id)->where('is_public', true)->where('is_delete', false)->sum('like_count');
            $user->like_count = $likeCount;

            // boolean for follow true or false

            $user->is_following = $authUser->isFollowing($user);

            $userPublicPosts = Post::where('user_id', $user->id)->where('is_public', true)->orderBy('created_at', 'desc')->get();
            return response()->json([
                'data' => $user,
                'userPublicPosts' => $userPublicPosts
            ]);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],500);
        }
    }

    public function followFollowingList(): JsonResponse
    {
        try{
            $user = Auth::user();
            $follower_ids = $user->followers()->pluck('follower_id')->toArray();
            $following_ids = $user->followings()->pluck('following_id')->toArray();
            $followings = User::select('id','full_name','username','avatar')->whereIn('id', $following_ids)->get();
            $followers = User::select('id','full_name','username','avatar')->whereIn('id', $follower_ids)->get();

        return response()->json([
            'followings' => $followings,
            'followers' => $followers
        ], 200);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],500);
        }
    }


    public function stateList(): JsonResponse
    {
        try{
            // get state from users table distinct
            $states = User::whereNotNull('state')->where('state', '!=', '')->distinct()->pluck('state');
            return response()->json([
                'data' => $states
            ]);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()],500);
        }
    }
}
