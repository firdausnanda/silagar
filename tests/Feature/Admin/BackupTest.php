<?php

namespace Tests\Feature\Admin;

use App\GoogleDriveBackup;
use App\Jobs\RunGoogleDriveBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_or_start_database_backup(): void
    {
        $petugas = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->get(route('admin.backups.index'))->assertRedirect('/login');
        $this->actingAs($petugas)->get(route('admin.backups.index'))->assertForbidden();
        $this->post(route('admin.backups.store'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.backups.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Backups/Index'));
    }

    public function test_admin_can_connect_drive_and_queue_a_manual_backup(): void
    {
        $admin = $this->admin();
        config()->set('services.google_drive.client_id', 'client-id');
        config()->set('services.google_drive.client_secret', 'client-secret');
        config()->set('backup.backup.password', 'long-archive-password');
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token', 'refresh_token' => 'refresh-token',
            ]),
            'https://www.googleapis.com/drive/v3/files*' => Http::response(['id' => 'folder-id']),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response(['email' => 'admin@example.test']),
        ]);
        Queue::fake();

        $this->actingAs($admin)->get(route('admin.backups.connect'))
            ->assertRedirectContains('accounts.google.com');
        $state = session('google_drive_oauth_state');
        $this->get(route('admin.backups.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('admin.backups.index'));

        $connection = DB::table('google_drive_connections')->first();
        $this->assertSame('refresh-token', Crypt::decryptString($connection->refresh_token));
        $this->assertSame('folder-id', $connection->folder_id);

        $this->post(route('admin.backups.store'))->assertRedirect();
        $this->assertDatabaseHas('backup_runs', ['status' => 'pending', 'source' => 'manual']);
        Queue::assertPushed(RunGoogleDriveBackup::class, 1);
    }

    public function test_callback_rejects_invalid_state_and_unconfigured_backup_does_not_queue(): void
    {
        config()->set('services.google_drive.client_id', null);
        config()->set('services.google_drive.client_secret', null);
        config()->set('services.google_drive.refresh_token', null);
        config()->set('services.google_drive.folder', null);
        config()->set('backup.backup.password', null);
        Queue::fake();
        $this->actingAs($this->admin())
            ->get(route('admin.backups.callback', ['state' => 'invalid', 'code' => 'code']))
            ->assertForbidden();

        $this->post(route('admin.backups.store'))->assertRedirect();
        $this->assertDatabaseCount('backup_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_encrypted_archive_is_uploaded_to_the_connected_drive_folder(): void
    {
        config()->set('services.google_drive.refresh_token', null);
        config()->set('services.google_drive.folder', null);
        Storage::fake('local');
        Storage::disk('local')->put('google-drive-staging/database.zip', 'archive-content');
        DB::table('google_drive_connections')->insert([
            'id' => 1,
            'refresh_token' => Crypt::encryptString('refresh-token'),
            'folder_id' => 'folder-id',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        config()->set('services.google_drive.client_id', 'client-id');
        config()->set('services.google_drive.client_secret', 'client-secret');
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'https://www.googleapis.com/drive/v3/files/folder-id*' => Http::response([
                'mimeType' => 'application/vnd.google-apps.folder',
                'capabilities' => ['canAddChildren' => true],
            ]),
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable*' => Http::response([], 200, [
                'Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=123',
            ]),
            'https://www.googleapis.com/upload/drive/v3/files?upload_id=123' => Http::response(['id' => 'file-id']),
        ]);

        $uploaded = app(GoogleDriveBackup::class)->upload('google-drive-staging/database.zip', 'database.zip');

        $this->assertSame(['id' => 'file-id', 'size' => 15], $uploaded);
        Http::assertSent(fn ($request) => $request->url() === 'https://www.googleapis.com/upload/drive/v3/files?upload_id=123'
            && $request->method() === 'PUT');
    }

    public function test_environment_refresh_token_and_folder_allow_backup_without_oauth_connection(): void
    {
        $admin = $this->admin();
        config()->set('services.google_drive.client_id', 'client-id');
        config()->set('services.google_drive.client_secret', 'client-secret');
        config()->set('services.google_drive.refresh_token', 'environment-refresh-token');
        config()->set('services.google_drive.folder', 'existing-folder-id');
        config()->set('backup.backup.password', 'long-archive-password');
        Queue::fake();
        Storage::fake('local');
        Storage::disk('local')->put('google-drive-staging/database.zip', 'archive-content');
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'https://www.googleapis.com/drive/v3/files/existing-folder-id*' => Http::response([
                'mimeType' => 'application/vnd.google-apps.folder',
                'capabilities' => ['canAddChildren' => true],
            ]),
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable*' => Http::response([], 200, [
                'Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=456',
            ]),
            'https://www.googleapis.com/upload/drive/v3/files?upload_id=456' => Http::response(['id' => 'file-id']),
        ]);

        $this->actingAs($admin)->get(route('admin.backups.index'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Backups/Index')
                ->where('connected', true)
                ->where('usesEnvironmentConnection', true));
        $this->post(route('admin.backups.store'))->assertRedirect();

        $uploaded = app(GoogleDriveBackup::class)->upload('google-drive-staging/database.zip', 'database.zip');

        $this->assertSame(['id' => 'file-id', 'size' => 15], $uploaded);
        $this->assertDatabaseCount('google_drive_connections', 0);
        Queue::assertPushed(RunGoogleDriveBackup::class, 1);
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['refresh_token'] === 'environment-refresh-token');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'uploadType=resumable')
            && $request['parents'] === ['existing-folder-id']);
    }

    public function test_manual_backup_reports_failure_if_another_backup_holds_the_lock(): void
    {
        $runId = DB::table('backup_runs')->insertGetId([
            'status' => 'pending', 'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lock = Cache::lock('backup:google-drive', 3600);
        $lock->get();

        try {
            $this->artisan('backup:google-drive', ['--run-id' => $runId])->assertExitCode(1);
        } finally {
            $lock->release();
        }

        $this->assertDatabaseHas('backup_runs', ['id' => $runId, 'status' => 'failed']);
    }

    public function test_drive_upload_session_error_identifies_folder_permission_failure_without_exposing_response_body(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('google-drive-staging/database.zip', 'archive-content');
        config()->set('services.google_drive.client_id', 'client-id');
        config()->set('services.google_drive.client_secret', 'client-secret');
        config()->set('services.google_drive.refresh_token', 'refresh-token');
        config()->set('services.google_drive.folder', 'existing-folder-id');
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'https://www.googleapis.com/drive/v3/files/existing-folder-id*' => Http::response([
                'mimeType' => 'application/vnd.google-apps.folder',
                'capabilities' => ['canAddChildren' => true],
            ]),
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable*' => Http::response([
                'error' => [
                    'message' => 'Sensitive response detail',
                    'errors' => [['reason' => 'insufficientFilePermissions']],
                ],
            ], 403),
        ]);

        try {
            app(GoogleDriveBackup::class)->upload('google-drive-staging/database.zip', 'database.zip');
            $this->fail('The upload should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 403 (insufficientFilePermissions)', $exception->getMessage());
            $this->assertStringNotContainsString('Sensitive response detail', $exception->getMessage());
        }
    }

    public function test_manually_created_folder_without_token_access_stops_before_upload(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('google-drive-staging/database.zip', 'archive-content');
        config()->set('services.google_drive.client_id', 'client-id');
        config()->set('services.google_drive.client_secret', 'client-secret');
        config()->set('services.google_drive.refresh_token', 'refresh-token');
        config()->set('services.google_drive.folder', 'manual-folder-id');
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'https://www.googleapis.com/drive/v3/files/manual-folder-id*' => Http::response([
                'error' => ['message' => 'File not found: manual-folder-id'],
            ], 404),
        ]);

        try {
            app(GoogleDriveBackup::class)->upload('google-drive-staging/database.zip', 'database.zip');
            $this->fail('The upload should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Folder Google Drive tidak ditemukan atau tidak dapat diakses token (HTTP 404)', $exception->getMessage());
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'uploadType=resumable'));
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
