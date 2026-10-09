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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('credits_per_month');
            $table->bigInteger('total_credits');
            $table->bigInteger('released_credits');
            $table->enum('plan', ["elite-yearly", "elite-monthly","pro-yearly", "pro-monthly", "elite_yearly", "elite_monthly","pro_yearly", "pro_monthly"]);
            $table->enum('platform', ['google', 'apple']);
            $table->enum('status', ['active', 'expired', 'canceled']);
            $table->enum('renewal_period', ['monthly','yearly']);
            $table->longText('transaction_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('last_released_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
