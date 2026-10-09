<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ! $request->user()->is_active) {
            $impersonation = app(ImpersonateManager::class);

            if ($impersonation->isImpersonating() && $impersonation->leave()) {
                $request->session()->regenerate();

                return redirect()->route('admin.users.index')
                    ->with('status', 'Akun petugas dinonaktifkan. Anda kembali sebagai admin.');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akun Anda dinonaktifkan.'], 401);
            }

            return redirect()->route('login')->with('status', 'Akun Anda dinonaktifkan. Hubungi admin.');
        }

        return $next($request);
    }
}
