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
            $table->index(
                ['is_public', 'is_delete', 'ref_id', 'created_at'],
                'posts_community_created_idx'
            );
            $table->index(
                ['is_public', 'is_delete', 'ref_id', 'like_count'],
                'posts_community_liked_idx'
            );
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->index(['follower_id', 'following_id'], 'follows_follower_following_idx');
        });

        Schema::table('post_likes', function (Blueprint $table) {
            $table->index(['user_id', 'post_id'], 'post_likes_user_post_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_community_created_idx');
            $table->dropIndex('posts_community_liked_idx');
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->dropIndex('follows_follower_following_idx');
        });

        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropIndex('post_likes_user_post_idx');
        });
    }
};
