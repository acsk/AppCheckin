<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'message' => 'pong',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'app_env' => config('app.env'),
            'api_version' => config('appcheckin.api_version'),
        ]);
    }

    public function health(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $dbStatus = 'connected';
            $status = 200;
            $body = [
                'status' => 'ok',
                'php' => 'running',
                'database' => $dbStatus,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'environment' => config('app.env'),
                'api_version' => config('appcheckin.api_version'),
            ];
        } catch (\Throwable $e) {
            $status = 503;
            $body = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'api_version' => config('appcheckin.api_version'),
            ];
        }

        return response()->json($body, $status);
    }

    public function healthBasic(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'php' => 'running',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'environment' => config('app.env'),
            'api_version' => config('appcheckin.api_version'),
        ]);
    }
}
