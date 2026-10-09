<?php

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request): RedirectResponse {
    $home = $request->user()?->hasRole('admin') ? 'admin.dashboard' : 'dashboard';

    return redirect()->route($request->user() === null ? 'login' : $home);
});

Route::middleware(['auth', 'verified', 'can:access-petugas'])
    ->group(base_path('routes/petugas.php'));

Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(base_path('routes/admin.php'));

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->middleware('impersonate.protect')->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('impersonate.protect')->name('profile.update');
    Route::delete('/impersonation', [ImpersonationController::class, 'destroy'])->name('impersonation.destroy');
});

require __DIR__.'/auth.php';
