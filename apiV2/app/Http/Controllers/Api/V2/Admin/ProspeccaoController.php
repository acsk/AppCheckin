<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\ProspeccaoRepository;
use App\Services\Admin\Prospeccao\ProspeccaoAgendamentoService;
use App\Services\Admin\Prospeccao\ProspeccaoDashboardService;
use App\Services\Admin\Prospeccao\ProspeccaoEquipeService;
use App\Services\Admin\Prospeccao\ProspeccaoModeloMensagemService;
use App\Services\Admin\Prospeccao\ProspeccaoOportunidadeService;
use App\Services\Admin\Prospeccao\ProspeccaoScoreService;
use App\Services\Admin\Prospeccao\ProspeccaoSincronizacaoService;
use App\Support\ProspeccaoStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Módulo Prospecção → Recuperação de Alunos. Tenant e papel vêm do token (middleware prospeccao.auth);
 * nenhum identificador de tenant é aceito do frontend.
 */
class ProspeccaoController extends Controller
{
    public function __construct(
        private readonly ProspeccaoOportunidadeService $oportunidades,
        private readonly ProspeccaoSincronizacaoService $sincronizacao,
        private readonly ProspeccaoScoreService $score,
        private readonly ProspeccaoAgendamentoService $agendamentos,
        private readonly ProspeccaoModeloMensagemService $modelos,
        private readonly ProspeccaoEquipeService $equipe,
        private readonly ProspeccaoDashboardService $dashboard,
        private readonly ProspeccaoRepository $repo,
    ) {}

    public function contexto(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        $tenantId = $this->tenant($request);

        return $this->json(['status' => 200, 'body' => [
            'admin' => $ctx['admin'],
            'usuario_id' => $ctx['usuario_id'],
            'estabelecimento' => $this->repo->nomeTenant($tenantId),
            'status' => ProspeccaoStatus::ROTULOS,
            'status_abertos' => ProspeccaoStatus::ABERTOS,
            'motivos' => ProspeccaoStatus::MOTIVOS,
            'canais' => ProspeccaoStatus::CANAIS,
            'resultados' => ProspeccaoStatus::RESULTADOS,
            'etapas_modelo' => ProspeccaoStatus::ETAPAS_MODELO,
            'tipos_contato' => ProspeccaoStatus::TIPOS_CONTATO,
            'criterios_score' => ProspeccaoScoreService::CRITERIOS,
            'responsaveis' => $this->repo->equipe($tenantId, false),
            'filtros' => [
                'modalidades' => $this->repo->modalidades($tenantId),
                'planos' => $this->repo->planos($tenantId),
            ],
        ]]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->sincronizacao->sincronizarSeNecessario($this->tenant($request));

        return $this->json($this->dashboard->indicadores($this->tenant($request), $request->query()));
    }

    public function relatorio(Request $request): JsonResponse
    {
        return $this->json($this->dashboard->relatorio($this->tenant($request), $request->query()));
    }

    /** Admin pode forçar (?forcar=1); para os demais vale o intervalo mínimo entre execuções. */
    public function sincronizar(Request $request): JsonResponse
    {
        $tenantId = $this->tenant($request);
        $forcar = $this->ctx($request)['admin'] && filter_var($request->input('forcar', false), FILTER_VALIDATE_BOOLEAN);
        $r = $this->sincronizacao->sincronizarSeNecessario($tenantId, $forcar);

        return $this->json(['status' => 200, 'body' => $r + [
            'message' => $r['executada'] ? 'Oportunidades atualizadas' : 'Já sincronizado há poucos minutos',
            'diagnostico' => $this->sincronizacao->diagnostico($tenantId),
        ]]);
    }

    public function diagnostico(Request $request): JsonResponse
    {
        return $this->json(['status' => 200, 'body' => ['diagnostico' => $this->sincronizacao->diagnostico($this->tenant($request))]]);
    }

    public function alunosCancelados(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('busca', ''));
        $digitos = preg_replace('/\D/', '', $busca);
        $porDigitos = $busca !== '' && ! preg_match('/\pL/u', $busca) && strlen($digitos) >= 3;

        return $this->json(['status' => 200, 'body' => ['alunos' => $this->repo->alunosComCancelamento(
            $this->tenant($request),
            ! $porDigitos && $busca !== '' ? mb_strtoupper(mb_substr($busca, 0, 100), 'UTF-8') : null,
            $porDigitos ? $digitos : null,
            30,
        )]]);
    }

    public function iniciar(Request $request): JsonResponse
    {
        return $this->json($this->sincronizacao->iniciarManual($this->tenant($request), (int) $request->input('aluno_id', 0), $this->ctx($request)['usuario_id']));
    }

    public function listar(Request $request): JsonResponse
    {
        $this->sincronizacao->sincronizarSeNecessario($this->tenant($request));

        return $this->json($this->oportunidades->listar($this->tenant($request), $request->query(), $this->ctx($request)));
    }

    public function detalhar(Request $request, int $id): JsonResponse
    {
        return $this->json($this->oportunidades->detalhar($this->tenant($request), $id, $this->ctx($request)));
    }

    public function atualizar(Request $request, int $id): JsonResponse
    {
        return $this->json($this->oportunidades->atualizar($this->tenant($request), $id, $request->all(), $this->ctx($request)));
    }

    public function alterarStatus(Request $request, int $id): JsonResponse
    {
        return $this->json($this->oportunidades->alterarStatus($this->tenant($request), $id, $request->all(), $this->ctx($request)));
    }

    public function registrarInteracao(Request $request, int $id): JsonResponse
    {
        return $this->json($this->oportunidades->registrarInteracao($this->tenant($request), $id, $request->all(), $this->ctx($request)));
    }

    public function removerRestricao(Request $request, int $id): JsonResponse
    {
        return $this->json($this->oportunidades->removerRestricao($this->tenant($request), $id, $request->all(), $this->ctx($request)));
    }

    public function recuperacao(Request $request, int $id): JsonResponse
    {
        return $this->json($this->oportunidades->verificarRecuperacao($this->tenant($request), $id, $this->ctx($request)));
    }

    public function recalcularScore(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenant($request);
        if (! $this->score->recalcular($tenantId, [$id])) {
            return $this->json(['status' => 404, 'body' => ['error' => 'Oportunidade não encontrada']]);
        }

        return $this->detalhar($request, $id);
    }

    public function scoreConfig(Request $request): JsonResponse
    {
        return $this->json(['status' => 200, 'body' => [
            'config' => $this->score->config($this->tenant($request)),
            'padrao' => ProspeccaoScoreService::CONFIG_PADRAO,
            'criterios' => ProspeccaoScoreService::CRITERIOS,
            'motivos' => ProspeccaoStatus::MOTIVOS,
        ]]);
    }

    public function salvarScoreConfig(Request $request): JsonResponse
    {
        return $this->json($this->score->salvarConfig($this->tenant($request), $request->all(), $this->ctx($request)['usuario_id']));
    }

    public function recalcularTodos(Request $request): JsonResponse
    {
        $tenantId = $this->tenant($request);
        $ids = $this->repo->oportunidadesComScoreDesatualizado($tenantId, '9999-12-31', 5000);

        return $this->json(['status' => 200, 'body' => ['recalculadas' => $this->score->recalcular($tenantId, $ids)]]);
    }

    public function agenda(Request $request): JsonResponse
    {
        return $this->json($this->agendamentos->agenda($this->tenant($request), $request->query(), $this->ctx($request)));
    }

    public function criarAgendamento(Request $request): JsonResponse
    {
        return $this->json($this->agendamentos->criar($this->tenant($request), $request->all(), $this->ctx($request)));
    }

    public function atualizarAgendamento(Request $request, int $id): JsonResponse
    {
        return $this->json($this->agendamentos->atualizar($this->tenant($request), $id, $request->all(), $this->ctx($request)));
    }

    public function listarModelos(Request $request): JsonResponse
    {
        return $this->json($this->modelos->listar($this->tenant($request), filter_var($request->query('ativos', false), FILTER_VALIDATE_BOOLEAN)));
    }

    public function criarModelo(Request $request): JsonResponse
    {
        return $this->json($this->modelos->salvar($this->tenant($request), null, $request->all(), $this->ctx($request)));
    }

    public function atualizarModelo(Request $request, int $id): JsonResponse
    {
        return $this->json($this->modelos->salvar($this->tenant($request), $id, $request->all(), $this->ctx($request)));
    }

    public function excluirModelo(Request $request, int $id): JsonResponse
    {
        return $this->json($this->modelos->excluir($this->tenant($request), $id, $this->ctx($request)));
    }

    public function listarEquipe(Request $request): JsonResponse
    {
        return $this->json($this->equipe->listar($this->tenant($request)));
    }

    public function adicionarEquipe(Request $request): JsonResponse
    {
        return $this->json($this->equipe->adicionar($this->tenant($request), $request->all(), $this->ctx($request)));
    }

    public function removerEquipe(Request $request, int $usuarioId): JsonResponse
    {
        return $this->json($this->equipe->remover($this->tenant($request), $usuarioId, $this->ctx($request)));
    }

    /** O middleware prospeccao.auth já garantiu o tenant. */
    private function tenant(Request $request): int
    {
        return (int) $request->attributes->get('tenantId');
    }

    /** @return array{usuario_id: int, admin: bool} */
    private function ctx(Request $request): array
    {
        return [
            'usuario_id' => (int) $request->attributes->get('userId'),
            'admin' => (bool) $request->attributes->get('prospeccao_admin', false),
        ];
    }

    /** @param array{status: int, body: array<string, mixed>} $r */
    private function json(array $r): JsonResponse
    {
        return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
