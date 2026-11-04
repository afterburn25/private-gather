<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('guest_policy', 32)->default('allowed')->after('rsvp_mode');
            $table->unsignedTinyInteger('max_party_size')->default(10)->after('guest_policy');
        });

        Schema::create('event_staff_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('capabilities');
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_staff_assignments');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['guest_policy', 'max_party_size']);
        });
    }
};
