<?php

namespace App\Services\Recorrencia;

use App\Support\AcademyDateTime;
use App\Support\RecorrenciaCalculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acesso de uma matrícula de plano com fidelização: cada mensalidade paga libera um mês a partir do seu
 * vencimento. Substitui, só para essas matrículas, a regra do plano à vista (que soma o ciclo inteiro por
 * pagamento). Chamado por PagamentoPlanoService::atualizarStatusMatricula e depois de cada baixa.
 */
class RecorrenciaAcesso
{
    private static ?bool $tabelas = null;

    /** true quando a matrícula pertence a um contrato de recorrência (e o acesso foi atualizado aqui). */
    public function atualizar(int $tenantId, int $matriculaId): bool
    {
        if (! self::tabelasExistem()) {
            return false;
        }
        $contratos = DB::table('contratos_recorrencia')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)
            ->orderByDesc('id')->get(['id', 'status']);
        if ($contratos->isEmpty()) {
            return false;
        }
        // Contrato encerrado e a matrícula reaproveitada numa compra avulsa (a assinatura grava 'recorrente'):
        // o ciclo novo segue a regra do plano à vista. Antes o contrato cancelado seguia mandando e a compra
        // paga deixava a matrícula cancelada (matrícula 359).
        $tipoCobranca = DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)->value('tipo_cobranca');
        if ($tipoCobranca === 'avulso' && in_array((string) $contratos->first()->status, ['cancelado', 'encerrado'], true)) {
            return false;
        }
        $ids = $contratos->pluck('id')->all();

        // Baixa manual no painel (parcela paga) também conta como mensalidade paga
        $baixadas = DB::table('recorrencia_cobrancas as rc')
            ->join('pagamentos_plano as pp', 'pp.id', '=', 'rc.pagamento_plano_id')
            ->whereIn('rc.contrato_id', $ids)->whereIn('rc.status', ['pendente', 'vencida'])
            ->where('pp.status_pagamento_id', 2)
            ->get(['rc.id', 'pp.data_pagamento']);
        foreach ($baixadas as $b) {
            DB::table('recorrencia_cobrancas')->where('id', $b->id)->update(['status' => 'paga', 'pago_em' => $b->data_pagamento, 'updated_at' => now()]);
        }

        $ultimaPaga = DB::table('recorrencia_cobrancas')->whereIn('contrato_id', $ids)->where('status', 'paga')->max('vencimento');
        $acesso = $ultimaPaga ? RecorrenciaCalculo::maisUmMes((string) $ultimaPaga) : null;
        $hoje = AcademyDateTime::today();
        $statusContrato = (string) $contratos->first()->status;

        if ($acesso === null) {
            $codigo = in_array($statusContrato, ['cancelado', 'encerrado'], true) ? 'cancelada' : 'pendente';
        } elseif ($acesso >= $hoje) {
            $codigo = 'ativa';
        } elseif ($statusContrato === 'encerrado') {
            $codigo = 'finalizada';
        } elseif ($statusContrato === 'cancelado') {
            $codigo = 'cancelada';
        } else {
            $atraso = (int) (new \DateTimeImmutable($acesso))->diff(new \DateTimeImmutable($hoje))->days;
            $codigo = $atraso >= 5 ? 'cancelada' : 'vencida';
        }

        $statusId = DB::table('status_matricula')->where('codigo', $codigo)->value('id');
        $update = ['updated_at' => now()];
        if ($statusId) {
            $update['status_id'] = $statusId;
        }
        if ($acesso) {
            $update['data_vencimento'] = $acesso;
            $update['proxima_data_vencimento'] = $acesso;
        }
        if ($statusId) {
            unset($update['status_id']);
            app(\App\Services\AcessoHibridoService::class)->aplicarStatus($tenantId, $matriculaId, (int) $statusId, $update);
        } else {
            DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)->update($update);
        }

        return true;
    }

    /** Fim do período pago (último dia de acesso) de um contrato, ou null. */
    public function acessoAte(int $contratoId): ?string
    {
        $ultima = DB::table('recorrencia_cobrancas')->where('contrato_id', $contratoId)->where('status', 'paga')->max('vencimento');

        return $ultima ? RecorrenciaCalculo::maisUmMes((string) $ultima) : null;
    }

    private static function tabelasExistem(): bool
    {
        return self::$tabelas ??= Schema::hasTable('contratos_recorrencia') && Schema::hasTable('recorrencia_cobrancas');
    }

    public static function esquecerSchema(): void
    {
        self::$tabelas = null;
    }
}
