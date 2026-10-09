<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\LogFile;
use Opcodes\LogViewer\LogFolder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::define('access-admin', fn (User $user): bool => $user->hasRole('admin'));
        Gate::define('access-petugas', fn (User $user): bool => $user->hasRole('user') && ! $user->hasRole('admin'));
        Gate::define('viewLogViewer', fn (?User $user): bool => $user?->is_active && $user->hasRole('admin'));
        Gate::define('deleteLogFile', fn (?User $user, LogFile $file): bool => false);
        Gate::define('deleteLogFolder', fn (?User $user, LogFolder $folder): bool => false);
    }
}
