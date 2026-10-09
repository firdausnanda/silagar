<?php

namespace Tests\Feature;

use App\Models\PenggarapLahan;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SensusLahanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google_drive.refresh_token', null);
        config()->set('services.google_drive.folder', null);
    }

    public function test_new_photo_uses_connected_drive_and_remains_private_to_its_owner_and_admin(): void
    {
        Storage::fake('local');
        $this->configureDrivePhotoConnection();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Http::preventStrayRequests();
        Http::fake($this->drivePhotoResponses());

        $this->actingAs($owner)->postJson('/sensus', $this->validPayload())->assertCreated();
        $record = PenggarapLahan::firstOrFail();
        $this->assertSame('google_drive', $record->foto_storage);
        $this->assertSame('photo-file-id', $record->foto_path);
        Storage::disk('local')->assertMissing($record->foto_path);

        $this->actingAs($otherUser)->get(route('sensus.photo', $record->id))->assertNotFound();
        $this->actingAs($owner)->get(route('sensus.photo', $record->id))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertStreamedContent('image-content');
        $this->actingAs($admin)->get(route('admin.sensus.photo', $record->id))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertStreamedContent('image-content');
    }

    public function test_drive_upload_failure_does_not_create_a_bidang(): void
    {
        Storage::fake('local');
        $this->configureDrivePhotoConnection();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'https://www.googleapis.com/drive/v3/files/folder-id*' => Http::response([
                'mimeType' => 'application/vnd.google-apps.folder',
                'capabilities' => ['canAddChildren' => true],
            ]),
            'https://www.googleapis.com/drive/v3/files?*' => function ($request) {
                return $this->existingDrivePhotoFolder($request);
            },
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable*' => Http::response([], 403),
        ]);

        $this->actingAs(User::factory()->create())->postJson('/sensus', $this->validPayload())
            ->assertInternalServerError();

        $this->assertDatabaseCount('penggarap_lahans', 0);
    }

    public function test_drive_creates_one_photo_folder_per_petugas_and_reuses_it(): void
    {
        $this->configureDrivePhotoConnection();
        $firstUser = User::factory()->create(['name' => 'Petugas Sama']);
        $secondUser = User::factory()->create(['name' => 'Petugas Sama']);
        $folders = [];
        $createdFolders = [];
        $renamedFolders = [];
        $uploadParents = [];
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'https://www.googleapis.com/drive/v3/files/folder-*' => function (Request $request) use (&$renamedFolders) {
                if ($request->method() === 'GET') {
                    return Http::response([
                        'mimeType' => 'application/vnd.google-apps.folder',
                        'capabilities' => ['canAddChildren' => true],
                    ]);
                }

                $renamedFolders[$request->url()] = $request->data()['name'];

                return Http::response(['id' => basename($request->url())]);
            },
            'https://www.googleapis.com/drive/v3/files?*' => function (Request $request) use (&$folders, &$createdFolders) {
                if ($request->method() === 'POST') {
                    $data = $request->data();
                    $marker = $data['appProperties']['sensus_folder'];
                    $id = $marker === 'root' ? 'photo-root-id' : 'folder-'.substr($marker, 5);
                    $folders[$marker] = ['id' => $id, 'name' => $data['name']];
                    $createdFolders[$marker] = $data;

                    return Http::response(['id' => $id]);
                }

                preg_match("/value='([^']+)'/", $request->data()['q'] ?? '', $matches);

                return Http::response(['files' => isset($folders[$matches[1] ?? ''])
                    ? [$folders[$matches[1]]]
                    : []]);
            },
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable*' => function (Request $request) use (&$uploadParents) {
                $uploadParents[] = $request->data()['parents'];

                return Http::response([], 200, [
                    'Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=photo-123',
                ]);
            },
            'https://www.googleapis.com/upload/drive/v3/files?upload_id=photo-123' => Http::response(['id' => 'photo-file-id']),
        ]);

        $this->actingAs($firstUser)->postJson('/sensus', $this->validPayload())->assertCreated();
        $secondPayload = $this->validPayload();
        $secondPayload['client_uuid'] = '11223344-0000-4000-8000-000000000002';
        $this->postJson('/sensus', $secondPayload)->assertCreated();
        $firstUser->update(['name' => 'Petugas Baru']);
        $renamedPayload = $this->validPayload();
        $renamedPayload['client_uuid'] = '11223344-0000-4000-8000-000000000004';
        $this->postJson('/sensus', $renamedPayload)->assertCreated();
        $thirdPayload = $this->validPayload();
        $thirdPayload['client_uuid'] = '11223344-0000-4000-8000-000000000003';
        $this->actingAs($secondUser)->postJson('/sensus', $thirdPayload)->assertCreated();

        $this->assertCount(3, $createdFolders);
        $this->assertSame(['folder-id'], $createdFolders['root']['parents']);
        $this->assertSame(['photo-root-id'], $createdFolders['user:'.$firstUser->id]['parents']);
        $this->assertSame('Petugas - Petugas Sama (ID '.$firstUser->id.')', $createdFolders['user:'.$firstUser->id]['name']);
        $this->assertSame('Petugas - Petugas Sama (ID '.$secondUser->id.')', $createdFolders['user:'.$secondUser->id]['name']);
        $this->assertSame([
            'https://www.googleapis.com/drive/v3/files/folder-'.$firstUser->id => 'Petugas - Petugas Baru (ID '.$firstUser->id.')',
        ], $renamedFolders);
        $this->assertSame([
            ['folder-'.$firstUser->id],
            ['folder-'.$firstUser->id],
            ['folder-'.$firstUser->id],
            ['folder-'.$secondUser->id],
        ], $uploadParents);
    }

    public function test_drive_folder_failure_stops_the_photo_upload_and_record_creation(): void
    {
        $this->configureDrivePhotoConnection();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'https://www.googleapis.com/drive/v3/files/folder-id*' => Http::response([
                'mimeType' => 'application/vnd.google-apps.folder',
                'capabilities' => ['canAddChildren' => true],
            ]),
            'https://www.googleapis.com/drive/v3/files?*' => Http::response([], 403),
        ]);

        $this->actingAs(User::factory()->create())->postJson('/sensus', $this->validPayload())
            ->assertInternalServerError();

        $this->assertDatabaseCount('penggarap_lahans', 0);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/upload/drive/'));
    }

    public function test_deleting_drive_photo_removes_the_remote_file(): void
    {
        $this->configureDrivePhotoConnection();
        Http::preventStrayRequests();
        Http::fake($this->drivePhotoResponses());
        $owner = User::factory()->create();

        $this->actingAs($owner)->postJson('/sensus', $this->validPayload())->assertCreated();
        $record = PenggarapLahan::firstOrFail();
        $this->deleteJson(route('sensus.destroy', $record->id))->assertNoContent();

        $this->assertDatabaseMissing('penggarap_lahans', ['id' => $record->id]);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'https://www.googleapis.com/drive/v3/files/photo-file-id');
    }

    public function test_existing_local_photo_remains_readable_after_drive_is_connected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/sensus', $this->validPayload())->assertCreated();
        $record = PenggarapLahan::firstOrFail();
        $this->assertSame('local', $record->foto_storage);

        $this->configureDrivePhotoConnection();
        Http::preventStrayRequests();

        $this->get(route('sensus.photo', $record->id))->assertOk();
        Storage::disk('local')->assertExists($record->foto_path);
        Http::assertNothingSent();
    }

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

    public function test_owner_can_delete_a_bidang_and_its_photo(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/sensus', $this->validPayload())->assertCreated();
        $record = PenggarapLahan::firstOrFail();
        $photoPath = $record->foto_path;

        $this->deleteJson(route('sensus.destroy', $record->id))->assertNoContent();

        $this->assertDatabaseMissing('penggarap_lahans', ['id' => $record->id]);
        Storage::disk('local')->assertMissing($photoPath);
        $this->getJson(route('dashboard.records'))->assertJsonPath('summary.total', 0);
    }

    public function test_other_petugas_cannot_delete_a_bidang(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($owner)->postJson('/sensus', $this->validPayload())->assertCreated();
        $record = PenggarapLahan::firstOrFail();

        $this->actingAs($otherUser)
            ->deleteJson(route('sensus.destroy', $record->id))
            ->assertNotFound();

        $this->assertDatabaseHas('penggarap_lahans', ['id' => $record->id]);
        Storage::disk('local')->assertExists($record->foto_path);
    }

    public function test_guest_cannot_delete_a_bidang(): void
    {
        $this->deleteJson(route('sensus.destroy', 1))->assertUnauthorized();
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

    private function configureDrivePhotoConnection(): void
    {
        config()->set('services.google_drive.client_id', 'client-id');
        config()->set('services.google_drive.client_secret', 'client-secret');
        config()->set('services.google_drive.refresh_token', 'refresh-token');
        config()->set('services.google_drive.folder', 'folder-id');
    }

    /** @return array<string, mixed> */
    private function drivePhotoResponses(): array
    {
        return [
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'https://www.googleapis.com/drive/v3/files/folder-id*' => Http::response([
                'mimeType' => 'application/vnd.google-apps.folder',
                'capabilities' => ['canAddChildren' => true],
            ]),
            'https://www.googleapis.com/drive/v3/files?*' => function ($request) {
                return $this->existingDrivePhotoFolder($request);
            },
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable*' => Http::response([], 200, [
                'Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=photo-123',
            ]),
            'https://www.googleapis.com/upload/drive/v3/files?upload_id=photo-123' => Http::response(['id' => 'photo-file-id']),
            'https://www.googleapis.com/drive/v3/files/photo-file-id*' => function ($request) {
                return $request->method() === 'DELETE'
                    ? Http::response('', 204)
                    : Http::response('image-content', 200, ['Content-Type' => 'image/jpeg']);
            },
        ];
    }

    private function existingDrivePhotoFolder(Request $request): PromiseInterface
    {
        $query = $request->data()['q'] ?? '';

        return Http::response([
            'files' => [['id' => str_contains($query, "value='root'") ? 'photo-root-id' : 'petugas-folder-id']],
        ]);
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
