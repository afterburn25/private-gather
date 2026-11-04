<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_community_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('join_policy', 24)->default('open')->index();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('network_community_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('network_community_group_id')->constrained('network_community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 24)->default('member');
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['network_community_group_id', 'user_id'], 'network_group_user_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::create('network_community_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('network_community_group_id')->nullable()->constrained('network_community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('status', 24)->default('active')->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->timestamps();
            $table->index(['network_community_group_id', 'status', 'is_pinned', 'created_at'], 'network_posts_feed_idx');
        });

        Schema::create('network_community_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('network_community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->index(['post_id', 'status', 'created_at']);
        });

        Schema::create('network_community_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('network_community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 24)->default('like');
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
            $table->index(['reaction', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_community_reactions');
        Schema::dropIfExists('network_community_comments');
        Schema::dropIfExists('network_community_posts');
        Schema::dropIfExists('network_community_group_members');
        Schema::dropIfExists('network_community_groups');
    }
};
