<?php

namespace Tests\Feature;

use App\Models\PenggarapLahan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAdminSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_petugas_have_separate_routes_and_admin_cannot_write_sensus(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();

        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs($petugas)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
        $this->get('/dashboard')->assertForbidden();
        $this->postJson('/sensus', [])->assertForbidden();
        $this->getJson('/sensus/export/choices')->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_roleless_account_cannot_enter_petugas_or_admin_routes(): void
    {
        $roleless = User::query()->create([
            'name' => 'Belum Diberi Role',
            'email' => 'tanpa-role@example.test',
            'password' => 'password',
        ]);

        $this->actingAs($roleless)->get('/dashboard')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->postJson('/sensus', [])->assertForbidden();
    }

    public function test_admin_monitoring_reads_all_server_records_without_changing_them(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $firstPetugas = User::factory()->create();
        $secondPetugas = User::factory()->create();
        $first = $this->createRecord($firstPetugas, 'Bidang pertama');
        $first->update(['nama_kth' => 'KTH Rimba Jaya', 'nama_penunjuk' => 'Pak Hadi', 'kabupaten_kota' => 'Kabupaten Malang']);
        $this->createRecord($secondPetugas, 'Bidang kedua');

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('summary.total', 2)
                ->where('records.data.0.lama_menggarap', 3)
                ->has('records.data', 2));

        $this->get('/admin?user_id='.$firstPetugas->id)
            ->assertInertia(fn ($page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.nama_kth', 'KTH Rimba Jaya')
                ->where('records.data.0.nama_penunjuk', 'Pak Hadi')
                ->where('records.data.0.kabupaten_kota', 'Kabupaten Malang')
                ->where('records.data.0.id', $first->id));

        $this->get('/admin?search=KTH%20Rimba')
            ->assertInertia(fn ($page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.id', $first->id));
        $this->get('/admin?search=Kabupaten%20Malang')
            ->assertInertia(fn ($page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.id', $first->id));

        Storage::fake('local');
        Storage::disk('local')->put('sensus/test.jpg', 'foto contoh');
        Storage::disk('local')->assertExists('sensus/test.jpg');
        $this->get(route('admin.sensus.photo', $first))->assertOk();
        $this->actingAs($firstPetugas)->get(route('admin.sensus.photo', $first))->assertForbidden();
    }

    public function test_admin_can_combine_advanced_filters_and_sort_results(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();

        $first = $this->createRecord($petugas, 'Garapan kecil');
        $first->update([
            'kabupaten_kota' => 'Kabupaten Malang',
            'luas_garapan' => 1.25,
            'captured_at' => '2026-10-22 17:30:00',
        ]);
        $second = $this->createRecord($petugas, 'Garapan besar');
        $second->update([
            'kabupaten_kota' => 'Kabupaten Malang',
            'luas_garapan' => 2.50,
            'captured_at' => '2026-10-23 16:30:00',
        ]);
        $other = $this->createRecord($petugas, 'Wilayah lain');
        $other->update([
            'kabupaten_kota' => 'Kota Batu',
            'luas_garapan' => 3.00,
            'captured_at' => '2026-10-23 04:00:00',
        ]);

        $this->actingAs($admin)->get('/admin?'.http_build_query([
            'user_id' => $petugas->id,
            'kabupaten_kota' => 'Kabupaten Malang',
            'date_from' => '2026-10-23',
            'date_to' => '2026-10-23',
            'luas_min' => '1',
            'luas_max' => '2.50',
            'foto' => 'ada',
            'koordinat' => 'ada',
            'sort' => 'luas_terbesar',
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('summary.total', 3)
            ->where('records.total', 2)
            ->where('records.data.0.id', $second->id)
            ->where('records.data.1.id', $first->id)
            ->where('filters.date_from', '2026-10-23')
            ->where('filters.sort', 'luas_terbesar')
            ->has('kabupatenKotaOptions', 2));

        $this->get('/admin?date_to=2026-10-22')
            ->assertInertia(fn ($page) => $page->where('records.total', 0));
    }

    public function test_admin_can_filter_records_missing_photos_or_coordinates(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();
        $complete = $this->createRecord($petugas, 'Lengkap');
        $incomplete = $this->createRecord($petugas, 'Belum lengkap');
        $incomplete->update(['foto_path' => null, 'longitude' => null]);

        $this->actingAs($admin)->get('/admin?foto=tidak_ada&koordinat=tidak_ada')
            ->assertOk()->assertInertia(fn ($page) => $page
            ->where('records.total', 1)
            ->where('records.data.0.id', $incomplete->id));

        $this->get('/admin?foto=ada&koordinat=ada')
            ->assertInertia(fn ($page) => $page
                ->where('records.total', 1)
                ->where('records.data.0.id', $complete->id));
    }

    public function test_admin_advanced_filters_reject_invalid_ranges_and_sort_values(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/admin?date_from=2026-10-24&date_to=2026-10-23&luas_min=2&luas_max=1&sort=unknown')
            ->assertRedirect()
            ->assertSessionHasErrors(['date_to', 'luas_max', 'sort']);
    }

    public function test_role_and_demo_admin_seeders_are_repeatable_and_dummy_account_is_not_created_in_production(): void
    {
        $existing = User::query()->create([
            'name' => 'Akun Lama',
            'email' => 'lama@example.test',
            'password' => 'password',
        ]);
        $this->seed(RoleSeeder::class);
        $this->seed(DemoAdminSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(DemoAdminSeeder::class);

        $this->assertDatabaseCount('roles', 2);
        $this->assertTrue($existing->fresh()->hasRole('user'));
        $this->assertSame(1, User::query()->where('email', 'admin@example.test')->count());
        $this->assertTrue(User::query()->where('email', 'admin@example.test')->firstOrFail()->hasRole('admin'));

        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');

        try {
            $this->expectException(RuntimeException::class);
            app(DemoAdminSeeder::class)->run();
        } finally {
            app()->instance('env', $originalEnvironment);
        }
    }

    public function test_default_seeder_creates_two_roles_and_dev_accounts_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseCount('users', 4);
        $this->assertSame(4, DB::table('model_has_roles')->count());
        $this->assertTrue(User::query()->where('email', 'admin@example.test')->firstOrFail()->hasRole('admin'));
    }

    public function test_default_seeder_does_not_create_dummy_accounts_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');

        try {
            app(DatabaseSeeder::class)->run();
        } finally {
            app()->instance('env', $originalEnvironment);
        }

        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseCount('users', 0);
    }

    private function createRecord(User $owner, string $name): PenggarapLahan
    {
        $id = DB::table('penggarap_lahans')->insertGetId([
            'nama' => $name,
            'luas_garapan' => 1.25,
            'lama_menggarap' => 3,
            'foto_path' => 'sensus/test.jpg',
            'latitude' => -7.25,
            'longitude' => 112.75,
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PenggarapLahan::query()->findOrFail($id);
    }
}
