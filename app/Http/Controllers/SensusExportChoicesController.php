<?php

namespace App\Http\Controllers;

use App\Models\PenggarapLahan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SensusExportChoicesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $records = PenggarapLahan::query()
            ->select(['id', 'nama', 'nama_kth', 'no_hp', 'latitude', 'longitude', 'captured_at', 'created_by'])
            ->where('created_by', $request->user()->id)
            ->with('creator:id,name')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PenggarapLahan $record): array => [
                'id' => $record->id,
                'nama' => $record->nama,
                'nama_kth' => $record->nama_kth,
                'no_hp' => $record->no_hp,
                'latitude' => $record->latitude === null ? null : (float) $record->latitude,
                'longitude' => $record->longitude === null ? null : (float) $record->longitude,
                'captured_at' => $record->captured_at?->toIso8601String(),
                'created_by' => $record->created_by,
                'creator_name' => $record->creator?->name ?? 'Pengguna tidak tersedia',
                'status' => 'synced',
            ]);

        return response()->json([
            'users' => [],
            'records' => $records,
        ])->header('Cache-Control', 'private, no-store');
    }
}
