<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_petugas_but_petugas_cannot_manage_accounts(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();

        $this->actingAs($petugas)->get('/admin/users')->assertForbidden();
        $this->postJson('/admin/users', [])->assertForbidden();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Petugas Baru',
            'email' => 'baru@example.test',
            'password' => 'rahasia123',
        ])->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'baru@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole('user'));
        $this->assertTrue(Hash::check('rahasia123', $created->password));

        $this->patch(route('admin.users.update', $created), [
            'name' => 'Petugas Diperbarui',
            'email' => 'diperbarui@example.test',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $created->id, 'name' => 'Petugas Diperbarui', 'email' => 'diperbarui@example.test']);

        $originalPassword = $created->fresh()->password;
        $this->patch(route('admin.users.update', $created), [
            'name' => 'Petugas Diperbarui',
            'email' => 'diperbarui@example.test',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertSame($originalPassword, $created->fresh()->password);

        $this->patch(route('admin.users.update', $created), [
            'name' => 'Petugas Diperbarui',
            'email' => 'diperbarui@example.test',
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertTrue(Hash::check('sandi-baru-123', $created->fresh()->password));

        $this->patch(route('admin.users.update', $created), [
            'name' => 'Petugas Diperbarui',
            'email' => 'diperbarui@example.test',
            'password' => 'sandi-baru-456',
            'password_confirmation' => 'tidak-sama',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('sandi-baru-123', $created->fresh()->password));

        $this->post('/admin/users', [
            'name' => 'Duplikat',
            'email' => 'diperbarui@example.test',
            'password' => 'pendek',
        ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_deactivated_petugas_cannot_sign_in_or_keep_using_an_existing_session(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.status', $petugas), ['is_active' => false])
            ->assertRedirect(route('admin.users.index'));
        $this->assertFalse($petugas->fresh()->is_active);

        $this->actingAs($petugas->fresh())->get('/dashboard')->assertRedirect('/login');
        $this->post('/login', ['email' => $petugas->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)->patch(route('admin.users.status', $petugas), ['is_active' => true])
            ->assertRedirect(route('admin.users.index'));
        $this->post('/logout');
        $this->post('/login', ['email' => $petugas->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_admin_account_cannot_be_modified_from_petugas_management(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->patch(route('admin.users.status', $admin), ['is_active' => false])
            ->assertForbidden();
        $this->patch(route('admin.users.update', $admin), [
            'name' => 'Changed',
            'email' => 'changed@example.test',
        ])->assertForbidden();
        $this->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->post(route('admin.users.impersonate', $admin))->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_can_delete_only_inactive_petugas_without_sensus(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $withSensus = User::factory()->create();
        $withoutSensus = User::factory()->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $withoutSensus))
            ->assertSessionHasErrors('delete');
        $this->assertModelExists($withoutSensus);

        $withSensus->update(['is_active' => false]);
        DB::table('penggarap_lahans')->insert([
            'nama' => 'Bidang petugas',
            'luas_garapan' => 1.25,
            'lama_menggarap' => 2,
            'foto_path' => 'sensus/test.jpg',
            'latitude' => -7.25,
            'longitude' => 112.75,
            'created_by' => $withSensus->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->delete(route('admin.users.destroy', $withSensus))
            ->assertSessionHasErrors('delete');
        $this->assertModelExists($withSensus);

        $withoutSensus->update(['is_active' => false]);
        $this->delete(route('admin.users.destroy', $withoutSensus))
            ->assertRedirect(route('admin.users.index'));
        $this->assertModelMissing($withoutSensus);
    }

    public function test_admin_can_impersonate_active_petugas_and_return_to_admin(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $petugas))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($petugas);
        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.is_impersonating', true));
        $this->get('/admin/users')->assertForbidden();
        $this->from('/dashboard')->patch(route('profile.update'), [
            'name' => 'Nama Tidak Boleh Berubah',
            'email' => 'tidak-berubah@example.test',
        ])->assertRedirect('/dashboard');
        $this->assertNotSame('Nama Tidak Boleh Berubah', $petugas->fresh()->name);
        $this->from('/dashboard')->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ])->assertRedirect('/dashboard');
        $this->assertTrue(Hash::check('password', $petugas->fresh()->password));

        $this->delete(route('impersonation.destroy'))
            ->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/users')->assertOk();
        $this->delete(route('impersonation.destroy'))->assertForbidden();
    }

    public function test_petugas_and_inactive_accounts_cannot_be_impersonated(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($petugas)->post(route('admin.users.impersonate', $admin))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $inactive))->assertForbidden();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_impersonation_ends_when_petugas_is_deactivated(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $petugas))
            ->assertRedirect(route('dashboard'));
        $petugas->update(['is_active' => false]);
        Auth::guard('web')->setUser($petugas->fresh());

        $this->get('/dashboard')->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($admin);
    }
}
