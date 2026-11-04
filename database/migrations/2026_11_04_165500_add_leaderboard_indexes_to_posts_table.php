<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Equality filters first, then ORDER BY score for leaderboard / trophy lists
            $table->index(
                ['is_trophy', 'is_delete', 'ref_id', 'score'],
                'posts_leaderboard_score_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_leaderboard_score_idx');
        });
    }
};
