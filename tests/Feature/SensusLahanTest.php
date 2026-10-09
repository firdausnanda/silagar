<?php

namespace Tests\Feature;

use App\Models\PenggarapLahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SensusLahanTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_petugas_can_upload_one_bidang_without_phone_number(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/sensus', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.nama', 'Siti Aminah')
            ->assertJsonPath('data.client_uuid', '29a24968-3e37-4444-a6b4-7a7b2f3ce016')
            ->assertJsonPath('data.utm_epsg', 32749);
        $this->assertDatabaseHas('penggarap_lahans', [
            'nama' => 'Siti Aminah',
            'no_hp' => null,
            'created_by' => $user->id,
            'client_uuid' => '29a24968-3e37-4444-a6b4-7a7b2f3ce016',
        ]);
        Storage::disk('local')->assertExists(PenggarapLahan::firstOrFail()->foto_path);
        $record = PenggarapLahan::firstOrFail();
        $this->assertGreaterThan(500000, $record->utm_x);
        $this->assertLessThan(800000, $record->utm_x);
        $this->assertGreaterThan(9000000, $record->utm_y);
        $this->assertLessThan(9500000, $record->utm_y);
        $this->assertSame(32749, $record->utm_epsg);
    }

    public function test_repeating_the_same_client_uuid_does_not_create_another_bidang(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->withHeaders(['Accept' => 'application/json']);
        $firstResponse = $this->post('/sensus', $this->validPayload());

        $retryResponse = $this->post('/sensus', $this->validPayload());

        $firstResponse->assertCreated();
        $retryResponse->assertOk()
            ->assertJsonPath('data.id', $firstResponse->json('data.id'));
        $this->assertDatabaseCount('penggarap_lahans', 1);
    }

    public function test_missing_client_uuid_is_rejected_and_logged_without_request_values(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $payload = $this->validPayload();
        unset($payload['client_uuid']);
        Log::shouldReceive('warning')->once()->with(
            'Sensus ditolak karena client_uuid tidak valid.',
            Mockery::on(fn (array $context): bool => $context['user_id'] === $user->id
                && $context['reason'] === 'missing'
                && $context['input_count'] === count($payload)
                && ! array_key_exists('nama', $context)
                && ! array_key_exists('foto', $context)),
        );

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/sensus', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_uuid');

        $this->assertDatabaseCount('penggarap_lahans', 0);
    }

    public function test_coordinates_outside_utm_latitude_range_are_rejected(): void
    {
        Storage::fake('local');
        $payload = $this->validPayload();
        $payload['latitude'] = 85;

        $this->actingAs(User::factory()->create())
            ->postJson('/sensus', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->assertDatabaseCount('penggarap_lahans', 0);
    }

    public function test_invalid_upload_is_rejected_without_creating_a_bidang(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $payload = $this->validPayload();
        $payload['foto'] = UploadedFile::fake()->create('catatan.txt', 1, 'text/plain');
        $payload['latitude'] = -100;
        $payload['luas_garapan'] = 0;

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/sensus', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'foto', 'latitude', 'luas_garapan',
        ]);
        $this->assertDatabaseCount('penggarap_lahans', 0);
    }

    public function test_dashboard_and_photo_are_scoped_to_the_recording_petugas(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->post('/sensus', $this->validPayload())->assertCreated();
        $record = PenggarapLahan::firstOrFail();

        $this->actingAs($otherUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('summary.total', 0)
                ->has('records', 0));
        $this->get("/sensus/{$record->id}/foto")->assertNotFound();
        $this->actingAs($owner)
            ->get("/sensus/{$record->id}/foto")
            ->assertOk();
    }

    public function test_dashboard_loads_bounded_pages_with_a_cursor_and_keeps_full_summary(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->insertDashboardRecords($owner, 35);
        $this->insertDashboardRecords($otherUser, 2);

        $firstPage = $this->actingAs($owner)->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('records', 30)
                ->where('summary.total', 35)
                ->where('summary.luas', 35));
        $firstIds = array_column($firstPage->viewData('page')['props']['records'], 'id');
        $cursor = $firstPage->viewData('page')['props']['next_cursor'];
        $this->assertNotNull($cursor);

        $secondPage = $this->getJson(route('dashboard.records', ['cursor' => $cursor]))
            ->assertOk()
            ->assertJsonCount(5, 'records')
            ->assertJsonPath('summary.total', 35)
            ->assertJsonPath('next_cursor', null);
        $secondIds = array_column($secondPage->json('records'), 'id');
        $this->assertCount(35, array_unique([...$firstIds, ...$secondIds]));
    }

    public function test_dashboard_search_finds_records_beyond_the_first_page_and_remains_private(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->insertDashboardRecords($owner, 35);
        $oldestId = DB::table('penggarap_lahans')->where('created_by', $owner->id)->min('id');
        DB::table('penggarap_lahans')->where('id', $oldestId)->update([
            'nama' => 'Target Terlama',
            'no_hp' => '081777000',
            'longitude' => 112.793841,
        ]);
        $this->insertDashboardRecords($otherUser, 1, 'Rahasia Pengguna Lain');

        $this->getJson('/dashboard/records?search=Penggarap%201')
            ->assertUnauthorized();

        $this->actingAs($owner)
            ->getJson('/dashboard/records?search=Target%20Terlama')
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.nama', 'Target Terlama');
        $this->getJson('/dashboard/records?search=Rahasia')
            ->assertOk()
            ->assertJsonCount(0, 'records');
        $this->getJson('/dashboard/records?search=081777000')
            ->assertOk()
            ->assertJsonPath('records.0.id', $oldestId);
        $this->getJson('/dashboard/records?search=112.793')
            ->assertOk()
            ->assertJsonPath('records.0.id', $oldestId);
    }

    public function test_guest_cannot_upload_a_bidang(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/sensus', $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_owner_can_update_penggarap_data_without_replacing_photo_or_creating_another_bidang(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->post('/sensus', $this->validPayload())->assertCreated();
        $record = PenggarapLahan::firstOrFail();
        $photoPath = $record->foto_path;

        $response = $this->patch("/sensus/{$record->id}", [
            'nama' => 'Siti Rahma',
            'no_hp' => '081234567890',
            'luas_garapan' => 1.25,
            'lama_menggarap' => 9,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.nama', 'Siti Rahma')
            ->assertJsonPath('data.no_hp', '081234567890');
        $this->assertDatabaseCount('penggarap_lahans', 1);
        $this->assertDatabaseHas('penggarap_lahans', [
            'id' => $record->id,
            'created_by' => $owner->id,
            'nama' => 'Siti Rahma',
            'luas_garapan' => 1.25,
            'foto_path' => $photoPath,
        ]);
        Storage::disk('local')->assertExists($photoPath);
    }

    public function test_other_petugas_cannot_update_a_bidang(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->post('/sensus', $this->validPayload())->assertCreated();
        $record = PenggarapLahan::firstOrFail();

        $this->actingAs($otherUser)
            ->patch("/sensus/{$record->id}", [
                'nama' => 'Diubah orang lain',
                'no_hp' => null,
                'luas_garapan' => 2,
                'lama_menggarap' => 3,
            ])
            ->assertNotFound();

        $this->assertSame('Siti Aminah', $record->fresh()->nama);
    }

    public function test_invalid_update_does_not_change_a_bidang(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->post('/sensus', $this->validPayload())->assertCreated();
        $record = PenggarapLahan::firstOrFail();

        $this->patch("/sensus/{$record->id}", [
            'nama' => '',
            'no_hp' => null,
            'luas_garapan' => 0,
            'lama_menggarap' => -1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['nama', 'luas_garapan', 'lama_menggarap']);

        $this->assertSame('Siti Aminah', $record->fresh()->nama);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'client_uuid' => '29a24968-3e37-4444-a6b4-7a7b2f3ce016',
            'nama' => 'Siti Aminah',
            'no_hp' => null,
            'luas_garapan' => 0.75,
            'lama_menggarap' => 8,
            'foto' => UploadedFile::fake()->image('lahan.jpg'),
            'latitude' => -7.289124,
            'longitude' => 112.793841,
            'gps_accuracy_m' => 4.5,
            'captured_at' => '2026-10-08T09:15:00+07:00',
        ];
    }

    private function insertDashboardRecords(User $owner, int $count, ?string $name = null): void
    {
        $rows = [];

        for ($number = 1; $number <= $count; $number++) {
            $rows[] = [
                'client_uuid' => sprintf('20000000-0000-4000-8000-%012d', $owner->id * 1000 + $number),
                'nama' => $name ?? "Penggarap {$number}",
                'luas_garapan' => 1,
                'lama_menggarap' => 1,
                'foto_path' => 'sensus/test.png',
                'latitude' => -7,
                'longitude' => 112,
                'captured_at' => now()->subDays($number),
                'created_by' => $owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('penggarap_lahans')->insert($rows);
    }
}
