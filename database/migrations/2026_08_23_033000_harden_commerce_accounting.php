<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->unsignedInteger('platform_fee_reversed_cents')->default(0)->after('amount_cents');
            $table->unsignedInteger('tenant_net_reversed_cents')->default(0)->after('platform_fee_reversed_cents');
            $table->foreignId('requested_by')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('provider_reference');
            $table->index(['tenant_id', 'status', 'created_at'], 'refunds_tenant_status_created_idx');
        });

        Schema::table('payouts', function (Blueprint $table): void {
            $table->foreignId('requested_by')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->text('status_reason')->nullable()->after('status');
            $table->timestamp('requested_at')->nullable()->after('scheduled_at');
            $table->index(['tenant_id', 'status', 'created_at'], 'payouts_tenant_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table): void {
            $table->dropIndex('payouts_tenant_status_created_idx');
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn(['status_reason', 'requested_at']);
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropIndex('refunds_tenant_status_created_idx');
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn(['platform_fee_reversed_cents', 'tenant_net_reversed_cents', 'processed_at']);
        });
    }
};
