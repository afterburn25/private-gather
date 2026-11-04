<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('audit_logs', function (Blueprint $table) { $table->id(); $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('action')->index(); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable(); $table->json('before')->nullable(); $table->json('after')->nullable(); $table->string('ip_address', 45)->nullable(); $table->text('user_agent')->nullable(); $table->timestamp('created_at')->useCurrent()->index(); $table->index(['subject_type', 'subject_id']); }); }
    public function down(): void { Schema::dropIfExists('audit_logs'); }
};
