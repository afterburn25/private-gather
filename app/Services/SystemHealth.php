<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Storage;use Throwable;
final class SystemHealth{
 public function report():array{
  $checks=[];
  $checks['php']=['ok'=>version_compare(PHP_VERSION,'8.3.0','>='),'value'=>PHP_VERSION,'required'=>'>= 8.3'];
  foreach(['pdo','openssl','mbstring','fileinfo','zip'] as $ext)$checks['ext_'.$ext]=['ok'=>extension_loaded($ext),'value'=>extension_loaded($ext)?'loaded':'missing'];
  try{DB::select('SELECT 1');$checks['database']=['ok'=>true,'value'=>DB::connection()->getDatabaseName()];}catch(Throwable $e){$checks['database']=['ok'=>false,'value'=>$e->getMessage()];}
  try{$probe='.health/'.bin2hex(random_bytes(6));$ok=Storage::disk('local')->put($probe,'ok');if($ok)Storage::disk('local')->delete($probe);$checks['private_storage']=['ok'=>(bool)$ok,'value'=>$ok?'writable':'write failed'];}catch(Throwable $e){$checks['private_storage']=['ok'=>false,'value'=>$e->getMessage()];}
  $checks['app_key']=['ok'=>strlen((string)config('app.key'))>20,'value'=>strlen((string)config('app.key'))>20?'configured':'missing/weak'];
  $checks['production_debug']=['ok'=>!app()->environment('production')||!config('app.debug'),'value'=>config('app.debug')?'debug on':'debug off'];
  return ['version'=>trim((string)@file_get_contents(base_path('VERSION'))),'generated_at'=>now()->toIso8601String(),'healthy'=>collect($checks)->every(fn($c)=>$c['ok']),'checks'=>$checks];
 }
}