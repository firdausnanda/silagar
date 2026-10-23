<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PenggarapLahan;
use App\Models\User;
use Carbon\CarbonImmutable;
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
            'kabupaten_kota' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'luas_min' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'luas_max' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'gte:luas_min'],
            'foto' => ['nullable', 'in:ada,tidak_ada'],
            'koordinat' => ['nullable', 'in:ada,tidak_ada'],
            'sort' => ['nullable', 'in:terbaru,terlama,luas_terbesar,luas_terkecil'],
        ]);
        $search = trim($filters['search'] ?? '');
        $userId = $filters['user_id'] ?? null;
        $kabupatenKota = trim($filters['kabupaten_kota'] ?? '');
        $sort = $filters['sort'] ?? 'terbaru';

        $records = PenggarapLahan::query()
            ->with('creator:id,name')
            ->when($userId, fn ($query) => $query->where('created_by', $userId))
            ->when($kabupatenKota !== '', fn ($query) => $query->where('kabupaten_kota', $kabupatenKota))
            ->when(isset($filters['date_from']), fn ($query) => $query->where('captured_at', '>=', CarbonImmutable::parse($filters['date_from'], 'Asia/Jakarta')->startOfDay()->utc()))
            ->when(isset($filters['date_to']), fn ($query) => $query->where('captured_at', '<=', CarbonImmutable::parse($filters['date_to'], 'Asia/Jakarta')->endOfDay()->utc()))
            ->when(isset($filters['luas_min']), fn ($query) => $query->where('luas_garapan', '>=', $filters['luas_min']))
            ->when(isset($filters['luas_max']), fn ($query) => $query->where('luas_garapan', '<=', $filters['luas_max']))
            ->when(($filters['foto'] ?? null) === 'ada', fn ($query) => $query->whereNotNull('foto_path'))
            ->when(($filters['foto'] ?? null) === 'tidak_ada', fn ($query) => $query->whereNull('foto_path'))
            ->when(($filters['koordinat'] ?? null) === 'ada', fn ($query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
            ->when(($filters['koordinat'] ?? null) === 'tidak_ada', fn ($query) => $query->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude')))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('nama', 'like', '%'.$search.'%')
                        ->orWhere('nama_kth', 'like', '%'.$search.'%')
                        ->orWhere('nama_penunjuk', 'like', '%'.$search.'%')
                        ->orWhere('kabupaten_kota', 'like', '%'.$search.'%')
                        ->orWhere('no_hp', 'like', '%'.$search.'%');
                });
            })
            ->when($sort === 'terlama', fn ($query) => $query->orderBy('id'))
            ->when($sort === 'luas_terbesar', fn ($query) => $query->orderByDesc('luas_garapan')->orderByDesc('id'))
            ->when($sort === 'luas_terkecil', fn ($query) => $query->orderBy('luas_garapan')->orderByDesc('id'))
            ->when($sort === 'terbaru', fn ($query) => $query->orderByDesc('id'))
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PenggarapLahan $record): array => [
                'id' => $record->id,
                'nama' => $record->nama,
                'nama_kth' => $record->nama_kth,
                'nama_penunjuk' => $record->nama_penunjuk,
                'kabupaten_kota' => $record->kabupaten_kota,
                'no_hp' => $record->no_hp,
                'luas_garapan' => (float) $record->luas_garapan,
                'lama_menggarap' => $record->lama_menggarap,
                'latitude' => $record->latitude === null ? null : (float) $record->latitude,
                'longitude' => $record->longitude === null ? null : (float) $record->longitude,
                'captured_at' => $record->captured_at?->toIso8601String(),
                'creator_name' => $record->creator?->name ?? 'Pengguna tidak tersedia',
                'foto_url' => $record->foto_path === null ? null : route('admin.sensus.photo', $record->id, false),
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
            'kabupatenKotaOptions' => PenggarapLahan::query()->whereNotNull('kabupaten_kota')
                ->where('kabupaten_kota', '<>', '')
                ->distinct()->orderBy('kabupaten_kota')->pluck('kabupaten_kota'),
            'filters' => [
                'search' => $search,
                'user_id' => $userId,
                'kabupaten_kota' => $kabupatenKota,
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'luas_min' => $filters['luas_min'] ?? '',
                'luas_max' => $filters['luas_max'] ?? '',
                'foto' => $filters['foto'] ?? '',
                'koordinat' => $filters['koordinat'] ?? '',
                'sort' => $sort,
            ],
        ]);
    }
}
