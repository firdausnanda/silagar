<?php

namespace App\Http\Controllers\Admin;

use App\GoogleDriveBackup;
use App\Http\Controllers\Controller;
use App\Jobs\RunGoogleDriveBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BackupController extends Controller
{
    public function index(GoogleDriveBackup $drive): Response
    {
        $connection = DB::table('google_drive_connections')->where('id', 1)->first();

        return Inertia::render('Admin/Backups/Index', [
            'configured' => $drive->isConfigured(),
            'connected' => $drive->isConnected(),
            'usesEnvironmentConnection' => $drive->usesEnvironmentConnection(),
            'email' => $drive->usesEnvironmentConnection() ? null : $connection?->email,
            'runs' => DB::table('backup_runs')->latest('id')->limit(20)->get([
                'id', 'status', 'source', 'filename', 'drive_file_id', 'size', 'error', 'started_at', 'finished_at', 'created_at',
            ]),
            'status' => session('status'),
            'schedule' => 'Setiap hari pukul 01.30 WIB',
        ]);
    }

    public function store(GoogleDriveBackup $drive): RedirectResponse
    {
        if (! $drive->isConfigured() || ! $drive->isConnected()) {
            return back()->with('status', 'Lengkapi konfigurasi Google Drive dan kata sandi arsip terlebih dahulu.');
        }

        if (DB::table('backup_runs')->whereIn('status', ['pending', 'running'])->exists()) {
            return back()->with('status', 'Backup sebelumnya masih menunggu atau sedang berjalan.');
        }

        $runId = DB::table('backup_runs')->insertGetId([
            'status' => 'pending',
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        RunGoogleDriveBackup::dispatch($runId);

        return back()->with('status', 'Backup masuk antrean. Muat ulang halaman untuk melihat hasilnya.');
    }
}
