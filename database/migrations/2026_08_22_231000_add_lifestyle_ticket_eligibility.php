<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->string('profile_eligibility', 24)->default('any')->after('description');
            $table->boolean('membership_required')->default(false)->after('profile_eligibility');
            $table->boolean('approval_required')->default(false)->after('membership_required');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn([
                'profile_eligibility',
                'membership_required',
                'approval_required',
            ]);
        });
    }
};
