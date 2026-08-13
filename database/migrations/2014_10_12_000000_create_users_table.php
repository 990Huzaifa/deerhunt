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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('username')->unique()->nullable();
            $table->longText('bio')->nullable();
            $table->string('email',100)->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('state')->nullable();
            $table->string('password')->nullable();
            $table->string('avatar')->nullable();
            $table->string('personal_site')->nullable();
            $table->string('google_id')->nullable();
            $table->string('facebook_id')->nullable();
            $table->string('apple_id')->nullable();
            $table->string('fcm_token')->nullable();
            $table->bigInteger('analysis_count')->default(0);
            $table->enum('is_active', ['active', 'inactive'])->default('active');
            
            $table->longText('county')->nullable();
            $table->string('phone')->nullable();
            $table->longText('species')->nullable();
            $table->longText('hunt_type')->nullable();
            $table->decimal('starting_price',8,2)->nullable();
            $table->longText('highlight_photos')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_bot')->default(false);
            
            $table->string('listen_from')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('device_id')->nullable();
            $table->longText('app_version')->nullable();
            $table->boolean('is_delete')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
