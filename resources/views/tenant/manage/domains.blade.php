@extends('layouts.app')

@section('title', 'Domains')

@section('content')
@php
    $isHosted = \App\Support\Edition::isHosted();
@endphp

<section class="container section">
    <div class="eyebrow">WEBSITE</div>

    <div class="section-head">
        <div>
            <h1>Domains & HTTPS</h1>
            <p class="muted">Connect custom domains, verify ownership, confirm routing and inspect the public TLS certificate without treating one check as proof of another.</p>
        </div>
    </div>

    @if(session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if($isHosted && config('platform.wildcard_enabled'))
        <div class="notice">
            <strong>Hosted subdomains are automatic.</strong>
            Private Gather uses <code>{{ \App\Support\TenantUrl::wildcardPattern() }}</code> → <code>{{ \App\Support\TenantUrl::wildcardTarget() }}</code>.
            Custom domains below are verified and diagnosed independently.
        </div>
    @elseif(! $isHosted)
        <div class="notice">
            <strong>Self-Hosted configuration is not part of the current Hosted release work.</strong>
            Existing Self-Hosted behavior is left intact.
        </div>
    @endif

    <div class="panel">
        <h2>Connected domains</h2>

        @if($domains->isEmpty())
            <div class="empty-state">No domains are connected yet.</div>
        @else
            @foreach($domains as $domain)
                @php
                    $siteUrl = \App\Support\TenantUrl::to($domain);
                    $dns = $service->dnsInstructions($domain);
                    $health = is_array($domain->health) ? $domain->health : [];
                    $dnsHealth = data_get($health, 'dns', []);
                    $tlsHealth = data_get($health, 'tls', []);
                    $provisionHealth = data_get($health, 'provisioning', []);
                    $ownershipVerified = (bool) $domain->verified_at;
                    $routingReady = in_array((string) $domain->dns_status, ['ready', 'active'], true)
                        || (bool) data_get($dnsHealth, 'routing_ready', false);
                @endphp

                <article class="panel" style="margin:18px 0">
                    <div class="section-head">
                        <div>
                            <div class="row-actions" style="justify-content:flex-start">
                                <h3 style="margin:0">{{ $domain->domain }}</h3>
                                @if($domain->is_primary)
                                    <span class="pill">Primary</span>
                                @endif
                                <span class="pill">{{ str_replace('_', ' ', $domain->type) }}</span>
                            </div>

                            @if($siteUrl)
                                <a class="text-link" target="_blank" rel="noopener" href="{{ $siteUrl }}">{{ $siteUrl }}</a>
                            @endif
                        </div>

                        <div class="row-actions">
                            @if($siteUrl)
                                <a class="btn" target="_blank" rel="noopener" href="{{ $siteUrl }}">Open Website</a>
                            @endif

                            <form method="post" action="{{ route('tenant.domains.diagnose', $domain) }}">
                                @csrf
                                <button class="btn">Refresh DNS</button>
                            </form>

                            @if($ownershipVerified && $routingReady)
                                <form method="post" action="{{ route('tenant.domains.provision', $domain) }}">
                                    @csrf
                                    <button class="btn">Retry HTTPS provisioning</button>
                                </form>

                                <form method="post" action="{{ route('tenant.domains.ssl-check', $domain) }}">
                                    @csrf
                                    <button class="btn">Check live TLS</button>
                                </form>
                            @elseif($ownershipVerified)
                                <span class="pill">Routing required before HTTPS</span>
                            @else
                                <form method="post" action="{{ route('tenant.domains.verify', $domain) }}">
                                    @csrf
                                    <button class="button button-primary">Verify ownership</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-4" style="margin-top:18px">
                        <div class="metric">
                            <small>Ownership</small>
                            <strong>{{ $ownershipVerified ? 'Verified' : 'Waiting' }}</strong>
                            <span class="muted">{{ $domain->verified_at?->format('M j, Y g:i A') ?: 'TXT proof not confirmed' }}</span>
                        </div>
                        <div class="metric">
                            <small>Routing</small>
                            <strong>{{ $routingReady ? 'Observed' : str_replace('_', ' ', $domain->dns_status ?? 'pending') }}</strong>
                            <span class="muted">{{ $domain->dns_last_checked_at?->format('M j, Y g:i A') ?: 'Not checked yet' }}</span>
                        </div>
                        <div class="metric">
                            <small>HTTPS</small>
                            <strong>{{ str_replace('_', ' ', $domain->ssl_status ?: 'pending') }}</strong>
                            <span class="muted">{{ $domain->ssl_last_checked_at?->format('M j, Y g:i A') ?: 'Live certificate not checked' }}</span>
                        </div>
                        <div class="metric">
                            <small>TLS expiry</small>
                            <strong>{{ $domain->ssl_expires_at?->format('M j, Y') ?: 'Unknown' }}</strong>
                            <span class="muted">Provision attempts: {{ (int) $domain->provision_attempts }}</span>
                        </div>
                    </div>

                    @if($domain->type !== \App\Models\TenantDomain::TYPE_PLATFORM_SUBDOMAIN)
                        <div class="grid grid-2" style="margin-top:18px">
                            <div>
                                <h4>1. Ownership proof</h4>
                                <p class="muted">Add this TXT record at your DNS provider. Ownership verification does not by itself prove that website traffic reaches Private Gather.</p>
                                <code>TXT {{ $dns['verification_record'] }} = {{ $dns['verification_value'] }}</code>

                                @if(data_get($dnsHealth, 'txt_values'))
                                    <p class="muted">Observed TXT values: {{ implode(', ', (array) data_get($dnsHealth, 'txt_values')) }}</p>
                                @endif
                            </div>

                            <div>
                                <h4>2. Website routing</h4>
                                <p class="muted">Route the hostname to the Hosted edge. Apex domains may use ALIAS/ANAME/CNAME flattening when the DNS provider supports it.</p>
                                <code>CNAME / edge target → {{ $dns['cname_target'] }}</code>

                                @if(data_get($dnsHealth, 'cname_targets'))
                                    <p class="muted">Observed CNAME: {{ implode(', ', (array) data_get($dnsHealth, 'cname_targets')) }}</p>
                                @endif

                                @if(data_get($dnsHealth, 'a_records'))
                                    <p class="muted">Observed A: {{ implode(', ', (array) data_get($dnsHealth, 'a_records')) }}</p>
                                @endif

                                @if(data_get($dnsHealth, 'aaaa_records'))
                                    <p class="muted">Observed AAAA: {{ implode(', ', (array) data_get($dnsHealth, 'aaaa_records')) }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-2" style="margin-top:18px">
                        <div>
                            <h4>HTTPS provisioning</h4>
                            <p class="muted">
                                Last attempt: {{ $domain->provision_last_attempt_at?->format('M j, Y g:i A') ?: 'Never' }}.
                                An accepted request means only that the configured edge/provider accepted the request; it is not certificate proof.
                            </p>

                            @if(data_get($provisionHealth, 'message'))
                                <p>{{ data_get($provisionHealth, 'message') }}</p>
                            @endif
                        </div>

                        <div>
                            <h4>Live certificate</h4>

                            @if(data_get($tlsHealth, 'issuer'))
                                <p class="muted">Issuer: {{ data_get($tlsHealth, 'issuer') }}</p>
                            @endif

                            @if(data_get($tlsHealth, 'subject'))
                                <p class="muted">Subject: {{ data_get($tlsHealth, 'subject') }}</p>
                            @endif

                            @if(data_get($tlsHealth, 'days_remaining') !== null)
                                <p class="muted">Days remaining: {{ data_get($tlsHealth, 'days_remaining') }}</p>
                            @endif
                        </div>
                    </div>

                    @if($domain->last_error)
                        <div class="notice" style="margin-top:16px">
                            <strong>Needs attention:</strong> {{ $domain->last_error }}
                        </div>
                    @endif

                    <div class="row-actions" style="margin-top:18px;justify-content:flex-start">
                        @if(! $domain->is_primary && $domain->status === 'active' && $ownershipVerified)
                            <form method="post" action="{{ route('tenant.domains.primary', $domain) }}">
                                @csrf
                                <button class="btn">Make primary</button>
                            </form>
                        @endif

                        @if(! $domain->is_primary && $domain->status === 'active')
                            <form method="post" action="{{ route('tenant.domains.redirect', $domain) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="redirect_to_primary" value="{{ $domain->redirect_to_primary ? '0' : '1' }}">
                                <button class="btn">{{ $domain->redirect_to_primary ? 'Disable primary redirect' : 'Redirect to primary' }}</button>
                            </form>
                        @endif

                        @if(! $domain->is_primary && $domain->type !== \App\Models\TenantDomain::TYPE_PLATFORM_SUBDOMAIN)
                            <form method="post" action="{{ route('tenant.domains.destroy', $domain) }}" onsubmit="return confirm('Disconnect this custom domain from Private Gather? DNS and registrar records will not be deleted.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn">Disconnect</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        @endif
    </div>

    <form method="post" action="{{ route('tenant.domains.store') }}" class="panel form-inline">
        @csrf
        <label>
            {{ $isHosted ? 'Connect a domain you own' : 'Add another domain' }}
            <input name="domain" placeholder="exampleclub.com" required>
        </label>
        <button class="button button-primary">Add Domain</button>
    </form>
</section>
@endsection
