<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateGeoStats extends Command
{
    protected $signature = 'app:aggregate-geo-stats';
    protected $description = 'Aggregate post data by state and county for heatmap';

    public function handle(): int
    {
        $this->info('Aggregation start...');

        $timeWindows = ['30_days', '90_days', 'all_time'];
        $rows = [];
        $now = now();

        foreach ($timeWindows as $timeWindow) {
            $query = DB::table('posts')
                ->select([
                    'state',
                    'county',
                    'is_private',
                    DB::raw('COUNT(*) as no_of_posts'),
                    DB::raw('COALESCE(AVG(CASE WHEN score IS NOT NULL AND score > 20 THEN score END), 0) as average_score'),
                    DB::raw('COALESCE(MAX(CASE WHEN score IS NOT NULL AND score > 20 THEN score END), 0) as highest_score'),
                    DB::raw('COUNT(CASE WHEN score IS NOT NULL AND score > 20 THEN 1 END) as scored_posts_count'),
                ])
                ->whereNotNull('state')
                ->where('score', '>', 20)
                ->where('is_delete', 0);

            if ($timeWindow === '30_days') {
                $query->where('created_at', '>=', Carbon::now()->subDays(30));
            } elseif ($timeWindow === '90_days') {
                $query->where('created_at', '>=', Carbon::now()->subDays(90));
            }

            $stats = $query->groupBy('state', 'county', 'is_private')->get();

            foreach ($stats as $stat) {
                $rows[] = [
                    'state' => $stat->state,
                    'county' => $stat->county,
                    'time_window' => $timeWindow,
                    'is_public' => $stat->is_private ? 0 : 1,
                    'no_of_posts' => $stat->no_of_posts,
                    'average_score' => $stat->average_score,
                    'highest_score' => $stat->highest_score,
                    'scored_posts_count' => $stat->scored_posts_count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($rows) {
            DB::table('geo_stats')->delete();

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('geo_stats')->insert($chunk);
            }
        });

        $this->info('Aggregation Completed!');

        return self::SUCCESS;
    }
}
