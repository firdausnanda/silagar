<?php

namespace App\Http\Controllers;

use App\Models\PenggarapLahan;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SensusExportChoicesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $records = PenggarapLahan::query()
            ->select(['id', 'nama', 'no_hp', 'latitude', 'longitude', 'captured_at', 'created_by'])
            ->with('creator:id,name')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PenggarapLahan $record): array => [
                'id' => $record->id,
                'nama' => $record->nama,
                'no_hp' => $record->no_hp,
                'latitude' => (float) $record->latitude,
                'longitude' => (float) $record->longitude,
                'captured_at' => $record->captured_at?->toIso8601String(),
                'created_by' => $record->created_by,
                'creator_name' => $record->creator?->name ?? 'Pengguna tidak tersedia',
                'status' => 'synced',
            ]);

        return response()->json([
            'users' => $users,
            'records' => $records,
        ])->header('Cache-Control', 'private, no-store');
    }
}
