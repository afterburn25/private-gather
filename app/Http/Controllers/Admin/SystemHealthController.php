<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealth;
use Illuminate\Http\Request;

class SystemHealthController extends Controller
{
    public function index(SystemHealth $health)
    {
        return view('admin.system-health', ['report' => $health->report()]);
    }

    public function json(Request $request, SystemHealth $health)
    {
        $report = $health->report();
        return response()->json($report, $report['healthy'] ? 200 : 503);
    }
}
