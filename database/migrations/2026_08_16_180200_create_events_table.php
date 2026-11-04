<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('visibility')->default('public')->index();
            $table->string('rsvp_mode')->default('instant')->index();
            $table->string('status')->default('draft')->index();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone')->default('America/Chicago');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('region')->nullable()->index();
            $table->string('public_location_label')->nullable();
            $table->text('exact_address')->nullable();
            $table->string('exact_address_visibility')->default('approved_attendees');
            $table->string('cover_image_path')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'starts_at']);
        });

        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('guest_count')->default(1);
            $table->json('answers')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('event_rsvps'); Schema::dropIfExists('events'); }
};
