<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PenggarapLahan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $search = trim($filters['search'] ?? '');
        $userId = $filters['user_id'] ?? null;

        $records = PenggarapLahan::query()
            ->with('creator:id,name')
            ->when($userId, fn ($query) => $query->where('created_by', $userId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('nama', 'like', '%'.$search.'%')
                        ->orWhere('no_hp', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PenggarapLahan $record): array => [
                'id' => $record->id,
                'nama' => $record->nama,
                'no_hp' => $record->no_hp,
                'luas_garapan' => (float) $record->luas_garapan,
                'latitude' => (float) $record->latitude,
                'longitude' => (float) $record->longitude,
                'captured_at' => $record->captured_at?->toIso8601String(),
                'creator_name' => $record->creator?->name ?? 'Pengguna tidak tersedia',
                'foto_url' => route('admin.sensus.photo', $record->id, false),
            ]);

        $summary = DB::table('penggarap_lahans')
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(luas_garapan), 0) AS luas')
            ->first();

        return Inertia::render('Admin/Dashboard', [
            'records' => $records,
            'summary' => [
                'total' => (int) $summary->total,
                'luas' => round((float) $summary->luas, 2),
                'active_users' => User::query()->where('is_active', true)
                    ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'admin'))
                    ->count(),
            ],
            'recorders' => User::query()->select(['id', 'name'])
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'admin'))
                ->orderBy('name')->get(),
            'filters' => ['search' => $search, 'user_id' => $userId],
        ]);
    }
}
