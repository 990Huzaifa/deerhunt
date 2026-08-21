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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // not cascade
            $table->foreign('user_id')->references('id')->on('users')->onDelete(null);
            $table->string('title')->nullable();
            $table->longText('image')->nullable();
            $table->longText('feed_images')->nullable();
            $table->bigInteger('share_count')->default(0);
            $table->bigInteger('like_count')->default(0);
            $table->bigInteger('comment_count')->default(0);
            $table->decimal('score',8,2)->nullable();
            $table->longText('analysis')->nullable();
            $table->longText('antler_points')->nullable();
            $table->longText('measurements')->nullable();
            $table->boolean('deer_age_estimate')->default(false);
            $table->boolean('growth_projection')->default(false);
            $table->bigInteger('estimated_age')->nullable();
            $table->longText('years_age')->nullable();
            $table->longText('comment')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_private')->default(true);
            $table->boolean('is_trophy')->default(false);
            $table->boolean('is_delete')->default(false);
            $table->longText('caption')->nullable();
            $table->longText('state')->nullable();
            $table->longText('county')->nullable();
            $table->longText('harvest_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->unsignedBigInteger('linked_post_id')->nullable();
            $table->foreign('linked_post_id')->references('id')->on('posts')->onDelete(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
