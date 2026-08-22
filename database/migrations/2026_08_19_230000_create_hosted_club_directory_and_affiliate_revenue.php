<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_directory_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_listed')->default(false)->index();
            $table->string('listing_name', 160)->nullable();
            $table->string('club_type', 40)->default('club')->index();
            $table->text('short_description')->nullable();
            $table->string('city', 120)->nullable()->index();
            $table->string('region', 120)->nullable()->index();
            $table->string('country_code', 2)->default('US')->index();
            $table->string('postal_code', 24)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('amenities')->nullable();
            $table->string('contact_url', 500)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('featured_until')->nullable()->index();
            $table->timestamps();
            $table->index(['is_listed', 'country_code', 'region', 'city'], 'club_directory_location_index');
        });

        Schema::create('affiliate_offers', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 180);
            $table->string('advertiser_name', 180);
            $table->string('relationship_type', 30)->default('affiliate');
            $table->string('category', 60)->default('other')->index();
            $table->text('description')->nullable();
            $table->text('affiliate_url');
            $table->string('image_url', 1000)->nullable();
            $table->string('cta_label', 80)->default('Learn More');
            $table->string('status', 30)->default('draft')->index();
            $table->json('placements')->nullable();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('region', 120)->nullable()->index();
            $table->string('city', 120)->nullable()->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('disclosure', 255)->default('Sponsored affiliate offer — Private Gather may earn a commission if you purchase through this link.');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'starts_at', 'ends_at'], 'affiliate_offer_delivery_index');
        });

        Schema::create('affiliate_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('placement', 80)->nullable()->index();
            $table->string('referrer_host', 255)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('clicked_at')->index();
            $table->timestamps();
            $table->index(['affiliate_offer_id', 'clicked_at'], 'affiliate_click_offer_time_index');
        });

        Schema::create('affiliate_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_offer_id')->constrained()->cascadeOnDelete();
            $table->string('external_reference', 180)->nullable();
            $table->unsignedInteger('sale_amount_cents')->default(0);
            $table->unsignedInteger('commission_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 30)->default('reported')->index();
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['affiliate_offer_id', 'status', 'occurred_at'], 'affiliate_conversion_offer_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_conversions');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_offers');
        Schema::dropIfExists('club_directory_profiles');
    }
};
