<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AggregateGeoStats extends Command
{
    protected $signature = 'app:aggregate-geo-stats';
    protected $description = 'Aggregate post data by state and county for heatmap';

    public function handle()
    {
        $this->info('Aggregation start...');
        DB::table('geo_stats')->truncate();  // Clear old data

        $timeWindows = ['30_days', '90_days', 'all_time']; // Define the time windows

        foreach ($timeWindows as $timeWindow) {
            $query = DB::table('posts')
                ->select([
                    'state',
                    'county',
                    'is_private',
                    DB::raw('COUNT(*) as no_of_posts'),
                    DB::raw('COALESCE(AVG(CASE WHEN score IS NOT NULL AND score > 20 THEN score END), 0) as average_score'),
                    DB::raw('COALESCE(MAX(CASE WHEN score IS NOT NULL AND score > 20 THEN score END), 0) as highest_score'),
                    DB::raw('COUNT(CASE WHEN score IS NOT NULL AND score > 20 THEN 1 END) as scored_posts_count')
                ])
                ->whereNotNull('state') // Ensure state is not null
                // ->where('is_trophy', 1)  // Only include trophy posts
                // ->where('is_public', 1)  // Only include public posts
                ->where('score', '>', 20)
                ->where('is_delete', 0); // Ensure posts are not deleted

            // Filter by created_at for time window (if it's not 'all_time')
            if ($timeWindow === '30_days') {
                $startDate = Carbon::now()->subDays(30); // Get posts in the last 30 days
            } elseif ($timeWindow === '90_days') {
                $startDate = Carbon::now()->subDays(90); // Get posts in the last 90 days
            } else {
                $startDate = Carbon::minValue(); // No filter for all_time
            }

            if ($timeWindow !== 'all_time') {
                $query->where('created_at', '>=', $startDate); // Apply date filter for 30_days or 90_days
            }

            // Perform aggregation by state and county
            $stats = $query->groupBy('state', 'county', 'is_private')->get();

            foreach ($stats as $stat) {
                // Use `firstOrCreate` instead of `updateOrInsert`
                DB::table('geo_stats')->updateOrInsert(
                    [
                        'state' => $stat->state,
                        'county' => $stat->county,
                        'time_window' => $timeWindow, // Include the time window
                        'is_public' => $stat->is_private ? 0 : 1 // 👈 add here
                    ],
                    [
                        'no_of_posts' => $stat->no_of_posts,
                        'average_score' => $stat->average_score,
                        'highest_score' => $stat->highest_score,
                        'scored_posts_count' => $stat->scored_posts_count,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->info('Aggregation Completed!');
    }
}
