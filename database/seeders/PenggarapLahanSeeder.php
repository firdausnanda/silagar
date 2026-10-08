<?php

namespace Database\Seeders;

use App\Helpers\UtmConverter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PenggarapLahanSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Seeder bidang uji tidak boleh dijalankan di produksi.');
        }

        $userIds = User::query()
            ->whereIn('email', UserSeeder::demoEmails())
            ->orderBy('email')
            ->pluck('id')
            ->all();

        if (count($userIds) !== 20) {
            throw new RuntimeException('Jalankan UserSeeder sebelum PenggarapLahanSeeder.');
        }

        $photoPath = 'sensus/seed/placeholder.png';
        $placeholder = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lVsAAAAASUVORK5CYII=', true);

        if ($placeholder === false || Storage::disk('local')->put($photoPath, $placeholder) === false) {
            throw new RuntimeException('Foto contoh untuk data sensus tidak dapat disimpan.');
        }

        $faker = fake('id_ID');
        $rows = [];

        for ($number = 1; $number <= 10000; $number++) {
            $capturedAt = $faker->dateTimeBetween('-2 years', 'now', 'UTC')->format('Y-m-d H:i:s');
            $latitude = $faker->randomFloat(8, -8.5, -6.0);
            $longitude = $faker->randomFloat(8, 110.0, 115.0);
            $rows[] = [
                'client_uuid' => sprintf('10000000-0000-4000-8000-%012d', $number),
                'nama' => $faker->name(),
                'no_hp' => $faker->numerify('08##########'),
                'luas_garapan' => $faker->randomFloat(2, 0.1, 9.5),
                'lama_menggarap' => $faker->numberBetween(1, 35),
                'foto_path' => $photoPath,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'gps_accuracy_m' => $faker->randomFloat(2, 1.5, 15.0),
                'captured_at' => $capturedAt,
                'created_by' => $userIds[($number - 1) % count($userIds)],
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ];

            if (count($rows) === 50) {
                $existing = DB::table('penggarap_lahans')
                    ->whereIn('client_uuid', array_column($rows, 'client_uuid'))
                    ->get(['client_uuid', 'latitude', 'longitude'])
                    ->keyBy('client_uuid');

                foreach ($rows as &$row) {
                    if ($existing->has($row['client_uuid'])) {
                        $row['latitude'] = (float) $existing[$row['client_uuid']]->latitude;
                        $row['longitude'] = (float) $existing[$row['client_uuid']]->longitude;
                    }

                    $row = array_replace($row, UtmConverter::fromCoordinates($row['latitude'], $row['longitude']));
                }
                unset($row);

                DB::table('penggarap_lahans')->upsert($rows, ['client_uuid'], [
                    'utm_x', 'utm_y', 'utm_epsg',
                ]);
                $rows = [];
            }
        }
    }
}
