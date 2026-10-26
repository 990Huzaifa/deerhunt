<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_engagements', function (Blueprint $table) {
            $table->timestamp('first_score_reminder_sent_at')->nullable()->after('second_score_at');
            $table->timestamp('second_score_reminder_sent_at')->nullable()->after('first_score_reminder_sent_at');
            $table->timestamp('score_ready_email_sent_at')->nullable()->after('second_score_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_engagements', function (Blueprint $table) {
            $table->dropColumn([
                'first_score_reminder_sent_at',
                'second_score_reminder_sent_at',
                'score_ready_email_sent_at',
            ]);
        });
    }
};
