@extends('layouts.app')
@section('title','Domains')
@section('content')
@php($isHosted=\App\Support\Edition::isHosted())
<section class="container section">
<div class="eyebrow">WEBSITE</div><h1>Domains</h1>
@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
@if($isHosted&&config('platform.wildcard_enabled'))
<div class="notice"><strong>Hosted subdomains are automatic.</strong> Private Gather uses <code>{{\App\Support\TenantUrl::wildcardPattern()}}</code> → <code>{{\App\Support\TenantUrl::wildcardTarget()}}</code>. Once that wildcard DNS and HTTPS mapping exists at the host/DNS provider, every generated Private Gather subdomain is public immediately.</div>
@elseif(!$isHosted)
<div class="notice"><strong>Self-Hosted domain.</strong> This installation is served by the customer’s own web host. No Private Gather wildcard subdomain is required. SSL/TLS is managed by this hosting account or its DNS/CDN provider.</div>
@endif
<div class="panel">
@foreach($domains as $domain)
@php($siteUrl=\App\Support\TenantUrl::to($domain))
<div class="domain-row"><div><strong>{{$domain->domain}}</strong><small>{{str_replace('_',' ',$domain->type)}} · {{$domain->status}} · DNS {{$domain->dns_status??'pending'}} · SSL {{$domain->ssl_status}}</small>@if($siteUrl)<a class="text-link" target="_blank" rel="noopener" href="{{$siteUrl}}">{{$siteUrl}}</a>@endif @if($domain->status!=='active'&&$domain->verification_token) @php($dns=$service->dnsInstructions($domain))<code>TXT {{$dns['verification_record']}} = {{$dns['verification_value']}}</code><code>CNAME / edge target → {{$dns['cname_target']}}</code>@endif</div><div class="row-actions">@if($siteUrl)<a class="btn" target="_blank" rel="noopener" href="{{$siteUrl}}">Open Website</a>@endif @if($domain->status!=='active'&&$domain->verification_token)<form method="post" action="{{route('tenant.domains.verify',$domain)}}">@csrf<button>Verify DNS</button></form>@endif @if(!$domain->is_primary&&$domain->status==='active')<form method="post" action="{{route('tenant.domains.primary',$domain)}}">@csrf<button>Make primary</button></form>@elseif($domain->is_primary)<span class="pill">Primary</span>@endif</div></div>
@endforeach
</div>
<form method="post" action="{{route('tenant.domains.store')}}" class="panel form-inline">@csrf<label>{{$isHosted?'Connect a domain you own':'Add another domain for this installation'}}<input name="domain" placeholder="exampleclub.com" required></label><button class="button button-primary">Add Domain</button></form>
</section>
@endsection
