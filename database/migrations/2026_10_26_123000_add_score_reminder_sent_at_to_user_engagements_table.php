<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_engagements', 'first_score_reminder_sent_at')) {
            Schema::table('user_engagements', function (Blueprint $table) {
                $table->timestamp('first_score_reminder_sent_at')->nullable()->after('second_score_at');
            });
        }

        if (!Schema::hasColumn('user_engagements', 'second_score_reminder_sent_at')) {
            Schema::table('user_engagements', function (Blueprint $table) {
                $table->timestamp('second_score_reminder_sent_at')->nullable()->after('first_score_reminder_sent_at');
            });
        }

        if (!Schema::hasColumn('user_engagements', 'score_ready_email_sent_at')) {
            Schema::table('user_engagements', function (Blueprint $table) {
                $table->timestamp('score_ready_email_sent_at')->nullable()->after('second_score_reminder_sent_at');
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('user_engagements', 'first_score_reminder_sent_at') ? 'first_score_reminder_sent_at' : null,
            Schema::hasColumn('user_engagements', 'second_score_reminder_sent_at') ? 'second_score_reminder_sent_at' : null,
            Schema::hasColumn('user_engagements', 'score_ready_email_sent_at') ? 'score_ready_email_sent_at' : null,
        ]));

        if (!empty($columns)) {
            Schema::table('user_engagements', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
