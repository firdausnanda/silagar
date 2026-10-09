<?php

namespace App\Http\Controllers;

use App\GoogleDrivePhotoStorage;
use App\Helpers\UtmConverter;
use App\Http\Requests\StoreSensusRequest;
use App\Http\Requests\UpdateSensusRequest;
use App\Http\Resources\PenggarapLahanResource;
use App\Models\PenggarapLahan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SensusController extends Controller
{
    public function store(StoreSensusRequest $request, GoogleDrivePhotoStorage $photos): JsonResponse
    {
        $data = $request->validated();
        $existing = PenggarapLahan::query()
            ->where('created_by', $request->user()->id)
            ->where('client_uuid', $data['client_uuid'])
            ->first();

        if ($existing !== null) {
            return (new PenggarapLahanResource($existing))->response();
        }

        $fotoStorage = $photos->isEnabled() ? 'google_drive' : 'local';
        $fotoPath = $fotoStorage === 'google_drive'
            ? $photos->store($request->file('foto'), $request->user())
            : $request->file('foto')->store('sensus/'.$request->user()->id, 'local');
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
                'foto_storage' => $fotoStorage,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                ...$utmCoordinates,
                'gps_accuracy_m' => $data['gps_accuracy_m'] ?? null,
                'captured_at' => $data['captured_at'],
            ]);
            $record->created_by = $request->user()->id;
            $record->save();
        } catch (UniqueConstraintViolationException $exception) {
            $this->deletePhoto($fotoStorage, $fotoPath, $photos);
            $existing = PenggarapLahan::query()
                ->where('created_by', $request->user()->id)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing !== null) {
                return (new PenggarapLahanResource($existing))->response();
            }

            abort(409, 'ID sensus sudah digunakan.');
        } catch (Throwable $exception) {
            $this->deletePhoto($fotoStorage, $fotoPath, $photos);
            throw $exception;
        }

        return (new PenggarapLahanResource($record))->response()->setStatusCode(201);
    }

    public function photo(Request $request, int $record, GoogleDrivePhotoStorage $photos): StreamedResponse
    {
        $penggarapLahan = PenggarapLahan::query()
            ->where('created_by', $request->user()->id)
            ->findOrFail($record);

        if ($penggarapLahan->foto_storage === 'google_drive') {
            return $photos->response($penggarapLahan->foto_path);
        }

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

    public function destroy(Request $request, int $record, GoogleDrivePhotoStorage $photos): Response
    {
        $penggarapLahan = PenggarapLahan::query()
            ->where('created_by', $request->user()->id)
            ->findOrFail($record);

        $fotoPath = $penggarapLahan->foto_path;
        $fotoStorage = $penggarapLahan->foto_storage;
        $penggarapLahan->delete();

        $this->deletePhoto($fotoStorage, $fotoPath, $photos);

        return response()->noContent();
    }

    private function deletePhoto(string $fotoStorage, string $fotoPath, GoogleDrivePhotoStorage $photos): void
    {
        try {
            $deleted = $fotoStorage === 'google_drive'
                ? $photos->delete($fotoPath)
                : Storage::disk('local')->delete($fotoPath);

            if ($deleted) {
                return;
            }

            Log::warning('Foto sensus tidak dapat dihapus setelah data dihapus.', [
                'foto_path' => $fotoPath,
                'foto_storage' => $fotoStorage,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Foto sensus tidak dapat dihapus setelah data dihapus.', [
                'foto_path' => $fotoPath,
                'foto_storage' => $fotoStorage,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
