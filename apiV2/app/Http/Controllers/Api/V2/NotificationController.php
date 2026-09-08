<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Repositories\NotificacaoRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificacaoRepository $notificacoes,
    ) {}

    public function unread(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId');
        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'tenantId ausente no contexto',
            ], 400, [], JSON_UNESCAPED_UNICODE);
        }

        $userId = (int) $request->attributes->get('userId');
        if ($userId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado',
            ], 401, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            $rows = $this->notificacoes->listUnread((int) $tenantId, $userId);

            return response()->json([
                'success' => true,
                'data' => $rows,
                'total' => count($rows),
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('[NotificationController@unread] '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar notificações não lidas',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }
}
