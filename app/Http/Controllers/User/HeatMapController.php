<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\GeoStats;
use App\Models\Post;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;

class HeatMapController extends Controller
{

    public function postByCounty(Request $request)
    {
        try{
            $county = $request->query('county');
            $state = $request->query('state');
            $is_public = $request->query('is_public',1);

            if (!$county) {
                return response()->json(['error' => 'County parameter is required.'], 400);
            }
            $selectFields = [
                    'posts.created_at',
                    'posts.caption',
                    'posts.harvest_type',
                    'posts.id',
                    'posts.state',
                    'posts.county',
                    'posts.image',
                    'posts.feed_images',
                    'posts.user_id',
                    'users.full_name',
                    'users.avatar',
                    'users.username',
                    'users.is_premium',
                    'posts.score',
                ];

            $postsQuery = Post::select($selectFields)
                    ->join('users', 'users.id', '=', 'posts.user_id')
                    ->where('posts.is_delete', false)
                    ->whereNull('posts.ref_id')
                    ->where('posts.county', $county)
                    ->where('posts.state', $state);
                    if($is_public == 1){
                        $postsQuery = $postsQuery->where('posts.is_public', 1);
                    }else{
                        $postsQuery = $postsQuery->where('posts.is_private', 1)
                        ->where('posts.score', '>', 20);
                    }

                    // paginate here
            $postsQuery = $postsQuery->paginate(20);
            return response()->json($postsQuery);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);

        }
    }

    public function getHeatmapData(Request $request)
    {
        $stateParam = $request->query('state');
        $filter = $request->query('heat_by', 'post_count'); // 'post_count' ya 'average_score'

        $sortBy = $request->query('sort_by', 'all_time'); // '30_days', '90_days', 'all_time'

        $is_public = $request->query('is_public',1);

        // Scenario 1: No state provided -> Group by State
        $query = GeoStats::select('state')
        ->selectRaw('SUM(no_of_posts) as no_of_posts')
        ->selectRaw('AVG(average_score) as average_score')
        ->selectRaw('MAX(highest_score) as highest_score')
        ->selectRaw('SUM(scored_posts_count) as scored_posts_count')
        ->groupBy('state');
        
        $query->where('time_window', $sortBy)
        ->where('is_public', $is_public);
        
        if (!$stateParam) {
            $data = $query->get();
            return $this->formatResponse($data, 'state', $filter);
        }

        // Scenario 2: State provided -> Filter by state and Group by County
        $data = GeoStats::where('state', $stateParam)
            ->whereNotNull('county')
            ->where('time_window', $sortBy)
            ->where('is_public', $is_public)
            ->select('county', 'no_of_posts', 'average_score', 'highest_score','scored_posts_count')
            ->get();

        return $this->formatResponse($data, 'county', $filter);
    }

    private function formatResponse($data, $keyName, $filter)
    {
        // Determine which field to use for ranking
        $filterField = ($filter == 'average_score') ? 'average_score' : 'no_of_posts';

        // Sort data by filter field (descending - highest first)
        $sortedData = $data->sortByDesc($filterField)->values();
        
        // Get unique values in descending order
        $uniqueValues = $sortedData->pluck($filterField)->unique()->sort()->values();
        $totalUniqueValues = $uniqueValues->count();
        
        // Create value to rank mapping with proper distribution
        $valueToRankMap = [];
        
        foreach ($uniqueValues as $index => $value) {
            // Calculate which rank this value belongs to
            // Distribute evenly across 7 ranks
            $rank = ceil((($index + 1) / $totalUniqueValues) * 7);
            $rank = max(1, min(7, $rank));
            $valueToRankMap[$value] = $rank;
        }

        // Assign ranks to all items
        $rankedData = $sortedData->map(function($item) use ($filterField, $valueToRankMap) {
            $item->calculated_rank = $valueToRankMap[$item->$filterField];
            return $item;
        });

        // Format final response
        return $rankedData->mapWithKeys(function ($item) use ($keyName) {
            $name = $item->$keyName;

            return [
                $name => [
                    "no_of_posts"   => (int) $item->no_of_posts,
                    "no_of_scored_posts"   => (int) $item->scored_posts_count,
                    "average_score" => round((float) $item->average_score, 2),
                    "highest_score" => (float) $item->highest_score,
                    "rank"          => $item->calculated_rank
                ]
            ];
        });
    }

    public function PostCount(){
        // public, privet count from geostats table
        $publicCount = Post::where('is_private', 0)->count();
        // $privateCount = GeoStats::where('is_public', 0)->sum('no_of_posts');
        $totalCount = Post::
        // where('is_trophy', 1)  // Only include trophy posts
        //         ->where('score', '>', 20)
        //         ->where('is_delete', 0)
        //         ->where('ref_id', null)
        //         ->
                count();
        $totalCount = strval($totalCount);
        return response()->json(['public_count' => $publicCount, 'private_count' => $totalCount]);
    }


    // v2 api

    public function getHeatmapDatav2(Request $request)
    {
        $stateParam = $request->query('state');
        $filter = $request->query('heat_by', 'post_count');
        $sortBy = $request->query('sort_by', 'all_time');
        $is_public = $request->query('is_public', 1);

        // Scenario 1: No state provided -> Group by State
        $query = GeoStats::select('state')
            ->selectRaw('SUM(no_of_posts) as no_of_posts')
            ->selectRaw('AVG(average_score) as average_score')
            ->selectRaw('MAX(highest_score) as highest_score')
            ->selectRaw('SUM(scored_posts_count) as scored_posts_count')
            ->where('time_window', $sortBy)
            ->where('is_public', $is_public)
            ->groupBy('state');

        if (!$stateParam) {
            $data = $query->get();
            $totalPosts = $data->sum('no_of_posts');

            return $this->formatResponsev2($data, 'state', $filter, $totalPosts);
        }

        // Scenario 2: State provided -> Group by County
        $data = GeoStats::where('state', $stateParam)
            ->whereNotNull('county')
            ->where('time_window', $sortBy)
            ->where('is_public', $is_public)
            ->selectRaw('county')
            ->selectRaw('SUM(no_of_posts) as no_of_posts')
            ->selectRaw('AVG(average_score) as average_score')
            ->selectRaw('MAX(highest_score) as highest_score')
            ->selectRaw('SUM(scored_posts_count) as scored_posts_count')
            ->groupBy('county')
            ->get();

        $totalPosts = $data->sum('no_of_posts');

        return $this->formatResponsev2($data, 'county', $filter, $totalPosts);
    }

    private function formatResponsev2($data, $keyName, $filter, $totalPosts = 0)
    {
        $filterField = ($filter == 'average_score') ? 'average_score' : 'no_of_posts';

        $sortedData = $data->sortByDesc($filterField)->values();

        $uniqueValues = $sortedData->pluck($filterField)->unique()->sort()->values();
        $totalUniqueValues = $uniqueValues->count();

        $valueToRankMap = [];

        foreach ($uniqueValues as $index => $value) {
            $rank = ceil((($index + 1) / $totalUniqueValues) * 7);
            $rank = max(1, min(7, $rank));
            $valueToRankMap[$value] = $rank;
        }

        $rankedData = $sortedData->map(function($item) use ($filterField, $valueToRankMap) {
            $item->calculated_rank = $valueToRankMap[$item->$filterField];
            return $item;
        });

        $formattedData = $rankedData->mapWithKeys(function ($item) use ($keyName) {
            $name = $item->$keyName;

            return [
                $name => [
                    "no_of_posts" => (int) $item->no_of_posts,
                    "no_of_scored_posts" => (int) $item->scored_posts_count,
                    "average_score" => round((float) $item->average_score, 2),
                    "highest_score" => (float) $item->highest_score,
                    "rank" => $item->calculated_rank
                ]
            ];
        });

        return [
            "total_post_count" => (int) $totalPosts,
            "data" => $formattedData
        ];
    }

    public function premiumHunters(Request $request)
    {
        $state = $request->state;

        $query = User::where('is_premium', 1)
            ->whereNotNull('state')
            ->whereNotNull('county')
            ->select('id', 'full_name', 'email', 'username', 'avatar', 'state', 'county');

        $users = $query->get();

        // 🔹 If state passed → group by county
        if ($state) {
            $users = $users->where('state', $state);

            $grouped = $users->groupBy('county')->map(function ($items) {
                return $items->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'email' => $user->email,
                        'full_name' => $user->full_name,
                        'username' => $user->username,
                        'avatar' => $user->avatar
                    ];
                })->values();
            });

        } else {
            // 🔹 No state → group by state
            $grouped = $users->groupBy('state')->map(function ($items) {
                return $items->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'email' => $user->email,
                        'full_name' => $user->full_name,
                        'username' => $user->username,
                        'avatar' => $user->avatar
                    ];
                })->values();
            });
        }

        return response()->json($grouped);
    }
}
