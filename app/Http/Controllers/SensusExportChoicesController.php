<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SensusExportChoicesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $isAdminExport = $request->routeIs('admin.sensus.export.choices');

        // Plain rows with a join instead of hydrated models plus an eager-loaded relation:
        // this list can cover the whole table, so it should stay light on memory.
        $records = DB::table('penggarap_lahans')
            ->leftJoin('users', 'users.id', '=', 'penggarap_lahans.created_by')
            ->select([
                'penggarap_lahans.id', 'penggarap_lahans.nama', 'penggarap_lahans.nama_kth', 'penggarap_lahans.nama_penunjuk',
                'penggarap_lahans.kabupaten_kota', 'penggarap_lahans.no_hp', 'penggarap_lahans.latitude', 'penggarap_lahans.longitude',
                'penggarap_lahans.captured_at', 'penggarap_lahans.created_by', 'users.name as creator_name',
            ])
            ->when(! $isAdminExport, fn ($query) => $query->where('penggarap_lahans.created_by', $request->user()->id))
            ->orderByDesc('penggarap_lahans.captured_at')
            ->orderByDesc('penggarap_lahans.id')
            ->get()
            ->map(fn (object $record): array => [
                'id' => (int) $record->id,
                'nama' => $record->nama,
                'nama_kth' => $record->nama_kth,
                'nama_penunjuk' => $record->nama_penunjuk,
                'kabupaten_kota' => $record->kabupaten_kota,
                'no_hp' => $record->no_hp,
                'latitude' => $record->latitude === null ? null : (float) $record->latitude,
                'longitude' => $record->longitude === null ? null : (float) $record->longitude,
                'captured_at' => $record->captured_at === null
                    ? null
                    : CarbonImmutable::parse($record->captured_at, config('app.timezone'))->toIso8601String(),
                'created_by' => $record->created_by === null ? null : (int) $record->created_by,
                'creator_name' => $record->creator_name ?? 'Pengguna tidak tersedia',
                'status' => 'synced',
            ]);

        return response()->json([
            'users' => $isAdminExport ? $records->unique('created_by')->map(fn (array $record): array => [
                'id' => $record['created_by'],
                'name' => $record['creator_name'],
            ])->sortBy('name')->values() : [],
            'records' => $records,
        ])->header('Cache-Control', 'private, no-store');
    }
}
