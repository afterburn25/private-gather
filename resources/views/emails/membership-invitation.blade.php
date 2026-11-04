<!doctype html>
<html lang="en"><body style="font-family:Arial,sans-serif;background:#0b0b0f;color:#f2eee7;padding:28px">
<div style="max-width:620px;margin:auto;background:#15151c;border:1px solid #2b2930;border-radius:16px;padding:28px">
<p style="letter-spacing:.14em;text-transform:uppercase;color:#d9bc78;font-size:12px">Private Gather membership invitation</p>
<h1 style="font-size:26px;margin:0 0 14px">You're invited to join {{ $tenant->name }}</h1>
<p>{{ $tenant->name }} has invited your existing Private Gather account to join as <strong>{{ $level->name }}</strong>.</p>
@if($invitationMessage)<p style="padding:14px 16px;border-left:3px solid #d9bc78;background:#0f0f14">{{ $invitationMessage }}</p>@endif
<p><a href="{{ $acceptUrl }}" style="display:inline-block;background:#d9bc78;color:#17130c;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:700">Review invitation</a></p>
<p style="font-size:13px;color:#b8b4ad">For privacy, sign in to the Private Gather account that uses the invited email address. The link cannot be accepted by another account.</p>
</div>
</body></html>
