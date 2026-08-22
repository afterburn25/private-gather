<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('liker_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('liked_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->string('source', 30)->default('network');
            $table->timestamp('matched_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'liker_user_id', 'liked_user_id']);
            $table->index(['tenant_id', 'liker_user_id', 'status']);
            $table->index(['tenant_id', 'liked_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_likes');
    }
};
