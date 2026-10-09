<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PenggarapLahan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SensusPhotoController extends Controller
{
    public function __invoke(PenggarapLahan $record): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($record->foto_path), 404);

        return Storage::disk('local')->response($record->foto_path);
    }
}
