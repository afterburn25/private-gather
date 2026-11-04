<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->string('social_title', 190)->nullable()->after('seo_description');
            $table->text('social_description')->nullable()->after('social_title');
            $table->string('social_image_url', 1000)->nullable()->after('social_description');
        });

        Schema::create('cms_reusable_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('type', 48)->index();
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'name'], 'cms_reusable_blocks_tenant_status_name_idx');
        });

        Schema::create('cms_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cms_page_id')->nullable()->constrained('cms_pages')->nullOnDelete();
            $table->foreignId('cms_section_id')->nullable()->constrained('cms_sections')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 190);
            $table->string('email', 255);
            $table->text('message');
            $table->boolean('privacy_ack')->default(false);
            $table->string('status', 24)->default('new')->index();
            $table->timestamp('submitted_at')->useCurrent()->index();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'submitted_at'], 'cms_form_submissions_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_form_submissions');
        Schema::dropIfExists('cms_reusable_blocks');
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropColumn(['social_title', 'social_description', 'social_image_url']);
        });
    }
};
