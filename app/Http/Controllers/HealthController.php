<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
final class HealthController extends Controller {
    public function __invoke(): JsonResponse {
        $db='ok'; try { DB::select('select 1'); } catch (\Throwable) { $db='error'; }
        return response()->json(['status'=>$db==='ok'?'ok':'degraded','version'=>config('release.version'),'database'=>$db,'time'=>now()->toIso8601String()], $db==='ok'?200:503);
    }
}
