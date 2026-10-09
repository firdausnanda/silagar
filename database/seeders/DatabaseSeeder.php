<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        if (in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->call([
                CDKUserSeeder::class,
                DemoAdminSeeder::class,
            ]);
        }
    }
}
