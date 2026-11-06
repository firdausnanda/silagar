<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportSensusRequest;
use App\Models\PenggarapLahan;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SensusExportController extends Controller
{
    private const COLUMN_LABELS = [
        'id' => 'ID bidang',
        'nama' => 'Nama penggarap',
        'nama_kth' => 'Nama KTH',
        'kabupaten_kota' => 'Kabupaten/Kota',
        'nama_penunjuk' => 'Nama Penunjuk',
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
        'foto_url' => 'Link foto',
    ];

    public function __invoke(ExportSensusRequest $request): StreamedResponse
    {
        $data = $request->validated();
        $isAdminExport = $request->routeIs('admin.sensus.export');
        $query = PenggarapLahan::query()
            ->with('creator:id,name')
            ->whereIn('id', $data['ids'])
            ->when($isAdminExport && $data['scope'] === 'user', fn ($query) => $query->where('created_by', $data['user_id']))
            ->when(! $isAdminExport, fn ($query) => $query->where('created_by', $request->user()->id))
            ->orderByDesc('captured_at')
            ->orderByDesc('id');

        $records = $query->get();

        if ($records->count() !== count($data['ids'])) {
            throw ValidationException::withMessages([
                'ids' => 'Sebagian bidang tidak tersedia dalam cakupan pengguna yang dipilih. Muat ulang daftar dan pilih kembali.',
            ]);
        }

        $columns = array_values($data['columns']);
        $photoRouteName = $isAdminExport ? 'admin.sensus.photo' : 'sensus.photo';
        $filename = 'sipintar-hut-'.now('Asia/Jakarta')->format('Ymd-His').'.xlsx';

        // OpenSpout writes rows straight to a temporary file instead of building the whole
        // workbook in memory, so memory use stays flat regardless of the number of rows.
        return response()->streamDownload(function () use ($records, $columns, $photoRouteName): void {
            // Shared strings (not inline) keep cells as plain text for every spreadsheet reader.
            $writer = new Writer(new Options(SHOULD_USE_INLINE_STRINGS: false));
            $writer->openToFile('php://output');

            try {
                $sheet = $writer->getCurrentSheet();
                $sheet->setName('Data sensus');
                $sheet->setSheetView(new SheetView(freezeRow: 2));

                $headerStyle = (new Style)->withFontBold(true);
                $writer->addRow(new Row(array_map(
                    fn (string $column): StringCell => new StringCell(self::COLUMN_LABELS[$column], $headerStyle),
                    $columns,
                )));

                foreach ($records as $record) {
                    $writer->addRow(new Row(array_map(
                        fn (string $column): Cell => $this->cell($this->columnValue($record, $column, $photoRouteName), $column),
                        $columns,
                    )));
                }
            } finally {
                $writer->close();
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Strings are always written as text cells (never formulas), so values such as "=2+2" or phone numbers stay literal.
     */
    private function cell(string|float|int|null $value, string $column): Cell
    {
        return match (true) {
            is_string($value) => new StringCell($value, null, null, $column === 'foto_url' ? $value : null),
            $value === null => new EmptyCell(null),
            default => new NumericCell($value),
        };
    }

    private function columnValue(PenggarapLahan $record, string $column, string $photoRouteName): string|float|int|null
    {
        return match ($column) {
            'id' => $record->id,
            'nama' => $record->nama,
            'nama_kth' => $record->nama_kth ?? '',
            'nama_penunjuk' => $record->nama_penunjuk ?? '',
            'kabupaten_kota' => $record->kabupaten_kota ?? '',
            'no_hp' => $record->no_hp ?? '',
            'luas_garapan' => (float) $record->luas_garapan,
            'lama_menggarap' => $record->lama_menggarap,
            'latitude' => $record->latitude === null ? null : (float) $record->latitude,
            'longitude' => $record->longitude === null ? null : (float) $record->longitude,
            'utm_x' => $record->utm_x,
            'utm_y' => $record->utm_y,
            'utm_epsg' => $record->utm_epsg,
            'gps_accuracy_m' => $record->gps_accuracy_m === null ? null : (float) $record->gps_accuracy_m,
            'captured_at' => $record->captured_at === null ? null : $record->captured_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            'updated_at' => $record->updated_at === null ? null : $record->updated_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            'creator_name' => $record->creator?->name ?? 'Pengguna tidak tersedia',
            'foto_url' => $record->foto_path === null ? null : route($photoRouteName, $record->id),
        };
    }
}
