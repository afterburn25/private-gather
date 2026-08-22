<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Contracts\AgeVerificationProvider;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Verification;
use App\Services\Verification\MemberTrust;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

final class AgeVerificationController extends Controller
{
    public function __construct(
        private readonly AgeVerificationProvider $provider,
        private readonly MemberTrust $trust,
    ) {}

    public function index(Request $request): View
    {
        $owner = $request->user();
        $badge = $this->trust->badge($owner);
        $participants = $this->participantCards($owner);

        return view('member.verification', [
            'owner' => $owner,
            'badge' => $badge,
            'participants' => $participants,
            'minimumAge' => max(18, (int) config('age_verification.minimum_age', 21)),
            'configured' => $this->configured(),
        ]);
    }

    public function start(Request $request, string $slot): RedirectResponse
    {
        abort_unless((bool) config('age_verification.enabled', true), 404);
        abort_unless($this->configured(), 503, 'Age verification provider is not configured yet.');

        $owner = $request->user();
        $participant = $this->trust->participantForSlot($owner, $slot);
        $minimumAge = max(18, (int) config('age_verification.minimum_age', 21));
        abort_unless(
            $participant->date_of_birth && $participant->date_of_birth->lte(now()->subYears($minimumAge)),
            422,
            'The account date of birth does not meet the configured verification age.'
        );
        if ($this->trust->individualVerified($participant)) {
            return redirect()->route('verification.index')->with('status', 'This participant is already verified.');
        }

        if ($slot === 'partner' && ! $this->trust->individualVerified($owner)) {
            return redirect()->route('verification.index')->with('status', 'Partner 1 must finish verification before Partner 2 takes a turn.');
        }

        $attempt = Verification::query()->where('user_id', $participant->id)->where('type', 'age_identity')->count() + 1;
        $opaque = 'pgv_'.substr(hash_hmac('sha256', 'age-verification|'.$participant->id, (string) config('app.key')), 0, 48);
        $returnUrl = route('verification.return');

        try {
            $session = $this->provider->begin($participant, $opaque, $returnUrl);
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('verification.index')->with('status', $e->getMessage());
        }

        $referenceHash = hash('sha256', $session['reference']);
        $verification = Verification::query()->create([
            'user_id' => $participant->id,
            'tenant_id' => null,
            'type' => 'age_identity',
            'status' => 'pending',
            'provider' => $session['provider'],
            'provider_reference' => Crypt::encryptString($session['reference']),
            'metadata' => [
                'slot' => $slot,
                'couple_owner_id' => (int) $owner->id,
                'age_threshold' => max(18, (int) config('age_verification.minimum_age', 21)),
                'verification_level' => 'high',
                'method' => 'government_id+selfie_liveness+face_match',
                'attempt' => $attempt,
            ],
        ]);
        if (Schema::hasColumn('verifications', 'provider_reference_hash')) {
            DB::table('verifications')->where('id', $verification->id)->update(['provider_reference_hash' => $referenceHash]);
        }

        $request->session()->put('pg.age_verification.last_reference', $session['reference']);
        Audit::write('age_verification.started', $verification, tenantId: null, request: $request);

        return redirect()->away($session['url']);
    }

    public function returned(Request $request): RedirectResponse
    {
        $sessionReference = (string) $request->session()->pull('pg.age_verification.last_reference', '');
        $queryReference = trim((string) $request->query('inquiry-id', ''));
        $reference = str_starts_with($queryReference, 'inq_') ? $queryReference : $sessionReference;
        if ($reference !== '') {
            $status = $this->provider->inquiryStatus($reference);
            if ($status) {
                $this->applyProviderStatus($reference, $status, 'return-sync');
            }
        }

        $badge = $this->trust->badge($request->user()->fresh());
        $message = $badge['verified']
            ? 'Verification complete. Your verified member badge is active.'
            : ($badge['key'] === 'partial'
                ? 'Partner 1 is verified. Hand the device to Partner 2 and complete the second verification.'
                : 'Verification result is being finalized. You can refresh this page or retry if the provider requests it.');

        return redirect()->route('verification.index')->with('status', $message);
    }

    public function webhook(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('Persona-Signature', '');
        if (! $this->provider->verifyWebhookSignature($raw, $signature)) {
            return response()->json(['received' => false], 401);
        }

        $event = json_decode($raw, true);
        if (! is_array($event)) {
            return response()->json(['received' => false], 400);
        }

        $eventId = (string) data_get($event, 'data.id', '');
        $eventType = (string) data_get($event, 'data.attributes.name', '');
        $reference = (string) data_get($event, 'data.attributes.payload.data.id', '');
        if ($eventId === '' || $eventType === '' || ! str_starts_with($reference, 'inq_')) {
            return response()->json(['received' => true]);
        }

        if (! Schema::hasTable('verification_webhook_events')) {
            return response()->json(['received' => false], 503);
        }

        DB::transaction(function () use ($eventId, $eventType, $reference, $raw): void {
            $inserted = DB::table('verification_webhook_events')->insertOrIgnore([
                'provider' => 'persona',
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payload_hash' => hash('sha256', $raw),
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted === 0) {
                return;
            }

            $status = match ($eventType) {
                'inquiry.approved' => 'approved',
                'inquiry.declined' => 'declined',
                'inquiry.failed' => 'failed',
                'inquiry.expired' => 'expired',
                'inquiry.marked-for-review' => 'review',
                default => null,
            };
            if ($status !== null) {
                $this->applyProviderStatus($reference, $status, $eventId);
            }

            DB::table('verification_webhook_events')->where('provider', 'persona')->where('event_id', $eventId)
                ->update(['processed_at' => now(), 'updated_at' => now()]);
        }, 3);

        return response()->json(['received' => true]);
    }

    public function admin(Request $request): View
    {
        $rows = Verification::query()->where('type', 'age_identity')->latest('id')->paginate(50);
        $rows->setCollection($rows->getCollection()->map(function (Verification $row): Verification {
            if ($row->user_id) {
                $row->setRelation('user', User::query()->with('profile')->find($row->user_id));
            }
            return $row;
        }));

        return view('admin.age-verification', [
            'rows' => $rows,
            'minimumAge' => max(18, (int) config('age_verification.minimum_age', 21)),
        ]);
    }

    private function participantCards(User $owner): array
    {
        $cards = [];
        $participants = $this->trust->participants($owner)->values();
        foreach ($participants as $index => $participant) {
            $latest = Verification::query()->where('user_id', $participant->id)->where('type', 'age_identity')->latest('id')->first();
            $cards[] = [
                'slot' => $index === 0 ? 'primary' : 'partner',
                'label' => $index === 0 ? 'Partner 1' : 'Partner 2',
                'username' => $participant->username ?: $participant->display_name ?: $participant->name,
                'verified' => $this->trust->individualVerified($participant),
                'status' => $latest?->status ?? 'unverified',
                'verified_at' => $latest?->verified_at,
                'expires_at' => $latest?->expires_at,
            ];
        }

        if ($owner->profile?->profile_type === 'couple' && count($cards) === 1) {
            $cards[] = [
                'slot' => 'partner',
                'label' => 'Partner 2',
                'username' => 'Partner account not linked',
                'verified' => false,
                'status' => 'link_required',
                'verified_at' => null,
                'expires_at' => null,
            ];
        }
        return $cards;
    }

    private function configured(): bool
    {
        return trim((string) config('age_verification.persona.api_key')) !== ''
            && trim((string) config('age_verification.persona.inquiry_template_id')) !== ''
            && trim((string) config('age_verification.persona.webhook_secret')) !== '';
    }

    private function applyProviderStatus(string $reference, string $providerStatus, string $source): void
    {
        if (! Schema::hasColumn('verifications', 'provider_reference_hash')) {
            return;
        }
        $verification = Verification::query()->where('provider', 'persona')
            ->where('provider_reference_hash', hash('sha256', $reference))->latest('id')->first();
        if (! $verification || ! $verification->user_id) {
            return;
        }

        $status = match (strtolower($providerStatus)) {
            'approved' => 'approved',
            'declined' => 'declined',
            'failed' => 'failed',
            'expired' => 'expired',
            'needs_review', 'review' => 'review',
            default => null,
        };
        if ($status === null) {
            return;
        }

        if ($verification->status === 'approved' && $status !== 'approved') {
            return;
        }

        $metadata = (array) ($verification->metadata ?? []);
        $metadata['last_decision_source'] = $source;
        $metadata['last_provider_status'] = $providerStatus;
        $updates = ['status' => $status, 'metadata' => $metadata];
        if ($status === 'approved') {
            $updates['verified_at'] = $verification->verified_at ?: now();
            $updates['expires_at'] = now()->addMonths(max(1, (int) config('age_verification.valid_months', 12)));
        }
        $verification->forceFill($updates)->save();
        Audit::write('age_verification.'.$status, $verification);

        if ($user = User::query()->find($verification->user_id)) {
            $this->trust->refreshIndividualSummary($user);
        }
    }
}
