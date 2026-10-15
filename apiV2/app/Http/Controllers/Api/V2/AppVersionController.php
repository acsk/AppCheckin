<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\AppVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function __construct(
        private readonly AppVersionService $versions,
    ) {}

    public function mobile(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->versions->show('mobile'),
        ]);
    }

    public function updateMobile(Request $request): JsonResponse
    {
        $authError = $this->authorizeUpdate($request);
        if ($authError !== null) {
            return $authError;
        }

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'release' => ['required', 'integer', 'min:0'],
            'release_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $data = $this->versions->store(
            'mobile',
            (int) $validated['version'],
            (int) $validated['release'],
            (string) $validated['release_date'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Versão atualizada com sucesso.',
            'data' => $data,
        ]);
    }

    private function authorizeUpdate(Request $request): ?JsonResponse
    {
        $configured = (string) config('appcheckin.app_version_update_token', '');
        $isProduction = config('app.env') === 'production';

        if ($configured === '') {
            if ($isProduction) {
                return response()->json([
                    'success' => false,
                    'error' => 'Publicação de versão não configurada (APP_VERSION_UPDATE_TOKEN).',
                ], 503);
            }

            return null;
        }

        $provided = (string) (
            $request->header('X-App-Version-Token')
            ?? $request->bearerToken()
            ?? $request->query('token')
            ?? ''
        );

        if (! hash_equals($configured, $provided)) {
            return response()->json([
                'success' => false,
                'error' => 'Token inválido.',
            ], 401);
        }

        return null;
    }
}
