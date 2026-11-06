<?php

namespace App;

use App\Models\PenggarapLahan;
use App\Models\User;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleDrivePhotoStorage
{
    private const FOLDER_CACHE_DAYS = 7;

    public function __construct(private readonly GoogleDriveBackup $drive) {}

    public function isEnabled(): bool
    {
        return $this->drive->isConnected();
    }

    /**
     * Upload a photo kept on the local disk to the petugas folder in Drive and return the Drive file ID.
     */
    public function storeLocalFile(string $localPath, User $user): string
    {
        $disk = Storage::disk('local');
        $absolutePath = $disk->path($localPath);
        $size = @filesize($absolutePath);
        $mimeType = $disk->mimeType($localPath);

        if (! is_int($size) || ! is_string($mimeType)) {
            throw new RuntimeException('Foto sensus tidak dapat dibaca.');
        }

        $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        $filename = 'sensus-'.Str::uuid().($extension === '' ? '' : '.'.$extension);
        ['access_token' => $accessToken, 'folder_id' => $parentId] = $this->drive->accessTokenAndFolder(false);
        $cachedFolderId = $this->cachedUserFolder($parentId, $user);

        $session = $cachedFolderId === null
            ? null
            : $this->startUploadSession($accessToken, $cachedFolderId, $filename, $mimeType, $size);

        // No cached folder yet, or it was removed in Drive: verify the parent and look the folders up again.
        if ($session === null || $session->status() === 404) {
            $this->forgetFolderCache($parentId, $user);
            ['access_token' => $accessToken, 'folder_id' => $parentId] = $this->drive->accessTokenAndFolder();
            $folderId = $this->folderForUser($accessToken, $parentId, $user);
            $session = $this->startUploadSession($accessToken, $folderId, $filename, $mimeType, $size);
        }

        $uploadUrl = $session->header('Location');

        if (! $session->successful() || ! is_string($uploadUrl)
            || ! str_starts_with($uploadUrl, 'https://www.googleapis.com/upload/drive/v3/files?')) {
            throw new RuntimeException('Sesi unggah foto ke Google Drive gagal (HTTP '.$session->status().').');
        }

        $stream = fopen($absolutePath, 'rb');
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

    /**
     * Respond with the record's photo from Drive or the local disk.
     *
     * Versioned URLs (?v= matching the current photo) are immutable, so the browser may keep them
     * for a long time; anything else must be revalidated so a replaced photo is never served stale.
     */
    public function recordResponse(PenggarapLahan $record, Request $request): StreamedResponse
    {
        abort_if($record->foto_path === null, 404);

        $cacheControl = $request->query('v') === $record->photoVersion()
            ? 'private, max-age=31536000, immutable'
            : 'private, no-cache';

        if ($record->foto_storage === 'google_drive') {
            return $this->response($record->foto_path, $cacheControl);
        }

        abort_unless(Storage::disk('local')->exists($record->foto_path), 404);

        return Storage::disk('local')->response($record->foto_path, null, [
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function response(string $fileId, string $cacheControl = 'private, no-store'): StreamedResponse
    {
        $download = Http::withToken($this->drive->accessTokenAndFolder(false)['access_token'])
            ->withOptions(['stream' => true])
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

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ];
        $length = $download->header('Content-Length');
        if (ctype_digit($length)) {
            $headers['Content-Length'] = $length;
        }

        // Relay the body in chunks so a large photo is never held in memory as a whole.
        $body = $download->toPsrResponse()->getBody();

        return response()->stream(static function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(65536);
                flush();
            }
            $body->close();
        }, 200, $headers);
    }

    public function delete(string $fileId): bool
    {
        $response = Http::withToken($this->drive->accessTokenAndFolder(false)['access_token'])
            ->connectTimeout(5)->timeout(20)->delete($this->fileUrl($fileId));

        return $response->successful() || $response->status() === 404;
    }

    private function startUploadSession(string $accessToken, string $folderId, string $filename, string $mimeType, int $size): HttpResponse
    {
        return Http::withToken($accessToken)
            ->withHeaders(['X-Upload-Content-Type' => $mimeType, 'X-Upload-Content-Length' => (string) $size])
            ->connectTimeout(5)->timeout(20)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id', [
                'name' => $filename,
                'parents' => [$folderId],
            ]);
    }

    private function folderForUser(string $accessToken, string $parentId, User $user): string
    {
        $rootId = Cache::lock('google-drive-photo-root:'.hash('sha256', $parentId), 90)
            ->block(30, fn (): string => $this->findOrCreateFolder($accessToken, $parentId, 'root', 'Foto Sensus'));
        Cache::put($this->rootFolderCacheKey($parentId), $rootId, now()->addDays(self::FOLDER_CACHE_DAYS));

        $folderId = Cache::lock('google-drive-photo-user:'.hash('sha256', $rootId.':'.$user->id), 90)
            ->block(30, fn (): string => $this->findOrCreateFolder($accessToken, $rootId, 'user:'.$user->id, $this->userFolderName($user)));
        Cache::put($this->userFolderCacheKey($rootId, $user), $folderId, now()->addDays(self::FOLDER_CACHE_DAYS));

        return $folderId;
    }

    /**
     * The cache key includes the folder name, so renaming a petugas falls back to a lookup that renames the Drive folder.
     */
    private function cachedUserFolder(string $parentId, User $user): ?string
    {
        $rootId = Cache::get($this->rootFolderCacheKey($parentId));
        if (! is_string($rootId)) {
            return null;
        }

        $folderId = Cache::get($this->userFolderCacheKey($rootId, $user));

        return is_string($folderId) && preg_match('/^[A-Za-z0-9_-]+$/', $folderId) ? $folderId : null;
    }

    private function forgetFolderCache(string $parentId, User $user): void
    {
        $rootId = Cache::pull($this->rootFolderCacheKey($parentId));
        if (is_string($rootId)) {
            Cache::forget($this->userFolderCacheKey($rootId, $user));
        }
    }

    private function rootFolderCacheKey(string $parentId): string
    {
        return 'google-drive-photo-folder:root:'.hash('sha256', $parentId);
    }

    private function userFolderCacheKey(string $rootId, User $user): string
    {
        return 'google-drive-photo-folder:user:'.hash('sha256', $rootId.':'.$user->id.':'.$this->userFolderName($user));
    }

    private function userFolderName(User $user): string
    {
        return 'Petugas - '.Str::of($user->name)
            ->replace(['/', '\\'], ' ')
            ->replaceMatches('/[[:cntrl:]]+/', ' ')
            ->squish()
            ->limit(80, '').' (ID '.$user->id.')';
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
