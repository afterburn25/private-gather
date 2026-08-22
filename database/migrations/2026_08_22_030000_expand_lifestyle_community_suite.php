<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('lifestyle_identity', 40)->nullable()->after('profile_type')->index();
            $table->string('relationship_status', 40)->nullable()->after('lifestyle_identity')->index();
            $table->string('experience_level', 40)->nullable()->after('relationship_status');
            $table->string('pronouns', 80)->nullable()->after('experience_level');
            $table->json('looking_for')->nullable()->after('interests');
            $table->json('lifestyle_interests')->nullable()->after('looking_for');
            $table->text('boundaries')->nullable()->after('lifestyle_interests');
            $table->string('message_permissions', 30)->default('members')->after('discoverable');
            $table->boolean('show_age')->default(false)->after('message_permissions');
            $table->boolean('show_last_active')->default(true)->after('show_age');
        });

        Schema::table('community_posts', function (Blueprint $table): void {
            $table->foreignId('wall_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->after('wall_user_id')->constrained('community_groups')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->after('group_id')->constrained('events')->nullOnDelete();
            $table->string('post_type', 30)->default('status')->after('event_id')->index();
            $table->string('visibility', 30)->default('members')->after('post_type')->index();
            $table->boolean('share_to_club_wall')->default(true)->after('visibility')->index();
            $table->string('media_path')->nullable()->after('body');
            $table->string('media_type', 20)->nullable()->after('media_path');
            $table->string('media_mime', 100)->nullable()->after('media_type');
            $table->unsignedBigInteger('media_size')->nullable()->after('media_mime');
            $table->json('poll_options')->nullable()->after('media_size');
            $table->timestamp('expires_at')->nullable()->after('edited_at')->index();
            $table->index(['tenant_id', 'wall_user_id', 'status', 'created_at'], 'community_posts_wall_idx');
            $table->index(['tenant_id', 'group_id', 'status', 'created_at'], 'community_posts_group_idx');
        });

        Schema::table('community_comments', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('post_id')->constrained('community_comments')->cascadeOnDelete();
        });

        Schema::table('private_album_photos', function (Blueprint $table): void {
            $table->string('media_type', 20)->default('image')->after('path')->index();
            $table->string('mime_type', 100)->nullable()->after('media_type');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->unsignedInteger('duration_seconds')->nullable()->after('size_bytes');
            $table->string('thumbnail_path')->nullable()->after('duration_seconds');
        });

        Schema::table('community_groups', function (Blueprint $table): void {
            $table->string('cover_path')->nullable()->after('description');
            $table->string('group_type', 40)->default('interest')->after('cover_path')->index();
            $table->boolean('allow_member_posts')->default(true)->after('visibility');
            $table->boolean('is_featured')->default(false)->after('allow_member_posts')->index();
        });

        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->timestamp('typing_at')->nullable()->after('last_read_at')->index();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->string('attachment_path')->nullable()->after('body');
            $table->string('attachment_type', 20)->nullable()->after('attachment_path');
            $table->string('attachment_mime', 100)->nullable()->after('attachment_type');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            $table->timestamp('edited_at')->nullable()->after('status');
            $table->timestamp('deleted_at')->nullable()->after('edited_at')->index();
        });

        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->boolean('email_reactions')->default(false)->after('email_messages');
            $table->boolean('email_connections')->default(true)->after('email_reactions');
            $table->boolean('email_group_activity')->default(false)->after('email_connections');
            $table->boolean('in_app_messages')->default(true)->after('browser_notifications');
            $table->boolean('in_app_reactions')->default(true)->after('in_app_messages');
            $table->boolean('in_app_connections')->default(true)->after('in_app_reactions');
            $table->boolean('in_app_events')->default(true)->after('in_app_connections');
        });

        Schema::table('platform_notifications', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'user_id', 'read_at'], 'platform_notifications_tenant_user_idx');
        });

        Schema::create('message_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('community_group_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('membership_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->json('answers')->nullable();
            $table->text('member_note')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('member_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('label', 120);
            $table->string('icon', 40)->nullable();
            $table->string('visibility', 24)->default('members');
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'code']);
        });

        Schema::create('club_news_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 190);
            $table->string('slug', 200);
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('cover_path')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('visibility', 24)->default('public')->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('community_post_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_views');
        Schema::dropIfExists('club_news_posts');
        Schema::dropIfExists('member_badges');
        Schema::dropIfExists('membership_applications');
        Schema::dropIfExists('community_group_invites');
        Schema::dropIfExists('message_reads');

        Schema::table('platform_notifications', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn(['email_reactions', 'email_connections', 'email_group_activity', 'in_app_messages', 'in_app_reactions', 'in_app_connections', 'in_app_events']);
        });
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn(['attachment_path', 'attachment_type', 'attachment_mime', 'attachment_size', 'edited_at', 'deleted_at']);
        });
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropColumn('typing_at');
        });
        Schema::table('community_groups', function (Blueprint $table): void {
            $table->dropColumn(['cover_path', 'group_type', 'allow_member_posts', 'is_featured']);
        });
        Schema::table('private_album_photos', function (Blueprint $table): void {
            $table->dropColumn(['media_type', 'mime_type', 'size_bytes', 'duration_seconds', 'thumbnail_path']);
        });
        Schema::table('community_comments', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
        Schema::table('community_posts', function (Blueprint $table): void {
            $table->dropForeign(['wall_user_id']);
            $table->dropForeign(['group_id']);
            $table->dropForeign(['event_id']);
            $table->dropColumn(['wall_user_id', 'group_id', 'event_id', 'post_type', 'visibility', 'share_to_club_wall', 'media_path', 'media_type', 'media_mime', 'media_size', 'poll_options', 'expires_at']);
        });
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn(['lifestyle_identity', 'relationship_status', 'experience_level', 'pronouns', 'looking_for', 'lifestyle_interests', 'boundaries', 'message_permissions', 'show_age', 'show_last_active']);
        });
    }
};
