<?php

namespace App\Services\Admin;

use App\Services\PagamentoPlanoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pagamentos de plano do painel (paridade com Slim PagamentoPlanoController + Models\PagamentoPlano).
 *
 * status_pagamento_id: 1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado.
 */
class AdminPagamentoPlanoService
{
    public function __construct(
        private readonly PagamentoPlanoService $pagamentosPlano,
        private readonly AdminMatriculaDescontoService $descontos,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, array $query): array
    {
        $sql = 'SELECT p.*,
                       sp.nome as status_nome,
                       fp.nome as forma_pagamento_nome,
                       a.nome as aluno_nome,
                       pl.nome as plano_nome
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                WHERE p.tenant_id = ?';
        $bindings = [$tenantId];

        if (! empty($query['status_pagamento_id'])) {
            $sql .= ' AND p.status_pagamento_id = ?';
            $bindings[] = $query['status_pagamento_id'];
        }
        // A Slim monta o filtro a partir de `usuario_id`, mas o model só filtra por
        // `aluno_id` — na prática o parâmetro não tem efeito. Mantido igual.
        if (! empty($query['aluno_id'])) {
            $sql .= ' AND p.aluno_id = ?';
            $bindings[] = $query['aluno_id'];
        }
        if (! empty($query['data_inicio'])) {
            $sql .= ' AND p.data_vencimento >= ?';
            $bindings[] = $query['data_inicio'];
        }
        if (! empty($query['data_fim'])) {
            $sql .= ' AND p.data_vencimento <= ?';
            $bindings[] = $query['data_fim'];
        }

        $sql .= ' ORDER BY p.data_vencimento DESC';

        return ['status' => 200, 'body' => ['pagamentos' => $this->rows($sql, $bindings)]];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resumo(int $tenantId, array $query): array
    {
        $sql = 'SELECT
                    COUNT(*) as total_pagamentos,
                    SUM(CASE WHEN status_pagamento_id = 1 THEN 1 ELSE 0 END) as aguardando,
                    SUM(CASE WHEN status_pagamento_id = 2 THEN 1 ELSE 0 END) as pagos,
                    SUM(CASE WHEN status_pagamento_id = 3 THEN 1 ELSE 0 END) as atrasados,
                    SUM(CASE WHEN status_pagamento_id = 4 THEN 1 ELSE 0 END) as cancelados,
                    SUM(CASE WHEN status_pagamento_id = 2 THEN valor ELSE 0 END) as valor_recebido,
                    SUM(CASE WHEN status_pagamento_id IN (1, 3) THEN valor ELSE 0 END) as valor_pendente,
                    SUM(valor) as valor_total
                FROM pagamentos_plano
                WHERE tenant_id = ?';
        $bindings = [$tenantId];

        if (! empty($query['data_inicio'])) {
            $sql .= ' AND data_vencimento >= ?';
            $bindings[] = $query['data_inicio'];
        }
        if (! empty($query['data_fim'])) {
            $sql .= ' AND data_vencimento <= ?';
            $bindings[] = $query['data_fim'];
        }

        $row = DB::selectOne($sql, $bindings);

        return ['status' => 200, 'body' => ['resumo' => $row ? (array) $row : []]];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $tenantId, int $id): array
    {
        $pagamento = $this->buscarPorId($tenantId, $id);
        if (! $pagamento) {
            return $this->erro('Pagamento não encontrado', 404);
        }

        return ['status' => 200, 'body' => ['pagamento' => $pagamento]];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarPorMatricula(int $tenantId, int $matriculaId): array
    {
        return [
            'status' => 200,
            'body' => ['pagamentos' => $this->pagamentosDaMatricula($tenantId, $matriculaId)],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarPorUsuario(int $tenantId, int $usuarioId, array $query): array
    {
        $sql = 'SELECT p.*,
                       sp.nome as status_nome,
                       fp.nome as forma_pagamento_nome,
                       pl.nome as plano_nome,
                       m.data_inicio as matricula_data_inicio
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                INNER JOIN matriculas m ON p.matricula_id = m.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                WHERE p.tenant_id = ? AND a.usuario_id = ?';
        $bindings = [$tenantId, $usuarioId];

        if (! empty($query['status_pagamento_id'])) {
            $sql .= ' AND p.status_pagamento_id = ?';
            $bindings[] = $query['status_pagamento_id'];
        }

        $sql .= ' ORDER BY p.data_vencimento DESC';

        return ['status' => 200, 'body' => ['pagamentos' => $this->rows($sql, $bindings)]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $tenantId, int $matriculaId, array $data): array
    {
        $errors = [];
        if (empty($data['valor']) || ! is_numeric($data['valor']) || $data['valor'] <= 0) {
            $errors[] = 'Valor inválido';
        }
        if (isset($data['desconto']) && ! is_numeric($data['desconto'])) {
            $errors[] = 'Desconto inválido';
        }
        if (empty($data['data_vencimento'])) {
            $errors[] = 'Data de vencimento é obrigatória';
        }
        if (empty($data['usuario_id'])) {
            $errors[] = 'ID do aluno é obrigatório';
        }
        if (empty($data['plano_id'])) {
            $errors[] = 'ID do plano é obrigatório';
        }

        if ($errors !== []) {
            return $this->erro(implode(', ', $errors), 422);
        }

        $alunoId = (int) ($data['aluno_id'] ?? $data['usuario_id']);

        $pagamentoId = $this->inserirPagamento([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
            'plano_id' => $data['plano_id'],
            'valor' => $data['valor'],
            'desconto' => $data['desconto'] ?? 0.00,
            'motivo_desconto' => $data['motivo_desconto'] ?? null,
            'data_vencimento' => $data['data_vencimento'],
            'data_pagamento' => $data['data_pagamento'] ?? null,
            'status_pagamento_id' => $data['status_pagamento_id'] ?? 1,
            'forma_pagamento_id' => $data['forma_pagamento_id'] ?? null,
            'comprovante' => $data['comprovante'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'criado_por' => null,
            'aluno_id' => $alunoId,
        ]);

        return [
            'status' => 201,
            'body' => [
                'type' => 'success',
                'message' => 'Pagamento criado com sucesso',
                'pagamento' => $this->buscarPorId($tenantId, $pagamentoId),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $tenantId, int $pagamentoId, ?int $adminId, array $data): array
    {
        $pagamento = $this->buscarPorId($tenantId, $pagamentoId);
        if (! $pagamento) {
            return $this->erro('Pagamento não encontrado', 404);
        }

        $allowed = [
            'valor', 'desconto', 'motivo_desconto', 'data_vencimento', 'data_pagamento',
            'status_pagamento_id', 'forma_pagamento_id', 'comprovante', 'observacoes',
        ];
        $updateData = [];
        foreach ($allowed as $campo) {
            if (array_key_exists($campo, $data)) {
                $updateData[$campo] = $data[$campo];
            }
        }

        if ($updateData === []) {
            return $this->erro('Nenhum campo para atualizar', 422);
        }

        $statusFinal = isset($updateData['status_pagamento_id'])
            ? (int) $updateData['status_pagamento_id']
            : (int) $pagamento['status_pagamento_id'];

        if ($statusFinal === 2 && empty($data['forcar'])) {
            $conflito = $this->checarSequenciaEDuplicidade(
                $tenantId,
                $pagamentoId,
                (int) $pagamento['matricula_id'],
                (string) ($updateData['data_vencimento'] ?? $pagamento['data_vencimento'])
            );
            if ($conflito !== null) {
                return $conflito;
            }
        }

        $confirmando = isset($updateData['status_pagamento_id'])
            && (int) $updateData['status_pagamento_id'] === 2
            && (int) $pagamento['status_pagamento_id'] !== 2;

        if ($confirmando && ! $adminId) {
            return $this->erro('Usuário não autenticado', 401);
        }

        if ($confirmando) {
            DB::beginTransaction();
            try {
                $pagamento = $this->aplicarDescontoPendente($tenantId, $pagamentoId, $pagamento, 'atualizar');

                $this->confirmarPagamentoRow(
                    $tenantId,
                    $pagamentoId,
                    $adminId,
                    $updateData['data_pagamento'] ?? null,
                    $updateData['forma_pagamento_id'] ?? null,
                    $updateData['comprovante'] ?? null,
                    $updateData['observacoes'] ?? null,
                    1
                );

                $this->gerarProximaParcela($tenantId, $pagamento, $adminId);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                return $this->erro($e->getMessage(), 500);
            }

            $pagamentoAtualizado = $this->buscarPorId($tenantId, $pagamentoId);
        } else {
            if (! $this->atualizarCampos($tenantId, $pagamentoId, $updateData)) {
                return $this->erro('Falha ao atualizar pagamento', 500);
            }

            $pagamentoAtualizado = $this->buscarPorId($tenantId, $pagamentoId);
        }

        $matriculaId = (int) $pagamento['matricula_id'];

        $this->sincronizarDatasMatricula($tenantId, $matriculaId);
        $this->pagamentosPlano->atualizarStatusMatricula($tenantId, $matriculaId);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Pagamento atualizado',
                'pagamento' => $pagamentoAtualizado,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function confirmar(int $tenantId, int $pagamentoId, ?int $adminId, array $data): array
    {
        if (! $adminId) {
            return $this->erro('Usuário não autenticado', 401);
        }

        DB::beginTransaction();

        try {
            $pagamento = $this->buscarPorId($tenantId, $pagamentoId);
            if (! $pagamento) {
                DB::rollBack();

                return $this->erro('Pagamento não encontrado', 404);
            }

            if (empty($data['forcar'])) {
                $conflito = $this->checarSequenciaEDuplicidade(
                    $tenantId,
                    $pagamentoId,
                    (int) $pagamento['matricula_id'],
                    (string) $pagamento['data_vencimento']
                );
                if ($conflito !== null) {
                    DB::rollBack();

                    return $conflito;
                }
            }

            $pagamento = $this->aplicarDescontoPendente($tenantId, $pagamentoId, $pagamento, 'confirmar');

            $this->confirmarPagamentoRow(
                $tenantId,
                $pagamentoId,
                $adminId,
                $data['data_pagamento'] ?? null,
                $data['forma_pagamento_id'] ?? null,
                $data['comprovante'] ?? null,
                $data['observacoes'] ?? null,
                1
            );

            // Primeira baixa da matrícula garante a data de início.
            DB::update(
                'UPDATE matriculas
                 SET data_inicio = COALESCE(data_inicio, CURDATE()), updated_at = NOW()
                 WHERE id = ?',
                [(int) $pagamento['matricula_id']]
            );

            $this->gerarProximaParcela($tenantId, $pagamento, $adminId);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->erro($e->getMessage(), 500);
        }

        $this->pagamentosPlano->atualizarStatusMatricula($tenantId, (int) $pagamento['matricula_id']);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Pagamento confirmado com sucesso. Próximo pagamento gerado automaticamente.',
                'pagamento' => $this->buscarPorId($tenantId, $pagamentoId),
                'pagamentos' => $this->pagamentosDaMatricula($tenantId, (int) $pagamento['matricula_id']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelar(int $tenantId, int $pagamentoId, array $data): array
    {
        $pagamento = $this->buscarPorId($tenantId, $pagamentoId);
        if (! $pagamento) {
            return $this->erro('Pagamento não encontrado', 404);
        }

        DB::update(
            'UPDATE pagamentos_plano
             SET status_pagamento_id = 4,
                 observacoes = COALESCE(?, observacoes),
                 updated_at = NOW()
             WHERE tenant_id = ? AND id = ?',
            [$data['observacoes'] ?? 'Pagamento cancelado', $tenantId, $pagamentoId]
        );

        if ($pagamento['matricula_id']) {
            $this->pagamentosPlano->atualizarStatusMatricula($tenantId, (int) $pagamento['matricula_id']);
        }

        return [
            'status' => 200,
            'body' => ['type' => 'success', 'message' => 'Pagamento cancelado com sucesso'],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function excluir(int $tenantId, int $pagamentoId): array
    {
        $pagamento = $this->buscarPorId($tenantId, $pagamentoId);
        if (! $pagamento) {
            return $this->erro('Pagamento não encontrado', 404);
        }

        DB::delete('DELETE FROM pagamentos_plano WHERE tenant_id = ? AND id = ?', [$tenantId, $pagamentoId]);

        $this->pagamentosPlano->atualizarStatusMatricula($tenantId, (int) $pagamento['matricula_id']);

        return [
            'status' => 200,
            'body' => ['type' => 'success', 'message' => 'Pagamento removido com sucesso'],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function marcarAtrasados(int $tenantId): array
    {
        $total = $this->pagamentosPlano->marcarAtrasados($tenantId);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => "Total de {$total} pagamento(s) marcado(s) como atrasado(s)",
                'total' => $total,
            ],
        ];
    }

    /**
     * Bloqueios da baixa manual: parcela anterior em aberto e outra parcela já paga no mês.
     * Ambos retornam 409 e podem ser ignorados com `forcar: true`.
     *
     * @return array{status: int, body: array<string, mixed>}|null
     */
    private function checarSequenciaEDuplicidade(
        int $tenantId,
        int $pagamentoId,
        int $matriculaId,
        string $dataVencimentoRef
    ): ?array {
        $anterior = DB::selectOne(
            "SELECT pp.id, pp.valor, pp.data_vencimento,
                    CASE pp.status_pagamento_id
                        WHEN 1 THEN 'Aguardando'
                        WHEN 3 THEN 'Atrasado'
                        ELSE CONCAT('Status_', pp.status_pagamento_id)
                    END AS status
             FROM pagamentos_plano pp
             WHERE pp.tenant_id = ?
               AND pp.matricula_id = ?
               AND pp.status_pagamento_id NOT IN (2, 4)
               AND pp.id != ?
               AND pp.data_vencimento < ?
             ORDER BY pp.data_vencimento ASC
             LIMIT 1",
            [$tenantId, $matriculaId, $pagamentoId, $dataVencimentoRef]
        );

        if ($anterior) {
            $dataFmt = date('d/m/Y', strtotime((string) $anterior->data_vencimento));

            return [
                'status' => 409,
                'body' => [
                    'type' => 'warning',
                    'message' => 'Existe uma parcela anterior (vencimento '.$dataFmt.') ainda não paga. Confirme as parcelas na sequência.',
                    'parcela_pendente' => [
                        'id' => (int) $anterior->id,
                        'valor' => $anterior->valor,
                        'data_vencimento' => $anterior->data_vencimento,
                        'status' => $anterior->status,
                    ],
                    'confirmar_mesmo_assim' => 'Envie o parâmetro "forcar": true para confirmar fora da sequência',
                ],
            ];
        }

        $duplicado = DB::selectOne(
            'SELECT pp.id, pp.valor, pp.data_vencimento, pp.data_pagamento
             FROM pagamentos_plano pp
             WHERE pp.tenant_id = ?
               AND pp.matricula_id = ?
               AND pp.status_pagamento_id = 2
               AND pp.id != ?
               AND YEAR(pp.data_vencimento) = YEAR(?)
               AND MONTH(pp.data_vencimento) = MONTH(?)
             LIMIT 1',
            [$tenantId, $matriculaId, $pagamentoId, $dataVencimentoRef, $dataVencimentoRef]
        );

        if ($duplicado) {
            $dataFmt = date('d/m/Y', strtotime((string) $duplicado->data_vencimento));

            return [
                'status' => 409,
                'body' => [
                    'type' => 'warning',
                    'message' => 'Já existe um pagamento confirmado neste mês (vencimento '.$dataFmt.'). Não é permitido dois pagamentos pagos no mesmo mês.',
                    'pagamento_existente' => [
                        'id' => (int) $duplicado->id,
                        'valor' => $duplicado->valor,
                        'data_pagamento' => $duplicado->data_pagamento,
                        'data_vencimento' => $duplicado->data_vencimento,
                    ],
                    'confirmar_mesmo_assim' => 'Envie o parâmetro "forcar": true para confirmar mesmo assim',
                ],
            ];
        }

        return null;
    }

    /**
     * Aplica os descontos vigentes à parcela antes da baixa, quando ainda não têm desconto.
     *
     * @param  array<string, mixed>  $pagamento
     * @return array<string, mixed>  Pagamento com valor/desconto já ajustados
     */
    private function aplicarDescontoPendente(
        int $tenantId,
        int $pagamentoId,
        array $pagamento,
        string $origem
    ): array {
        if ((float) ($pagamento['desconto'] ?? 0) != 0) {
            return $pagamento;
        }

        $matriculaId = (int) $pagamento['matricula_id'];

        $anteriores = DB::selectOne(
            'SELECT COUNT(*) as total FROM pagamentos_plano
             WHERE tenant_id = ? AND matricula_id = ? AND id != ? AND data_vencimento < ?',
            [$tenantId, $matriculaId, $pagamentoId, $pagamento['data_vencimento']]
        );
        $isPrimeiraParcela = ((int) ($anteriores->total ?? 0)) === 0;

        $aplicaveis = $this->descontos->buscarAplicaveis(
            $tenantId,
            $matriculaId,
            (string) $pagamento['data_vencimento'],
            $isPrimeiraParcela
        );
        $info = AdminMatriculaDescontoService::calcularDesconto((float) $pagamento['valor'], $aplicaveis);

        if ($info['desconto_total'] <= 0) {
            return $pagamento;
        }

        $valorComDesconto = max(0, (float) $pagamento['valor'] - $info['desconto_total']);

        DB::update(
            'UPDATE pagamentos_plano
             SET valor = ?, valor_original = ?, desconto = ?, motivo_desconto = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?',
            [
                $valorComDesconto,
                (float) $pagamento['valor'],
                $info['desconto_total'],
                $info['motivos'],
                $pagamentoId,
                $tenantId,
            ]
        );

        $this->descontos->salvarDescontosAplicados($pagamentoId, $info['detalhes']);
        $this->descontos->decrementarParcelas($info['ids']);

        Log::info("[{$origem}] Desconto R$".$info['desconto_total']." aplicado ao pagamento #{$pagamentoId}");

        $pagamento['valor'] = $valorComDesconto;

        if ($origem === 'confirmar') {
            $pagamento['desconto'] = $info['desconto_total'];
            $pagamento['motivo_desconto'] = $info['motivos'];
        }

        return $pagamento;
    }

    /**
     * Cria a parcela seguinte (se ainda não existir) e move a próxima data de vencimento da matrícula.
     *
     * @param  array<string, mixed>  $pagamento
     */
    private function gerarProximaParcela(int $tenantId, array $pagamento, ?int $adminId): void
    {
        $plano = DB::selectOne(
            'SELECT p.* FROM planos p WHERE p.id = ? AND p.tenant_id = ?',
            [$pagamento['plano_id'], $tenantId]
        );

        $matCicloInfo = DB::selectOne(
            'SELECT m.plano_ciclo_id, m.aluno_id, m.valor as matricula_valor,
                    pc.meses as ciclo_meses, af.meses as frequencia_meses
             FROM matriculas m
             LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
             LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
             WHERE m.id = ?',
            [$pagamento['matricula_id']]
        );

        $mesesCiclo = $matCicloInfo?->ciclo_meses ?? $matCicloInfo?->frequencia_meses ?? null;
        $alunoIdProxima = $pagamento['aluno_id'] ?? $matCicloInfo?->aluno_id ?? null;
        // Próxima parcela cobra o valor cheio da matrícula (plano/ciclo), não o valor com desconto.
        $valorProximaParcela = $matCicloInfo?->matricula_valor ?? $plano?->valor;

        if (! $plano || (! $mesesCiclo && (int) $plano->duracao_dias <= 0)) {
            return;
        }

        $proximaData = new \DateTime((string) $pagamento['data_vencimento']);
        if ($mesesCiclo) {
            $proximaData->modify("+{$mesesCiclo} months");
        } else {
            $proximaData->modify('+'.$plano->duracao_dias.' days');
        }
        $proximaDataStr = $proximaData->format('Y-m-d');

        if (
            $valorProximaParcela !== null
            && (float) $valorProximaParcela > 0
            && ! $this->existePagamentoParaData($tenantId, (int) $pagamento['matricula_id'], $proximaDataStr)
        ) {
            $aplicaveis = $this->descontos->buscarAplicaveis(
                $tenantId,
                (int) $pagamento['matricula_id'],
                $proximaDataStr,
                false
            );
            $info = AdminMatriculaDescontoService::calcularDesconto((float) $valorProximaParcela, $aplicaveis);

            $novoId = $this->inserirPagamento([
                'tenant_id' => $tenantId,
                'aluno_id' => $alunoIdProxima,
                'matricula_id' => $pagamento['matricula_id'],
                'plano_id' => $pagamento['plano_id'],
                'valor' => $valorProximaParcela,
                'desconto' => $info['desconto_total'],
                'motivo_desconto' => $info['motivos'] ?: null,
                'data_vencimento' => $proximaDataStr,
                'status_pagamento_id' => 1,
                'observacoes' => 'Pagamento gerado automaticamente após confirmação',
                'criado_por' => $adminId,
            ]);

            $this->descontos->salvarDescontosAplicados($novoId, $info['detalhes']);
            $this->descontos->decrementarParcelas($info['ids']);
        }

        DB::update(
            'UPDATE matriculas SET proxima_data_vencimento = ?, updated_at = NOW() WHERE id = ?',
            [$proximaDataStr, $pagamento['matricula_id']]
        );
    }

    /**
     * Realinha data_inicio / data_vencimento / proxima_data_vencimento da matrícula com as parcelas.
     * Matrícula com assinatura ativa é ignorada: as datas são geridas pela assinatura.
     */
    private function sincronizarDatasMatricula(int $tenantId, int $matriculaId): void
    {
        $assinaturaAtiva = DB::selectOne(
            "SELECT COUNT(*) as total FROM assinaturas a
             INNER JOIN assinatura_status ast ON ast.id = a.status_id
             WHERE a.matricula_id = ? AND ast.codigo NOT IN ('cancelada', 'paga')",
            [$matriculaId]
        );

        if ((int) ($assinaturaAtiva->total ?? 0) > 0) {
            return;
        }

        $minVenc = DB::selectOne(
            'SELECT MIN(data_vencimento) as min_venc FROM pagamentos_plano
             WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id != 4',
            [$tenantId, $matriculaId]
        )?->min_venc;

        $proxVenc = DB::selectOne(
            'SELECT MIN(data_vencimento) as prox_venc FROM pagamentos_plano
             WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id IN (1, 3)',
            [$tenantId, $matriculaId]
        )?->prox_venc;

        // Acesso vai até a próxima parcela em aberto; sem parcela em aberto, até o último ciclo pago.
        $dataAcessoAte = $proxVenc;
        if (! $dataAcessoAte) {
            $dataAcessoAte = DB::selectOne(
                'SELECT MAX(data_vencimento) as max_venc FROM pagamentos_plano
                 WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id = 2',
                [$tenantId, $matriculaId]
            )?->max_venc;
        }

        if (! $minVenc && ! $proxVenc && ! $dataAcessoAte) {
            return;
        }

        $sets = [];
        $bindings = [];
        if ($minVenc) {
            $sets[] = 'data_inicio = ?';
            $bindings[] = $minVenc;
        }
        if ($dataAcessoAte) {
            $sets[] = 'data_vencimento = ?';
            $bindings[] = $dataAcessoAte;
        }
        if ($proxVenc) {
            $sets[] = 'proxima_data_vencimento = ?';
            $bindings[] = $proxVenc;
        }
        $sets[] = 'updated_at = NOW()';

        $bindings[] = $matriculaId;
        $bindings[] = $tenantId;

        DB::update(
            'UPDATE matriculas SET '.implode(', ', $sets).' WHERE id = ? AND tenant_id = ?',
            $bindings
        );
    }

    /**
     * `valor` recebido é o valor cheio: é gravado em `valor_original` e o desconto é abatido em `valor`.
     *
     * @param  array<string, mixed>  $dados
     */
    private function inserirPagamento(array $dados): int
    {
        $valorOriginal = isset($dados['valor']) ? (float) $dados['valor'] : 0.0;
        $desconto = isset($dados['desconto']) ? (float) $dados['desconto'] : 0.0;

        DB::insert(
            'INSERT INTO pagamentos_plano
                (tenant_id, aluno_id, matricula_id, plano_id, valor, valor_original, desconto, motivo_desconto,
                 data_vencimento, data_pagamento, status_pagamento_id, forma_pagamento_id, comprovante,
                 observacoes, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $dados['tenant_id'],
                $dados['aluno_id'] ?? null,
                $dados['matricula_id'],
                $dados['plano_id'],
                max(0, $valorOriginal - $desconto),
                $valorOriginal,
                $desconto,
                $dados['motivo_desconto'] ?? null,
                $dados['data_vencimento'],
                $dados['data_pagamento'] ?? null,
                $dados['status_pagamento_id'] ?? 1,
                $dados['forma_pagamento_id'] ?? null,
                $dados['comprovante'] ?? null,
                $dados['observacoes'] ?? null,
                $dados['criado_por'] ?? null,
            ]
        );

        return (int) DB::getPdo()->lastInsertId();
    }

    private function confirmarPagamentoRow(
        int $tenantId,
        int $id,
        int $adminId,
        ?string $dataPagamento,
        int|string|null $formaPagamentoId,
        ?string $comprovante,
        ?string $observacoes,
        ?int $tipoBaixaId
    ): void {
        DB::update(
            'UPDATE pagamentos_plano
             SET status_pagamento_id = 2,
                 data_pagamento = COALESCE(?, CURDATE()),
                 forma_pagamento_id = COALESCE(?, forma_pagamento_id),
                 comprovante = COALESCE(?, comprovante),
                 observacoes = COALESCE(?, observacoes),
                 baixado_por = ?,
                 tipo_baixa_id = ?,
                 updated_at = NOW()
             WHERE tenant_id = ? AND id = ?',
            [
                $dataPagamento,
                $formaPagamentoId,
                $comprovante,
                $observacoes,
                $adminId,
                $tipoBaixaId,
                $tenantId,
                $id,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function atualizarCampos(int $tenantId, int $id, array $dados): bool
    {
        // Desconto sempre recalcula o valor a partir de `valor_original`, para não descontar duas vezes.
        if (array_key_exists('desconto', $dados)) {
            $desconto = (float) $dados['desconto'];
            $atual = DB::selectOne(
                'SELECT valor, valor_original, desconto FROM pagamentos_plano WHERE tenant_id = ? AND id = ? LIMIT 1',
                [$tenantId, $id]
            );
            $baseValor = $atual && $atual->valor_original
                ? (float) $atual->valor_original
                : ($atual ? (float) $atual->valor + (float) ($atual->desconto ?? 0) : 0.0);
            $dados['valor'] = max(0, $baseValor - $desconto);
        }

        $sets = [];
        $bindings = [];

        foreach (['valor', 'data_vencimento', 'status_pagamento_id', 'forma_pagamento_id',
            'comprovante', 'desconto', 'observacoes'] as $campo) {
            if (isset($dados[$campo])) {
                $sets[] = "{$campo} = ?";
                $bindings[] = $dados[$campo];
            }
        }
        foreach (['data_pagamento', 'motivo_desconto'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $sets[] = "{$campo} = ?";
                $bindings[] = $dados[$campo];
            }
        }

        if ($sets === []) {
            return false;
        }

        $bindings[] = $tenantId;
        $bindings[] = $id;

        DB::update(
            'UPDATE pagamentos_plano SET '.implode(', ', $sets).', updated_at = NOW()
             WHERE tenant_id = ? AND id = ?',
            $bindings
        );

        return true;
    }

    private function existePagamentoParaData(int $tenantId, int $matriculaId, string $dataVencimento): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) as total FROM pagamentos_plano
             WHERE tenant_id = ? AND matricula_id = ? AND data_vencimento = ?',
            [$tenantId, $matriculaId, $dataVencimento]
        );

        return (int) ($row->total ?? 0) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarPorId(int $tenantId, int $id): ?array
    {
        $row = DB::selectOne(
            'SELECT p.*,
                    sp.nome as status_nome,
                    fp.nome as forma_pagamento_nome,
                    a.nome as aluno_nome,
                    pl.nome as plano_nome,
                    m.data_inicio as matricula_data_inicio,
                    m.data_vencimento as matricula_data_vencimento
             FROM pagamentos_plano p
             INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
             LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
             INNER JOIN alunos a ON p.aluno_id = a.id
             INNER JOIN planos pl ON p.plano_id = pl.id
             INNER JOIN matriculas m ON p.matricula_id = m.id
             WHERE p.tenant_id = ? AND p.id = ?',
            [$tenantId, $id]
        );

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pagamentosDaMatricula(int $tenantId, int $matriculaId): array
    {
        return PagamentoPlanoService::anexarNumeroParcela($this->rows(
            'SELECT p.*,
                    sp.nome as status_pagamento_nome,
                    fp.nome as forma_pagamento_nome,
                    a.nome as aluno_nome,
                    pl.nome as plano_nome,
                    criador.nome as criado_por_nome,
                    baixador.nome as baixado_por_nome,
                    tb.nome as tipo_baixa_nome
             FROM pagamentos_plano p
             INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
             LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
             INNER JOIN alunos a ON p.aluno_id = a.id
             INNER JOIN planos pl ON p.plano_id = pl.id
             LEFT JOIN usuarios criador ON p.criado_por = criador.id
             LEFT JOIN usuarios baixador ON p.baixado_por = baixador.id
             LEFT JOIN tipos_baixa tb ON p.tipo_baixa_id = tb.id
             WHERE p.tenant_id = ? AND p.matricula_id = ?
             ORDER BY p.data_vencimento ASC, p.id ASC',
            [$tenantId, $matriculaId]
        ));
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function erro(string $message, int $status): array
    {
        return ['status' => $status, 'body' => ['type' => 'error', 'message' => $message]];
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $bindings): array
    {
        return array_map(static fn ($row) => (array) $row, DB::select($sql, $bindings));
    }
}
