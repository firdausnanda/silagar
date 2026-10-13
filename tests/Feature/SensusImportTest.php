<?php

namespace Tests\Feature;

use App\Models\PenggarapLahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SensusImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google_drive.refresh_token', null);
        config()->set('services.google_drive.folder', null);
    }

    public function test_petugas_can_import_excel_without_photo_or_coordinates_and_complete_it_later(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $file = $this->excelFile([
            ['Siti Aminah', '081234567890', 0.75, 8, 'KTH Tunas Hutan', 'Pak Hadi', 'Kabupaten Malang'],
            ['Budi', '', 1.25, 3],
        ]);

        $this->actingAs($owner)->post(route('sensus.import'), ['file' => $file], [
            'Accept' => 'application/json',
        ])->assertCreated()->assertJsonPath('imported', 2);

        $this->assertDatabaseCount('penggarap_lahans', 2);
        $record = PenggarapLahan::query()->where('nama', 'Siti Aminah')->firstOrFail();
        $this->assertSame($owner->id, $record->created_by);
        $this->assertSame('081234567890', $record->no_hp);
        $this->assertSame('KTH Tunas Hutan', $record->nama_kth);
        $this->assertSame('Pak Hadi', $record->nama_penunjuk);
        $this->assertSame('Kabupaten Malang', $record->kabupaten_kota);
        $this->assertNull($record->foto_path);
        $this->assertNull($record->latitude);
        $this->assertNull($record->longitude);
        $this->getJson(route('dashboard.records'))->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('records.1.foto_url', null)
            ->assertJsonPath('records.1.nama_kth', 'KTH Tunas Hutan')
            ->assertJsonPath('records.1.nama_penunjuk', 'Pak Hadi')
            ->assertJsonPath('records.1.kabupaten_kota', 'Kabupaten Malang')
            ->assertJsonPath('records.1.latitude', null);
        $this->get(route('sensus.photo', $record->id))->assertNotFound();

        $this->post(route('sensus.update', $record->id), [
            '_method' => 'PATCH',
            'nama' => 'Siti Aminah',
            'nama_kth' => 'KTH Tunas Hutan',
            'no_hp' => '081234567890',
            'luas_garapan' => 0.75,
            'lama_menggarap' => 8,
            'foto' => UploadedFile::fake()->image('lahan.jpg'),
            'latitude' => -7.289124,
            'longitude' => 112.793841,
            'gps_accuracy_m' => 4.5,
            'captured_at' => '2026-10-12T02:15:00Z',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.utm_epsg', 32749);

        $record->refresh();
        $this->assertNotNull($record->foto_path);
        $this->assertSame('-7.28912400', $record->latitude);
        Storage::disk('local')->assertExists($record->foto_path);
    }

    public function test_invalid_excel_row_rejects_the_whole_import(): void
    {
        $owner = User::factory()->create();
        $file = $this->excelFile([
            ['Siti Aminah', '081234567890', 0.75, 8],
            ['Budi', '', -1, 3],
        ]);

        $this->actingAs($owner)->post(route('sensus.import'), ['file' => $file], [
            'Accept' => 'application/json',
        ])->assertUnprocessable()->assertJsonValidationErrors('file')
            ->assertSee('Baris 3');

        $this->assertDatabaseCount('penggarap_lahans', 0);
    }

    public function test_numeric_phone_number_is_rejected_to_preserve_leading_zero(): void
    {
        $owner = User::factory()->create();
        $file = $this->excelFile([['Siti Aminah', 81234567890, 0.75, 8]]);

        $this->actingAs($owner)->post(route('sensus.import'), ['file' => $file], [
            'Accept' => 'application/json',
        ])->assertUnprocessable()->assertJsonValidationErrors('file')
            ->assertSee('Nomor HP harus berformat teks');

        $this->assertDatabaseCount('penggarap_lahans', 0);
    }

    public function test_guest_cannot_import_or_download_the_template(): void
    {
        $this->postJson(route('sensus.import'), [])->assertUnauthorized();
        $this->get(route('sensus.import.template'))->assertRedirect('/login');
    }

    public function test_template_has_the_required_headers(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->get(route('sensus.import.template'));

        $response->assertOk()->assertDownload('template-impor-sensus.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'sensus-template-');
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            $this->assertSame([
                'Nama penggarap', 'Nomor HP', 'Luas garapan (Ha)', 'Lama menggarap (tahun)',
                'Nama KTH', 'Nama Penunjuk', 'Kabupaten/Kota',
            ], $workbook->getSheet(0)->rangeToArray('A1:G1')[0]);
        } finally {
            if (isset($workbook)) {
                $workbook->disconnectWorksheets();
            }
            unlink($path);
        }
    }

    public function test_imported_records_remain_private_to_owner_and_safe_on_admin_dashboard(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $file = $this->excelFile([['Siti Aminah', '', 0.75, 8]]);
        $this->actingAs($owner)->post(route('sensus.import'), ['file' => $file], [
            'Accept' => 'application/json',
        ])->assertCreated();
        $record = PenggarapLahan::firstOrFail();

        $this->actingAs($otherUser)->getJson(route('dashboard.records'))
            ->assertOk()->assertJsonPath('summary.total', 0);
        $this->patchJson(route('sensus.update', $record->id), [
            'nama' => 'Diubah orang lain', 'luas_garapan' => 1, 'lama_menggarap' => 1,
        ])->assertNotFound();

        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('records.data.0.foto_url', null)
                ->where('records.data.0.latitude', null));
    }

    /** @param array<int, array{0: string, 1: string|int, 2: float|int, 3: int, 4?: string, 5?: string, 6?: string}> $rows */
    private function excelFile(array $rows): UploadedFile
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet();
        $sheet->fromArray([[
            'Nama penggarap', 'Nomor HP', 'Luas garapan (Ha)', 'Lama menggarap (tahun)',
        ]]);
        foreach (['Nama KTH', 'Nama Penunjuk', 'Kabupaten/Kota'] as $index => $heading) {
            if (array_filter($rows, fn (array $row): bool => isset($row[$index + 4]))) {
                $sheet->setCellValue(chr(69 + $index).'1', $heading);
            }
        }
        foreach ($rows as $index => $row) {
            $sheet->setCellValue('A'.($index + 2), $row[0]);
            $sheet->setCellValueExplicit('B'.($index + 2), (string) $row[1], is_int($row[1]) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            $sheet->setCellValue('C'.($index + 2), $row[2]);
            $sheet->setCellValue('D'.($index + 2), $row[3]);
            if (isset($row[4])) {
                $sheet->setCellValue('E'.($index + 2), $row[4]);
            }
            if (isset($row[5])) {
                $sheet->setCellValue('F'.($index + 2), $row[5]);
            }
            if (isset($row[6])) {
                $sheet->setCellValue('G'.($index + 2), $row[6]);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'sensus-import-');
        try {
            (new Xlsx($workbook))->save($path);

            return UploadedFile::fake()->createWithContent('bidang.xlsx', file_get_contents($path));
        } finally {
            $workbook->disconnectWorksheets();
            unlink($path);
        }
    }
}
