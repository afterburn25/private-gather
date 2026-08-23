<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->string('visibility', 24)->default('tenant');
            $table->string('join_policy', 24)->default('approval');
            $table->string('status', 24)->default('active');
            $table->boolean('allow_member_posts')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('community_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_group_id')->constrained('community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 24)->default('member');
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['community_group_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('community_posts', function (Blueprint $table): void {
            $table->foreignId('community_group_id')->nullable()->after('tenant_id')->constrained('community_groups')->nullOnDelete();
            $table->index(['tenant_id', 'community_group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            $table->dropForeign(['community_group_id']);
            $table->dropIndex(['tenant_id', 'community_group_id', 'status']);
            $table->dropColumn('community_group_id');
        });

        Schema::dropIfExists('community_group_members');
        Schema::dropIfExists('community_groups');
    }
};
