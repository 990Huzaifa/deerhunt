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
        Schema::table('post_comments', function (Blueprint $table) {
            // Top-level comments: post_id + parent_id IS NULL + is_delete + ORDER BY created_at
            $table->index(
                ['post_id', 'parent_id', 'is_delete', 'created_at'],
                'post_comments_list_idx'
            );

            // Replies eager-load by parent_id
            $table->index(
                ['parent_id', 'is_delete', 'created_at'],
                'post_comments_replies_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropIndex('post_comments_list_idx');
            $table->dropIndex('post_comments_replies_idx');
        });
    }
};
