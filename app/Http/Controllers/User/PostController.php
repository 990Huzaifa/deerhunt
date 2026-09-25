<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PostShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Models\PostView;
use App\Models\PostLike;
use App\Models\PostComment;
use App\Services\NotificationService;

class PostController extends Controller
{
    /**
     * Display a listing of the resource in private trophy room.
     */
    // connect firebase service
    protected $notificationService;
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }


    public function index(Request $request): JsonResponse
    {
        try {
            // search query
            $search = $request->query('search');

            $user = Auth::user();
            $posts = Post::select('posts.*', 'users.full_name', 'users.avatar')
                ->join('users', 'users.id', '=', 'posts.user_id')->orderBy('created_at', 'desc')
                ->where('posts.user_id', $user->id)
                ->where('posts.is_trophy', true)
                ->where('posts.is_private', true)
                ->where('posts.is_delete', false)
                ->where('ref_id', null)
                ->excludeRescoreVersions()
                ->where(function ($query) use ($search) {
                    $query->where('posts.title', 'like', '%' . $search . '%');
                })
                ->paginate(200);
            return response()->json($posts);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function trophyList(): JsonResponse
    {
        try {
            $user = Auth::user();
            $publicPosts = Post::select('posts.*', 'users.full_name', 'users.avatar')
                ->join('users', 'users.id', '=', 'posts.user_id')->orderBy('posts.created_at', 'desc')
                ->where('posts.user_id', $user->id)
                ->where('posts.is_trophy', true)
                ->where('posts.is_delete', false)
                ->where('posts.is_public', true)
                ->where('ref_id', null)
                ->excludeRescoreVersions()
                ->paginate(20);

            return response()->json($publicPosts);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function recentList(Request $request): JsonResponse
    {
        try {
            $search = $request->query('search');
            $user = Auth::user();

            $posts = Post::select('posts.*', 'users.full_name', 'users.avatar')
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->orderBy('posts.created_at', 'desc')
                ->where('posts.user_id', $user->id)
                ->where('posts.is_delete', false)
                ->where('ref_id', null)
                ->paginate(200);

            return response()->json($posts);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $post = $this->createPost($request, false);
            return response()->json($post);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function storeAsRecent(Request $request): JsonResponse
    {
        try {
            $post = $this->createPost($request, true);
            return response()->json($post);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    private function createPost(Request $request, bool $isRecent = false): Post
    {
        $user = Auth::user();
        $rules = $isRecent
            ? [
                'score' => 'required',
                'title' => 'nullable|string',
                'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
                'images' => 'nullable|array|min:1',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
                'analysis' => 'nullable',
                'ref_data' => 'nullable|array',
                'ref_data.*.title' => 'nullable|string',
                'ref_data.*.score' => 'nullable|numeric',
                'ref_data.*.analysis' => 'nullable|string',
                'ref_data.*.image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
                'measurements' => 'nullable|array',
                'antler_points' => 'nullable|array',
                'deer_age_estimate' => 'nullable|boolean',
                'growth_projection' => 'nullable|boolean',
                'estimated_age' => 'nullable|integer',
                'is_public' => 'nullable|boolean',
                'is_private' => 'nullable|boolean',
                'hunt_date' => 'nullable|date',
                'location' => 'nullable|string',
                'notes' => 'nullable',
                'caption' => 'nullable',
                'harvest_type' => 'nullable|string',
                'state' => 'nullable|string',
                'county' => 'nullable|string',
            ]
            : [
                'title' => 'required|string',
                'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
                'images' => 'nullable|array|min:1',
                'images.*' => 'file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
                'score' => 'nullable',
                'analysis'=> 'nullable',
                'ref_data' => 'nullable|array',
                'ref_data.*.title' => 'nullable|string',
                'ref_data.*.score' => 'required_with:ref_data|numeric',
                'ref_data.*.analysis' => 'required_with:ref_data|string',
                'ref_data.*.image' => 'required_with:ref_data|file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
                'measurements' => 'nullable|array',
                'antler_points' => 'nullable|array',
                'deer_age_estimate' => 'nullable|boolean',
                'growth_projection' => 'nullable|boolean',
                'estimated_age' => 'required_if:deer_age_estimate,true|integer',
                'is_public' => 'nullable|boolean',
                'is_private' => 'nullable|boolean',
                'hunt_date' => 'required|date',
                'location' => 'required|string',
                'notes' => 'nullable',
                'caption' => 'nullable',
                'harvest_type' => 'nullable|string',
                'state' => 'nullable|string',
                'county' => 'nullable|string',
            ];

        $validator = Validator::make($request->all(), $rules, [
            'image.required' => 'Image is required',
            'image.uploaded' => 'Image must be uploaded',
            'image.file' => 'Image must be a file',
            'image.image' => 'Image must be an image',
            'image.mimes' => 'Image must be a jpeg, png, jpg, gif, or svg',
            'images.array' => 'Images must be an array',
            'images.min' => 'At least one image is required',
            'images.*.image' => 'Each image must be an image',
            'images.*.mimes' => 'Each image must be a jpeg, png, jpg, gif, or svg',
            'score.required' => 'Score is required',
            'analysis.required' => 'Analysis is required',
            'ref_data.array' => 'Reference data must be an array',
            'ref_data.*.score.required_with' => 'Score is required for reference data',
            'ref_data.*.score.numeric' => 'Score must be a number for reference data',
            'ref_data.*.analysis.required_with' => 'Analysis is required for reference data',
            'ref_data.*.image.required_with' => 'Image is required for reference data',
            'ref_data.*.image.image' => 'Image must be an image for reference data',
            'ref_data.*.image.mimes' => 'Image must be a jpeg, png, jpg, gif, or svg for reference data',
            'measurements.array' => 'Measurements must be an array',
            'antler_points.array' => 'Antler points must be an array',
            'deer_age_estimate.boolean' => 'Deer age estimate must be true or false',
            'growth_projection.boolean' => 'Growth projection must be true or false',
            'estimated_age.required_if' => 'Estimated age is required when deer age estimate is true',
            'estimated_age.integer' => 'Estimated age must be an integer',
            'title.required' => 'Title is required',
            'hunt_date.required' => 'Hunt date is required',
            'hunt_date.date' => 'Hunt date must be a valid date',
            'location.required' => 'Location is required',
        ]);

        if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

        $image = null;
        if ($request->hasFile('image')) {
            $uploadedImage = uploadImageToPublic(
                $request->file('image'),
                'post-image',
                'post-image'
            );
            $image = $uploadedImage['path'];
        }
        if($request->hasFile('images')){
            $images = [];
            foreach($request->file('images') as $img){
                $uploadedImage = uploadImageToPublic(
                    $img,
                    'post-image',
                    'post-image'
                );
                $images[] = $uploadedImage['path'];
            }
            $image = implode(',', $images);
        }

        $allImagePaths = [$image];
        $post = Post::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'image' => $image,
            'score' => $request->score ?? null,
            'analysis' => $request->analysis ?? null,
            'measurements' => json_encode($request->measurements) ?? null,
            'antler_points' => json_encode($request->antler_points) ?? null,
            'deer_age_estimate' => $request->deer_age_estimate ?? false,
            'growth_projection' => $request->growth_projection ?? false,
            'estimated_age' => $request->estimated_age ?? null,
            'years_age' => json_encode($request->years_age) ?? null,
            'is_public' => $request->is_public ?? false,
            'is_private' => $request->is_private ?? false,
            'caption' => $request->caption ?? null,
            'state' => $request->state ?? $user->state,
            'county' => $request->county ?? $user->county,
            'harvest_type' => $request->harvest_type ?? null,
            'hunt_date' => $request->hunt_date ?? null,
            'location' => $request->location ?? null,
            'notes' => $request->notes ?? null,
            'is_trophy' => $isRecent ? false : true,
        ]);
        $user->increment('analysis_count');

        if(isset($request->ref_data)){
            $ref_data = $request->ref_data;
            foreach($ref_data as $item){
                $ref_image = null;
                if (isset($item['image']) && $item['image']) {
                    $ref_image = uploadImageToPublic(
                        $item['image'],
                        'post-image',
                        'post-image'
                    );
                    $ref_image = $ref_image['path'];
                }
                $allImagePaths = array_merge($allImagePaths, [$ref_image]);
                Post::create([
                    'user_id' => $user->id,
                    'title' => $item['title'] ?? null,
                    'image' => $ref_image,
                    'score' => $item['score'] ?? null,
                    'analysis' => $item['analysis'] ?? null,
                    'ref_id' => $post->id
                ]);
            }
        }

        $post->update([
            'image' => implode(',', $allImagePaths)
        ]);

        $post = $post->fresh();
        $this->notifyPreviousTopScoreBeaten($post);

        return $post;
    }

    /**
     * Notify the previous global top-score holder when a new post beats their score.
     * Uses NotificationService (DB row + FirebaseService FCM).
     */
    private function notifyPreviousTopScoreBeaten(Post $post): void
    {
        if ($post->score === null || $post->score === '') {
            return;
        }

        try {
            $previousTop = Post::with('user')
                ->where('is_delete', false)
                ->whereNull('ref_id')
                ->where('is_trophy', true)
                ->where('id', '!=', $post->id)
                ->excludeRescoreVersions()
                ->orderByDesc('score')
                ->first();

            if (!$previousTop || !$previousTop->user_id) {
                return;
            }

            if ((float) $post->score <= (float) $previousTop->score) {
                return;
            }

            if ($post->user_id == $previousTop->user_id) {
                return;
            }

            $beater = Auth::user();
            $receiver = $previousTop->user;
            if (!$receiver) {
                return;
            }

            $this->notificationService->send(
                $post->user_id,
                $previousTop->user_id,
                'high_score',
                "{$beater->username} beat your high score.",
                'High Score Beaten',
                [
                    'type' => 'high_score',
                    'post_id' => $post->id,
                    'new_score' => $post->score,
                    'previous_score' => $previousTop->score,
                    'previous_post_id' => $previousTop->id,
                    'receiver_username' => $receiver->username,
                ]
            );
        } catch (Exception $e) {
            Log::error('Failed to send high score beaten notification', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $post = Post::select('posts.created_at',
                'posts.caption',
                'posts.harvest_type',
                'posts.id',
                'posts.state',
                'posts.county',
                'posts.image',
                'posts.feed_images',
                'posts.is_trophy',
                'posts.is_public',
                'posts.is_private',
                'posts.share_count',
                'posts.like_count',
                'posts.comment_count',
                'posts.user_id',
                'posts.score',
                'posts.analysis',
                'posts.measurements',
                'posts.antler_points',
                'posts.linked_post_id',
                'users.full_name',
                'users.avatar',
                'users.username')
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->where('posts.id', $id)->first();
            if (!$post) throw new Exception('Post not found', 404);

            $model = Post::find($post->id);
            $latest = $model->getLatestInChain();
            $payload = array_merge($post->toArray(), $model->withRescoreMetadata());

            if ($latest->id !== $model->id) {
                $payload['score'] = $latest->score;
                $payload['analysis'] = $latest->analysis;
                $payload['image'] = $latest->image;
                $payload['measurements'] = $latest->measurements;
                $payload['antler_points'] = $latest->antler_points;
            }

            return response()->json($payload);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function communityPosts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $sortBy = $request->query('sort_by', 'latest');        // oldest, latest, most_liked

            $search = $request->query('search');

            $applySorting = function ($query) use ($sortBy) {
                switch ($sortBy) {
                    case 'oldest':
                        return $query->orderBy('posts.created_at', 'asc');

                    case 'most_liked':
                        return $query->orderBy('posts.like_count', 'desc');

                    case 'latest':
                    default:
                        return $query->orderBy('posts.created_at', 'desc');
                }
            };

            $selectFields = [
                'posts.created_at',
                'posts.caption',
                'posts.harvest_type',
                'posts.score',
                'posts.id',
                'posts.state',
                'posts.county',
                'posts.image',
                'posts.feed_images',
                'posts.share_count',
                'posts.like_count',
                'posts.comment_count',
                'posts.user_id',
                'users.full_name',
                'users.avatar',
                'users.username',
                'users.is_premium',
            ];

            /**
             * Public community posts
             */
            $postsQuery = Post::select($selectFields)
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->where('posts.is_delete', false)
                ->whereNull('posts.ref_id')
                ->excludeRescoreVersions()
                ->where('posts.is_public', true);


            if ($search) {
                $postsQuery->where(function ($query) use ($search) {
                    $query->where('users.full_name', 'like', '%' . $search . '%')
                        ->orWhere('users.username', 'like', '%' . $search . '%');
                });
            }

            $applySorting($postsQuery);

            $posts = $postsQuery->paginate(20);

            $followingQuery = Post::select($selectFields)
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->join('follows', 'follows.following_id', '=', 'posts.user_id')
                ->where('follows.follower_id', $user->id)
                ->where('posts.is_delete', false)
                ->whereNull('posts.ref_id')
                ->excludeRescoreVersions()
                ->where('posts.is_public', true);

            
            if ($search) {
                $followingQuery->where(function ($query) use ($search) {
                    $query->where('users.full_name', 'like', '%' . $search . '%')
                        ->orWhere('users.username', 'like', '%' . $search . '%');
                });
            }

            $applySorting($followingQuery);

            $following_posts = $followingQuery->paginate(20);

                    // 2. Extract Post IDs for efficient checking
            $postIds = $posts->pluck('id')->merge($following_posts->pluck('id'))->unique();

            // 3. Get all the likes for these posts by the authenticated user in one query
            $likedPostIds = PostLike::where('user_id', $user->id)
                                    ->whereIn('post_id', $postIds)
                                    ->pluck('post_id');

            // 4. Create the transformation function
            $transformer = function ($post) use ($likedPostIds) {
                // Convert to array to easily add the new field
                $postArray = $post instanceof \Illuminate\Database\Eloquent\Model ? $post->toArray() : (array) $post;
                
                // Check if the post ID is in the list of liked posts
                $postArray['is_like'] = $likedPostIds->contains($postArray['id']);
                
                return $postArray;
            };

            // 5. Apply the transformation to both paginated results
            $posts->getCollection()->transform($transformer);
            $following_posts->getCollection()->transform($transformer);
            $posts = [
                'feeds' => $posts,
                'following_posts' => $following_posts
            ];
            return response()->json($posts);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function postLike(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $post = Post::find($id);
            if(!$post) throw new Exception('Post not found',404);
            $check_like = PostLike::where('post_id',$id)->where('user_id',$user->id)->first();
            if($check_like){
                $check_like->delete();
                $post->decrement('like_count');
                return response()->json(['message' => 'Post unliked','like_count' => $post->like_count],200);
            }else{
                $post_like = PostLike::create([
                    'post_id' => $id,
                    'user_id' => $user->id
                ]);
                $post->increment('like_count');
                // send notification
                if($user->id != $post->user_id){
                    $this->notificationService->send(
                        $user->id,
                        $post->user_id,
                        'like',
                        "{$user->username} liked your post.",
                        'New Like on Your Post',
                        ['type' => 'like', 'post_id' => $post->id, 'receiver_username' => $post->user->username]
                    );
                }

                return response()->json(['message' => 'Post liked','like_count' => $post->like_count],200);
            }
        }catch(Exception $e){
            return response()->json($e->getMessage(),500);
        }
    }


    public function postShare(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $post = Post::find($id);
            if(!$post) throw new Exception('Post not found',404);
                $post_share = PostShare::create([
                    'post_id' => $id,
                    'user_id' => $user->id
                ]);  
            $post->increment('share_count');
            return response()->json(['message' => 'Post shared','share_count' => $post->share_count],200);
        }catch(Exception $e){
            return response()->json($e->getMessage(),500);
        }
    }

    public function saveTrophy(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $post = Post::find($id);
            if(!$post) throw new Exception('Post not found',404);
            // if($post->user_id != $user->id) throw new Exception('Unauthorized',403);
            $validator = Validator::make($request->all(), [
                'title' => 'required',
            ], [
                'title.required' => 'Title is required',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);
            if($post->is_trophy) throw new Exception('Post is already a trophy',400);
            $post->update([
                'title' => $request->title ?? $post->title,
                'is_trophy' => true,
                'is_private' => true,
            ]);
            return response()->json(['message' => 'Post saved as trophy'],200);
        }catch(Exception $e){
            return response()->json($e->getMessage(),500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $post = Post::find($id);
            if (!$post) throw new Exception('Post not found', 404);
            if ($post->user_id != $user->id) throw new Exception('Unauthorized', 403);
            $post->is_delete = true;
            $post->save();
            return response()->json(['message' => 'Post deleted'], 200);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function postComment(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $post = Post::find($id);
            if(!$post) throw new Exception('Post not found',404);
            // if($post->user_id != $user->id) throw new Exception('Unauthorized',403);
            $validator = Validator::make($request->all(), [
                'comment' => 'required',
                'parent_id' => 'nullable|exists:post_comments,id',
            ], [
                'comment.required' => 'Comment is required',
                'parent_id.exists' => 'Parent comment not found',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);
            $comment = PostComment::create([
                'post_id' => $id,
                'user_id' => $user->id,
                'comment' => $request->comment,
                'parent_id' => $request->parent_id ?? null
            ]);
            // send notification
            if($user->id != $post->user_id){
                $this->notificationService->send(
                    $user->id,
                    $post->user_id,
                    'comment',
                    "{$user->username} commented on your post.",
                    'New Comment received',
                    ['type' => 'comment', 'post_id' => $post->id, 'comment_id' => $comment->id, 'receiver_username' => $post->user->username]
                );
            }    
            if($comment->parent_id != null){
                // reply notification to parent comment user
                $parentComment = PostComment::find($comment->parent_id);
                if($parentComment && $parentComment->user_id != $user->id){
                    $this->notificationService->send(
                        $user->id,
                        $parentComment->user_id,
                        'reply',
                        "{$user->username} replied to your comment.",
                        'New Reply received',
                        ['type' => 'reply', 'post_id' => $post->id, 'comment_id' => $comment->id, 'receiver_username' => $post->user->username]
                    );
                }
            }
            $post->increment('comment_count');
            return response()->json(['message' => 'Comment send successfully' ,'data' => $comment, 'comment_count' => $post->comment_count],200);
        }catch(Exception $e){
            return response()->json($e->getMessage(),500);
        }
    }

    public function postCommentList(string $id): JsonResponse
    {
        try {
            $post = Post::find($id);
            if (!$post) {
                throw new Exception('Post not found', 404);
            }

            // Fetch only top-level comments (parent_id is NULL)
            $comments = PostComment::where('post_id', $id)
                ->whereNull('parent_id')
                ->where('is_delete', false)
                ->with([
                    // We still need to Eager Load the 'user' relationship
                    // for the accessors (username, avatar) to work efficiently.
                    // We select only the necessary columns: id, username, avatar
                    'user:id,username,avatar',

                    // Eager Load the replies, which is the custom relationship name
                    'replies' => function ($query) {
                        // Eager load the user for each reply as well
                        $query->where('is_delete', false)->with('user:id,username,avatar');
                    },
                ])
                ->latest('created_at') // Order by latest comment
                ->get();

            // The structure of the $comments collection will now automatically
            // include username/avatar and the nested 'replies' array
            // because of the model changes in Step 1.

            return response()->json([
                'message' => 'Comments fetched successfully',
                'data' => $comments
            ], 200);

        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
    // here v2


    public function storeV2(Request $request): JsonResponse
    {
        try {
            $post = $this->createPostV2($request);
            return response()->json($post);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    private function createPostV2(Request $request): Post
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'title' => 'nullable',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
            'images' => 'nullable|array|min:1',
            'images.*' => 'file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
            'score' => 'nullable',
            'analysis'=> 'nullable',
            'ref_data' => 'nullable|array',
            'ref_data.*.title' => 'nullable|string',
            'ref_data.*.score' => 'required_with:ref_data|numeric',
            'ref_data.*.analysis' => 'required_with:ref_data|string',
            'ref_data.*.image' => 'required_with:ref_data|file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
            'measurements' => 'nullable|array',
            'antler_points' => 'nullable|array',
            'deer_age_estimate' => 'nullable|boolean',
            'growth_projection' => 'nullable|boolean',
            'estimated_age' => 'required_if:deer_age_estimate,true|integer',
            'is_public' => 'nullable|boolean',
            'is_private' => 'nullable|boolean',

            'caption' => 'nullable',
            'harvest_type' => 'nullable|string',
            'state' => 'nullable|string',
            'county' => 'nullable|string',
        ], [
            'image.required' => 'Image is required',
            'image.uploaded' => 'Image must be uploaded',
            'image.file' => 'Image must be a file',
            'image.image' => 'Image must be an image',
            'image.mimes' => 'Image must be a jpeg, png, jpg, gif, or svg',
            'images.array' => 'Images must be an array',
            'images.min' => 'At least one image is required',
            'images.*.image' => 'Each image must be an image',
            'images.*.mimes' => 'Each image must be a jpeg, png, jpg, gif, or svg',
            'score.required' => 'Score is required',
            'analysis.required' => 'Analysis is required',
            'ref_data.array' => 'Reference data must be an array',
            'ref_data.*.score.required_with' => 'Score is required for reference data',
            'ref_data.*.score.numeric' => 'Score must be a number for reference data',
            'ref_data.*.analysis.required_with' => 'Analysis is required for reference data',
            'ref_data.*.image.required_with' => 'Image is required for reference data',
            'ref_data.*.image.image' => 'Image must be an image for reference data',
            'ref_data.*.image.mimes' => 'Image must be a jpeg, png, jpg, gif, or svg for reference data',
            'measurements.array' => 'Measurements must be an array',
            'antler_points.array' => 'Antler points must be an array',
            'deer_age_estimate.boolean' => 'Deer age estimate must be true or false',
            'growth_projection.boolean' => 'Growth projection must be true or false',
            'estimated_age.required_if' => 'Estimated age is required when deer age estimate is true',
            'estimated_age.integer' => 'Estimated age must be an integer',
        ]);

        if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

        $image = null;
        if ($request->hasFile('image')) {
            $uploadedImage = uploadImageToPublic(
                $request->file('image'),
                'post-image',
                'post-image'
            );
            $image = $uploadedImage['path'];
        }
        if($request->hasFile('images')){
            $images = [];
            foreach($request->file('images') as $img){
                $uploadedImage = uploadImageToPublic(
                    $img,
                    'post-image',
                    'post-image'
                );
                $images[] = $uploadedImage['path'];
            }
            $image = implode(',', $images);
        }

        $allImagePaths = [$image];
        $is_trophy = $request->is_public ? true : false;
        $post = Post::create([
            'user_id' => $user->id,
            'title' => $request->title ?? null,
            'image' => $image,
            'score' => $request->score ?? null,
            'analysis' => $request->analysis ?? null,
            'measurements' => json_encode($request->measurements) ?? null,
            'antler_points' => json_encode($request->antler_points) ?? null,
            'deer_age_estimate' => $request->deer_age_estimate ?? false,
            'growth_projection' => $request->growth_projection ?? false,
            'estimated_age' => $request->estimated_age ?? null,
            'years_age' => json_encode($request->years_age) ?? null,
            'is_public' => $request->is_public ?? false,
            'is_private' => $request->is_private ?? false,
            'caption' => $request->caption ?? null,
            'state' => $request->state ?? $user->state,
            'county' => $request->county ?? $user->county,
            'harvest_type' => $request->harvest_type ?? null,
            'is_trophy' => $is_trophy,
        ]);
        $user->increment('analysis_count');

        if(isset($request->ref_data)){
            $ref_data = $request->ref_data;
            foreach($ref_data as $item){
                $ref_image = null;
                if (isset($item['image']) && $item['image']) {
                    $ref_image = uploadImageToPublic(
                        $item['image'],
                        'post-image',
                        'post-image'
                    );
                    $ref_image = $ref_image['path'];
                }
                $allImagePaths = array_merge($allImagePaths, [$ref_image]);
                Post::create([
                    'user_id' => $user->id,
                    'title' => $item['title'] ?? null,
                    'image' => $ref_image,
                    'score' => $item['score'],
                    'analysis' => $item['analysis'],
                    'ref_id' => $post->id
                ]);
            }
        }

        $post->update([
            'image' => implode(',', $allImagePaths)
        ]);

        return $post->fresh();
    }

    public function P2P($id, Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'is_public' => 'nullable|boolean',
                'is_private' => 'nullable|boolean',
                'caption' => 'nullable',
                'harvest_type' => 'nullable|string',
                'state' => 'nullable|string',
                'title' => 'nullable|string',
                'county' => 'nullable|string',
                'feed_images' => 'nullable|array',
                'feed_images.*' => 'file|image|mimes:jpeg,png,jpg,gif,svg,heic,heif,image/heic,image/heif',
            ], [
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);
            $post = Post::find($id);
            if(!$request) throw new Exception('Post not found',404);

            
            if($post->is_public != true){
                if ($request->hasFile('feed_images')) {
                    $paths = [];
                    $uploadedImages = $request->file('feed_images');

                    foreach ($uploadedImages as $image) {
                        $uploadedImage = uploadImageToPublic(
                            $image,
                            'post-image',
                            'post-image'
                        );
                        $paths[] = $uploadedImage['path'];
                    }
                    $images = implode(',', $paths);

                    $post->update([
                        'feed_images' => $images,
                    ]);
                }                
            }


            $post->update([
                'title' => $request->title ?? null,
                'is_private' => $request->is_private ?? false,
                'is_trophy' => true,
                'is_public' => $request->is_public ?? false,
                'caption' => $request->caption ?? null,
                'harvest_type' => $request->harvest_type ?? null,
                'state' =>  $request->state ?? $post->state ?? $user->state,
                'county' => $request->county ?? $post->county ?? $user->county,
                'created_at' => now(),
            ]);

            // get feed_images size and make its array and sent in api response here.. code

            $feedImagesArray = [];

            if (!empty($post->feed_images)) {
                $images = explode(',', $post->feed_images);

                foreach ($images as $path) {
                    // $fullPath = public_path($path);

                    // if (file_exists($fullPath)) {
                        $feedImagesArray[] = [
                            'path' => $path,
                            // 'size' => filesize($fullPath), // bytes
                            // 'size_kb' => round(filesize($fullPath) / 1024, 2),
                            // 'size_mb' => round(filesize($fullPath) / (1024 * 1024), 2),
                        ];
                    // }
                }
            }

            

            return response()->json(['message' => 'Post updated successfully', 'post' => $post, 'feed_images' => $feedImagesArray],200);

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'post_ids' => 'required|array|min:1',
                'post_ids.*' => 'exists:posts,id',
            ], [
                'post_ids.required' => 'Post IDs are required',
                'post_ids.array' => 'Post IDs must be an array',
                'post_ids.min' => 'At least one Post ID is required',
                'post_ids.*.exists' => 'One or more Post IDs do not exist',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

            $postIds = $request->post_ids;

            // Update is_delete to true for the specified posts
            Post::whereIn('id', $postIds)
                ->where('user_id', $user->id) // Ensure user owns the posts
                ->update(['is_delete' => true]);

            return response()->json(['message' => 'Posts deleted successfully'], 200);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function delete($id): JsonResponse
    {
        try {
            $user = Auth::user();

            $validator = Validator::make(request()->all(), [
                'type' => 'required|in:delete,public,private',
            ],[
                'type.required' => 'Type is required',
                'type.in' => 'Type must be one of the following: delete, public, private',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

            $type = request()->input('type');
            $post = Post::where('id', $id)->where('user_id', $user->id)->first();
            if (!$post) throw new Exception('Post not found', 404);
            if ($type === 'delete') {
                $post->update(['is_delete' => true, 'is_public' => false, 'is_private' => false]);
            } elseif ($type === 'public') {
                $post->update([
                    'is_public' => false,
                    'is_delete' => $post->is_private ? false : true,
                ]);
            } elseif ($type === 'private') {
                $post->update([
                    'is_private' => false,
                    'is_delete' => $post->is_public ? false : true,
                ]);
            }

            return response()->json(['message' => 'All posts deleted successfully', 'post' => $post], 200);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function reScore(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $post = Post::where('id', $id)
                ->where('user_id', $user->id)
                ->whereNull('ref_id')
                ->first();

            if (!$post) {
                throw new Exception('Post not found', 404);
            }

            if ($post->linked_post_id) {
                $latest = $post->getLatestInChain();
                throw new Exception(
                    "Re-score from the latest version (post id: {$latest->id})",
                    400
                );
            }

            $newPost = $this->createPostV2($request);
            $post->update(['linked_post_id' => $newPost->id]);

            $rootPost = $post->getRootPost();
            $rootPost->update([
                'score' => $newPost->score,
                'analysis' => $newPost->analysis,
                'image' => $newPost->image,
                'measurements' => $newPost->measurements,
                'antler_points' => $newPost->antler_points,
            ]);

            $post->refresh();

            return response()->json([
                'message' => 'Post re-scored successfully',
                'post' => $post,
                'new_post' => $newPost,
                'root_post_id' => $rootPost->id,
                'latest_post_id' => $newPost->id,
            ], 200);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function reScoreHistory($id): JsonResponse
    {
        try {
            $post = Post::where('id', $id)->whereNull('ref_id')->first();
            if (!$post) {
                throw new Exception('Post not found', 404);
            }

            $root = $post->getRootPost();
            $chain = collect($root->getRescoreChain())->map(function (Post $chainPost) {
                return [
                    'id' => $chainPost->id,
                    'score' => $chainPost->score,
                    'analysis' => $chainPost->analysis,
                    'image' => $chainPost->image,
                    'linked_post_id' => $chainPost->linked_post_id,
                    'created_at' => $chainPost->created_at,
                ];
            });

            return response()->json([
                'root_post_id' => $root->id,
                'latest_post_id' => $root->getLatestInChain()->id,
                'rescore_count' => max($chain->count() - 1, 0),
                'chain' => $chain,
            ], 200);
        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
