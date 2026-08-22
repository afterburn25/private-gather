<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_albums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('visibility', 30)->default('granted');
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->index(['tenant_id', 'owner_user_id', 'status']);
        });

        Schema::create('private_album_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('album_id')->constrained('private_albums')->cascadeOnDelete();
            $table->string('path');
            $table->string('caption', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['album_id', 'sort_order']);
        });

        Schema::create('private_album_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('album_id')->constrained('private_albums')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['album_id', 'user_id']);
            $table->index(['user_id', 'revoked_at', 'expires_at']);
        });

        Schema::create('member_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_one_id', 'user_two_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('community_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->string('visibility', 30)->default('members');
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'visibility', 'status']);
        });

        Schema::create('community_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('member');
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('travel_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('city', 120);
            $table->string('region', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('visibility', 30)->default('connections');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
            $table->index(['tenant_id', 'starts_on', 'ends_on', 'status']);
            $table->index(['tenant_id', 'city', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_plans');
        Schema::dropIfExists('community_group_members');
        Schema::dropIfExists('community_groups');
        Schema::dropIfExists('member_connections');
        Schema::dropIfExists('private_album_access_grants');
        Schema::dropIfExists('private_album_photos');
        Schema::dropIfExists('private_albums');
    }
};
