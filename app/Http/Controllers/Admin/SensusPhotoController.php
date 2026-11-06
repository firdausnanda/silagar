<?php

namespace App\Http\Controllers\Admin;

use App\GoogleDrivePhotoStorage;
use App\Http\Controllers\Controller;
use App\Models\PenggarapLahan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SensusPhotoController extends Controller
{
    public function __invoke(Request $request, PenggarapLahan $record, GoogleDrivePhotoStorage $photos): StreamedResponse
    {
        return $photos->recordResponse($record, $request);
    }
}
