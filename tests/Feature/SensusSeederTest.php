<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PenggarapLahanSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SensusSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_ten_thousand_fake_records_for_twenty_petugas_without_duplicates(): void
    {
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 21);
        $this->assertDatabaseCount('penggarap_lahans', 10000);
        $this->assertSame(20, DB::table('penggarap_lahans')->distinct()->count('created_by'));
        $recordsPerPetugas = DB::table('penggarap_lahans')
            ->selectRaw('count(*) as total')
            ->groupBy('created_by')
            ->pluck('total')
            ->map(fn (int $total): int => $total)
            ->all();
        $this->assertSame(array_fill(0, 20, 500), $recordsPerPetugas);
        $this->assertSame(0, DB::table('penggarap_lahans')->whereNull('captured_at')->count());
        $this->assertSame(0, DB::table('penggarap_lahans')->whereNull('created_by')->count());
        $this->assertSame(10000, DB::table('penggarap_lahans')->distinct()->count('client_uuid'));
        $this->assertSame(0, DB::table('penggarap_lahans')->whereNull('utm_x')->orWhereNull('utm_y')->orWhereNull('utm_epsg')->count());
        $this->assertSame([32749, 32750], DB::table('penggarap_lahans')->distinct()->orderBy('utm_epsg')->pluck('utm_epsg')->all());
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertTrue(Hash::check('password', DB::table('users')->where('email', 'petugas01@example.test')->value('password')));

        $first = DB::table('penggarap_lahans')->first();
        $this->assertStringStartsWith('08', $first->no_hp);
        Storage::disk('local')->assertExists($first->foto_path);
        $originalCoordinates = [$first->latitude, $first->longitude, $first->utm_x, $first->utm_y, $first->utm_epsg];

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 21);
        $this->assertDatabaseCount('penggarap_lahans', 10000);
        $updated = DB::table('penggarap_lahans')->where('id', $first->id)->first();
        $this->assertSame($originalCoordinates, [$updated->latitude, $updated->longitude, $updated->utm_x, $updated->utm_y, $updated->utm_epsg]);
    }

    public function test_demo_seeders_refuse_to_create_fake_accounts_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');

        try {
            foreach ([UserSeeder::class, PenggarapLahanSeeder::class] as $seeder) {
                try {
                    app($seeder)->run();
                    $this->fail("{$seeder} should reject the production environment.");
                } catch (RuntimeException $exception) {
                    $this->assertStringContainsString('produksi', $exception->getMessage());
                }
            }
        } finally {
            app()->instance('env', $originalEnvironment);
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('penggarap_lahans', 0);
    }
}
