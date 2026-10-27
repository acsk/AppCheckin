<?php

namespace App\Services\Recorrencia;

use App\Support\AcademyDateTime;
use App\Support\RecorrenciaCalculo as Calc;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Painel → Financeiro → Recorrências: indicadores, lista e detalhe dos contratos (sempre da academia do token). */
class AdminRecorrenciaService
{
    public const STATUS = [
        'pendente_ativacao' => 'Pendente de ativação', 'ativo' => 'Ativo', 'pagamento_pendente' => 'Pagamento pendente', 'inadimplente' => 'Inadimplente',
        'cancelamento_solicitado' => 'Cancelamento solicitado', 'cancelamento_agendado' => 'Cancelamento agendado', 'encerrado' => 'Encerrado', 'cancelado' => 'Cancelado',
    ];

    public function __construct(
        private readonly RecorrenciaService $recorrencia,
        private readonly RecorrenciaAcesso $acesso,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function indicadores(int $tenantId): array
    {
        $hoje = AcademyDateTime::today();
        $em30 = date('Y-m-d', strtotime($hoje.' +30 days'));
        $c = fn () => DB::table('contratos_recorrencia')->where('tenant_id', $tenantId);
        $soma = fn ($q, string $col) => Calc::centavos((string) ($q->sum($col) ?: '0'));

        $pagasNaFidelizacao = DB::table('recorrencia_cobrancas as rc')->join('contratos_recorrencia as c', 'c.id', '=', 'rc.contrato_id')
            ->where('rc.tenant_id', $tenantId)->where('rc.status', 'paga')->whereColumn('rc.numero', '<=', 'c.fidelizacao_meses')
            ->selectRaw('COALESCE(SUM(c.valor_mensal_referencia - c.valor_mensal_contratado), 0) as total')->value('total');
        $regSolicitadas = DB::table('cancelamentos_recorrencia')->where('tenant_id', $tenantId)->where('status', 'solicitado')
            ->selectRaw('COALESCE(SUM(valor_regularizacao - valor_isencao), 0) as total')->value('total');
        $regAbertas = DB::table('cancelamentos_recorrencia as k')->join('pagamentos_plano as pp', 'pp.id', '=', 'k.pagamento_plano_id')
            ->where('k.tenant_id', $tenantId)->whereIn('pp.status_pagamento_id', [1, 3])->sum('pp.valor');
        $vencidas = DB::table('recorrencia_cobrancas')->where('tenant_id', $tenantId)->whereIn('status', ['pendente', 'vencida'])->where('vencimento', '<', $hoje);

        return ['status' => 200, 'body' => ['success' => true,
            'contratos_ativos' => $c()->whereIn('status', ['ativo', 'pagamento_pendente', 'inadimplente'])->count(),
            'mrr' => Calc::reais($soma($c()->whereIn('status', ['ativo', 'pagamento_pendente']), 'valor_mensal_vigente')),
            'receita_prevista_30d' => Calc::reais($soma($c()->whereIn('status', ['ativo', 'pagamento_pendente', 'inadimplente'])->whereNotNull('proxima_cobranca_em')->where('proxima_cobranca_em', '<=', $em30), 'valor_mensal_vigente')),
            'em_fidelizacao' => $c()->whereIn('status', RecorrenciaService::ATIVOS)->where('em_fidelizacao', 1)->count(),
            'fidelizacao_terminando_30d' => $c()->whereIn('status', RecorrenciaService::ATIVOS)->where('em_fidelizacao', 1)->whereBetween('fim_fidelizacao_em', [$hoje, $em30])->count(),
            'pendentes_ativacao' => $c()->where('status', 'pendente_ativacao')->count(),
            'cancelamentos_solicitados' => DB::table('cancelamentos_recorrencia')->where('tenant_id', $tenantId)->where('status', 'solicitado')->count(),
            'inadimplentes' => $c()->where('status', 'inadimplente')->count(),
            'valor_em_atraso' => Calc::reais($soma((clone $vencidas), 'valor')),
            'descontos_concedidos' => Calc::reais(Calc::centavos((string) $pagasNaFidelizacao)),
            'regularizacoes_pendentes' => Calc::reais(Calc::centavos((string) $regSolicitadas) + Calc::centavos((string) ($regAbertas ?: '0'))),
        ]];
    }

    /**
     * @param  array<string, mixed>  $f  status, busca, fidelizacao (1|0), pagina, por_pagina
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $tenantId, array $f): array
    {
        $q = DB::table('contratos_recorrencia as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->join('planos as p', 'p.id', '=', 'c.plano_id')->leftJoin('plano_ciclos as pc', 'pc.id', '=', 'c.plano_ciclo_id')
            ->leftJoin('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('c.tenant_id', $tenantId);
        if (! empty($f['status']) && isset(self::STATUS[$f['status']])) {
            $q->where('c.status', $f['status']);
        } elseif (($f['status'] ?? '') === 'vigentes') {
            $q->whereIn('c.status', RecorrenciaService::ATIVOS);
        }
        if (isset($f['fidelizacao']) && $f['fidelizacao'] !== '') {
            $q->where('c.em_fidelizacao', filter_var($f['fidelizacao'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }
        if (($busca = trim((string) ($f['busca'] ?? ''))) !== '') {
            $q->where(fn ($w) => $w->where('u.nome', 'like', "%{$busca}%")->orWhere('u.email', 'like', "%{$busca}%")->orWhere('p.nome', 'like', "%{$busca}%"));
        }
        $total = (clone $q)->count();
        $porPagina = max(5, min(100, (int) ($f['por_pagina'] ?? 20)));
        $pagina = max(1, (int) ($f['pagina'] ?? 1));
        $linhas = $q->orderByRaw("CASE c.status WHEN 'cancelamento_solicitado' THEN 0 WHEN 'inadimplente' THEN 1 WHEN 'pagamento_pendente' THEN 2 ELSE 3 END")
            ->orderBy('c.proxima_cobranca_em')->orderByDesc('c.id')
            ->offset(($pagina - 1) * $porPagina)->limit($porPagina)
            ->get(['c.*', 'u.nome as aluno_nome', 'u.email as aluno_email', 'p.nome as plano_nome', 'af.nome as ciclo_nome'])->map(fn ($r) => (array) $r)->all();
        $pagas = $linhas ? DB::table('recorrencia_cobrancas')->whereIn('contrato_id', array_column($linhas, 'id'))->where('status', 'paga')
            ->groupBy('contrato_id')->selectRaw('contrato_id, COUNT(*) as n')->pluck('n', 'contrato_id')->all() : [];

        return ['status' => 200, 'body' => ['success' => true,
            'contratos' => array_map(fn ($c) => $this->resumo($c, (int) ($pagas[$c['id']] ?? 0)), $linhas),
            'paginacao' => ['pagina' => $pagina, 'por_pagina' => $porPagina, 'total' => $total, 'paginas' => (int) ceil($total / $porPagina)],
        ]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function detalhe(int $tenantId, int $contratoId, bool $consultarGateway = false): array
    {
        $c = DB::table('contratos_recorrencia as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->join('planos as p', 'p.id', '=', 'c.plano_id')->leftJoin('plano_ciclos as pc', 'pc.id', '=', 'c.plano_ciclo_id')
            ->leftJoin('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('c.id', $contratoId)->where('c.tenant_id', $tenantId)
            ->first(['c.*', 'u.nome as aluno_nome', 'u.email as aluno_email', 'u.telefone as aluno_telefone', 'p.nome as plano_nome', 'af.nome as ciclo_nome']);
        if (! $c) {
            return ['status' => 404, 'body' => ['success' => false, 'error' => 'Contrato não encontrado']];
        }
        $c = (array) $c;
        $cobrancas = DB::table('recorrencia_cobrancas')->where('contrato_id', $contratoId)->orderBy('numero')->get()->map(fn ($r) => (array) $r)->all();
        $pagas = count(array_filter($cobrancas, fn ($x) => $x['status'] === 'paga'));
        $gateway = null;
        if ($consultarGateway && $c['assinatura_gateway_id']) {
            try {
                $sub = $this->recorrenciaAsaas($tenantId)->obterAssinatura((string) $c['assinatura_gateway_id']);
                $gateway = ['status' => $sub['status'] ?? null, 'proximo_vencimento' => $sub['nextDueDate'] ?? null, 'valor' => $sub['value'] ?? null, 'forma' => $sub['billingType'] ?? null, 'removida' => (bool) ($sub['deleted'] ?? false)];
            } catch (\Throwable $e) {
                $gateway = ['erro' => mb_substr($e->getMessage(), 0, 200)];
            }
        }

        return ['status' => 200, 'body' => ['success' => true, 'contrato' => $this->resumo($c, $pagas) + [
            'aluno_telefone' => $c['aluno_telefone'], 'assinatura_gateway_id' => $c['assinatura_gateway_id'], 'metodo' => $c['metodo'],
            'termos_aceitos' => $c['termos_aceitos'], 'termos_versao' => (int) $c['termos_versao'], 'termos_hash' => $c['termos_hash'],
            'aceite_em' => $c['aceite_em'], 'aceite_ip' => $c['aceite_ip'], 'aceite_user_agent' => $c['aceite_user_agent'],
            'politica_cancelamento' => json_decode((string) $c['politica_cancelamento'], true),
            'contrato_anterior_id' => $c['contrato_anterior_id'], 'encerrado_em' => $c['encerrado_em'],
            'acesso_ate' => $this->acesso->acessoAte($contratoId),
        ],
            'cronograma' => $this->cronograma($c, $cobrancas),
            'eventos' => DB::table('recorrencia_eventos as e')->leftJoin('usuarios as u', 'u.id', '=', 'e.usuario_id')->where('e.contrato_id', $contratoId)
                ->orderByDesc('e.id')->limit(100)->get(['e.*', 'u.nome as usuario_nome'])->map(fn ($r) => ['dados' => json_decode((string) $r->dados, true)] + (array) $r)->all(),
            'cancelamentos' => DB::table('cancelamentos_recorrencia')->where('contrato_id', $contratoId)->orderByDesc('id')->get()
                ->map(fn ($r) => ['memoria_calculo' => json_decode((string) $r->memoria_calculo, true)] + (array) $r)->all(),
            'pagamentos' => DB::table('pagamentos_plano as pp')->leftJoin('status_pagamento as sp', 'sp.id', '=', 'pp.status_pagamento_id')
                ->where('pp.tenant_id', $tenantId)->where('pp.matricula_id', $c['matricula_id'])->orderByDesc('pp.data_vencimento')
                ->get(['pp.id', 'pp.valor', 'pp.data_vencimento', 'pp.data_pagamento', 'pp.status_pagamento_id', 'sp.nome as status_nome', 'pp.observacoes'])->map(fn ($r) => (array) $r)->all(),
            'simulacao_cancelamento' => in_array($c['status'], RecorrenciaService::ATIVOS, true) ? $this->recorrencia->simularCancelamento($tenantId, $contratoId) : null,
            'gateway' => $gateway,
        ]];
    }

    /**
     * Mensalidades da fidelização: as geradas pelo gateway e as previstas (ainda não geradas).
     *
     * @param  array<string, mixed>  $c
     * @param  list<array<string, mixed>>  $cobrancas
     * @return list<array<string, mixed>>
     */
    private function cronograma(array $c, array $cobrancas): array
    {
        $porNumero = [];
        foreach ($cobrancas as $x) {
            $porNumero[(int) $x['numero']] = $x;
        }
        $ate = max((int) $c['fidelizacao_meses'], $porNumero ? max(array_keys($porNumero)) : 0);
        $out = [];
        for ($n = 1; $n <= $ate; $n++) {
            $x = $porNumero[$n] ?? null;
            $out[] = [
                'numero' => $n, 'na_fidelizacao' => $n <= (int) $c['fidelizacao_meses'],
                'vencimento' => $x['vencimento'] ?? Calc::maisMeses((string) $c['inicio_em'], $n - 1),
                'valor' => $x['valor'] ?? $c['valor_mensal_vigente'], 'status' => $x['status'] ?? 'prevista',
                'pago_em' => $x['pago_em'] ?? null, 'tentativas' => (int) ($x['tentativas'] ?? 0), 'gateway_payment_id' => $x['gateway_payment_id'] ?? null,
                'url' => $x['url'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function resumo(array $c, int $pagas): array
    {
        $fid = (int) $c['fidelizacao_meses'];

        return [
            'id' => (int) $c['id'], 'aluno_id' => (int) $c['aluno_id'], 'aluno_nome' => $c['aluno_nome'], 'aluno_email' => $c['aluno_email'],
            'matricula_id' => (int) $c['matricula_id'], 'plano_nome' => $c['plano_nome'], 'ciclo_nome' => $c['ciclo_nome'] ?? null,
            'valor_mensal_referencia' => $c['valor_mensal_referencia'], 'valor_mensal_contratado' => $c['valor_mensal_contratado'], 'valor_mensal_vigente' => $c['valor_mensal_vigente'],
            'desconto_mensal' => Calc::reais(max(0, Calc::centavos((string) $c['valor_mensal_referencia']) - Calc::centavos((string) $c['valor_mensal_contratado']))),
            'fidelizacao_meses' => $fid, 'meses_pagos' => $pagas, 'meses_cumpridos' => min($pagas, $fid), 'em_fidelizacao' => (bool) $c['em_fidelizacao'],
            'inicio_em' => $c['inicio_em'], 'fim_fidelizacao_em' => $c['fim_fidelizacao_em'], 'proxima_cobranca_em' => $c['proxima_cobranca_em'],
            'status' => $c['status'], 'status_nome' => self::STATUS[$c['status']] ?? $c['status'], 'politica_renovacao' => $c['politica_renovacao'],
            'renovacao_oferecida_em' => $c['renovacao_oferecida_em'], 'created_at' => $c['created_at'],
        ];
    }

    private function recorrenciaAsaas(int $tenantId): \App\Services\Asaas\AsaasService
    {
        return app(\App\Services\Asaas\AsaasService::class, ['tenantId' => $tenantId]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function sincronizar(int $tenantId, int $contratoId): array
    {
        if (! DB::table('contratos_recorrencia')->where('id', $contratoId)->where('tenant_id', $tenantId)->exists()) {
            return ['status' => 404, 'body' => ['success' => false, 'error' => 'Contrato não encontrado']];
        }
        try {
            $n = $this->recorrencia->sincronizar($tenantId, $contratoId);
        } catch (\Throwable $e) {
            Log::warning('Recorrência: sincronização manual falhou', ['contrato_id' => $contratoId, 'error' => $e->getMessage()]);

            return ['status' => 502, 'body' => ['success' => false, 'error' => 'Não foi possível consultar o Asaas agora.']];
        }

        return ['status' => 200, 'body' => ['success' => true, 'message' => "{$n} cobrança(s) conferida(s) no Asaas."] + $this->detalhe($tenantId, $contratoId)['body']];
    }
}
