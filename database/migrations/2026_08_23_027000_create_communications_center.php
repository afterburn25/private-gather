<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('definition');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('marketing_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('channel', 24)->default('email');
            $table->string('message_type', 24)->default('marketing');
            $table->string('subject', 255)->nullable();
            $table->longText('body');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['tenant_id', 'channel', 'message_type'], 'marketing_templates_tenant_channel_type_idx');
        });

        Schema::table('marketing_contacts', function (Blueprint $table): void {
            $table->timestamp('email_opt_out_at')->nullable()->after('email_opt_in_at')->index();
            $table->timestamp('sms_opt_out_at')->nullable()->after('sms_opt_in_at')->index();
            $table->timestamp('last_contacted_at')->nullable()->after('status');
        });

        Schema::table('marketing_campaigns', function (Blueprint $table): void {
            $table->foreignId('segment_id')->nullable()->after('created_by')->constrained('marketing_segments')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->after('segment_id')->constrained('marketing_templates')->nullOnDelete();
            $table->string('message_type', 24)->default('marketing')->after('channel')->index();
            $table->unsignedInteger('recipient_total')->default(0)->after('stats');
            $table->unsignedInteger('sent_total')->default(0)->after('recipient_total');
            $table->unsignedInteger('failed_total')->default(0)->after('sent_total');
            $table->unsignedInteger('skipped_total')->default(0)->after('failed_total');
            $table->timestamp('processing_started_at')->nullable()->after('scheduled_at');
            $table->index(['tenant_id', 'status', 'scheduled_at'], 'marketing_campaigns_due_idx');
        });

        Schema::create('marketing_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->foreignId('marketing_contact_id')->nullable()->constrained('marketing_contacts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 24)->default('email');
            $table->char('address_hash', 64);
            $table->string('address', 255);
            $table->string('status', 24)->default('queued')->index();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'channel', 'address_hash'], 'marketing_campaign_recipient_unique');
            $table->index(['tenant_id', 'status', 'id'], 'marketing_campaign_recipients_tenant_status_idx');
        });

        Schema::create('marketing_unsubscribe_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_contact_id')->constrained('marketing_contacts')->cascadeOnDelete();
            $table->string('channel', 24)->default('email');
            $table->char('token_digest', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['marketing_contact_id', 'channel', 'used_at'], 'marketing_unsubscribe_contact_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_unsubscribe_tokens');
        Schema::dropIfExists('marketing_campaign_recipients');

        Schema::table('marketing_campaigns', function (Blueprint $table): void {
            $table->dropForeign(['segment_id']);
            $table->dropForeign(['template_id']);
            $table->dropIndex('marketing_campaigns_due_idx');
            $table->dropIndex(['message_type']);
            $table->dropColumn([
                'segment_id', 'template_id', 'message_type', 'recipient_total', 'sent_total',
                'failed_total', 'skipped_total', 'processing_started_at',
            ]);
        });

        Schema::table('marketing_contacts', function (Blueprint $table): void {
            $table->dropIndex(['email_opt_out_at']);
            $table->dropIndex(['sms_opt_out_at']);
            $table->dropColumn(['email_opt_out_at', 'sms_opt_out_at', 'last_contacted_at']);
        });

        Schema::dropIfExists('marketing_templates');
        Schema::dropIfExists('marketing_segments');
    }
};
