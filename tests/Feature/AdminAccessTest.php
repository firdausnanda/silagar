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
        $this->createRecord($secondPetugas, 'Bidang kedua');

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('summary.total', 2)
                ->has('records.data', 2));

        $this->get('/admin?user_id='.$firstPetugas->id)
            ->assertInertia(fn ($page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.id', $first->id));

        Storage::fake('local');
        Storage::disk('local')->put('sensus/test.jpg', 'foto contoh');
        $this->get(route('admin.sensus.photo', $first))->assertOk();
        $this->actingAs($firstPetugas)->get(route('admin.sensus.photo', $first))->assertForbidden();
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
