<?php
namespace App\Services;
use Illuminate\Support\Arr;
final class PlaceholderRenderer{
 private const MAX_PASSES=2;
 public function render(?string $text,array $context):string{
  if($text===null||$text==='')return (string)$text;
  $flat=$this->flatten($context);
  $out=$text;
  for($pass=0;$pass<self::MAX_PASSES;$pass++){
   $next=preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',function(array $m)use($flat){
    $key=$m[1]; if(!array_key_exists($key,$flat))return $m[0];
    $v=$flat[$key]; return is_scalar($v)||$v===null?(string)$v:$m[0];
   },$out);
   if($next===null||$next===$out)break;$out=$next;
  }
  return $out;
 }
 public function available(array $context):array{return array_keys($this->flatten($context));}
 public function eventContext(object $event,?object $tenant=null,?object $member=null):array{
  return ['site'=>['name'=>config('app.name'),'url'=>config('app.url')],
   'event'=>['name'=>$event->title??'','title'=>$event->title??'','date'=>isset($event->starts_at)?$event->starts_at?->format('F j, Y'):'','time'=>isset($event->starts_at)?$event->starts_at?->format('g:i A'):'','city'=>$event->public_city??'','price'=>method_exists($event,'ticketTypes')?number_format(((int)($event->ticketTypes->min('price_cents')??0))/100,2):'','capacity'=>$event->capacity??'','spots_remaining'=>method_exists($event,'remainingCapacity')?$event->remainingCapacity():''],
   'club'=>['name'=>$tenant->name??'','city'=>data_get($tenant?->settings??[],'city','')],
   'organizer'=>['name'=>$tenant->name??''],
   'member'=>['display_name'=>$member->display_name??$member->name??'']];
 }
 private function flatten(array $input,string $prefix=''):array{
  $out=[];foreach($input as $key=>$value){$name=$prefix===''?(string)$key:$prefix.'.'.$key;if(is_array($value))$out+=$this->flatten($value,$name);elseif(is_scalar($value)||$value===null)$out[$name]=$value;}return $out;
 }
}