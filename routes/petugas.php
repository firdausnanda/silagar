<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SensusController;
use App\Http\Controllers\SensusExportChoicesController;
use App\Http\Controllers\SensusExportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/dashboard', DashboardController::class)->name('dashboard');
Route::get('/dashboard/records', [DashboardController::class, 'records'])->name('dashboard.records');

Route::get('/input-sensus', fn () => Inertia::render('InputSensus'))->name('input-sensus');
Route::get('/input-sensus-2', fn () => redirect()->route('input-sensus'))->name('input-sensus-2');

Route::post('/sensus', [SensusController::class, 'store'])->name('sensus.store');
Route::get('/sensus/export/choices', SensusExportChoicesController::class)->name('sensus.export.choices');
Route::post('/sensus/export', SensusExportController::class)->name('sensus.export');
Route::patch('/sensus/{record}', [SensusController::class, 'update'])->name('sensus.update');
Route::delete('/sensus/{record}', [SensusController::class, 'destroy'])->name('sensus.destroy');
Route::get('/sensus/{record}/foto', [SensusController::class, 'photo'])->name('sensus.photo');
