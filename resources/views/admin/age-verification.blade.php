@extends('layouts.admin')
@section('title','Age & Identity Verification')
@section('content')
<section class="pg-admin-page">
    <div class="pg-admin-page-head"><div><span class="eyebrow">MEMBER TRUST</span><h1>Age & Identity Verification</h1><p>Operational status only. Raw identity documents and selfie biometrics are not stored in Private Gather.</p></div></div>
    <div class="panel"><div class="row-between"><div><strong>Configured minimum age</strong><small>{{$minimumAge}}+</small></div><div><strong>Records</strong><small>{{number_format($rows->total())}}</small></div></div></div>
    <div class="panel" style="margin-top:18px;overflow:auto"><table class="pg-admin-table"><thead><tr><th>Member</th><th>Status</th><th>Provider</th><th>Verified</th><th>Expires</th><th>Updated</th></tr></thead><tbody>@forelse($rows as $row)<tr><td><strong>{{$row->user?->username ?: $row->user?->display_name ?: 'Member #'.$row->user_id}}</strong><small>#{{$row->user_id}}</small></td><td><span class="status-pill {{$row->status==='approved'?'status-ok':'status-pending'}}">{{ucwords(str_replace('_',' ',$row->status))}}</span></td><td>{{ucfirst($row->provider ?: 'manual')}}</td><td>{{$row->verified_at?->format('M j, Y') ?: '—'}}</td><td>{{$row->expires_at?->format('M j, Y') ?: '—'}}</td><td>{{$row->updated_at?->diffForHumans()}}</td></tr>@empty<tr><td colspan="6">No age/identity verification attempts yet.</td></tr>@endforelse</tbody></table></div>
    {{$rows->links()}}
</section>
@endsection
