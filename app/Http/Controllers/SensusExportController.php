<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportSensusRequest;
use App\Models\PenggarapLahan;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SensusExportController extends Controller
{
    private const COLUMN_LABELS = [
        'id' => 'ID bidang',
        'nama' => 'Nama penggarap',
        'no_hp' => 'Nomor HP',
        'luas_garapan' => 'Luas garapan (Ha)',
        'lama_menggarap' => 'Lama menggarap (tahun)',
        'latitude' => 'Lintang',
        'longitude' => 'Bujur',
        'utm_x' => 'UTM X (m)',
        'utm_y' => 'UTM Y (m)',
        'utm_epsg' => 'EPSG UTM',
        'gps_accuracy_m' => 'Akurasi GPS (m)',
        'captured_at' => 'Waktu pencatatan (WIB)',
        'updated_at' => 'Waktu pembaruan (WIB)',
        'creator_name' => 'Petugas pencatat',
    ];

    public function __invoke(ExportSensusRequest $request): StreamedResponse
    {
        $data = $request->validated();
        $scope = $data['scope'] ?? 'mine';
        $query = PenggarapLahan::query()
            ->with('creator:id,name')
            ->whereIn('id', $data['ids'])
            ->orderByDesc('captured_at')
            ->orderByDesc('id');

        if ($scope === 'mine') {
            $query->where('created_by', $request->user()->id);
        } elseif ($scope === 'user') {
            $query->where('created_by', $data['user_id']);
        }

        $records = $query->get();

        if ($records->count() !== count($data['ids'])) {
            throw ValidationException::withMessages([
                'ids' => 'Sebagian bidang tidak tersedia dalam cakupan pengguna yang dipilih. Muat ulang daftar dan pilih kembali.',
            ]);
        }

        $columns = array_values($data['columns']);
        $filename = 'sipintar-hut-'.now('Asia/Jakarta')->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($records, $columns): void {
            $workbook = new Spreadsheet;
            $sheet = $workbook->getActiveSheet();
            $sheet->setTitle('Data sensus');

            foreach ($columns as $index => $column) {
                $address = Coordinate::stringFromColumnIndex($index + 1).'1';
                $sheet->setCellValueExplicit($address, self::COLUMN_LABELS[$column], DataType::TYPE_STRING);
            }

            foreach ($records as $rowIndex => $record) {
                foreach ($columns as $columnIndex => $column) {
                    $value = $this->columnValue($record, $column);
                    $address = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 2);

                    if (is_string($value)) {
                        $sheet->setCellValueExplicit($address, $value, DataType::TYPE_STRING);
                    } elseif ($value !== null) {
                        $sheet->setCellValue($address, $value);
                    }
                }
            }

            $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($columns)).'1')->getFont()->setBold(true);
            $sheet->freezePane('A2');

            try {
                (new Xlsx($workbook))->save('php://output');
            } finally {
                $workbook->disconnectWorksheets();
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function columnValue(PenggarapLahan $record, string $column): string|float|int|null
    {
        return match ($column) {
            'id' => $record->id,
            'nama' => $record->nama,
            'no_hp' => $record->no_hp ?? '',
            'luas_garapan' => (float) $record->luas_garapan,
            'lama_menggarap' => $record->lama_menggarap,
            'latitude' => (float) $record->latitude,
            'longitude' => (float) $record->longitude,
            'utm_x' => $record->utm_x,
            'utm_y' => $record->utm_y,
            'utm_epsg' => $record->utm_epsg,
            'gps_accuracy_m' => $record->gps_accuracy_m === null ? null : (float) $record->gps_accuracy_m,
            'captured_at' => $record->captured_at === null ? null : $record->captured_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            'updated_at' => $record->updated_at === null ? null : $record->updated_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            'creator_name' => $record->creator?->name ?? 'Pengguna tidak tersedia',
        };
    }
}
