<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_engagements', 'paywall_reminder_sent_at')) {
            Schema::table('user_engagements', function (Blueprint $table) {
                $table->timestamp('paywall_reminder_sent_at')->nullable()->after('score_ready_email_sent_at');
            });
        }

        if (!Schema::hasColumn('user_engagements', 'feature_reminder_sent_at')) {
            Schema::table('user_engagements', function (Blueprint $table) {
                $table->timestamp('feature_reminder_sent_at')->nullable()->after('paywall_reminder_sent_at');
            });
        }

        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM(
            'like',
            'comment',
            'reply',
            'follow',
            'high_score',
            'first_score_reminder',
            'paywall_reminder',
            'feature_reminder'
        ) NOT NULL");
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('user_engagements', 'paywall_reminder_sent_at') ? 'paywall_reminder_sent_at' : null,
            Schema::hasColumn('user_engagements', 'feature_reminder_sent_at') ? 'feature_reminder_sent_at' : null,
        ]));

        if (!empty($columns)) {
            Schema::table('user_engagements', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }

        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM(
            'like',
            'comment',
            'reply',
            'follow',
            'high_score'
        ) NOT NULL");
    }
};
