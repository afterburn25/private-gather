<?php
namespace App\Support;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
final class Audit {
    public static function write(string $action, ?Model $subject=null, ?array $before=null, ?array $after=null, ?int $tenantId=null, ?Request $request=null): void {
        try { AuditLog::query()->create([
            'tenant_id'=>$tenantId,'user_id'=>auth()->id(),'action'=>$action,
            'subject_type'=>$subject ? $subject::class : null,'subject_id'=>$subject?->getKey(),
            'before'=>$before,'after'=>$after,'ip_address'=>$request?->ip() ?? request()?->ip(),
            'user_agent'=>mb_substr((string)($request?->userAgent() ?? request()?->userAgent()),0,1000),'created_at'=>now(),
        ]);} catch (\Throwable) {}
    }
}
