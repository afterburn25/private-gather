<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table): void {
            $table->timestamp('ssl_expires_at')->nullable()->after('ssl_last_checked_at');
            $table->timestamp('provision_last_attempt_at')->nullable()->after('ssl_expires_at');
            $table->unsignedSmallInteger('provision_attempts')->default(0)->after('provision_last_attempt_at');
            $table->index(['tenant_id', 'ssl_status', 'ssl_expires_at'], 'tenant_domains_tenant_ssl_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table): void {
            $table->dropIndex('tenant_domains_tenant_ssl_expiry_idx');
            $table->dropColumn(['ssl_expires_at', 'provision_last_attempt_at', 'provision_attempts']);
        });
    }
};
