<?php

namespace App\Http\Controllers\Admin;

use App\GoogleDriveBackup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GoogleDriveConnectionController extends Controller
{
    public function create(Request $request, GoogleDriveBackup $drive): RedirectResponse
    {
        if (! filled(config('services.google_drive.client_id')) || ! filled(config('services.google_drive.client_secret'))) {
            return redirect()->route('admin.backups.index')->with('status', 'Isi GOOGLE_DRIVE_CLIENT_ID dan GOOGLE_DRIVE_CLIENT_SECRET di VPS.');
        }

        $state = Str::random(40);
        $request->session()->put('google_drive_oauth_state', $state);

        return redirect()->away($drive->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleDriveBackup $drive): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_drive_oauth_state');
        $state = $request->query('state');

        abort_unless(is_string($expectedState) && is_string($state) && hash_equals($expectedState, $state), 403);

        if ($request->query('error') !== null) {
            return redirect()->route('admin.backups.index')->with('status', 'Izin Google Drive tidak diberikan.');
        }

        $code = $request->query('code');
        abort_unless(is_string($code) && $code !== '', 422);

        try {
            $drive->connect($code);
        } catch (RuntimeException $exception) {
            return redirect()->route('admin.backups.index')->with('status', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.backups.index')->with('status', 'Koneksi ke Google gagal. Periksa log aplikasi dan coba lagi.');
        }

        return redirect()->route('admin.backups.index')->with('status', 'Google Drive terhubung. Backup database siap dijalankan.');
    }
}
