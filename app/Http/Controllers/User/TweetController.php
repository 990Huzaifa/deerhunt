<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Models\Tweet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Models\TweetView;
use App\Models\TweetShare;
use App\Models\TweetLike;
use App\Models\TweetComment;

class TweetController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tweets = Tweet::select('tweets.*', 'users.full_name', 'users.avatar', 'users.username','users.state')
                ->join('users', 'users.id', '=', 'tweets.user_id')->orderBy('created_at', 'desc')
                ->where('tweets.user_id', $user->id)
                ->where('tweets.is_delete', false)
                ->paginate(20);

            // following tweets
            $following_tweets = Tweet::select('tweets.*', 'users.full_name', 'users.avatar', 'users.username','users.state')
                ->join('users', 'users.id', '=', 'tweets.user_id')
                ->join('follows', 'follows.followed_id', '=', 'tweets.user_id')
                ->where('follows.follower_id', $user->id)
                ->where('tweets.is_delete', false)
                ->orderBy('tweets.created_at', 'desc')
                ->paginate(20);
            return response()->json([
                'feeds' => $tweets,
                'following_tweets' => $following_tweets
            ]);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'caption' => 'required',
                'images' => 'required|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg',
                'score' => 'required',
                'state' => 'nullable|string',
                'harvest_type' => 'nullable|string',
                'is_public' => 'nullable|boolean',
            ], [
                'caption.required' => 'Caption is required',
                'images.required' => 'At least one image is required',
                'images.array' => 'Images must be an array',
                'images.*.image' => 'Each file must be an image',
                'images.*.mimes' => 'Each image must be a file of type: jpeg, png, jpg, gif, svg',
                'score.required' => 'Score data is required',
                'state.string' => 'State must be a string',
                'harvest_type.string' => 'Harvest type must be a string',
                'is_public.boolean' => 'Is public must be a boolean',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);
    
            $images = [];
            // handle multiple image upload
            foreach($request->images as $image){
                $image_name = 'image-' . $user->id . '-' . time() .rand(1000, 999999) . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('tweet'), $image_name);
                $images[] = 'tweet/' . $image_name;
            }
            $tweet = Tweet::create([
                'user_id' => $user->id,
                'caption' => $request->captionz,
                'images' => json_encode($images),
                'score_data' => $request->score,
                'state' => $request->state,
                'harvest_type' => $request->harvest_type ?? null,
                'is_public' => $request->is_public ?? true,
            ]);
            return response()->json($tweet);

        } catch (QueryException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function tweetLike(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $tweet = Tweet::find($id);
            if(!$tweet) throw new Exception('Tweet not found',404);
            $check_like = TweetLike::where('tweet_id',$id)->where('user_id',$user->id)->first();
            if($check_like){
                $check_like->delete();
                $tweet->decrement('like_count');
                return response()->json(['message' => 'Tweet unliked'],200);
            }else{
                $tweet_like = TweetLike::create([
                    'tweet_id' => $id,
                    'user_id' => $user->id
                ]);
                $tweet->increment('like_count');
                return response()->json($tweet_like,200);
            }
        }catch(Exception $e){
            return response()->json($e->getMessage(),500);
        }
    }

    public function tweetShare(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $tweet = Tweet::find($id);
            if(!$tweet) throw new Exception('Tweet not found',404);
                $tweet_share = TweetShare::create([
                    'tweet_id' => $id,
                    'user_id' => $user->id
                ]);  
            $tweet->increment('share_count');
            return response()->json("Tweet shared",200);
        }catch(Exception $e){
            return response()->json($e->getMessage(),500);
        }
    }

    public function tweetComment(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $tweet = Tweet::find($id);
            if(!$tweet) throw new Exception('Tweet not found',404);
            $validator = Validator::make($request->all(), [
                'comment' => 'required',
            ], [
                'comment.required' => 'Comment is required',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

            TweetComment::create([
                'user_id' => $user->id,
                'tweet_id' => $id,
                'comment' => $request->comment,
                'parent_id' => $request->parent_id ?? null,
            ]);
            return response()->json(['message' => 'Comment send successfully'],200);
        }catch(Exception $e){
            return response()->json($e->getMessage(),500);
        }
    }
}


