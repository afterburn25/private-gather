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
            $table->string('group_type', 24)->default('community')->after('description')->index();
            $table->string('city', 120)->nullable()->after('group_type')->index();
            $table->string('region', 120)->nullable()->after('city')->index();
            $table->boolean('operator_managed')->default(false)->after('region')->index();
            $table->foreignId('chapter_lead_user_id')->nullable()->after('operator_managed')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'group_type', 'status'], 'community_groups_tenant_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('community_groups', function (Blueprint $table): void {
            $table->dropForeign(['chapter_lead_user_id']);
            $table->dropIndex('community_groups_tenant_type_status_idx');
            $table->dropIndex(['group_type']);
            $table->dropIndex(['city']);
            $table->dropIndex(['region']);
            $table->dropIndex(['operator_managed']);
            $table->dropColumn(['group_type', 'city', 'region', 'operator_managed', 'chapter_lead_user_id']);
        });
    }
};
