<?php

namespace App\Jobs;

use App\GoogleDrivePhotoStorage;
use App\Models\PenggarapLahan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves a sensus photo from the local disk to Google Drive outside the HTTP request,
 * so a slow Drive upload never holds a PHP-FPM worker. Until it finishes the photo is
 * served from the local disk; if every attempt fails it simply stays there.
 */
class UploadSensusPhotoToDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 180;

    public function __construct(public int $recordId, public string $localPath) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(GoogleDrivePhotoStorage $photos): void
    {
        if (! $photos->isEnabled()) {
            return;
        }

        $record = PenggarapLahan::query()->with('creator')->find($this->recordId);

        // The record was deleted, or its photo was replaced while this job waited.
        if ($record === null || ! $this->stillPointsAtLocalPhoto($record) || $record->creator === null
            || ! Storage::disk('local')->exists($this->localPath)) {
            return;
        }

        $fileId = $photos->storeLocalFile($this->localPath, $record->creator);

        // Only swap if the record still points at this local photo; otherwise discard the upload.
        $updated = PenggarapLahan::query()
            ->whereKey($record->id)
            ->where('foto_storage', 'local')
            ->where('foto_path', $this->localPath)
            ->update(['foto_storage' => 'google_drive', 'foto_path' => $fileId]);

        if ($updated === 0) {
            $photos->delete($fileId);

            return;
        }

        Storage::disk('local')->delete($this->localPath);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Foto sensus gagal dipindahkan ke Google Drive dan tetap tersimpan lokal.', [
            'record_id' => $this->recordId,
            'foto_path' => $this->localPath,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function stillPointsAtLocalPhoto(PenggarapLahan $record): bool
    {
        return $record->foto_storage === 'local' && $record->foto_path === $this->localPath;
    }
}
