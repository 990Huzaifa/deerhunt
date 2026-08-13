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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');   // who performed the action
            $table->unsignedBigInteger('receiver_id'); // who receives the notification
            $table->longText('title'); // like, comment, follow
            $table->enum('type', ['like', 'comment', 'reply', 'follow']); // like, comment, reply, follow
            $table->longText('message')->nullable(); // message shown to user
            $table->json('data')->nullable(); // extra data like post_id, comment_id etc.
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
