<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DomainProvisioner;
use App\Models\TenantDomain;
use Carbon\CarbonImmutable;

final class DomainHealthService
{
    public function __construct(private DomainProvisioner $provisioner) {}

    public function verify(TenantDomain $domain): array
    {
        $result = $this->diagnoseDns($domain);
        if (! $result['ownership_verified']) {
            return ['verified' => false] + $result;
        }

        $domain->forceFill([
            'verified_at' => $domain->verified_at ?? now(),
            'status' => TenantDomain::STATUS_ACTIVE,
            'last_error' => $result['routing_ready'] ? null : 'Domain ownership is verified, but the website routing record is not visible yet.',
        ])->save();

        if (! $result['routing_ready']) {
            return ['verified' => true, 'provisioner' => null] + $result;
        }

        $edge = $this->retryProvision($domain->fresh());

        return ['verified' => true, 'provisioner' => $edge] + $result;
    }

    public function diagnoseDns(TenantDomain $domain): array
    {
        if ($domain->type === TenantDomain::TYPE_PLATFORM_SUBDOMAIN) {
            $result = [
                'ownership_verified' => true,
                'routing_ready' => true,
                'dns_status' => 'active',
                'verification_record' => null,
                'txt_values' => [],
                'cname_targets' => [],
                'a_records' => [],
                'aaaa_records' => [],
                'expected_target' => (string) config('platform.domain_target'),
                'expected_target_a_records' => [],
                'expected_target_aaaa_records' => [],
                'cname_matches_expected_target' => true,
                'flattened_matches_expected_target' => true,
            ];
        } else {
            $verificationRecord = (string) config('platform.domain_verification.txt_prefix').'.'.$domain->domain;
            $txtValues = $this->txtValues($verificationRecord);
            $cnameTargets = $this->cnameTargets($domain->domain);
            $aRecords = $this->recordValues($domain->domain, DNS_A, 'ip');
            $aaaaRecords = $this->recordValues($domain->domain, DNS_AAAA, 'ipv6');
            $expectedTarget = rtrim(strtolower((string) config('platform.domain_target')), '.');
            $expectedTargetA = $expectedTarget !== '' ? $this->recordValues($expectedTarget, DNS_A, 'ip') : [];
            $expectedTargetAaaa = $expectedTarget !== '' ? $this->recordValues($expectedTarget, DNS_AAAA, 'ipv6') : [];
            $cnameMatches = $expectedTarget !== '' && collect($cnameTargets)->contains(fn ($target) => rtrim(strtolower((string) $target), '.') === $expectedTarget);
            $flattenedMatches = array_intersect($aRecords, $expectedTargetA) !== [] || array_intersect($aaaaRecords, $expectedTargetAaaa) !== [];
            $routingReady = $cnameMatches || $flattenedMatches;
            $ownershipVerified = in_array((string) $domain->verification_token, $txtValues, true);
            $dnsStatus = ! $ownershipVerified ? 'waiting_verification' : ($routingReady ? 'ready' : 'routing_missing');
            $result = [
                'ownership_verified' => $ownershipVerified,
                'routing_ready' => $routingReady,
                'dns_status' => $dnsStatus,
                'verification_record' => $verificationRecord,
                'txt_values' => $txtValues,
                'cname_targets' => $cnameTargets,
                'a_records' => $aRecords,
                'aaaa_records' => $aaaaRecords,
                'expected_target' => $expectedTarget,
                'expected_target_a_records' => $expectedTargetA,
                'expected_target_aaaa_records' => $expectedTargetAaaa,
                'cname_matches_expected_target' => $cnameMatches,
                'flattened_matches_expected_target' => $flattenedMatches,
            ];
        }

        $health = is_array($domain->health) ? $domain->health : [];
        $health['dns'] = $result;
        $health['dns']['checked_at'] = now()->toIso8601String();
        $domain->forceFill([
            'dns_status' => $result['dns_status'],
            'dns_last_checked_at' => now(),
            'health' => $health,
            'last_error' => $result['ownership_verified']
                ? ($result['routing_ready'] ? null : 'Ownership verified; website routing does not match the configured Hosted edge yet.')
                : 'Verification TXT record not detected yet.',
        ])->save();

        return $result;
    }

    public function retryProvision(TenantDomain $domain): array
    {
        if (! $domain->verified_at || $domain->status !== TenantDomain::STATUS_ACTIVE) {
            return ['accepted' => false, 'ssl_status' => $domain->ssl_status, 'message' => 'Verify domain ownership before requesting HTTPS provisioning.'];
        }
        if (! in_array((string) $domain->dns_status, ['ready', 'active'], true)) {
            return ['accepted' => false, 'ssl_status' => $domain->ssl_status, 'message' => 'Confirm routing to the Hosted edge before requesting HTTPS provisioning.'];
        }

        $domain->forceFill([
            'provision_last_attempt_at' => now(),
            'provision_attempts' => min(65535, ((int) $domain->provision_attempts) + 1),
        ])->save();

        try {
            $edge = $this->provisioner->provision($domain);
        } catch (\Throwable $exception) {
            $edge = ['accepted' => false, 'ssl_status' => $domain->ssl_status, 'message' => $exception->getMessage()];
        }

        $accepted = (bool) ($edge['accepted'] ?? false);
        $sslStatus = trim((string) ($edge['ssl_status'] ?? '')) ?: (string) $domain->ssl_status;
        $sslExpiresAt = null;
        if (! empty($edge['ssl_expires_at'])) {
            try { $sslExpiresAt = CarbonImmutable::parse((string) $edge['ssl_expires_at']); } catch (\Throwable) { $sslExpiresAt = null; }
        }
        $health = is_array($domain->health) ? $domain->health : [];
        $health['provisioning'] = [
            'accepted' => $accepted,
            'ssl_status' => $sslStatus,
            'message' => (string) ($edge['message'] ?? ''),
            'attempted_at' => now()->toIso8601String(),
        ];
        $domain->forceFill([
            'ssl_status' => $sslStatus,
            'ssl_expires_at' => $sslExpiresAt ?: $domain->ssl_expires_at,
            'health' => $health,
            'last_error' => $accepted ? null : ((string) ($edge['message'] ?? 'HTTPS provisioning request was not accepted.')),
        ])->save();

        return $edge;
    }

    public function inspectSsl(TenantDomain $domain): array
    {
        if (! $domain->verified_at || $domain->status !== TenantDomain::STATUS_ACTIVE) {
            return ['reachable' => false, 'status' => 'unverified', 'message' => 'Verify the domain before checking its public TLS certificate.'];
        }

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $domain->domain,
            'SNI_enabled' => true,
        ]]);
        $errno = 0;
        $error = '';
        $socket = @stream_socket_client('ssl://'.$domain->domain.':443', $errno, $error, 5, STREAM_CLIENT_CONNECT, $context);
        $domain->ssl_last_checked_at = now();

        if (! is_resource($socket)) {
            $result = ['reachable' => false, 'status' => 'unreachable', 'message' => trim($error) ?: 'TLS connection could not be established.'];
            $health = is_array($domain->health) ? $domain->health : [];
            $health['tls'] = $result + ['checked_at' => now()->toIso8601String()];
            $domain->forceFill(['ssl_status' => 'unreachable', 'health' => $health, 'last_error' => $result['message']])->save();
            return $result;
        }

        $params = stream_context_get_params($socket);
        fclose($socket);
        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
        $parsed = $certificate ? @openssl_x509_parse($certificate) : false;
        if (! is_array($parsed) || empty($parsed['validTo_time_t'])) {
            $result = ['reachable' => true, 'status' => 'certificate_unreadable', 'message' => 'TLS connected, but certificate expiry could not be read.'];
            $health = is_array($domain->health) ? $domain->health : [];
            $health['tls'] = $result + ['checked_at' => now()->toIso8601String()];
            $domain->forceFill(['ssl_status' => 'certificate_unreadable', 'health' => $health, 'last_error' => $result['message']])->save();
            return $result;
        }

        $expiresAt = CarbonImmutable::createFromTimestamp((int) $parsed['validTo_time_t']);
        $status = $expiresAt->lte(now()) ? 'expired' : ($expiresAt->lte(now()->addDays(30)) ? 'expiring' : 'active');
        $result = [
            'reachable' => true,
            'status' => $status,
            'expires_at' => $expiresAt->toIso8601String(),
            'days_remaining' => now()->diffInDays($expiresAt, false),
            'subject' => $parsed['subject']['CN'] ?? null,
            'issuer' => $parsed['issuer']['CN'] ?? null,
        ];
        $health = is_array($domain->health) ? $domain->health : [];
        $health['tls'] = $result + ['checked_at' => now()->toIso8601String()];
        $domain->forceFill([
            'ssl_status' => $status,
            'ssl_expires_at' => $expiresAt,
            'health' => $health,
            'last_error' => $status === 'active' ? null : ($status === 'expiring' ? 'TLS certificate expires within 30 days.' : 'TLS certificate is expired.'),
        ])->save();

        return $result;
    }

    private function txtValues(string $record): array
    {
        $records = function_exists('dns_get_record') ? (dns_get_record($record, DNS_TXT) ?: []) : [];
        $values = [];
        foreach ($records as $entry) {
            $parts = $entry['entries'] ?? [($entry['txt'] ?? '')];
            foreach ($parts as $value) if ((string) $value !== '') $values[] = (string) $value;
        }
        return array_values(array_unique($values));
    }

    private function cnameTargets(string $record): array
    {
        return $this->recordValues($record, DNS_CNAME, 'target');
    }

    private function recordValues(string $record, int $type, string $key): array
    {
        $records = function_exists('dns_get_record') ? (dns_get_record($record, $type) ?: []) : [];
        $values = [];
        foreach ($records as $entry) if (! empty($entry[$key])) $values[] = (string) $entry[$key];
        return array_values(array_unique($values));
    }
}
