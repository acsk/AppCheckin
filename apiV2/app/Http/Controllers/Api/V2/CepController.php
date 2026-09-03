<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\CepService;
use Illuminate\Http\JsonResponse;

class CepController extends Controller
{
    public function __construct(
        private readonly CepService $service,
    ) {}

    public function buscar(string $cep): JsonResponse
    {
        $result = $this->service->buscar($cep);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
