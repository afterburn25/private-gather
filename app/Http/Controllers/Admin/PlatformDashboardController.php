<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\Tenant;use App\Models\User;use App\Models\Event;use App\Models\Order;use App\Models\Report;
class PlatformDashboardController extends Controller{
 public function __invoke(){return view('admin.dashboard',['stats'=>['users'=>User::count(),'tenants'=>Tenant::count(),'events'=>Event::count(),'orders'=>Order::count(),'open_reports'=>Report::where('status','open')->count()],'recentTenants'=>Tenant::latest()->limit(8)->get()]);}
}