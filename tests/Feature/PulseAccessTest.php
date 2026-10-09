<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PulseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pulse_dashboard_is_available_to_active_admins_only(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();

        $this->assertSame('/admin/pulse', parse_url(route('pulse'), PHP_URL_PATH));
        $this->get(route('pulse'))->assertRedirect(route('login'));
        $this->actingAs($petugas)->get(route('pulse'))->assertForbidden();
        $this->actingAs($admin)->get(route('pulse'))->assertOk();

        $admin->update(['is_active' => false]);
        $this->get(route('pulse'))->assertRedirect(route('login'));
    }

    public function test_pulse_gate_requires_an_active_admin(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create();

        $this->assertFalse(Gate::allows('viewPulse'));
        $this->assertFalse(Gate::forUser($petugas)->allows('viewPulse'));
        $this->assertTrue(Gate::forUser($admin)->allows('viewPulse'));

        $admin->update(['is_active' => false]);
        $this->assertFalse(Gate::forUser($admin)->allows('viewPulse'));
    }
}
