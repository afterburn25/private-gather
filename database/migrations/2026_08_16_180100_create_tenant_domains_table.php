<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('type')->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->string('status')->default('pending')->index();
            $table->string('verification_token', 80)->nullable()->unique();
            $table->timestamp('verified_at')->nullable();
            $table->string('ssl_status')->default('pending')->index();
            $table->timestamp('ssl_last_checked_at')->nullable();
            $table->boolean('redirect_to_primary')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('tenant_domains'); }
};
