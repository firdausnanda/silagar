<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lab404\Impersonate\Services\ImpersonateManager;

class ImpersonationController extends Controller
{
    public function store(Request $request, User $user, ImpersonateManager $impersonation): RedirectResponse
    {
        abort_unless(
            $request->user()->canImpersonate()
            && $user->canBeImpersonated()
            && ! $impersonation->isImpersonating(),
            403,
        );

        abort_unless($impersonation->take($request->user(), $user), 403);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request, ImpersonateManager $impersonation): RedirectResponse
    {
        abort_unless($impersonation->isImpersonating(), 403);
        abort_unless($impersonation->leave(), 403);
        $request->session()->regenerate();

        return redirect()->route('admin.users.index');
    }
}
