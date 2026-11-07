<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\PreMatriculaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobilePreMatriculaController extends Controller
{
    public function __construct(
        private readonly PreMatriculaService $service,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenantId');
        $atorId = (int) $request->attributes->get('userId');
        [$alunoId, $responsavelId] = $this->beneficiario($request, $atorId);
        $estado = $this->service->paraAluno($tenantId, $alunoId, $responsavelId);

        if ($estado !== null) {
            $perfilDependente = $this->perfilDependente($request);
            // Compras e pagamentos: só no perfil do responsável ou do próprio dependente com a conta do responsável
            $estado['contratar'] = $perfilDependente && $request->attributes->get('dependente_login')
                ? 'responsavel'
                : ($alunoId !== $atorId ? 'perfil_dependente' : 'aqui');
            if ($alunoId === $atorId && ! $perfilDependente) {
                $estado['dependentes'] = $this->service->dependentes($tenantId, $atorId);
            }
        }

        return response()->json(['pre_matricula' => $estado], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenantId');
        $atorId = (int) $request->attributes->get('userId');
        [$alunoId] = $this->beneficiario($request, $atorId);

        $resultado = $this->service->enviar($tenantId, $alunoId, $request->except('usuario_id'), $atorId);

        return response()->json($resultado, 201, [], JSON_UNESCAPED_UNICODE);
    }

    private function perfilDependente(Request $request): bool
    {
        return (bool) ($request->attributes->get('responsavel_id') || $request->attributes->get('dependente_login'));
    }

    /** @return array{0: int, 1: ?int} */
    private function beneficiario(Request $request, int $atorId): array
    {
        $usuarioId = $request->input('usuario_id', $request->query('usuario_id'));

        return $this->service->resolverBeneficiario(
            $atorId,
            is_numeric($usuarioId) ? (int) $usuarioId : null,
            $this->perfilDependente($request),
        );
    }
}
