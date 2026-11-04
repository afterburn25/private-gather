@extends('layouts.admin')
@section('title','Privacy Requests')
@section('content')
<div class="pg-admin-page-head">
    <div>
        <span class="pg-admin-kicker">Privacy operations</span>
        <h1>Privacy Requests</h1>
        <p>Track member data-export and account-deletion requests through review and fulfillment.</p>
    </div>
</div>

<div class="admin-card">
    <div class="security-note"><strong>Operational tracking:</strong> marking a request completed records that fulfillment was performed. It does not automatically delete an account or generate an export artifact.</div>
    <form method="get" class="form-inline">
        <select name="type">
            <option value="">All request types</option>
            <option value="export" @selected(request('type')==='export')>Data export</option>
            <option value="delete" @selected(request('type')==='delete')>Account deletion</option>
        </select>
        <select name="status">
            <option value="">All statuses</option>
            @foreach(['pending','processing','completed','rejected'] as $status)
                <option value="{{$status}}" @selected(request('status')===$status)>{{ucfirst($status)}}</option>
            @endforeach
        </select>
        <button type="submit">Filter</button>
    </form>
</div>

<div class="pg-admin-list">
@forelse($requests as $privacyRequest)
    <form method="post" action="{{route('admin.privacy-requests.update',$privacyRequest)}}" class="admin-card">
        @csrf @method('patch')
        <div class="row-between">
            <div>
                <strong>#{{$privacyRequest->id}} · {{ucfirst($privacyRequest->type)}} request</strong>
                <small>{{$privacyRequest->user?->display_name ?: $privacyRequest->user?->name}} · {{$privacyRequest->user?->email}}</small>
                <small>Requested {{$privacyRequest->requested_at?->format('M j, Y g:i A') ?: 'unknown'}}@if($privacyRequest->completed_at) · closed {{$privacyRequest->completed_at->format('M j, Y g:i A')}}@endif</small>
                @if($privacyRequest->artifact_path)<small>Artifact recorded: {{basename($privacyRequest->artifact_path)}}</small>@endif
            </div>
            <span class="status-pill">{{str_replace('_',' ',$privacyRequest->status)}}</span>
        </div>
        <div class="inline-controls">
            <select name="status" required>
                @foreach(['pending','processing','completed','rejected'] as $status)
                    <option value="{{$status}}" @selected($privacyRequest->status===$status)>{{ucfirst($status)}}</option>
                @endforeach
            </select>
            <input name="admin_notes" value="{{$privacyRequest->admin_notes}}" maxlength="5000" placeholder="Internal fulfillment notes">
            <button type="submit">Update</button>
        </div>
    </form>
@empty
    <div class="admin-card muted">No privacy requests match this view.</div>
@endforelse
</div>
{{$requests->links()}}
@endsection