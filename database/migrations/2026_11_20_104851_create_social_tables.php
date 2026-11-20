<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamps();
        });

        Schema::create('social_friendships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16);
            $table->timestamps();
            $table->unique(['user_low_id', 'user_high_id']);
            $table->index(['user_high_id', 'status']);
        });

        Schema::create('social_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id']);
            $table->index(['blocked_id', 'blocker_id']);
        });

        Schema::create('social_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->string('visibility', 16);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at', 'id']);
            $table->index(['deleted_at', 'id']);
        });

        Schema::create('social_post_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('mime_type', 64);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->index(['post_id', 'sort_order']);
        });

        Schema::create('social_post_likes', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['post_id', 'user_id']);
        });

        Schema::create('social_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('social_comments')->nullOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['post_id', 'deleted_at', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['social_comments', 'social_post_likes', 'social_post_media', 'social_posts', 'social_blocks', 'social_friendships', 'social_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
