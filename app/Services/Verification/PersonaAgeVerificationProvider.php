<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Contracts\AgeVerificationProvider;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PersonaAgeVerificationProvider implements AgeVerificationProvider
{
    public function begin(User $participant, string $opaqueReference, string $returnUrl): array
    {
        $apiKey = trim((string) config('age_verification.persona.api_key'));
        $templateId = trim((string) config('age_verification.persona.inquiry_template_id'));
        if ($apiKey === '' || $templateId === '') {
            throw new RuntimeException('Age verification is not configured. Add the Persona API key and Inquiry Template ID.');
        }

        $base = rtrim((string) config('age_verification.persona.api_base'), '/');
        $headers = ['Persona-Version' => (string) config('age_verification.persona.api_version', '2025-10-27')];
        $create = Http::acceptJson()->asJson()->timeout(20)->retry(2, 250, throw: false)
            ->withToken($apiKey)->withHeaders($headers)->post($base.'/inquiries', [
                'data' => ['attributes' => [
                    'inquiry-template-id' => $templateId,
                    // Private Gather never sends the user's database id to Persona.
                    'reference-id' => $opaqueReference,
                ]],
            ]);

        if (! $create->successful()) {
            throw new RuntimeException('The identity provider could not start verification. Please try again.');
        }

        $inquiryId = (string) $create->json('data.id');
        if (! str_starts_with($inquiryId, 'inq_')) {
            throw new RuntimeException('The identity provider returned an invalid verification session.');
        }

        // Persona requires a session token when loading a pending pre-created inquiry.
        $resume = Http::acceptJson()->asJson()->timeout(20)->retry(2, 250, throw: false)
            ->withToken($apiKey)->withHeaders($headers)->post($base.'/inquiries/'.rawurlencode($inquiryId).'/resume');
        if (! $resume->successful()) {
            throw new RuntimeException('The identity provider could not create a secure verification session. Please try again.');
        }
        $sessionToken = trim((string) $resume->json('meta.session-token'));
        if ($sessionToken === '') {
            throw new RuntimeException('The identity provider returned an invalid session token.');
        }

        $query = [
            'inquiry-id' => $inquiryId,
            'session-token' => $sessionToken,
            'redirect-uri' => $returnUrl,
        ];
        if ($environmentId = trim((string) config('age_verification.persona.environment_id'))) {
            $query['environment-id'] = $environmentId;
        }

        return [
            'provider' => 'persona',
            'reference' => $inquiryId,
            'url' => rtrim((string) config('age_verification.persona.hosted_base'), '?').'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986),
        ];
    }

    public function inquiryStatus(string $providerReference): ?string
    {
        $apiKey = trim((string) config('age_verification.persona.api_key'));
        if ($apiKey === '' || ! str_starts_with($providerReference, 'inq_')) return null;

        $response = Http::acceptJson()->timeout(15)->withToken($apiKey)
            ->withHeaders(['Persona-Version' => (string) config('age_verification.persona.api_version', '2025-10-27')])
            ->get(rtrim((string) config('age_verification.persona.api_base'), '/').'/inquiries/'.rawurlencode($providerReference));
        if (! $response->successful()) return null;

        $status = strtolower(trim((string) $response->json('data.attributes.status')));
        return $status !== '' ? $status : null;
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        $secrets = array_values(array_filter(array_unique([
            trim((string) config('age_verification.persona.webhook_secret')),
            trim((string) config('age_verification.persona.webhook_previous_secret')),
        ])));
        if ($secrets === [] || trim($signatureHeader) === '') return false;

        $tolerance = max(60, (int) config('age_verification.webhook_tolerance_seconds', 300));
        if (! preg_match('/(?:^|[ ,])t=(\d+)/', $signatureHeader, $timestampMatch)) return false;
        $timestamp = (int) $timestampMatch[1];
        if (abs(time() - $timestamp) > $tolerance) return false;

        if (! preg_match_all('/(?:^|[ ,])v1=([0-9a-fA-F]{64})/', $signatureHeader, $signatureMatches)) return false;
        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
            foreach ($signatureMatches[1] as $signature) {
                if (hash_equals($expected, strtolower((string) $signature))) return true;
            }
        }
        return false;
    }
}
