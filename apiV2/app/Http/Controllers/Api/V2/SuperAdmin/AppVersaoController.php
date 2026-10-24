<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Api\V2\AppVersionController;
use App\Http\Controllers\Controller;
use App\Services\AppVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Super Admin → App mobile (rotas antigas): obrigatoriedade e limite do ranking, gravados na configuração remota. */
class AppVersaoController extends Controller
{
    public function __construct(
        private readonly AppVersionService $versions,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['mobile' => AppVersionController::comAndroid($this->versions->show('mobile'))]);
    }

    public function update(Request $request): JsonResponse
    {
        $dados = $request->validate(['obrigatoria' => ['required', 'boolean']]);

        return response()->json([
            'message' => $dados['obrigatoria'] ? 'Atualização obrigatória ativada' : 'Atualização passou a ser opcional',
            'mobile' => AppVersionController::comAndroid($this->versions->definirObrigatoria('mobile', (bool) $dados['obrigatoria'], $this->userId($request))),
        ]);
    }
    public function rankingCheckins(): JsonResponse
    {
        return response()->json(['limite' => $this->versions->limiteRankingCheckins()]);
    }

    public function updateRankingCheckins(Request $request): JsonResponse
    {
        $dados = $request->validate(['limite' => ['required', 'integer', 'between:1,100']]);

        return response()->json([
            'message' => 'Limite do ranking atualizado',
            'limite' => $this->versions->definirLimiteRankingCheckins((int) $dados['limite'], $this->userId($request)),
        ]);
    }

    private function userId(Request $request): ?int
    {
        return ((int) $request->attributes->get('userId', 0)) ?: null;
    }
}
