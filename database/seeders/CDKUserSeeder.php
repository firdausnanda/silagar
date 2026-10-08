<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CDKUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => $user['password'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
