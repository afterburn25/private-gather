<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_upgrades', function (Blueprint $table): void {
            $table->id();
            $table->uuid('upgrade_id')->unique();
            $table->string('from_version', 40);
            $table->string('to_version', 40);
            $table->string('status', 40)->index();
            $table->string('package_name');
            $table->char('package_sha256', 64);
            $table->text('backup_path')->nullable();
            $table->text('log_path')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('manifest');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_upgrades');
    }
};
