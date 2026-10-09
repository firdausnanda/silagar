<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Role;

class CDKUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            throw new RuntimeException('Seeder akun contoh hanya boleh dijalankan di lingkungan lokal atau pengujian.');
        }

        Role::findOrCreate('user', 'web');

        $users = [
            [
                'name' => 'ALIKA FATTA KUMALA',
                'email' => 'alika.fatta.kumala@cdk.com',
                'password' => 'cdk_alika',
            ],
            [
                'name' => 'ANTONIUS KRISTIANTO YUHANDONO',
                'email' => 'antonius.kristianto.yuhandono@cdk.com',
                'password' => 'cdk_yuhandono',
            ],
            [
                'name' => 'SIDIK WICAKSONO',
                'email' => 'sidik.wicaksono@cdk.com',
                'password' => 'cdk_sidik',
            ],
        ];

        foreach ($users as $user) {
            $account = User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => $user['password'],
                    'email_verified_at' => now(),
                ]
            );
            if (! $account->hasRole('admin')) {
                $account->assignRole('user');
            }
        }
    }
}
