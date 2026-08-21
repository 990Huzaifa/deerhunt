<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostShare;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Exception;


class HomeController extends Controller
{
    public function index(): JsonResponse
    {
        try{
            $user = Auth::user();

            $topScore = Post::where('user_id', $user->id)->where('is_trophy', true)->where('ref_id', null)->where('is_delete', false)->orderBy('score', 'desc')->value('score');
            $shared = PostShare::where('user_id', $user->id)->count();
            $trophyRoom = Post::where('user_id', $user->id)->where('is_trophy', true)->where('ref_id', null)->where('is_delete', false)->count();

            $data = [
                'TopScore' => $topScore ?? 0,
                'Shared' => $shared ?? 0,
                'TrophyRoom' => $trophyRoom ?? 0,
            ];

            $recentPosts = Post::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->where('ref_id', null)
                ->where('is_delete', false)
                ->where('is_public', false)
                ->take(5)
                ->get();

            $communityPosts = Post::orderBy('created_at', 'desc')
                ->where('ref_id', null)
                ->where('is_delete', false)
                ->where('is_public', true)
                ->take(5)
                ->get();

            
            return response()->json(['stats' => $data, 'recentPosts' => $recentPosts, 'communityPosts' => $communityPosts], 200);

        }catch(QueryException $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }

    public function recent(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();

            $posts = Post::select('posts.*', 'users.full_name', 'users.avatar')
                ->join('users', 'users.id', '=', 'posts.user_id')->orderBy('created_at', 'desc')
                ->where('posts.user_id', $user->id)
                ->where('posts.is_delete', false)
                ->where('posts.ref_id', null)
                ->where('posts.is_public', false)
                ->paginate(200);
            return response()->json($posts);
        }catch(QueryException $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }


    public function leaderboard():JsonResponse
    {
        try{
            $leaderboard = Post::select('posts.*', 'users.full_name', 'users.avatar')
                ->join('users', 'users.id', '=', 'posts.user_id')->orderBy('posts.score', 'desc')
                ->where('posts.is_delete', false)
                ->where('posts.ref_id', null)
                ->where('posts.is_trophy', true)
                ->paginate(200);
            return response()->json($leaderboard);
        }catch(QueryException $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
