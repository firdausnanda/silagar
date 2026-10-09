<?php

namespace App\Http\Controllers\Admin;

use App\GoogleDrivePhotoStorage;
use App\Http\Controllers\Controller;
use App\Models\PenggarapLahan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SensusPhotoController extends Controller
{
    public function __invoke(PenggarapLahan $record, GoogleDrivePhotoStorage $photos): StreamedResponse
    {
        if ($record->foto_storage === 'google_drive') {
            return $photos->response($record->foto_path);
        }

        abort_unless(Storage::disk('local')->exists($record->foto_path), 404);

        return Storage::disk('local')->response($record->foto_path);
    }
}
