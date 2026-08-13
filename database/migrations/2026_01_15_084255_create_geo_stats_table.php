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
        Schema::create('geo_stats', function (Blueprint $table) {
            $table->id();
            $table->string('state')->index(); // e.g., 'California'
            $table->string('county')->nullable()->index(); // e.g., 'Orange'
            
            $table->integer('no_of_posts')->default(0);
            $table->decimal('average_score', 8, 2)->default(0.00);
            $table->decimal('highest_score', 8, 2)->default(0.00);
            $table->integer('scored_posts_count')->default(0);
            $table->enum('time_window', ['30_days', '90_days', 'all_time'])->index();
            $table->boolean('is_public')->default(false);
            $table->unique(['state', 'county', 'time_window']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geo_stats');
    }
};
