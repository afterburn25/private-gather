<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_rewards', function(Blueprint $t):void{
            $t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->string('name',160);$t->text('description')->nullable();$t->unsignedInteger('points_cost');$t->unsignedInteger('inventory')->nullable();$t->boolean('active')->default(true)->index();$t->timestamps();$t->index(['tenant_id','active','points_cost']);
        });
        Schema::create('member_point_ledger', function(Blueprint $t):void{
            $t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->integer('points');$t->string('reason',190);$t->string('source_type',60)->nullable();$t->unsignedBigInteger('source_id')->nullable();$t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();$t->index(['tenant_id','user_id','created_at']);
        });
        Schema::create('reward_redemptions', function(Blueprint $t):void{
            $t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->foreignId('reward_id')->constrained('club_rewards')->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->unsignedInteger('points_spent');$t->string('status',24)->default('pending')->index();$t->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('fulfilled_at')->nullable();$t->timestamps();$t->index(['tenant_id','status','created_at']);
        });
    }
    public function down():void{Schema::dropIfExists('reward_redemptions');Schema::dropIfExists('member_point_ledger');Schema::dropIfExists('club_rewards');}
};
