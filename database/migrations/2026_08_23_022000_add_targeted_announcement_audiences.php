<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_announcements', function (Blueprint $table): void {
            $table->foreignId('membership_level_id')->nullable()->after('event_id')->constrained('tenant_membership_levels')->nullOnDelete();
            $table->foreignId('community_group_id')->nullable()->after('membership_level_id')->constrained('community_groups')->nullOnDelete();
            $table->index(['tenant_id', 'audience', 'membership_level_id'], 'tenant_announcements_level_audience_idx');
            $table->index(['tenant_id', 'audience', 'community_group_id'], 'tenant_announcements_group_audience_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_announcements', function (Blueprint $table): void {
            $table->dropForeign(['membership_level_id']);
            $table->dropForeign(['community_group_id']);
            $table->dropIndex('tenant_announcements_level_audience_idx');
            $table->dropIndex('tenant_announcements_group_audience_idx');
            $table->dropColumn(['membership_level_id', 'community_group_id']);
        });
    }
};
