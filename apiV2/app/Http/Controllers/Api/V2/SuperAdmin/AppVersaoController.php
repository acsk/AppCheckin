<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Api\V2\AppVersionController;
use App\Http\Controllers\Controller;
use App\Services\AppVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Super Admin → App mobile: versão publicada e se a atualização é obrigatória. */
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
            'mobile' => AppVersionController::comAndroid($this->versions->definirObrigatoria('mobile', (bool) $dados['obrigatoria'])),
        ]);
    }
}
