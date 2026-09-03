<?php

namespace App\Services\Admin;

use App\Models\Parametro;
use Illuminate\Support\Facades\DB;

class AdminParametroService
{
    private function model(): Parametro
    {
        return new Parametro(DB::connection()->getPdo());
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId): array
    {
        try {
            $parametro = $this->model();
            $categorias = $parametro->getCategorias();
            $result = [];

            foreach ($categorias as $categoria) {
                $result[] = [
                    'categoria' => $categoria,
                    'parametros' => $parametro->getByCategoria($tenantId, $categoria['codigo']),
                ];
            }

            return [
                'status' => 200,
                'body' => ['success' => true, 'data' => $result],
            ];
        } catch (\Throwable $e) {
            error_log('[AdminParametroService::index] '.$e->getMessage());

            return [
                'status' => 500,
                'body' => ['success' => false, 'message' => 'Erro ao carregar parâmetros'],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function categorias(): array
    {
        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $this->model()->getCategorias(),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function byCategoria(int $tenantId, string $categoria): array
    {
        if ($categoria === '') {
            return [
                'status' => 400,
                'body' => ['success' => false, 'message' => 'Categoria não informada'],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'categoria' => $categoria,
                'data' => $this->model()->getByCategoria($tenantId, $categoria),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function getValue(int $tenantId, string $codigo): array
    {
        if ($codigo === '') {
            return [
                'status' => 400,
                'body' => ['success' => false, 'message' => 'Tenant ou código não informado'],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'codigo' => $codigo,
                'valor' => $this->model()->get($tenantId, $codigo),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array{status: int, body: array<string, mixed>}
     */
    public function updateMultiple(int $tenantId, array $parametros, ?int $usuarioId): array
    {
        if ($parametros === []) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'message' => 'Parâmetros não informados. Esperado: {"parametros": {"codigo": "valor", ...}}',
                ],
            ];
        }

        $ok = $this->model()->setMultiple($tenantId, $parametros, $usuarioId);

        if ($ok) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => count($parametros).' parâmetro(s) atualizado(s) com sucesso',
                ],
            ];
        }

        return [
            'status' => 500,
            'body' => ['success' => false, 'message' => 'Erro ao atualizar parâmetros'],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $tenantId, string $codigo, mixed $valor, ?int $usuarioId): array
    {
        if ($codigo === '') {
            return [
                'status' => 400,
                'body' => ['success' => false, 'message' => 'Código do parâmetro não informado'],
            ];
        }

        $ok = $this->model()->set($tenantId, $codigo, $valor, $usuarioId);

        if ($ok) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Parâmetro atualizado com sucesso',
                    'codigo' => $codigo,
                    'valor' => $valor,
                ],
            ];
        }

        return [
            'status' => 404,
            'body' => ['success' => false, 'message' => 'Parâmetro não encontrado'],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function patch(int $tenantId, string $codigo, mixed $valor, ?int $usuarioId): array
    {
        $update = $this->update($tenantId, $codigo, $valor, $usuarioId);
        if ($update['status'] !== 200) {
            return $update;
        }

        $parametro = DB::selectOne(
            'SELECT p.id, p.codigo, p.nome, p.descricao, p.tipo_valor,
                    p.valor_padrao, p.opcoes_select,
                    COALESCE(pt.valor, p.valor_padrao) as valor,
                    tp.codigo as categoria_codigo, tp.nome as categoria_nome
             FROM parametros p
             LEFT JOIN tipos_parametro tp ON tp.id = p.tipo_parametro_id
             LEFT JOIN parametros_tenant pt ON pt.parametro_id = p.id AND pt.tenant_id = ?
             WHERE p.codigo = ?
             LIMIT 1',
            [$tenantId, $codigo]
        );

        $data = $parametro ? (array) $parametro : null;
        if ($data) {
            $model = $this->model();
            $data['valor'] = $model->convertValue($data['valor'], $data['tipo_valor']);
            if ($data['opcoes_select']) {
                $data['opcoes_select'] = json_decode($data['opcoes_select'], true);
            }
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Parâmetro atualizado com sucesso',
                'data' => $data,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function toggle(int $tenantId, string $codigo, ?int $usuarioId): array
    {
        if ($codigo === '') {
            return [
                'status' => 400,
                'body' => ['success' => false, 'message' => 'Código do parâmetro não informado'],
            ];
        }

        $model = $this->model();
        $valorAtual = $model->get($tenantId, $codigo);

        if ($valorAtual === null) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'message' => 'Parâmetro não encontrado'],
            ];
        }

        $novoValor = ! $valorAtual;
        $ok = $model->set($tenantId, $codigo, $novoValor, $usuarioId);

        if ($ok) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Parâmetro alternado com sucesso',
                    'codigo' => $codigo,
                    'valor_anterior' => $valorAtual,
                    'valor' => $novoValor,
                ],
            ];
        }

        return [
            'status' => 500,
            'body' => ['success' => false, 'message' => 'Erro ao alternar parâmetro'],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resumoPagamentos(int $tenantId): array
    {
        $model = $this->model();

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'formas_pagamento' => [
                        'pix' => $model->isEnabled($tenantId, 'habilitar_pix'),
                        'cartao_credito' => $model->isEnabled($tenantId, 'habilitar_cartao_credito'),
                        'cartao_debito' => $model->isEnabled($tenantId, 'habilitar_cartao_debito'),
                        'boleto' => $model->isEnabled($tenantId, 'habilitar_boleto'),
                    ],
                    'cobranca' => [
                        'modo' => $model->get($tenantId, 'modo_cobranca', 'avulso'),
                        'recorrencia_habilitada' => $model->isEnabled($tenantId, 'habilitar_cobranca_recorrente'),
                        'gerar_proxima_automatico' => $model->isEnabled($tenantId, 'gerar_proxima_cobranca'),
                        'dias_antecedencia' => $model->getInt($tenantId, 'dias_antecedencia_cobranca', 5),
                    ],
                    'gateway' => $model->get($tenantId, 'gateway_pagamento', 'mercadopago'),
                    'tolerancia' => [
                        'dias_vencimento' => $model->getInt($tenantId, 'dias_tolerancia_vencimento', 5),
                        'pagamento_parcial' => $model->isEnabled($tenantId, 'permitir_pagamento_parcial'),
                    ],
                ],
            ],
        ];
    }
}
