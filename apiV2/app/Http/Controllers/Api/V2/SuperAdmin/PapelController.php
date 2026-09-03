<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminPapelService;
use Illuminate\Http\JsonResponse;

class PapelController extends Controller
{
    public function __construct(
        private readonly SuperAdminPapelService $service,
    ) {}

    public function index(): JsonResponse
    {
        $result = $this->service->listarPapeis();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
