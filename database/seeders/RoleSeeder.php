<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');

        User::query()->whereDoesntHave('roles')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $user->assignRole('user');
            }
        });
    }
}
