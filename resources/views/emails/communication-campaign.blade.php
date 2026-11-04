<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $campaignSubject }}</title></head>
<body style="margin:0;background:#111114;color:#f4efe6;font-family:Arial,sans-serif;line-height:1.6">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#111114;padding:24px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#19191e;border:1px solid #303038;border-radius:14px;padding:28px">
<tr><td><div style="font-size:12px;letter-spacing:.16em;color:#d9bc78;font-weight:700">{{ strtoupper($tenantName) }} · {{ $messageType === 'operational' ? 'MEMBER NOTICE' : 'COMMUNITY UPDATE' }}</div></td></tr>
<tr><td><h1 style="font-size:26px;margin:12px 0 18px;color:#fff">{{ $campaignSubject }}</h1></td></tr>
<tr><td style="white-space:pre-line;color:#e2ddd5">{{ $campaignBody }}</td></tr>
@if($messageType === 'marketing' && $unsubscribeUrl)
<tr><td style="padding-top:28px;border-top:1px solid #303038"><p style="font-size:12px;color:#aaa6a0;margin:0">You are receiving this marketing email because this address explicitly opted in for {{ $tenantName }} communications. <a href="{{ $unsubscribeUrl }}" style="color:#d9bc78">Unsubscribe from marketing email</a>.</p></td></tr>
@else
<tr><td style="padding-top:28px;border-top:1px solid #303038"><p style="font-size:12px;color:#aaa6a0;margin:0">This is an operational notice related to your active membership or participation with {{ $tenantName }}.</p></td></tr>
@endif
</table>
</td></tr></table>
</body>
</html>
