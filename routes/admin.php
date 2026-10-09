<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\SensusPhotoController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/sensus/{record}/foto', SensusPhotoController::class)->name('sensus.photo');
Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
Route::post('/users/{user}/impersonation', [ImpersonationController::class, 'store'])->name('users.impersonate');
