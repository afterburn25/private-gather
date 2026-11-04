<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_membership_levels', function (Blueprint $table): void {
            $table->unsignedSmallInteger('grace_days')->default(0)->after('duration_days');
        });

        Schema::table('tenant_membership_terms', function (Blueprint $table): void {
            $table->timestamp('renewal_reminder_sent_at')->nullable()->after('renews_at');
            $table->index(['status', 'current_period_ends_at'], 'membership_terms_lifecycle_idx');
        });

        Schema::create('tenant_membership_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_level_id')->nullable()->constrained('tenant_membership_levels')->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->char('token_digest', 64)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->text('message')->nullable();
            $table->timestamp('expires_at')->index();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'email', 'status'], 'membership_invite_tenant_email_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_membership_invitations');
        Schema::table('tenant_membership_terms', function (Blueprint $table): void {
            $table->dropIndex('membership_terms_lifecycle_idx');
            $table->dropColumn('renewal_reminder_sent_at');
        });
        Schema::table('tenant_membership_levels', function (Blueprint $table): void {
            $table->dropColumn('grace_days');
        });
    }
};
