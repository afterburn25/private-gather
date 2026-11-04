<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 24)->default('tenant')->index();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('badge_color', 16)->default('#7d3b69');
            $table->string('text_color', 16)->default('#ffffff');
            $table->string('category', 32)->default('custom')->index();
            $table->string('visibility', 32)->default('members');
            $table->string('issuance_type', 32)->default('manual')->index();
            $table->json('criteria')->nullable();
            $table->unsignedInteger('expires_after_days')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system_reserved')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'scope', 'is_active']);
            $table->index(['tenant_id', 'slug']);
        });

        Schema::create('user_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at', 'expires_at']);
            $table->index(['tenant_id', 'user_id', 'revoked_at']);
            $table->index(['badge_id', 'user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
    }
};
