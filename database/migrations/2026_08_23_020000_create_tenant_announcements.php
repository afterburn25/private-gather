<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->longText('body');
            $table->string('audience', 32)->default('all_members')->index();
            $table->string('priority', 24)->default('normal')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'published_at']);
        });

        Schema::create('tenant_announcement_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('tenant_announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('notification_id')->nullable()->unique();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_announcement_deliveries');
        Schema::dropIfExists('tenant_announcements');
    }
};
