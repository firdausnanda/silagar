<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Role;

class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            throw new RuntimeException('Seeder akun admin contoh hanya boleh dijalankan di lingkungan lokal atau pengujian.');
        }

        Role::findOrCreate('admin', 'web');
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Admin Contoh',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles('admin');
    }
}
