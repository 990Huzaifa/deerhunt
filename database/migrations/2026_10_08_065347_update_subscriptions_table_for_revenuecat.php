<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `subscriptions` MODIFY `plan` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `subscriptions` MODIFY `credits_per_month` BIGINT NULL');
        DB::statement('ALTER TABLE `subscriptions` MODIFY `total_credits` BIGINT NULL');
        DB::statement('ALTER TABLE `subscriptions` MODIFY `released_credits` BIGINT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `subscriptions` MODIFY `credits_per_month` BIGINT NOT NULL');
        DB::statement('ALTER TABLE `subscriptions` MODIFY `total_credits` BIGINT NOT NULL');
        DB::statement('ALTER TABLE `subscriptions` MODIFY `released_credits` BIGINT NOT NULL');
    }
};
