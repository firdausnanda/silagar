<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Opcodes\LogViewer\LogFile;
use Opcodes\LogViewer\LogFolder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_viewer_page_only_allows_active_admins(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();

        $this->get(route('log-viewer.index'))->assertForbidden();
        $this->actingAs($petugas)->get(route('log-viewer.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('log-viewer.index'))->assertOk();
        $admin->update(['is_active' => false]);
        $this->get(route('log-viewer.index'))->assertRedirect(route('login'));
    }

    public function test_log_viewer_api_only_allows_active_admins(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();
        $apiUrl = route('log-viewer.hosts');
        $this->withHeader('Referer', route('log-viewer.index'));

        $this->get($apiUrl)->assertForbidden();
        $this->actingAs($petugas)->get($apiUrl)->assertForbidden();
        $this->actingAs($admin)->get($apiUrl)->assertOk();
        $admin->update(['is_active' => false]);
        $this->get($apiUrl)->assertForbidden();
    }

    public function test_admin_can_view_logs_but_cannot_delete_log_files_or_folders(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue(Gate::forUser($admin)->allows('viewLogViewer'));
        $this->assertFalse(Gate::forUser($admin)->allows('deleteLogFile', Mockery::mock(LogFile::class)));
        $this->assertFalse(Gate::forUser($admin)->allows('deleteLogFolder', Mockery::mock(LogFolder::class)));
    }
}
