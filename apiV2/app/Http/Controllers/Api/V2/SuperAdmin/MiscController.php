<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminMiscService;
use Illuminate\Http\JsonResponse;

class MiscController extends Controller
{
    public function __construct(
        private readonly SuperAdminMiscService $misc,
    ) {}

    public function env(): JsonResponse
    {
        $result = $this->misc->getEnvironmentVariables();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
