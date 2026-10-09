<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunGoogleDriveBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        Artisan::call('backup:google-drive', ['--run-id' => $this->runId]);
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('backup_runs')->where('id', $this->runId)->whereIn('status', ['pending', 'running'])->update([
            'status' => 'failed',
            'error' => 'Pekerjaan backup berhenti sebelum selesai. Periksa log aplikasi.',
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
