<?php

namespace App\Console\Commands;

use App\GoogleDriveBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('backup:google-drive {--run-id=}')]
#[Description('Back up the database with Spatie and upload it to Google Drive')]
class BackupDatabaseToGoogleDrive extends Command
{
    public function handle(GoogleDriveBackup $drive): int
    {
        $runId = $this->option('run-id');
        $lock = Cache::lock('backup:google-drive', 3600);

        if (! $lock->get()) {
            if ($runId !== null) {
                DB::table('backup_runs')->where('id', $runId)->update([
                    'status' => 'failed',
                    'error' => 'Backup lain masih berjalan. Coba lagi setelah selesai.',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->error('Backup lain masih berjalan.');

            return self::FAILURE;
        }

        try {
            if ($runId === null) {
                $runId = DB::table('backup_runs')->insertGetId([
                    'status' => 'pending',
                    'source' => 'scheduled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('backup_runs')->where('id', $runId)->update([
                'status' => 'running', 'started_at' => now(), 'updated_at' => now(),
            ]);

            try {
                if (! $drive->isConfigured() || ! $drive->isConnected()) {
                    throw new \RuntimeException('Konfigurasi atau koneksi Google Drive belum lengkap.');
                }

                $filename = 'database-'.now('Asia/Jakarta')->format('Y-m-d-His').'-'.$runId.'.zip';
                $path = 'google-drive-staging/'.$filename;

                $exitCode = Artisan::call('backup:run', [
                    '--only-db' => true,
                    '--only-to-disk' => 'local',
                    '--destination-path' => 'google-drive-staging',
                    '--filename' => $filename,
                    '--disable-notifications' => true,
                ]);

                if ($exitCode !== 0 || ! Storage::disk('local')->exists($path)) {
                    throw new \RuntimeException('Pembuatan arsip database gagal. Periksa log aplikasi.');
                }

                $uploaded = $drive->upload($path, $filename);

                DB::table('backup_runs')->where('id', $runId)->update([
                    'status' => 'success',
                    'filename' => $filename,
                    'drive_file_id' => $uploaded['id'],
                    'size' => $uploaded['size'],
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

                Storage::disk('local')->delete($path);
                $this->info('Backup database berhasil diunggah ke Google Drive.');

                return self::SUCCESS;
            } catch (Throwable $exception) {
                report($exception);
                $message = $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'Kesalahan sistem saat membuat atau mengunggah backup. Periksa log aplikasi.';
                DB::table('backup_runs')->where('id', $runId)->update([
                    'status' => 'failed',
                    'error' => $message,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->error('Backup database gagal: '.$message);

                return self::FAILURE;
            }
        } finally {
            $lock->release();
        }
    }
}
