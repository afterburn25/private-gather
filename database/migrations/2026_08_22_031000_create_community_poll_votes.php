<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('option_index');
            $table->timestamps();
            $table->unique(['post_id','user_id']);
            $table->index(['post_id','option_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_poll_votes');
    }
};
