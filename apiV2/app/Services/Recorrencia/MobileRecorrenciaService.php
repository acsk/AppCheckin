<?php

namespace App\Services\Recorrencia;

use App\Support\RecorrenciaCalculo as Calc;
use Illuminate\Support\Facades\DB;

/**
 * App → Planos com fidelização e "Minha assinatura". Sempre o aluno do token na academia do token:
 * contrato de outra pessoa responde 404.
 */
class MobileRecorrenciaService
{
    private const RENOVACAO = [
        'encerrar' => 'Ao fim da fidelização a assinatura se encerra, sem novas cobranças.',
        'mensal' => 'Ao fim da fidelização continua mensal pelo valor de referência, sem prazo mínimo.',
        'promocional' => 'Ao fim da fidelização continua pelo mesmo valor, sem prazo mínimo.',
        'nova_fidelizacao' => 'Ao fim da fidelização você pode aceitar um novo período promocional; até lá continua pelo mesmo valor, sem prazo mínimo.',
    ];

    public function __construct(
        private readonly RecorrenciaService $recorrencia,
        private readonly RecorrenciaAcesso $acesso,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function ofertas(int $tenantId, int $planoId): array
    {
        return $this->ok(['ofertas' => $this->recorrencia->ofertas($tenantId, $planoId)]);
    }

    /**
     * Assinatura no cartão: taxa do cartão por mês, que o aluno vê antes de assinar (uma linha só).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function taxaCartao(int $tenantId, int $cicloId): array
    {
        $ciclo = DB::table('plano_ciclos')->where('id', $cicloId)->where('tenant_id', $tenantId)->where('ativo', 1)->where('cobranca_mensal', 1)->first(['valor', 'meses']);
        if (! $ciclo) {
            return $this->erro('Assinatura não disponível para este ciclo.', 404);
        }
        $mensal = Calc::reais(intdiv(Calc::centavos((string) $ciclo->valor), max(1, (int) $ciclo->meses)));
        $taxa = $this->recorrencia->taxaCartao($tenantId, $mensal);

        return $this->ok(['valor_mensal' => $mensal, 'taxa_cartao' => $taxa, 'total_mensal' => Calc::reais(Calc::centavos($mensal) + Calc::centavos($taxa))]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function contratos(int $tenantId, int $usuarioId): array
    {
        $linhas = DB::table('contratos_recorrencia as c')->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->join('planos as p', 'p.id', '=', 'c.plano_id')->leftJoin('modalidades as m', 'm.id', '=', 'p.modalidade_id')
            ->where('c.tenant_id', $tenantId)->where('a.usuario_id', $usuarioId)
            // Cancelados ficam visíveis por 60 dias (histórico recente); o resto sempre
            ->where(fn ($q) => $q->where('c.status', '<>', 'cancelado')->orWhere('c.updated_at', '>=', now()->subDays(60)))
            ->orderByRaw("CASE WHEN c.status IN ('pendente_ativacao','ativo','pagamento_pendente','inadimplente','cancelamento_solicitado','cancelamento_agendado') THEN 0 ELSE 1 END")
            ->orderByDesc('c.id')->limit(20)
            ->get(['c.*', 'p.nome as plano_nome', 'm.nome as modalidade_nome'])->map(fn ($r) => (array) $r)->all();

        return $this->ok(['contratos' => array_map(fn ($c) => $this->formatar($tenantId, $c), $linhas)]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function simular(int $tenantId, int $usuarioId, int $contratoId): array
    {
        if (! $this->recorrencia->contratoDoUsuario($tenantId, $usuarioId, $contratoId)) {
            return $this->erro('Assinatura não encontrada.', 404);
        }

        return $this->ok(['simulacao' => $this->recorrencia->simularCancelamento($tenantId, $contratoId)]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function cancelar(int $tenantId, int $usuarioId, int $contratoId, ?string $motivo): array
    {
        if (! $this->recorrencia->contratoDoUsuario($tenantId, $usuarioId, $contratoId)) {
            return $this->erro('Assinatura não encontrada.', 404);
        }

        return $this->recorrencia->solicitarCancelamento($tenantId, $contratoId, 'aluno', $usuarioId, ['motivo' => $motivo]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function pagamento(int $tenantId, int $usuarioId, int $contratoId): array
    {
        if (! $this->recorrencia->contratoDoUsuario($tenantId, $usuarioId, $contratoId)) {
            return $this->erro('Assinatura não encontrada.', 404);
        }
        $this->recorrencia->sincronizar($tenantId, $contratoId);

        return $this->ok($this->recorrencia->pagamentoAberto($tenantId, $contratoId));
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function formatar(int $tenantId, array $c): array
    {
        $cobrancas = DB::table('recorrencia_cobrancas')->where('contrato_id', $c['id'])->orderByDesc('numero')
            ->get(['numero', 'vencimento', 'valor', 'status', 'pago_em'])->map(fn ($r) => (array) $r)->all();
        $pagasFid = count(array_filter($cobrancas, fn ($x) => $x['status'] === 'paga' && (int) $x['numero'] <= (int) $c['fidelizacao_meses']));
        $vivo = in_array($c['status'], RecorrenciaService::ATIVOS, true);
        $cancelamento = DB::table('cancelamentos_recorrencia')->where('contrato_id', $c['id'])->orderByDesc('id')
            ->first(['id', 'status', 'data_efetiva', 'valor_regularizacao', 'valor_isencao', 'solicitado_em']);
        $oferta = null;
        if ($vivo && $c['renovacao_oferecida_em'] && ! $c['em_fidelizacao']) {
            $plano = (array) DB::table('planos as p')->leftJoin('modalidades as m', 'm.id', '=', 'p.modalidade_id')->where('p.id', $c['plano_id'])
                ->first(['p.id', 'p.nome', 'p.valor', 'p.modalidade_id', 'm.nome as modalidade_nome']);
            $ciclo = (array) DB::table('plano_ciclos as pc')->leftJoin('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
                ->where('pc.id', $c['plano_ciclo_id'])->where('pc.ativo', 1)->where('pc.cobranca_mensal', 1)->first(['pc.*', 'af.nome as ciclo_nome']);
            $oferta = $ciclo && $plano ? $this->recorrencia->oferta($tenantId, $plano, $ciclo) : null;
        }

        return [
            'id' => (int) $c['id'], 'plano_nome' => $c['plano_nome'], 'modalidade_nome' => $c['modalidade_nome'],
            'status' => $c['status'], 'status_nome' => AdminRecorrenciaService::STATUS[$c['status']] ?? $c['status'],
            'valor_mensal' => $c['valor_mensal_vigente'], 'taxa_cartao' => $c['taxa_cartao_mensal'] ?? '0.00', 'valor_mensal_contratado' => $c['valor_mensal_contratado'], 'valor_mensal_referencia' => $c['valor_mensal_referencia'],
            'metodo' => $c['metodo'], 'inicio_em' => $c['inicio_em'], 'proxima_cobranca_em' => $vivo ? $c['proxima_cobranca_em'] : null,
            'acesso_ate' => $this->acesso->acessoAte((int) $c['id']),
            'fidelizacao_meses' => (int) $c['fidelizacao_meses'], 'em_fidelizacao' => (bool) $c['em_fidelizacao'],
            'fidelizacao_restante' => $c['em_fidelizacao'] ? max(0, (int) $c['fidelizacao_meses'] - $pagasFid) : 0,
            'fim_fidelizacao_em' => $c['fim_fidelizacao_em'],
            'condicoes_renovacao' => self::RENOVACAO[$c['politica_renovacao']] ?? null,
            'termos_aceitos' => $c['termos_aceitos'], 'termos_versao' => (int) $c['termos_versao'], 'aceite_em' => $c['aceite_em'],
            'pagamentos' => array_map(fn ($x) => ['numero' => (int) $x['numero'], 'vencimento' => $x['vencimento'], 'valor' => $x['valor'], 'status' => $x['status'], 'pago_em' => $x['pago_em']], $cobrancas),
            'tem_pagamento_aberto' => $vivo && (bool) array_filter($cobrancas, fn ($x) => in_array($x['status'], ['pendente', 'vencida'], true)),
            'pode_cancelar' => $vivo,
            'cancelamento' => $cancelamento ? (array) $cancelamento + ['valor_final' => Calc::reais(max(0, Calc::centavos((string) $cancelamento->valor_regularizacao) - Calc::centavos((string) $cancelamento->valor_isencao)))] : null,
            'nova_fidelizacao' => $oferta,
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function ok(array $data): array
    {
        return ['status' => 200, 'body' => ['success' => true, 'data' => $data]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'error' => $mensagem, 'message' => $mensagem]];
    }
}
