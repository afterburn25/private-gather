<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->after('community_group_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'event_id', 'status']);
        });
        Schema::table('network_community_posts', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->after('network_community_group_id')->constrained()->nullOnDelete();
            $table->index(['event_id', 'status']);
        });

        Schema::create('community_mentions', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 16);
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'source_type', 'source_id', 'mentioned_user_id'], 'community_mentions_source_user_unique');
            $table->index(['mentioned_user_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_mentions');
        Schema::table('network_community_posts', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropIndex(['event_id', 'status']);
            $table->dropColumn('event_id');
        });
        Schema::table('community_posts', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropIndex(['tenant_id', 'event_id', 'status']);
            $table->dropColumn('event_id');
        });
    }
};
