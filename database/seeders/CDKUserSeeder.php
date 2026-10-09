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
                'name' => 'Dwi Altin Fajrunnafi',
                'email' => 'dwialtinf@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Satria Prasenda MS',
                'email' => 'satmahe1205@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Antonius Kristianto Yuhandono, S.Hut',
                'email' => 'kristiantoyuhandono85@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Supriyanto, S.Hut, M.Agr',
                'email' => 'yanto_s28@ymail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Totok Ary Sujadmiko, S.Hut',
                'email' => 'totokary81@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Sidik Wicaksono, S.Hut',
                'email' => 'alazzamsidik@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Hamdani Panduwinata, S.Hut.,M.Si',
                'email' => 'dani_pandu@yahoo.co.id',
                'password' => 'password123',
            ],
            [
                'name' => 'Agus Munir',
                'email' => 'munirforest2000@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Ramadhany Fatahillah Siswanto',
                'email' => 'edhaany2@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Ali Widodo. S.Hut',
                'email' => 'widodo.ali@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Fajar Mahardika',
                'email' => 'fajarmahardiika@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Taufiq Sudaryo, SP',
                'email' => 'taufiqsud@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Roni Rohendi',
                'email' => 'ronirohendi516@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Ruli Endriadi',
                'email' => 'rulienthunk@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Agus Priyono, SP',
                'email' => 'priyonoguus@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Alika Fatta Kumala, S.Hut',
                'email' => 'alikafath@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Pancadani Okto Yusbiyanto, SP, MMA',
                'email' => 'dewapancadhani@gmail.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Ihwan Yusuf Habibi, S.Hut',
                'email' => 'ihwan.y.habibi@gmail.com',
                'password' => 'password123',
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
