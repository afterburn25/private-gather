@extends('layouts.app')
@section('title','Domains')
@section('content')
<section class="container section">
<div class="eyebrow">WEBSITE</div><h1>Domains</h1>
@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
@if(config('platform.wildcard_enabled'))
<div class="notice"><strong>Hosted subdomains are automatic.</strong> Private Gather uses <code>{{\App\Support\TenantUrl::wildcardPattern()}}</code> → <code>{{\App\Support\TenantUrl::wildcardTarget()}}</code>. Once that wildcard DNS and HTTPS mapping exists at the host/DNS provider, every generated Private Gather subdomain is public immediately.</div>
@endif
<div class="panel">
@foreach($domains as $domain)
@php($publicUrl=\App\Support\TenantUrl::to($domain))
<div class="domain-row"><div><strong>{{$domain->domain}}</strong><small>{{str_replace('_',' ',$domain->type)}} · {{$domain->status}} · DNS {{$domain->dns_status??'pending'}} · SSL {{$domain->ssl_status}}</small>@if($domain->type===\App\Models\TenantDomain::TYPE_PLATFORM_SUBDOMAIN)<a class="text-link" target="_blank" rel="noopener" href="{{$publicUrl}}">{{$publicUrl}}</a>@endif @if($domain->status!=='active'&&$domain->verification_token) @php($dns=$service->dnsInstructions($domain))<code>TXT {{$dns['verification_record']}} = {{$dns['verification_value']}}</code><code>CNAME / edge target → {{$dns['cname_target']}}</code>@endif</div><div class="row-actions">@if($domain->type===\App\Models\TenantDomain::TYPE_PLATFORM_SUBDOMAIN)<a class="btn" target="_blank" rel="noopener" href="{{$publicUrl}}">Open Website</a>@endif @if($domain->status!=='active'&&$domain->verification_token)<form method="post" action="{{route('tenant.domains.verify',$domain)}}">@csrf<button>Verify DNS</button></form>@endif @if(!$domain->is_primary&&$domain->status==='active')<form method="post" action="{{route('tenant.domains.primary',$domain)}}">@csrf<button>Make primary</button></form>@elseif($domain->is_primary)<span class="pill">Primary</span>@endif</div></div>
@endforeach
</div>
<form method="post" action="{{route('tenant.domains.store')}}" class="panel form-inline">@csrf<label>Connect a domain you own<input name="domain" placeholder="exampleclub.com" required></label><button class="button button-primary">Add Domain</button></form>
</section>
@endsection
