<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_membership_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_interval', 24)->default('none')->index();
            $table->unsignedInteger('duration_days')->nullable();
            $table->string('profile_eligibility', 24)->default('any')->index();
            $table->unsignedInteger('guest_limit')->default(0);
            $table->unsignedTinyInteger('event_discount_percent')->default(0);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('benefits')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });

        Schema::create('tenant_membership_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_level_id')->constrained('tenant_membership_levels')->restrictOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('starts_at');
            $table->timestamp('current_period_starts_at');
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->boolean('cancel_at_period_end')->default(false);
            $table->string('source', 32)->default('staff');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->index(['membership_level_id', 'status']);
        });

        $now = now();
        foreach (DB::table('tenants')->select(['id'])->get() as $tenant) {
            if (DB::table('tenant_membership_levels')->where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            DB::table('tenant_membership_levels')->insert([
                'tenant_id' => $tenant->id,
                'name' => 'Standard Member',
                'slug' => 'standard-member',
                'description' => 'Default approved lifestyle club or organization membership.',
                'price_cents' => 0,
                'currency' => 'USD',
                'billing_interval' => 'none',
                'profile_eligibility' => 'any',
                'guest_limit' => 0,
                'event_discount_percent' => 0,
                'requires_approval' => true,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 10,
                'benefits' => json_encode(['Approved member access']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_membership_terms');
        Schema::dropIfExists('tenant_membership_levels');
    }
};
