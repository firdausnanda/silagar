<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($data['search'] ?? '');

        $users = User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'admin'))
            ->with('roles')
            ->withCount('sensusRecords')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'sensus_count' => $user->sensus_records_count,
                'can_impersonate' => $user->canBeImpersonated(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create($data);
        $user->assignRole('user');

        return redirect()->route('admin.users.index')->with('status', 'Akun petugas dibuat.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->hasRole('admin'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'Akun petugas diperbarui.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        abort_if($user->hasRole('admin'), 403);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $user->update(['is_active' => $data['is_active']]);

        return redirect()->route('admin.users.index')
            ->with('status', $user->is_active ? 'Akun petugas diaktifkan.' : 'Akun petugas dinonaktifkan.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->hasRole('admin'), 403);

        if ($user->is_active) {
            return back()->withErrors(['delete' => 'Nonaktifkan akun petugas sebelum menghapusnya.']);
        }

        if ($user->sensusRecords()->exists()) {
            return back()->withErrors(['delete' => 'Akun dengan data sensus tidak dapat dihapus.']);
        }

        DB::transaction(function () use ($user): void {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->delete();
        });

        return redirect()->route('admin.users.index')->with('status', 'Akun petugas dihapus.');
    }
}
