<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Repositories\UsuarioRepository;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(
        private readonly UsuarioRepository $usuarios,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $userId = (int) $request->attributes->get('userId');
        $tenantId = $request->attributes->get('tenantId');

        $usuario = $this->usuarios->findProfile(
            $userId,
            $tenantId ? (int) $tenantId : null,
        );

        if (! $usuario) {
            return ApiError::json('Usuário não encontrado', 'USER_NOT_FOUND', 404);
        }

        return response()->json($usuario);
    }
}
