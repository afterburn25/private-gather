<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('email', 'like', '%'.$search.'%')
                ->orWhere('display_name', 'like', '%'.$search.'%')
                ->orWhere('name', 'like', '%'.$search.'%'));
        }

        return view('admin.users.index', ['users' => $query->latest()->paginate(100)->withQueryString()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,active,suspended,banned',
            'is_platform_admin' => 'nullable|boolean',
        ]);
        $platformAdmin = $request->boolean('is_platform_admin');

        if ($user->id === $request->user()->id) {
            abort_if($data['status'] !== 'active', 422, 'You cannot disable your own current administrator account.');
            abort_unless($platformAdmin, 422, 'You cannot remove your own current administrator access.');
        }

        $user->update([
            'status' => $data['status'],
            'is_platform_admin' => $platformAdmin,
        ]);

        return back()->with('status', 'User updated.');
    }
}
