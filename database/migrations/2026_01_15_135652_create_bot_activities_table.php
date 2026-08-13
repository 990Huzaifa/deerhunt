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
        Schema::create('bot_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('users')->onDelete('cascade');

            $table->unsignedBigInteger('target_post_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();

            $table->enum('action_type', ['like', 'comment', 'follow', 'repost', 'dm']);

            $table->text('metadata')->nullable(); 

            $table->timestamps();

            // Indexes for fast reporting
            $table->index(['bot_id', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_activities');
    }
};
