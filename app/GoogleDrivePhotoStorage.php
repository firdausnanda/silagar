<?php

namespace App;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleDrivePhotoStorage
{
    public function __construct(private readonly GoogleDriveBackup $drive) {}

    public function isEnabled(): bool
    {
        return $this->drive->isConnected();
    }

    public function store(UploadedFile $photo, User $user): string
    {
        ['access_token' => $accessToken, 'folder_id' => $folderId] = $this->drive->accessTokenAndFolder();
        $folderId = $this->folderForUser($accessToken, $folderId, $user);
        $size = $photo->getSize();
        $mimeType = $photo->getMimeType();

        if (! is_int($size) || ! is_string($mimeType)) {
            throw new RuntimeException('Foto sensus tidak dapat dibaca.');
        }

        $session = Http::withToken($accessToken)
            ->withHeaders(['X-Upload-Content-Type' => $mimeType, 'X-Upload-Content-Length' => (string) $size])
            ->connectTimeout(5)->timeout(20)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id', [
                'name' => 'sensus-'.Str::uuid().'.'.$photo->guessExtension(),
                'parents' => [$folderId],
            ]);

        $uploadUrl = $session->header('Location');

        if (! $session->successful() || ! is_string($uploadUrl)
            || ! str_starts_with($uploadUrl, 'https://www.googleapis.com/upload/drive/v3/files?')) {
            throw new RuntimeException('Sesi unggah foto ke Google Drive gagal (HTTP '.$session->status().').');
        }

        $stream = fopen($photo->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Foto sensus tidak dapat dibaca.');
        }

        try {
            $response = Http::withToken($accessToken)
                ->withBody($stream, $mimeType)
                ->withHeaders(['Content-Length' => (string) $size])
                ->connectTimeout(10)->timeout(120)->put($uploadUrl);
        } finally {
            fclose($stream);
        }

        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new RuntimeException('Unggah foto ke Google Drive gagal (HTTP '.$response->status().').');
        }

        return $response->json('id');
    }

    public function response(string $fileId): StreamedResponse
    {
        $download = Http::withToken($this->drive->accessTokenAndFolder(false)['access_token'])
            ->connectTimeout(5)->timeout(30)
            ->get($this->fileUrl($fileId), ['alt' => 'media']);

        abort_if($download->status() === 404, 404);

        if (! $download->successful()) {
            throw new RuntimeException('Foto dari Google Drive gagal dibaca (HTTP '.$download->status().').');
        }

        $mimeType = $download->header('Content-Type');
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'], true)) {
            throw new RuntimeException('Jenis foto dari Google Drive tidak valid.');
        }

        return response()->stream(static function () use ($download): void {
            echo $download->body();
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(string $fileId): bool
    {
        $response = Http::withToken($this->drive->accessTokenAndFolder(false)['access_token'])
            ->connectTimeout(5)->timeout(20)->delete($this->fileUrl($fileId));

        return $response->successful() || $response->status() === 404;
    }

    private function folderForUser(string $accessToken, string $parentId, User $user): string
    {
        $rootId = Cache::lock('google-drive-photo-root:'.hash('sha256', $parentId), 90)
            ->block(30, fn (): string => $this->findOrCreateFolder($accessToken, $parentId, 'root', 'Foto Sensus'));

        $folderName = 'Petugas - '.Str::of($user->name)
            ->replace(['/', '\\'], ' ')
            ->replaceMatches('/[[:cntrl:]]+/', ' ')
            ->squish()
            ->limit(80, '').' (ID '.$user->id.')';

        return Cache::lock('google-drive-photo-user:'.hash('sha256', $rootId.':'.$user->id), 90)
            ->block(30, fn (): string => $this->findOrCreateFolder($accessToken, $rootId, 'user:'.$user->id, $folderName));
    }

    private function findOrCreateFolder(string $accessToken, string $parentId, string $marker, string $name): string
    {
        $query = "'{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false and appProperties has { key='sensus_folder' and value='{$marker}' }";
        $folders = Http::withToken($accessToken)->connectTimeout(5)->timeout(20)
            ->get('https://www.googleapis.com/drive/v3/files', [
                'q' => $query,
                'fields' => 'files(id,name)',
                'pageSize' => 1,
            ]);

        if (! $folders->successful()) {
            throw new RuntimeException('Folder foto Google Drive gagal dicari (HTTP '.$folders->status().').');
        }

        $existingId = $folders->json('files.0.id');
        if (is_string($existingId)) {
            $this->assertFolderId($existingId);
            $existingName = $folders->json('files.0.name');

            if (is_string($existingName) && $existingName !== $name) {
                $renamed = Http::withToken($accessToken)->connectTimeout(5)->timeout(20)
                    ->patch('https://www.googleapis.com/drive/v3/files/'.$existingId, ['name' => $name]);

                if (! $renamed->successful()) {
                    throw new RuntimeException('Nama folder petugas di Google Drive gagal diperbarui (HTTP '.$renamed->status().').');
                }
            }

            return $existingId;
        }

        $created = Http::withToken($accessToken)->connectTimeout(5)->timeout(20)
            ->post('https://www.googleapis.com/drive/v3/files?fields=id', [
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId],
                'appProperties' => ['sensus_folder' => $marker],
            ]);

        $createdId = $created->json('id');
        if (! $created->successful() || ! is_string($createdId)) {
            throw new RuntimeException('Folder foto Google Drive gagal dibuat (HTTP '.$created->status().').');
        }

        $this->assertFolderId($createdId);

        return $createdId;
    }

    private function assertFolderId(string $folderId): void
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $folderId)) {
            throw new RuntimeException('ID folder foto Google Drive tidak valid.');
        }
    }

    private function fileUrl(string $fileId): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $fileId)) {
            throw new RuntimeException('ID foto Google Drive tidak valid.');
        }

        return 'https://www.googleapis.com/drive/v3/files/'.$fileId;
    }
}
