<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Encadeamento de matrículas: um ciclo com dinheiro recebido nunca é apagado nem sobrescrito.
 *
 * - Matrícula sem pagamento confirmado pode ser reaproveitada (ex.: compra abandonada) ou excluída.
 * - Com pagamento confirmado, a renovação cria uma matrícula nova ligada à anterior
 *   (matricula_anterior_id + motivo renovação/upgrade/downgrade) e a anterior vira "finalizada"
 *   (exceto se cancelada: o cancelamento é preservado, só o vínculo é criado).
 *
 * Usado na criação pelo painel (AdminMatriculaService), na compra pelo app (MobileCompraPlanoService)
 * e na ação manual "vincular à matrícula atual".
 */
class MatriculaEncadeamentoService
{
    /** pagamentos_plano.status_pagamento_id de "Pago" */
    private const PAGO = 2;

    /**
     * @return array{pagamentos_plano: int, pagamentos_provedor: int, valor: float}
     */
    public function pagamentosConfirmados(int $matriculaId): array
    {
        $plano = DB::table('pagamentos_plano')->where('matricula_id', $matriculaId)->where('status_pagamento_id', self::PAGO)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(valor), 0) as valor')->first();
        $provedor = Schema::hasTable('pagamentos_mercadopago')
            ? (int) DB::table('pagamentos_mercadopago')->where('matricula_id', $matriculaId)->where('status', 'approved')->count()
            : 0;

        return [
            'pagamentos_plano' => (int) ($plano->total ?? 0),
            'pagamentos_provedor' => $provedor,
            'valor' => round((float) ($plano->valor ?? 0), 2),
        ];
    }

    public function temPagamentoConfirmado(int $matriculaId): bool
    {
        $p = $this->pagamentosConfirmados($matriculaId);

        return $p['pagamentos_plano'] > 0 || $p['pagamentos_provedor'] > 0;
    }

    /** Pode ser sobrescrita por uma nova compra/matrícula? Só se nenhum dinheiro passou por ela. */
    public function podeReaproveitar(int $matriculaId): bool
    {
        return ! $this->temPagamentoConfirmado($matriculaId);
    }

    public function motivoCodigo(int $planoAnteriorId, int $planoNovoId, float $valorAnterior, float $valorNovo): string
    {
        if ($planoAnteriorId === $planoNovoId) {
            return 'renovacao';
        }

        return $valorNovo >= $valorAnterior ? 'upgrade' : 'downgrade';
    }

    /**
     * Encerra a anterior ao ser substituída: vencida/ativa/pendente → finalizada; cancelada continua cancelada.
     */
    public function encerrarAnterior(int $anteriorId): void
    {
        $codigo = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.id', $anteriorId)->value('s.codigo');
        if (in_array($codigo, ['cancelada', 'finalizada', 'concluida'], true)) {
            return;
        }
        $finalizada = DB::table('status_matricula')->where('codigo', 'finalizada')->value('id');
        if ($finalizada) {
            DB::table('matriculas')->where('id', $anteriorId)->update(['status_id' => $finalizada, 'updated_at' => now()]);
        }
    }

    /**
     * Grava na nova matrícula de quem ela é continuação.
     *
     * @param  array<string, mixed>  $anterior  linha de matriculas (id, plano_id, valor)
     */
    public function ligar(int $novaId, array $anterior, int $planoNovoId, float $valorNovo): string
    {
        $motivo = $this->motivoCodigo((int) $anterior['plano_id'], $planoNovoId, (float) ($anterior['valor'] ?? 0), $valorNovo);
        DB::table('matriculas')->where('id', $novaId)->update([
            'matricula_anterior_id' => (int) $anterior['id'],
            'plano_anterior_id' => (int) $anterior['plano_id'],
            'motivo_id' => (int) (DB::table('motivo_matricula')->where('codigo', $motivo)->value('id') ?? 1),
            'updated_at' => now(),
        ]);

        return $motivo;
    }

    /**
     * Próxima matrícula da mesma aluna/modalidade (a primeira criada depois desta e ainda sem vínculo).
     * A próxima, e não a mais recente, para várias antigas formarem uma cadeia (239 → 240 → 241).
     *
     * @return array<string, mixed>|null
     */
    public function sugerirAtual(int $anteriorId, int $tenantId): ?array
    {
        $anterior = $this->buscar($anteriorId, $tenantId);
        if (! $anterior) {
            return null;
        }

        $row = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.aluno_id', $anterior['aluno_id'])
            ->where('p.modalidade_id', $anterior['modalidade_id'])
            ->where('m.id', '<>', $anteriorId)
            ->where('m.created_at', '>=', $anterior['created_at'])
            ->where(fn ($q) => $q->whereNull('m.matricula_anterior_id')->orWhere('m.matricula_anterior_id', $anteriorId))
            ->orderBy('m.created_at')->orderBy('m.id')
            ->first(['m.id', 'm.plano_id', 'p.nome as plano_nome', 'm.data_inicio', 'm.data_vencimento', 's.codigo as status_codigo', 's.nome as status_nome', 'm.matricula_anterior_id']);

        return $row ? (array) $row : null;
    }

    /**
     * Ação manual: liga a matrícula antiga à atual (mesma aluna e modalidade) e encerra a antiga.
     * Pagamentos não mudam de matrícula.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function vincularManual(int $anteriorId, int $tenantId, ?int $atualId, ?int $usuarioId): array
    {
        $anterior = $this->buscar($anteriorId, $tenantId);
        if (! $anterior) {
            return $this->erro('Matrícula não encontrada', 404, 'NAO_ENCONTRADA');
        }
        if (! in_array($anterior['status_codigo'], ['cancelada', 'vencida', 'finalizada', 'concluida'], true)) {
            return $this->erro('Só matrículas canceladas, vencidas ou finalizadas podem ser vinculadas a outra.', 422, 'STATUS_INVALIDO');
        }

        $sugerida = $this->sugerirAtual($anteriorId, $tenantId);
        $atualId ??= $sugerida['id'] ?? null;
        $atual = $atualId ? $this->buscar((int) $atualId, $tenantId) : null;
        if (! $atual) {
            return $this->erro('Não há matrícula posterior desta aluna na mesma modalidade para vincular.', 422, 'SEM_MATRICULA_ATUAL');
        }
        if ((int) $atual['aluno_id'] !== (int) $anterior['aluno_id'] || (int) $atual['modalidade_id'] !== (int) $anterior['modalidade_id']) {
            return $this->erro('A matrícula atual precisa ser da mesma aluna e da mesma modalidade.', 422, 'MATRICULA_INCOMPATIVEL');
        }
        if ((int) $atual['id'] === $anteriorId || $atual['created_at'] < $anterior['created_at']) {
            return $this->erro('A matrícula atual precisa ser posterior à que será vinculada.', 422, 'ORDEM_INVALIDA');
        }
        if ($atual['matricula_anterior_id'] !== null && (int) $atual['matricula_anterior_id'] !== $anteriorId) {
            return $this->erro("A matrícula #{$atual['id']} já é continuação da #{$atual['matricula_anterior_id']}.", 409, 'JA_VINCULADA');
        }

        $motivo = DB::transaction(function () use ($anterior, $atual, $anteriorId) {
            $motivo = $this->ligar((int) $atual['id'], $anterior, (int) $atual['plano_id'], (float) $atual['valor']);
            $finalizada = DB::table('status_matricula')->where('codigo', 'finalizada')->value('id');
            if ($finalizada && $anterior['status_codigo'] !== 'finalizada') {
                DB::table('matriculas')->where('id', $anteriorId)->update(['status_id' => $finalizada, 'updated_at' => now()]);
            }

            return $motivo;
        });

        Log::info('matricula.vinculada', ['tenant_id' => $tenantId, 'anterior_id' => $anteriorId, 'atual_id' => (int) $atual['id'],
            'status_anterior' => $anterior['status_codigo'], 'motivo' => $motivo, 'usuario_id' => $usuarioId]);

        return ['status' => 200, 'body' => [
            'message' => "Matrícula #{$anteriorId} finalizada e vinculada à #{$atual['id']}. Os pagamentos continuam no ciclo em que foram feitos.",
            'anterior_id' => $anteriorId,
            'atual_id' => (int) $atual['id'],
            'motivo' => $motivo,
        ]];
    }

    /** @return array<string, mixed>|null */
    private function buscar(int $id, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.id', $id)->where('m.tenant_id', $tenantId)
            ->first(['m.id', 'm.aluno_id', 'm.plano_id', 'm.valor', 'm.created_at', 'm.matricula_anterior_id', 'p.modalidade_id', 's.codigo as status_codigo']);

        return $row ? (array) $row : null;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status, string $codigo): array
    {
        return ['status' => $status, 'body' => ['error' => $mensagem, 'message' => $mensagem, 'code' => $codigo]];
    }
}
