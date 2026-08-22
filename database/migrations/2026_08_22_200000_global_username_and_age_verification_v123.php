<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('username', 32)->nullable()->after('name'));
        }
        if (! Schema::hasColumn('users', 'verification_status')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('verification_status', 32)->default('unverified')->index());
        }
        if (! Schema::hasColumn('users', 'verification_level')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('verification_level', 32)->nullable());
        }
        if (! Schema::hasColumn('users', 'identity_verified_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('identity_verified_at')->nullable());
        }
        if (! Schema::hasColumn('users', 'identity_verification_expires_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('identity_verification_expires_at')->nullable());
        }

        // Reconcile a clean 1.2.0 DB and any partially-applied quarantined 1.2.2 DB
        // deterministically. Every username is normalized and made globally unique.
        $used = [];
        DB::table('users')->orderBy('id')->select(['id','display_name','name','email','username'])->chunkById(200, function ($users) use (&$used): void {
            foreach ($users as $user) {
                $seed = trim((string) ($user->username ?: $user->display_name ?: $user->name ?: Str::before((string) $user->email, '@')));
                $base = Str::lower(Str::ascii($seed));
                $base = preg_replace('/[^a-z0-9._-]+/', '-', $base) ?: '';
                $base = trim($base, '.-_');
                if (strlen($base) < 3) $base = 'member-'.$user->id;
                $base = substr($base, 0, 32);
                $candidate = $base;
                $counter = 0;
                while (isset($used[$candidate])) {
                    ++$counter;
                    $suffix = '-'.$user->id.($counter > 1 ? '-'.$counter : '');
                    $candidate = substr($base, 0, max(3, 32 - strlen($suffix))).$suffix;
                }
                $used[$candidate] = true;
                DB::table('users')->where('id', $user->id)->update(['username'=>$candidate,'display_name'=>$candidate]);
            }
        }, 'id');

        $indexes = collect(Schema::getIndexes('users'));
        $hasUsernameUnique = $indexes->contains(function (array $index): bool {
            $columns = array_map('strtolower', $index['columns'] ?? []);
            return ($index['unique'] ?? false) && $columns === ['username'];
        });
        if (! $hasUsernameUnique) {
            Schema::table('users', fn (Blueprint $table) => $table->unique('username', 'users_username_unique'));
        }

        if (Schema::hasTable('verifications') && ! Schema::hasColumn('verifications', 'provider_reference_hash')) {
            Schema::table('verifications', fn (Blueprint $table) => $table->char('provider_reference_hash', 64)->nullable()->index()->after('provider_reference'));
        }

        if (! Schema::hasTable('verification_webhook_events')) {
            Schema::create('verification_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 32);
                $table->string('event_id', 120);
                $table->string('event_type', 120);
                $table->char('payload_hash', 64);
                $table->timestamp('received_at');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->unique(['provider','event_id'], 'verification_webhook_provider_event_unique');
                $table->index(['event_type','received_at']);
            });
        }

        if (Schema::hasTable('verifications')) {
            DB::table('verifications')->whereIn('type', ['age_identity','identity','age'])
                ->whereIn('status', ['approved','verified','passed'])->orderBy('id')->get()
                ->each(function ($verification): void {
                    if (! $verification->user_id) return;
                    DB::table('users')->where('id', $verification->user_id)->update([
                        'verification_status'=>'verified',
                        'verification_level'=>'high',
                        'identity_verified_at'=>$verification->verified_at ?? now(),
                        'identity_verification_expires_at'=>$verification->expires_at,
                    ]);
                });
        }
    }

    public function down(): void
    {
        // Intentionally conservative: verification history is security/audit data.
        // Rollbacks should use the transactional upgrade snapshot instead of deleting it.
    }
};
