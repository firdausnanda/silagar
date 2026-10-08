<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Seeder akun uji tidak boleh dijalankan di produksi.');
        }

        if (User::query()->where('email', 'test@example.com')->doesntExist()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        foreach (self::demoEmails() as $index => $email) {
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            User::factory()->create([
                'name' => sprintf('Petugas Uji %02d', $index + 1),
                'email' => $email,
            ]);
        }
    }

    /** @return list<string> */
    public static function demoEmails(): array
    {
        return array_map(
            fn (int $number): string => sprintf('petugas%02d@example.test', $number),
            range(1, 20),
        );
    }
}
