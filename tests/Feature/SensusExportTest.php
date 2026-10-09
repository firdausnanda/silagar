<?php

namespace Tests\Feature;

use App\Models\PenggarapLahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SensusExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_downloads_only_selected_rows_and_columns_as_xlsx(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $first = $this->createRecord($owner, '11111111-1111-4111-8111-111111111111', '=SUM(1,2)', '081234567890');
        $this->createRecord($owner, '22222222-2222-4222-8222-222222222222', 'Bidang lain', '089999999999');
        $first->update(['nama' => '=2+2']);

        $response = $this->actingAs($owner)->postJson(route('sensus.export'), [
            'ids' => [$first->id],
            'columns' => ['nama', 'no_hp', 'luas_garapan', 'captured_at'],
        ]);

        $response->assertOk()
            ->assertDownload()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'sensus-export-');
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();

            $this->assertSame(['Nama penggarap', 'Nomor HP', 'Luas garapan (Ha)', 'Waktu pencatatan (WIB)'], $sheet->rangeToArray('A1:D1')[0]);
            $this->assertSame('=2+2', $sheet->getCell('A2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
            $this->assertSame('081234567890', $sheet->getCell('B2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
            $this->assertSame(0.75, $sheet->getCell('C2')->getValue());
            $this->assertSame('08/10/2026 09:15 WIB', $sheet->getCell('D2')->getValue());
            $this->assertSame(2, $sheet->getHighestRow());
            $this->assertSame('D', $sheet->getHighestColumn());
        } finally {
            if (isset($workbook)) {
                $workbook->disconnectWorksheets();
            }
            unlink($path);
        }
    }

    public function test_guest_cannot_export_sensus(): void
    {
        $this->postJson('/sensus/export', [
            'ids' => [1],
            'columns' => ['nama'],
        ])->assertUnauthorized();
    }

    public function test_utm_coordinates_and_epsg_can_be_exported_as_numeric_columns(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $record = $this->createRecord($owner, '11111111-1111-4111-8111-111111111111', 'Bidang UTM', '081234567890');

        $response = $this->actingAs($owner)->postJson(route('sensus.export'), [
            'ids' => [$record->id],
            'columns' => ['utm_x', 'utm_y', 'utm_epsg'],
        ])->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'sensus-export-');
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();

            $this->assertSame(['UTM X (m)', 'UTM Y (m)', 'EPSG UTM'], $sheet->rangeToArray('A1:C1')[0]);
            $this->assertSame($record->utm_x, $sheet->getCell('A2')->getValue());
            $this->assertSame($record->utm_y, $sheet->getCell('B2')->getValue());
            $this->assertSame(32749, $sheet->getCell('C2')->getValue());
        } finally {
            if (isset($workbook)) {
                $workbook->disconnectWorksheets();
            }
            unlink($path);
        }
    }

    public function test_petugas_lists_only_own_export_choices(): void
    {
        $this->getJson('/sensus/export/choices')->assertUnauthorized();

        Storage::fake('local');
        $owner = User::factory()->create(['name' => 'Petugas A']);
        $other = User::factory()->create(['name' => 'Petugas B']);
        $ownRecord = $this->createRecord($owner, '11111111-1111-4111-8111-111111111111', 'Milik A', '08111');
        $this->createRecord($other, '22222222-2222-4222-8222-222222222222', 'Milik B', '08222');

        $response = $this->actingAs($owner)->getJson('/sensus/export/choices')
            ->assertOk()
            ->assertJsonCount(0, 'users')
            ->assertJsonCount(1, 'records')
            ->assertJsonFragment(['id' => $ownRecord->id, 'nama' => 'Milik A', 'created_by' => $owner->id, 'creator_name' => 'Petugas A', 'status' => 'synced']);

        $this->assertContains($ownRecord->id, array_column($response->json('records'), 'id'));
        $this->assertArrayNotHasKey('foto_path', $response->json('records.0'));
    }

    public function test_petugas_cannot_export_selected_records_from_all_users(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['name' => 'Petugas A']);
        $other = User::factory()->create(['name' => 'Petugas B']);
        $ownRecord = $this->createRecord($owner, '11111111-1111-4111-8111-111111111111', 'Milik A', '08111');
        $otherRecord = $this->createRecord($other, '22222222-2222-4222-8222-222222222222', 'Milik B', '08222');

        $this->actingAs($owner)->postJson('/sensus/export', [
            'ids' => [$ownRecord->id, $otherRecord->id],
            'columns' => ['nama', 'creator_name'],
            'scope' => 'all',
        ])->assertUnprocessable()->assertJsonValidationErrors(['scope']);
    }

    public function test_petugas_cannot_choose_another_recorder_or_include_a_foreign_record(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownRecord = $this->createRecord($owner, '11111111-1111-4111-8111-111111111111', 'Milik saya', '08111');
        $otherRecord = $this->createRecord($other, '22222222-2222-4222-8222-222222222222', 'Milik orang lain', '08222');

        $this->actingAs($owner)->postJson('/sensus/export', [
            'ids' => [$ownRecord->id, $otherRecord->id],
            'columns' => ['nama'],
            'scope' => 'user',
            'user_id' => $other->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['scope', 'user_id']);

        $this->postJson('/sensus/export', [
            'ids' => [$otherRecord->id],
            'columns' => ['nama'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ids']);
    }

    public function test_export_rejects_invalid_recorder_scope(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->postJson('/sensus/export', [
            'ids' => [1],
            'columns' => ['nama'],
            'scope' => 'anything',
        ])->assertUnprocessable()->assertJsonValidationErrors(['scope']);

        $this->postJson('/sensus/export', [
            'ids' => [1],
            'columns' => ['nama'],
            'scope' => 'mine',
            'user_id' => $owner->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['user_id']);

        $this->postJson('/sensus/export', [
            'ids' => [1],
            'columns' => ['nama'],
            'scope' => 'user',
            'user_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['scope', 'user_id']);
    }

    public function test_associative_column_keys_do_not_break_the_download(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $record = $this->createRecord($owner, '11111111-1111-4111-8111-111111111111', 'Siti', '08123');

        $response = $this->actingAs($owner)->postJson('/sensus/export', [
            'ids' => [$record->id],
            'columns' => ['chosen' => 'nama'],
        ])->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'sensus-export-');
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            $this->assertSame('Siti', $workbook->getActiveSheet()->getCell('A2')->getValue());
        } finally {
            if (isset($workbook)) {
                $workbook->disconnectWorksheets();
            }
            unlink($path);
        }
    }

    public function test_missing_capture_time_is_blank_in_excel(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $record = $this->createRecord($owner, '11111111-1111-4111-8111-111111111111', 'Siti', '08123');
        $record->update(['captured_at' => null]);

        $response = $this->actingAs($owner)->postJson('/sensus/export', [
            'ids' => [$record->id],
            'columns' => ['captured_at'],
        ])->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'sensus-export-');
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            $this->assertNull($workbook->getActiveSheet()->getCell('A2')->getValue());
        } finally {
            if (isset($workbook)) {
                $workbook->disconnectWorksheets();
            }
            unlink($path);
        }
    }

    public function test_owner_cannot_export_another_petugas_record(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownRecord = $this->createRecord($owner, '11111111-1111-4111-8111-111111111111', 'Milik saya', '08111');
        $foreignRecord = $this->createRecord($other, '22222222-2222-4222-8222-222222222222', 'Milik orang lain', '08222');

        $this->actingAs($owner)->postJson('/sensus/export', [
            'ids' => [$ownRecord->id, $foreignRecord->id],
            'columns' => ['nama'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ids']);
    }

    public function test_empty_and_unknown_selections_are_rejected(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->postJson('/sensus/export', [
            'ids' => [],
            'columns' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ids', 'columns']);

        $this->postJson('/sensus/export', [
            'ids' => [999999],
            'columns' => ['foto_path'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['columns.0']);

        $this->postJson('/sensus/export', [
            'ids' => [999999],
            'columns' => ['nama'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ids']);
    }

    public function test_export_rejects_more_than_one_thousand_records(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->postJson('/sensus/export', [
            'ids' => range(1, 1001),
            'columns' => ['nama'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ids']);

        $this->assertStringContainsString('1000', $response->json('errors.ids.0'));
    }

    private function createRecord(User $owner, string $uuid, string $name, string $phone): PenggarapLahan
    {
        $this->actingAs($owner)->postJson('/sensus', [
            'client_uuid' => $uuid,
            'nama' => $name,
            'no_hp' => $phone,
            'luas_garapan' => 0.75,
            'lama_menggarap' => 8,
            'foto' => UploadedFile::fake()->image('lahan.jpg'),
            'latitude' => -7.289124,
            'longitude' => 112.793841,
            'gps_accuracy_m' => 4.5,
            'captured_at' => '2026-10-08T02:15:00.000Z',
        ])->assertCreated();

        return PenggarapLahan::query()->where('client_uuid', $uuid)->firstOrFail();
    }
}
