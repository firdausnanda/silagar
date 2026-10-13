<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportSensusRequest;
use App\Models\PenggarapLahan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SensusImportController extends Controller
{
    private const HEADERS = [
        'nama' => 'Nama penggarap',
        'no_hp' => 'Nomor HP',
        'luas_garapan' => 'Luas garapan (Ha)',
        'lama_menggarap' => 'Lama menggarap (tahun)',
    ];

    private const OPTIONAL_HEADERS = [
        'nama_kth' => 'Nama KTH',
        'kabupaten_kota' => 'Kabupaten/Kota',
        'nama_penunjuk' => 'Nama Penunjuk',
    ];

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $workbook = new Spreadsheet;
            $sheet = $workbook->getSheet(0);
            $sheet->setTitle('Data bidang');

            foreach ([...array_values(self::HEADERS), ...array_values(self::OPTIONAL_HEADERS)] as $index => $heading) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $heading, DataType::TYPE_STRING);
            }

            $sheet->getStyle('A1:G1')->getFont()->setBold(true);
            $sheet->getColumnDimension('A')->setWidth(28);
            $sheet->getColumnDimension('B')->setWidth(20);
            $sheet->getColumnDimension('C')->setWidth(22);
            $sheet->getColumnDimension('D')->setWidth(28);
            $sheet->getColumnDimension('E')->setWidth(28);
            $sheet->getColumnDimension('F')->setWidth(28);
            $sheet->getColumnDimension('G')->setWidth(28);
            $sheet->freezePane('A2');

            try {
                (new XlsxWriter($workbook))->save('php://output');
            } finally {
                $workbook->disconnectWorksheets();
            }
        }, 'template-impor-sensus.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(ImportSensusRequest $request): JsonResponse
    {
        $path = $request->file('file')->getPathname();
        $reader = new XlsxReader;
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        try {
            $sheets = $reader->listWorksheetInfo($path);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak dapat dibaca. Gunakan file .xlsx yang valid.']);
        }

        if ($sheets === [] || $sheets[0]['totalRows'] < 2) {
            throw ValidationException::withMessages(['file' => 'File Excel belum berisi data bidang.']);
        }
        if ($sheets[0]['totalRows'] > 1001 || $sheets[0]['totalColumns'] > 30) {
            throw ValidationException::withMessages(['file' => 'Maksimal 1.000 baris data dan 30 kolom per file.']);
        }

        $reader->setLoadSheetsOnly([$sheets[0]['worksheetName']]);
        try {
            $workbook = $reader->load($path);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak dapat dibaca. Gunakan file .xlsx yang valid.']);
        }

        try {
            $sheet = $workbook->getSheet(0);
            $columns = [];
            foreach (self::HEADERS as $field => $heading) {
                for ($columnIndex = 1; $columnIndex <= $sheets[0]['totalColumns']; $columnIndex++) {
                    $value = $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex).'1')->getValue();
                    if (is_string($value) && mb_strtolower(trim($value)) === mb_strtolower($heading)) {
                        $columns[$field] = $columnIndex;
                        break;
                    }
                }
            }

            if (count($columns) !== count(self::HEADERS)) {
                throw ValidationException::withMessages([
                    'file' => 'Baris pertama harus memuat: '.implode(', ', self::HEADERS).'.',
                ]);
            }

            foreach (self::OPTIONAL_HEADERS as $field => $heading) {
                for ($columnIndex = 1; $columnIndex <= $sheets[0]['totalColumns']; $columnIndex++) {
                    $value = $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex).'1')->getValue();
                    if (is_string($value) && mb_strtolower(trim($value)) === mb_strtolower($heading)) {
                        $columns[$field] = $columnIndex;
                        break;
                    }
                }
            }

            $rows = [];
            for ($rowNumber = 2; $rowNumber <= $sheets[0]['totalRows']; $rowNumber++) {
                $values = [];
                foreach ($columns as $field => $columnIndex) {
                    $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex).$rowNumber);
                    if ($field === 'no_hp' && $cell->getValue() !== null && $cell->getValue() !== ''
                        && $cell->getDataType() !== DataType::TYPE_STRING) {
                        throw ValidationException::withMessages([
                            'file' => "Baris {$rowNumber}: Nomor HP harus berformat teks agar angka nol di depan tidak hilang.",
                        ]);
                    }
                    $values[$field] = trim((string) ($cell->getValue() ?? ''));
                }

                if (count(array_filter($values, fn (string $value): bool => $value !== '')) === 0) {
                    continue;
                }

                $validator = Validator::make($values, [
                    'nama' => ['required', 'string', 'max:100'],
                    'nama_kth' => ['nullable', 'string', 'max:100'],
                    'nama_penunjuk' => ['nullable', 'string', 'max:100'],
                    'kabupaten_kota' => ['nullable', 'string', 'max:100'],
                    'no_hp' => ['nullable', 'string', 'max:20'],
                    'luas_garapan' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
                    'lama_menggarap' => ['required', 'integer', 'min:0', 'max:150'],
                ], [], [...self::HEADERS, ...self::OPTIONAL_HEADERS]);
                if ($validator->fails()) {
                    throw ValidationException::withMessages([
                        'file' => "Baris {$rowNumber}: ".$validator->errors()->first(),
                    ]);
                }

                $rows[] = $validator->validated();
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['file' => 'File Excel belum berisi data bidang.']);
            }
        } finally {
            $workbook->disconnectWorksheets();
        }

        DB::transaction(function () use ($request, $rows): void {
            foreach ($rows as $row) {
                $record = new PenggarapLahan([
                    ...$row,
                    'nama_kth' => ($row['nama_kth'] ?? '') ?: null,
                    'nama_penunjuk' => ($row['nama_penunjuk'] ?? '') ?: null,
                    'kabupaten_kota' => ($row['kabupaten_kota'] ?? '') ?: null,
                    'no_hp' => $row['no_hp'] ?: null,
                    'client_uuid' => (string) Str::uuid(),
                ]);
                $record->created_by = $request->user()->id;
                $record->save();
            }
        });

        return response()->json(['imported' => count($rows)], 201)
            ->header('Cache-Control', 'private, no-store');
    }
}
