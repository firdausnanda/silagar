<?php

namespace App\Http\Controllers;

use App\Http\Resources\PenggarapLahanResource;
use App\Models\PenggarapLahan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            ...$this->paginatedRecords($request),
            'summary' => $this->summary($request),
        ]);
    }

    public function records(Request $request): JsonResponse
    {
        return response()->json([
            ...$this->paginatedRecords($request),
            'summary' => $this->summary($request),
        ])
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return array{records: array<mixed>, next_cursor: string|null} */
    private function paginatedRecords(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);
        $query = PenggarapLahan::query()->where('created_by', $request->user()->id);
        $search = trim($data['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where('nama', 'like', $term)
                    ->orWhere('no_hp', 'like', $term)
                    ->orWhere('latitude', 'like', $term)
                    ->orWhere('longitude', 'like', $term);
            });
        }

        $page = $query->orderByDesc('id')->cursorPaginate(30, ['*'], 'cursor', $data['cursor'] ?? null);

        return [
            'records' => PenggarapLahanResource::collection(collect($page->items()))->resolve(),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /** @return array{total: int, luas: float} */
    private function summary(Request $request): array
    {
        $summary = DB::table('penggarap_lahans')
            ->where('created_by', $request->user()->id)
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(luas_garapan), 0) AS luas')
            ->first();

        return [
            'total' => (int) $summary->total,
            'luas' => round((float) $summary->luas, 2),
        ];
    }
}
