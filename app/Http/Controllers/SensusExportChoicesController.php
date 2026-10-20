<?php

namespace App\Http\Controllers;

use App\Models\PenggarapLahan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SensusExportChoicesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $isAdminExport = $request->routeIs('admin.sensus.export.choices');
        $records = PenggarapLahan::query()
            ->select(['id', 'nama', 'nama_kth', 'nama_penunjuk', 'kabupaten_kota', 'no_hp', 'latitude', 'longitude', 'captured_at', 'created_by'])
            ->when(! $isAdminExport, fn ($query) => $query->where('created_by', $request->user()->id))
            ->with('creator:id,name')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PenggarapLahan $record): array => [
                'id' => $record->id,
                'nama' => $record->nama,
                'nama_kth' => $record->nama_kth,
                'nama_penunjuk' => $record->nama_penunjuk,
                'kabupaten_kota' => $record->kabupaten_kota,
                'no_hp' => $record->no_hp,
                'latitude' => $record->latitude === null ? null : (float) $record->latitude,
                'longitude' => $record->longitude === null ? null : (float) $record->longitude,
                'captured_at' => $record->captured_at?->toIso8601String(),
                'created_by' => $record->created_by,
                'creator_name' => $record->creator?->name ?? 'Pengguna tidak tersedia',
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
