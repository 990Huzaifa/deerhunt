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

            $topScore = Post::where('user_id', $user->id)->where('is_trophy', true)->where('ref_id', null)->where('is_delete', false)->excludeRescoreVersions()->orderBy('score', 'desc')->value('score');
            $shared = PostShare::where('user_id', $user->id)->count();
            $trophyRoom = Post::where('user_id', $user->id)->where('is_trophy', true)->where('ref_id', null)->where('is_delete', false)->excludeRescoreVersions()->count();

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
                ->excludeRescoreVersions()
                ->take(5)
                ->get();

            $communityPosts = Post::orderBy('created_at', 'desc')
                ->where('ref_id', null)
                ->where('is_delete', false)
                ->where('is_public', true)
                ->excludeRescoreVersions()
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

            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

            $posts = Post::query()
                ->select([
                    'posts.id',
                    'posts.user_id',
                    'posts.title',
                    'posts.image',
                    'posts.score',
                    'posts.like_count',
                    'posts.comment_count',
                    'posts.share_count',
                    'posts.state',
                    'posts.county',
                    'posts.harvest_type',
                    'posts.is_trophy',
                    'posts.is_public',
                    'posts.created_at',
                    'users.full_name',
                    'users.avatar',
                ])
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->where('posts.user_id', $user->id)
                ->where('posts.is_delete', false)
                ->whereNull('posts.ref_id')
                ->excludeRescoreVersions()
                ->orderByDesc('posts.created_at')
                ->orderByDesc('posts.id')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json($posts);
        }catch(QueryException $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }


    public function leaderboard(Request $request):JsonResponse
    {
        try{
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

            $leaderboard = Post::query()
                ->select([
                    'posts.id',
                    'posts.user_id',
                    'posts.title',
                    'posts.image',
                    'posts.score',
                    'posts.like_count',
                    'posts.comment_count',
                    'posts.share_count',
                    'posts.state',
                    'posts.county',
                    'posts.harvest_type',
                    'posts.created_at',
                    'users.full_name',
                    'users.avatar',
                ])
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->where('posts.is_delete', false)
                ->whereNull('posts.ref_id')
                ->where('posts.is_trophy', true)
                ->excludeRescoreVersions()
                ->orderByDesc('posts.score')
                ->orderByDesc('posts.id')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json($leaderboard);
        }catch(QueryException $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
