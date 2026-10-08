<?php

namespace App\Http\Controllers;

use App\Helpers\UtmConverter;
use App\Http\Requests\StoreSensusRequest;
use App\Http\Requests\UpdateSensusRequest;
use App\Http\Resources\PenggarapLahanResource;
use App\Models\PenggarapLahan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SensusController extends Controller
{
    public function store(StoreSensusRequest $request): JsonResponse
    {
        $data = $request->validated();
        $existing = PenggarapLahan::query()
            ->where('created_by', $request->user()->id)
            ->where('client_uuid', $data['client_uuid'])
            ->first();

        if ($existing !== null) {
            return (new PenggarapLahanResource($existing))->response();
        }

        $fotoPath = $request->file('foto')->store('sensus/'.$request->user()->id, 'local');
        if ($fotoPath === false) {
            throw new RuntimeException('Foto sensus tidak dapat disimpan.');
        }

        try {
            $utmCoordinates = UtmConverter::fromCoordinates((float) $data['latitude'], (float) $data['longitude']);
            $record = new PenggarapLahan([
                'client_uuid' => $data['client_uuid'],
                'nama' => $data['nama'],
                'no_hp' => $data['no_hp'] ?? null,
                'luas_garapan' => $data['luas_garapan'],
                'lama_menggarap' => $data['lama_menggarap'],
                'foto_path' => $fotoPath,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                ...$utmCoordinates,
                'gps_accuracy_m' => $data['gps_accuracy_m'] ?? null,
                'captured_at' => $data['captured_at'],
            ]);
            $record->created_by = $request->user()->id;
            $record->save();
        } catch (UniqueConstraintViolationException $exception) {
            Storage::disk('local')->delete($fotoPath);
            $existing = PenggarapLahan::query()
                ->where('created_by', $request->user()->id)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing !== null) {
                return (new PenggarapLahanResource($existing))->response();
            }

            abort(409, 'ID sensus sudah digunakan.');
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($fotoPath);
            throw $exception;
        }

        return (new PenggarapLahanResource($record))->response()->setStatusCode(201);
    }

    public function photo(Request $request, int $record): StreamedResponse
    {
        $penggarapLahan = PenggarapLahan::query()
            ->where('created_by', $request->user()->id)
            ->findOrFail($record);

        abort_unless(Storage::disk('local')->exists($penggarapLahan->foto_path), 404);

        return Storage::disk('local')->response($penggarapLahan->foto_path);
    }

    public function update(UpdateSensusRequest $request, int $record): JsonResponse
    {
        $penggarapLahan = PenggarapLahan::query()
            ->where('created_by', $request->user()->id)
            ->findOrFail($record);

        $penggarapLahan->update($request->validated());

        return (new PenggarapLahanResource($penggarapLahan))->response();
    }
}
