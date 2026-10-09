<?php

namespace App;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GoogleDriveBackup
{
    public function isConfigured(): bool
    {
        return filled(config('services.google_drive.client_id'))
            && filled(config('services.google_drive.client_secret'))
            && filled(config('backup.backup.password'));
    }

    public function isConnected(): bool
    {
        if ($this->usesEnvironmentConnection()) {
            return true;
        }

        return DB::table('google_drive_connections')->where('id', 1)->exists();
    }

    public function usesEnvironmentConnection(): bool
    {
        return filled(config('services.google_drive.refresh_token'))
            && filled(config('services.google_drive.folder'));
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_drive.client_id'),
            'redirect_uri' => route('admin.backups.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/userinfo.email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function connect(string $code): void
    {
        $tokens = Http::asForm()->connectTimeout(5)->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => route('admin.backups.callback'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $tokens->successful() || ! is_string($tokens->json('refresh_token')) || ! is_string($tokens->json('access_token'))) {
            throw new RuntimeException('Google tidak memberikan izin akses jangka panjang. Coba hubungkan kembali.');
        }

        $accessToken = $tokens->json('access_token');
        $folder = Http::withToken($accessToken)->connectTimeout(5)->timeout(20)
            ->post('https://www.googleapis.com/drive/v3/files?fields=id', [
                'name' => config('app.name').' - Backup Database',
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);

        if (! $folder->successful() || ! is_string($folder->json('id'))) {
            throw new RuntimeException('Folder backup gagal dibuat di Google Drive.');
        }

        $profile = Http::withToken($accessToken)->connectTimeout(5)->timeout(20)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        DB::table('google_drive_connections')->updateOrInsert(['id' => 1], [
            'refresh_token' => Crypt::encryptString($tokens->json('refresh_token')),
            'folder_id' => $folder->json('id'),
            'email' => $profile->successful() ? $profile->json('email') : null,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    /** @return array{id: string, size: int} */
    public function upload(string $path, string $filename): array
    {
        ['access_token' => $accessToken, 'folder_id' => $folderId] = $this->accessTokenAndFolder();

        $size = Storage::disk('local')->size($path);
        $session = Http::withToken($accessToken)
            ->withHeaders(['X-Upload-Content-Type' => 'application/zip', 'X-Upload-Content-Length' => (string) $size])
            ->connectTimeout(5)->timeout(20)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,size', [
                'name' => $filename,
                'parents' => [$folderId],
            ]);

        $uploadUrl = $session->header('Location');
        if (! $session->successful()) {
            throw $this->uploadSessionException($session);
        }

        if (! is_string($uploadUrl)
            || ! str_starts_with($uploadUrl, 'https://www.googleapis.com/upload/drive/v3/files?')) {
            throw new RuntimeException('Sesi unggah Google Drive tidak mengembalikan alamat unggah yang valid (HTTP '.$session->status().').');
        }

        $stream = Storage::disk('local')->readStream($path);
        if ($stream === false) {
            throw new RuntimeException('Arsip backup tidak dapat dibaca.');
        }

        try {
            $response = Http::withToken($accessToken)
                ->withBody($stream, 'application/zip')
                ->withHeaders(['Content-Length' => (string) $size])
                ->connectTimeout(10)->timeout(3300)->put($uploadUrl);
        } finally {
            fclose($stream);
        }

        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new RuntimeException('Unggah backup ke Google Drive gagal.');
        }

        return ['id' => $response->json('id'), 'size' => $size];
    }

    /** @return array{access_token: string, folder_id: string} */
    public function accessTokenAndFolder(bool $verifyFolder = true): array
    {
        if ($this->usesEnvironmentConnection()) {
            $refreshToken = config('services.google_drive.refresh_token');
            $folderId = config('services.google_drive.folder');
        } else {
            $connection = DB::table('google_drive_connections')->where('id', 1)->first();

            if ($connection === null) {
                throw new RuntimeException('Google Drive belum terhubung.');
            }

            $refreshToken = Crypt::decryptString($connection->refresh_token);
            $folderId = $connection->folder_id;
        }

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $folderId)) {
            throw new RuntimeException('GOOGLE_DRIVE_FOLDER harus berisi ID folder Google Drive, bukan tautan atau nama folder.');
        }

        $tokenCacheKey = 'google-drive-access-token:'.hash('sha256', config('services.google_drive.client_id').$refreshToken);
        $cachedToken = Cache::get($tokenCacheKey);
        $accessToken = is_string($cachedToken) ? Crypt::decryptString($cachedToken) : null;

        if (! is_string($accessToken)) {
            $tokens = Http::asForm()->connectTimeout(5)->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'refresh_token' => $refreshToken,
                'client_id' => config('services.google_drive.client_id'),
                'client_secret' => config('services.google_drive.client_secret'),
                'grant_type' => 'refresh_token',
            ]);

            if (! $tokens->successful() || ! is_string($tokens->json('access_token'))) {
                throw new RuntimeException($this->usesEnvironmentConnection()
                    ? 'Token Google Drive tidak berlaku. Periksa GOOGLE_DRIVE_REFRESH_TOKEN dan OAuth client di .env.'
                    : 'Izin Google Drive tidak berlaku. Hubungkan ulang akun Google.');
            }

            $accessToken = $tokens->json('access_token');
            $expiresIn = $tokens->json('expires_in');
            Cache::put($tokenCacheKey, Crypt::encryptString($accessToken), is_int($expiresIn) ? max(1, min(3000, $expiresIn - 60)) : 3000);
        }

        if (! $verifyFolder) {
            return ['access_token' => $accessToken, 'folder_id' => $folderId];
        }

        $folder = Http::withToken($accessToken)->connectTimeout(5)->timeout(20)
            ->get('https://www.googleapis.com/drive/v3/files/'.$folderId, [
                'fields' => 'id,mimeType,trashed,capabilities(canAddChildren)',
            ]);

        if (! $folder->successful()) {
            throw new RuntimeException(match ($folder->status()) {
                404 => 'Folder Google Drive tidak ditemukan atau tidak dapat diakses token (HTTP 404). Periksa ID folder dan cakupan izin refresh token.',
                403 => 'Token tidak diizinkan mengakses folder Google Drive (HTTP 403). Periksa cakupan izin dan akun pemilik folder.',
                401 => 'Access token ditolak Google saat memeriksa folder (HTTP 401). Periksa OAuth client dan refresh token.',
                default => 'Pemeriksaan folder Google Drive gagal (HTTP '.$folder->status().').',
            });
        }

        if ($folder->json('mimeType') !== 'application/vnd.google-apps.folder' || $folder->json('trashed') === true) {
            throw new RuntimeException('GOOGLE_DRIVE_FOLDER harus menunjuk folder aktif di Google Drive.');
        }

        if ($folder->json('capabilities.canAddChildren') === false) {
            throw new RuntimeException('Akun Google tidak memiliki izin menambahkan file ke folder tujuan.');
        }

        return ['access_token' => $accessToken, 'folder_id' => $folderId];
    }

    private function uploadSessionException(Response $response): RuntimeException
    {
        $reason = $response->json('error.errors.0.reason');
        $reasonText = is_string($reason) && preg_match('/^[A-Za-z0-9_]{1,80}$/', $reason)
            ? ' ('.$reason.')'
            : '';

        $detail = match ($response->status()) {
            400 => 'Permintaan unggah ditolak. Periksa ID folder dan konfigurasi Google Drive.',
            401 => 'Access token tidak diterima. Periksa refresh token dan OAuth client.',
            403 => 'Akun Google atau cakupan izin token tidak dapat menulis ke folder tujuan.',
            404 => 'Folder tujuan tidak ditemukan atau tidak dapat diakses oleh token ini.',
            429 => 'Batas permintaan Google Drive tercapai. Coba lagi nanti.',
            default => $response->serverError()
                ? 'Layanan Google Drive sedang bermasalah. Coba lagi nanti.'
                : 'Periksa izin dan konfigurasi Google Drive.',
        };

        return new RuntimeException('Sesi unggah Google Drive gagal (HTTP '.$response->status().$reasonText.'). '.$detail);
    }
}
