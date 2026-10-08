<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SensusController;
use App\Http\Controllers\SensusExportChoicesController;
use App\Http\Controllers\SensusExportController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request): RedirectResponse {
    return redirect()->route($request->user() === null ? 'login' : 'dashboard');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');
Route::get('/dashboard/records', [DashboardController::class, 'records'])->middleware(['auth', 'verified'])->name('dashboard.records');

Route::get('/input-sensus', function () {
    return Inertia::render('InputSensus');
})->middleware(['auth', 'verified'])->name('input-sensus');

Route::get('/input-sensus-2', function () {
    return redirect()->route('input-sensus');
})->middleware(['auth', 'verified'])->name('input-sensus-2');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/sensus', [SensusController::class, 'store'])->name('sensus.store');
    Route::get('/sensus/export/choices', SensusExportChoicesController::class)->name('sensus.export.choices');
    Route::post('/sensus/export', SensusExportController::class)->name('sensus.export');
    Route::patch('/sensus/{record}', [SensusController::class, 'update'])->name('sensus.update');
    Route::get('/sensus/{record}/foto', [SensusController::class, 'photo'])->name('sensus.photo');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
