<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Support\FotoStorage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class UploadsController extends Controller
{
    public function foto(string $filename): Response
    {
        try {
            $result = FotoStorage::readByFilename($filename);
            if ($result === null) {
                return response('', 404);
            }

            return response($result['body'], 200, [
                'Content-Type' => $result['mime'],
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (\Throwable $e) {
            Log::error('UploadsController::foto: '.$e->getMessage());

            return response('', 500);
        }
    }
}
