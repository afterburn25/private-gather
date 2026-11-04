<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_groups', function (Blueprint $table): void {
            $table->json('branding')->nullable()->after('chapter_lead_user_id');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('community_group_id')->nullable()->after('tenant_id')->constrained('community_groups')->nullOnDelete();
            $table->index(['tenant_id', 'community_group_id', 'status', 'starts_at'], 'events_tenant_chapter_status_starts_idx');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign(['community_group_id']);
            $table->dropIndex('events_tenant_chapter_status_starts_idx');
            $table->dropColumn('community_group_id');
        });
        Schema::table('community_groups', function (Blueprint $table): void {
            $table->dropColumn('branding');
        });
    }
};
