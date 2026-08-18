<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('event_invitations')
            ->select(['id', 'token'])
            ->orderBy('id')
            ->chunkById(250, function ($rows): void {
                foreach ($rows as $row) {
                    $token = (string) $row->token;
                    if (str_starts_with($token, 'sha256:')) {
                        continue;
                    }

                    DB::table('event_invitations')
                        ->where('id', $row->id)
                        ->update([
                            'token' => 'sha256:'.hash('sha256', $token),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // One-way hardening is intentionally irreversible: recovering bearer
        // secrets from their SHA-256 digests would defeat the security goal.
    }
};
