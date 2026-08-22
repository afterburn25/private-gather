<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 80);
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_interval', 20)->default('annual');
            $table->boolean('application_required')->default(true);
            $table->unsignedTinyInteger('ticket_discount_percent')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(10);
            $table->json('benefits')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'active', 'sort_order']);
        });

        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->foreignId('membership_level_id')->nullable()->constrained('membership_levels')->nullOnDelete();
            $table->timestamp('membership_started_at')->nullable();
            $table->timestamp('membership_expires_at')->nullable();
            $table->string('membership_billing_status', 30)->nullable();
        });

        Schema::create('event_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('type', 30)->default('other');
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('max_per_order')->default(10);
            $table->boolean('member_only')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(10);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'active', 'sort_order']);
        });

        Schema::create('order_addon_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_addon_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('total_cents');
            $table->timestamps();
            $table->unique(['order_id', 'event_addon_id']);
        });

        Schema::create('promoters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->string('code', 80);
            $table->string('status', 30)->default('active')->index();
            $table->string('commission_type', 20)->default('percent');
            $table->unsignedInteger('commission_value')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('promoter_attributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promoter_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('commission_cents')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique('order_id');
            $table->index(['tenant_id', 'promoter_id', 'status']);
        });

        Schema::create('marketing_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('source', 40)->default('manual');
            $table->string('status', 30)->default('active')->index();
            $table->boolean('email_opt_in')->default(false);
            $table->boolean('sms_opt_in')->default(false);
            $table->timestamp('email_consented_at')->nullable();
            $table->timestamp('sms_consented_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone']);
        });

        Schema::create('marketing_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('marketing_list_contacts', function (Blueprint $table): void {
            $table->foreignId('marketing_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['marketing_list_id', 'marketing_contact_id']);
        });

        Schema::create('marketing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('channel', 20)->default('email');
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_list_contacts');
        Schema::dropIfExists('marketing_lists');
        Schema::dropIfExists('marketing_contacts');
        Schema::dropIfExists('promoter_attributions');
        Schema::dropIfExists('promoters');
        Schema::dropIfExists('order_addon_items');
        Schema::dropIfExists('event_addons');

        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('membership_level_id');
            $table->dropColumn(['membership_started_at', 'membership_expires_at', 'membership_billing_status']);
        });

        Schema::dropIfExists('membership_levels');
    }
};
