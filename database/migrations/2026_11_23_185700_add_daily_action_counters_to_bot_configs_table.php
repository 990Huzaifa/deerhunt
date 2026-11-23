<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('bot_configs', 'likes_done_today')) {
                $table->integer('likes_done_today')->default(0)->after('daily_comment_limit');
            }
            if (!Schema::hasColumn('bot_configs', 'comments_done_today')) {
                $table->integer('comments_done_today')->default(0)->after('likes_done_today');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('bot_configs', 'likes_done_today') ? 'likes_done_today' : null,
                Schema::hasColumn('bot_configs', 'comments_done_today') ? 'comments_done_today' : null,
            ]));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
