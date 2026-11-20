<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geo_stats', function (Blueprint $table) {
            $table->dropUnique('geo_stats_state_county_time_window_unique');
            $table->unique(
                ['state', 'county', 'time_window', 'is_public'],
                'geo_stats_state_county_window_public_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('geo_stats', function (Blueprint $table) {
            $table->dropUnique('geo_stats_state_county_window_public_unique');
            $table->unique(['state', 'county', 'time_window']);
        });
    }
};
