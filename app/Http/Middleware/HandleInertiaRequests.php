<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Lab404\Impersonate\Services\ImpersonateManager;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                // Only what the pages read: keeps every page payload small and internal columns (e.g. google_sub) private.
                'user' => $user?->only(['id', 'name', 'email', 'email_verified_at']),
                'is_admin' => $request->user()?->hasRole('admin') ?? false,
                'is_impersonating' => $request->user() !== null && app(ImpersonateManager::class)->isImpersonating(),
            ],
        ];
    }
}
