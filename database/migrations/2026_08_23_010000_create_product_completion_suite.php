<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_progress', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('member_step')->default(1); $t->timestamp('member_completed_at')->nullable();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete(); $t->unsignedTinyInteger('tenant_step')->default(1); $t->timestamp('tenant_completed_at')->nullable();
            $t->json('dismissed_prompts')->nullable(); $t->timestamps();
        });
        Schema::create('favorites', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('target_type', 24); $t->unsignedBigInteger('target_id'); $t->timestamps();
            $t->unique(['user_id','target_type','target_id']); $t->index(['target_type','target_id']);
        });
        Schema::create('saved_searches', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('name',120); $t->string('search_type',24)->default('events'); $t->json('filters'); $t->boolean('alert_enabled')->default(false); $t->timestamp('last_notified_at')->nullable(); $t->timestamps();
        });
        Schema::create('user_connections', function (Blueprint $t): void {
            $t->id(); $t->foreignId('requester_id')->constrained('users')->cascadeOnDelete(); $t->foreignId('addressee_id')->constrained('users')->cascadeOnDelete(); $t->string('status',24)->default('pending'); $t->timestamp('accepted_at')->nullable(); $t->timestamps();
            $t->unique(['requester_id','addressee_id']); $t->index(['addressee_id','status']);
        });
        Schema::create('user_follows', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('target_type',24); $t->unsignedBigInteger('target_id'); $t->timestamps();
            $t->unique(['user_id','target_type','target_id']); $t->index(['target_type','target_id']);
        });
        Schema::create('user_blocks', function (Blueprint $t): void {
            $t->id(); $t->foreignId('blocker_id')->constrained('users')->cascadeOnDelete(); $t->foreignId('blocked_id')->constrained('users')->cascadeOnDelete(); $t->timestamps(); $t->unique(['blocker_id','blocked_id']);
        });
        Schema::create('user_privacy_settings', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('profile_visibility',24)->default('members'); $t->string('messages_from',24)->default('connections'); $t->string('location_visibility',24)->default('city');
            $t->string('memberships_visibility',24)->default('private'); $t->string('attendance_visibility',24)->default('private'); $t->string('online_visibility',24)->default('connections');
            $t->boolean('read_receipts')->default(true); $t->boolean('profile_view_receipts')->default(false); $t->timestamps();
        });
        Schema::create('reviews', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('rating'); $t->text('body')->nullable(); $t->string('status',24)->default('published'); $t->boolean('verified_attendee')->default(false);
            $t->text('tenant_response')->nullable(); $t->timestamp('tenant_responded_at')->nullable(); $t->timestamps(); $t->index(['tenant_id','status']); $t->index(['event_id','status']);
        });
        Schema::create('tenant_member_tags', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->string('name',80); $t->string('color',16)->default('#d9bc78'); $t->timestamps(); $t->unique(['tenant_id','name']);
        });
        Schema::create('tenant_member_tag_assignments', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('tag_id')->constrained('tenant_member_tags')->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->timestamps(); $t->unique(['tag_id','user_id']); $t->index(['tenant_id','user_id']);
        });
        Schema::create('tenant_member_notes', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete(); $t->text('body'); $t->boolean('is_private')->default(true); $t->timestamps(); $t->index(['tenant_id','user_id']);
        });
        Schema::create('marketing_contacts', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->string('name')->nullable(); $t->string('email')->nullable(); $t->string('phone',40)->nullable();
            $t->timestamp('email_opt_in_at')->nullable(); $t->timestamp('sms_opt_in_at')->nullable(); $t->string('source',48)->default('member'); $t->string('status',24)->default('active'); $t->timestamps(); $t->index(['tenant_id','status']);
        });
        Schema::create('marketing_campaigns', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->string('name',160); $t->string('channel',24)->default('email'); $t->string('status',24)->default('draft'); $t->json('audience')->nullable(); $t->string('subject')->nullable(); $t->longText('body')->nullable(); $t->timestamp('scheduled_at')->nullable(); $t->timestamp('sent_at')->nullable(); $t->json('stats')->nullable(); $t->timestamps();
        });
        Schema::create('referral_codes', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); $t->string('code',64)->unique(); $t->string('type',24)->default('referral'); $t->unsignedSmallInteger('commission_bps')->default(0); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('referral_attributions', function (Blueprint $t): void {
            $t->id(); $t->foreignId('referral_code_id')->constrained('referral_codes')->cascadeOnDelete(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('event_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); $t->string('session_key',100)->nullable(); $t->timestamp('attributed_at'); $t->timestamps();
        });
        Schema::create('merchant_accounts', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete(); $t->string('provider',60)->default('manual'); $t->string('provider_account_ref')->nullable(); $t->string('status',24)->default('not_connected'); $t->json('capabilities')->nullable(); $t->timestamp('onboarded_at')->nullable(); $t->timestamps();
        });
        Schema::create('marketplace_ledger_entries', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete(); $t->string('type',32); $t->unsignedInteger('gross_cents')->default(0); $t->unsignedInteger('platform_fee_cents')->default(0); $t->integer('tenant_net_cents')->default(0); $t->string('currency',3)->default('USD'); $t->string('status',24)->default('posted'); $t->string('provider_reference')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->index(['tenant_id','status']); $t->unique(['order_id','type']);
        });
        Schema::create('payouts', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->unsignedInteger('amount_cents'); $t->string('currency',3)->default('USD'); $t->string('status',24)->default('requested'); $t->string('provider_reference')->nullable(); $t->timestamp('scheduled_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->index(['tenant_id','status']);
        });
        Schema::create('refunds', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('order_id')->constrained()->cascadeOnDelete(); $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete(); $t->unsignedInteger('amount_cents'); $t->string('status',24)->default('requested'); $t->string('provider_reference')->nullable(); $t->text('reason')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('membership_subscriptions', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('membership_level_id')->constrained('tenant_membership_levels')->cascadeOnDelete(); $t->foreignId('membership_term_id')->nullable()->constrained('tenant_membership_terms')->nullOnDelete();
            $t->string('provider',60)->default('manual'); $t->string('provider_reference')->nullable(); $t->string('status',24)->default('active'); $t->string('billing_interval',24)->default('month'); $t->unsignedInteger('amount_cents')->default(0); $t->string('currency',3)->default('USD'); $t->timestamp('current_period_starts_at')->nullable(); $t->timestamp('current_period_ends_at')->nullable(); $t->boolean('cancel_at_period_end')->default(false); $t->timestamps(); $t->index(['tenant_id','status']);
        });
        Schema::create('domain_orders', function (Blueprint $t): void {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->string('domain',253); $t->string('provider',60)->default('manual'); $t->string('status',32)->default('requested'); $t->unsignedInteger('price_cents')->nullable(); $t->string('currency',3)->default('USD'); $t->string('provider_reference')->nullable(); $t->timestamp('requested_at')->nullable(); $t->timestamp('registered_at')->nullable(); $t->timestamp('renews_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->unique(['tenant_id','domain']);
        });
        Schema::create('push_subscriptions', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->text('endpoint'); $t->string('endpoint_hash',64)->unique(); $t->text('p256dh')->nullable(); $t->text('auth_token')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamps();
        });
        Schema::create('trust_cases', function (Blueprint $t): void {
            $t->id(); $t->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('report_id')->nullable()->constrained()->nullOnDelete(); $t->string('type',40); $t->string('status',24)->default('open'); $t->string('priority',24)->default('normal'); $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); $t->text('notes')->nullable(); $t->text('resolution')->nullable(); $t->timestamp('resolved_at')->nullable(); $t->timestamps(); $t->index(['status','priority']);
        });
        Schema::create('cms_section_templates', function (Blueprint $t): void {
            $t->id(); $t->string('scope',24)->default('tenant'); $t->string('template_key',80)->unique(); $t->string('label',120); $t->string('section_type',60); $t->json('content'); $t->boolean('active')->default(true); $t->timestamps();
        });

        Schema::table('notification_preferences', function (Blueprint $t): void {
            $t->boolean('email_membership')->default(true); $t->boolean('email_tickets')->default(true); $t->boolean('push_messages')->default(true); $t->boolean('push_events')->default(true); $t->boolean('push_membership')->default(true); $t->boolean('push_tickets')->default(true); $t->string('digest_frequency',24)->default('instant');
        });
        Schema::table('events', function (Blueprint $t): void {
            $t->decimal('latitude',10,7)->nullable(); $t->decimal('longitude',10,7)->nullable(); $t->timestamp('featured_at')->nullable(); $t->json('gallery')->nullable(); $t->json('faq')->nullable(); $t->json('hosts')->nullable(); $t->json('updates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $t): void { $t->dropColumn(['latitude','longitude','featured_at','gallery','faq','hosts','updates']); });
        Schema::table('notification_preferences', function (Blueprint $t): void { $t->dropColumn(['email_membership','email_tickets','push_messages','push_events','push_membership','push_tickets','digest_frequency']); });
        foreach (['cms_section_templates','trust_cases','push_subscriptions','domain_orders','membership_subscriptions','refunds','payouts','marketplace_ledger_entries','merchant_accounts','referral_attributions','referral_codes','marketing_campaigns','marketing_contacts','tenant_member_notes','tenant_member_tag_assignments','tenant_member_tags','reviews','user_privacy_settings','user_blocks','user_follows','user_connections','saved_searches','favorites','onboarding_progress'] as $table) Schema::dropIfExists($table);
    }
};
