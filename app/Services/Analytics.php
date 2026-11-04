<?php
namespace App\Services;
use App\Models\AnalyticsEvent;use Illuminate\Http\Request;
final class Analytics{
 public function record(string $name,Request $r,array $properties=[],?int $tenantId=null,?int $eventId=null):void{
  try{AnalyticsEvent::create(['tenant_id'=>$tenantId,'event_id'=>$eventId,'user_id'=>$r->user()?->id,'event_name'=>$name,'session_key'=>substr(hash('sha256',$r->session()->getId()),0,80),'path'=>substr($r->path(),0,500),'referrer'=>substr((string)$r->headers->get('referer'),0,1000),'properties'=>$properties,'occurred_at'=>now()]);}catch(\Throwable){}
 }
}