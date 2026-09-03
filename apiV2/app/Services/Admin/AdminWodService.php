<?php

namespace App\Services\Admin;

use App\Repositories\ModalidadeRepository;
use App\Repositories\WodBlocoRepository;
use App\Repositories\WodRepository;
use App\Repositories\WodResultadoRepository;
use App\Repositories\WodVariacaoRepository;
use Illuminate\Support\Facades\DB;

class AdminWodService
{
    public function __construct(
        private readonly WodRepository $wods,
        private readonly WodBlocoRepository $blocos,
        private readonly WodVariacaoRepository $variacoes,
        private readonly WodResultadoRepository $resultados,
        private readonly ModalidadeRepository $modalidades,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, array $query): array
    {
        $filters = [];
        if (! empty($query['status'])) {
            $filters['status'] = $query['status'];
        }
        if (! empty($query['data_inicio']) && ! empty($query['data_fim'])) {
            $filters['data_inicio'] = $query['data_inicio'];
            $filters['data_fim'] = $query['data_fim'];
        }
        if (! empty($query['data'])) {
            $filters['data'] = $query['data'];
        }
        if (! empty($query['modalidade_id'])) {
            $filters['modalidade_id'] = (int) $query['modalidade_id'];
        }

        $wods = $this->wods->listByTenant($tenantId, $filters);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'WODs listados com sucesso',
                'data' => $wods,
                'total' => count($wods),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $wodId, int $tenantId): array
    {
        $wod = $this->wods->findById($wodId, $tenantId);
        if (! $wod) {
            return $this->error('WOD não encontrado', 404);
        }

        $wod['blocos'] = $this->blocos->listByWod($wodId);
        $wod['variacoes'] = $this->variacoes->listByWod($wodId);
        $wod['resultados'] = $this->resultados->listByWod($wodId, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'WOD obtido com sucesso',
                'data' => $wod,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(int $tenantId, int $usuarioId, array $data): array
    {
        $criadoPor = $usuarioId > 0 ? $usuarioId : null;

        $erros = [];
        if (empty($data['titulo'])) {
            $erros[] = 'Título é obrigatório';
        }
        if (empty($data['data'])) {
            $erros[] = 'Data é obrigatória';
        }
        if (empty($data['modalidade_id'])) {
            $erros[] = 'Modalidade é obrigatória';
        }

        if ($erros !== []) {
            return $this->validationError($erros);
        }

        if ($this->wods->existePorDataModalidade($data['data'], (int) $data['modalidade_id'], $tenantId)) {
            return $this->error('Já existe um WOD para essa data e modalidade', 409);
        }

        try {
            $wodId = $this->wods->create([
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'] ?? null,
                'data' => $data['data'],
                'modalidade_id' => $data['modalidade_id'],
                'status' => $data['status'] ?? WodRepository::STATUS_DRAFT,
                'criado_por' => $criadoPor,
            ], $tenantId);
        } catch (\Throwable $e) {
            return $this->error('Erro ao criar WOD', 500, $e->getMessage());
        }

        if (! $wodId) {
            return $this->error('Erro ao criar WOD', 500, 'Não foi possível gerar o ID');
        }

        return [
            'status' => 201,
            'body' => [
                'type' => 'success',
                'message' => 'WOD criado com sucesso',
                'data' => $this->wods->findById($wodId, $tenantId),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function createCompleto(int $tenantId, int $usuarioId, array $data): array
    {
        $criadoPor = $usuarioId > 0 ? $usuarioId : null;

        $erros = [];
        if (empty($data['titulo'])) {
            $erros[] = 'Título é obrigatório';
        }
        if (empty($data['data'])) {
            $erros[] = 'Data é obrigatória';
        }
        if (empty($data['modalidade_id'])) {
            $erros[] = 'Modalidade é obrigatória';
        }
        if (empty($data['blocos']) || ! is_array($data['blocos']) || count($data['blocos']) === 0) {
            $erros[] = 'Pelo menos um bloco é obrigatório';
        }

        if ($erros !== []) {
            return $this->validationError($erros);
        }

        if ($this->wods->existePorDataModalidade($data['data'], (int) $data['modalidade_id'], $tenantId)) {
            return $this->error('Já existe um WOD para essa data e modalidade', 409);
        }

        $tiposValidos = ['warmup', 'strength', 'metcon', 'accessory', 'cooldown', 'note'];

        try {
            $wodId = DB::transaction(function () use ($tenantId, $data, $criadoPor, $tiposValidos) {
                $wodId = $this->wods->create([
                    'titulo' => $data['titulo'],
                    'descricao' => $data['descricao'] ?? null,
                    'data' => $data['data'],
                    'modalidade_id' => $data['modalidade_id'],
                    'status' => $data['status'] ?? WodRepository::STATUS_DRAFT,
                    'criado_por' => $criadoPor,
                ], $tenantId);

                if (! $wodId) {
                    throw new \RuntimeException('Erro ao criar WOD');
                }

                foreach ($data['blocos'] as $ordem => $bloco) {
                    $tipo = isset($bloco['tipo']) ? strtolower(trim((string) $bloco['tipo'])) : 'metcon';
                    if (! in_array($tipo, $tiposValidos, true)) {
                        $tipo = 'metcon';
                    }

                    $blocoId = $this->blocos->create([
                        'wod_id' => $wodId,
                        'ordem' => $bloco['ordem'] ?? ($ordem + 1),
                        'tipo' => $tipo,
                        'titulo' => $bloco['titulo'] ?? null,
                        'conteudo' => $bloco['conteudo'] ?? '',
                        'tempo_cap' => $bloco['tempo_cap'] ?? null,
                    ]);

                    if (! $blocoId) {
                        throw new \RuntimeException('Erro ao criar bloco de WOD');
                    }
                }

                if (! empty($data['variacoes']) && is_array($data['variacoes'])) {
                    foreach ($data['variacoes'] as $variacao) {
                        $this->variacoes->create([
                            'wod_id' => $wodId,
                            'nome' => $variacao['nome'] ?? 'RX',
                            'descricao' => $variacao['descricao'] ?? null,
                        ]);
                    }
                }

                return $wodId;
            });
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => [
                    'type' => 'error',
                    'message' => 'Erro ao criar WOD completo',
                    'details' => $e->getMessage(),
                    'debug' => true,
                ],
            ];
        }

        $wod = $this->wods->findById($wodId, $tenantId);
        $wod['blocos'] = $this->blocos->listByWod($wodId);
        $wod['variacoes'] = $this->variacoes->listByWod($wodId);
        $wod['resultados'] = [];

        return [
            'status' => 201,
            'body' => [
                'type' => 'success',
                'message' => 'WOD completo criado com sucesso',
                'data' => $wod,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $wodId, int $tenantId, array $data): array
    {
        $wod = $this->wods->findById($wodId, $tenantId);
        if (! $wod) {
            return $this->error('WOD não encontrado', 404);
        }

        if (! empty($data['data']) && $data['data'] !== $wod['data']) {
            $modalidadeId = $data['modalidade_id'] ?? $wod['modalidade_id'];
            if ($this->wods->existePorDataModalidade($data['data'], (int) $modalidadeId, $tenantId, $wodId)) {
                return $this->error('Já existe um WOD para essa data e modalidade', 409);
            }
        }

        if (isset($data['variacoes'])) {
            $variacoes = $data['variacoes'];

            if (is_array($variacoes) && $variacoes === []) {
                $this->variacoes->deleteByWod($wodId);
            } elseif (is_array($variacoes) && $variacoes !== []) {
                $this->variacoes->deleteByWod($wodId);

                foreach ($variacoes as $variacao) {
                    if (isset($variacao['nome'])) {
                        try {
                            $this->variacoes->create([
                                'wod_id' => $wodId,
                                'nome' => $variacao['nome'],
                                'descricao' => $variacao['descricao'] ?? null,
                            ]);
                        } catch (\Throwable) {
                            // Slim ignora erro individual de variação
                        }
                    }
                }
            }

            unset($data['variacoes']);
        }

        if (! $this->wods->update($wodId, $tenantId, $data)) {
            return $this->error('Erro ao atualizar WOD', 500);
        }

        $wod = $this->wods->findById($wodId, $tenantId);
        $wod['blocos'] = $this->blocos->listByWod($wodId);
        $wod['variacoes'] = $this->variacoes->listByWod($wodId);
        $wod['resultados'] = $this->resultados->listByWod($wodId, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'WOD atualizado com sucesso',
                'data' => $wod,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $wodId, int $tenantId): array
    {
        $wod = $this->wods->findById($wodId, $tenantId);
        if (! $wod) {
            return $this->error('WOD não encontrado', 404);
        }

        if (! $this->wods->delete($wodId, $tenantId)) {
            return $this->error('Erro ao deletar WOD', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'WOD deletado com sucesso',
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function publish(int $wodId, int $tenantId): array
    {
        $wod = $this->wods->findById($wodId, $tenantId);
        if (! $wod) {
            return $this->error('WOD não encontrado', 404);
        }

        if (! $this->wods->publicar($wodId, $tenantId)) {
            return $this->error('Erro ao publicar WOD', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'WOD publicado com sucesso',
                'data' => $this->wods->findById($wodId, $tenantId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function archive(int $wodId, int $tenantId): array
    {
        $wod = $this->wods->findById($wodId, $tenantId);
        if (! $wod) {
            return $this->error('WOD não encontrado', 404);
        }

        if (! $this->wods->arquivar($wodId, $tenantId)) {
            return $this->error('Erro ao arquivar WOD', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'WOD arquivado com sucesso',
                'data' => $this->wods->findById($wodId, $tenantId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarModalidades(int $tenantId): array
    {
        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Modalidades listadas com sucesso',
                'data' => $this->modalidades->listarPorTenant($tenantId, true),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarPorDataModalidade(int $tenantId, array $query): array
    {
        $erros = [];
        if (empty($query['data'])) {
            $erros[] = 'Parâmetro data é obrigatório';
        }
        if (empty($query['modalidade_id'])) {
            $erros[] = 'Parâmetro modalidade_id é obrigatório';
        }

        if ($erros !== []) {
            return $this->validationError($erros);
        }

        $wod = $this->wods->findByDataModalidade(
            (string) $query['data'],
            (int) $query['modalidade_id'],
            $tenantId,
        );

        if (! $wod) {
            return [
                'status' => 404,
                'body' => [
                    'type' => 'error',
                    'message' => 'Nenhum WOD encontrado para essa data e modalidade',
                    'data' => null,
                ],
            ];
        }

        $wod['blocos'] = $this->blocos->listByWod((int) $wod['id']);
        $wod['variacoes'] = $this->variacoes->listByWod((int) $wod['id']);
        $wod['resultados'] = $this->resultados->listByWod((int) $wod['id'], $tenantId);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'WOD encontrado',
                'data' => $wod,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarBlocos(int $wodId, int $tenantId): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $blocos = $this->blocos->listByWod($wodId);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Blocos listados com sucesso',
                'data' => $blocos,
                'total' => count($blocos),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarBloco(int $wodId, int $tenantId, array $data): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $erros = [];
        if (empty($data['tipo'])) {
            $erros[] = 'Tipo é obrigatório';
        }
        if (empty($data['conteudo'])) {
            $erros[] = 'Conteúdo é obrigatório';
        }

        if ($erros !== []) {
            return $this->validationError($erros);
        }

        $blocoId = $this->blocos->create([
            'wod_id' => $wodId,
            'ordem' => $data['ordem'] ?? 1,
            'tipo' => $data['tipo'],
            'titulo' => $data['titulo'] ?? null,
            'conteudo' => $data['conteudo'],
            'tempo_cap' => $data['tempo_cap'] ?? null,
        ]);

        if (! $blocoId) {
            return $this->error('Erro ao criar bloco', 500);
        }

        return [
            'status' => 201,
            'body' => [
                'type' => 'success',
                'message' => 'Bloco criado com sucesso',
                'data' => $this->blocos->findById($blocoId),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarBloco(int $wodId, int $blocoId, int $tenantId, array $data): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $bloco = $this->blocos->findById($blocoId);
        if (! $bloco || (int) $bloco['wod_id'] !== $wodId) {
            return $this->error('Bloco não encontrado', 404);
        }

        if (! $this->blocos->update($blocoId, $data)) {
            return $this->error('Erro ao atualizar bloco', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Bloco atualizado com sucesso',
                'data' => $this->blocos->findById($blocoId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function deletarBloco(int $wodId, int $blocoId, int $tenantId): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $bloco = $this->blocos->findById($blocoId);
        if (! $bloco || (int) $bloco['wod_id'] !== $wodId) {
            return $this->error('Bloco não encontrado', 404);
        }

        if (! $this->blocos->delete($blocoId)) {
            return $this->error('Erro ao deletar bloco', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Bloco deletado com sucesso',
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarVariacoes(int $wodId, int $tenantId): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $variacoes = $this->variacoes->listByWod($wodId);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Variações listadas com sucesso',
                'data' => $variacoes,
                'total' => count($variacoes),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarVariacao(int $wodId, int $tenantId, array $data): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        if (empty($data['nome'])) {
            return $this->validationError(['Nome é obrigatório']);
        }

        if ($this->variacoes->findByNome($wodId, (string) $data['nome'])) {
            return $this->error('Já existe uma variação com esse nome para este WOD', 409);
        }

        $variacaoId = $this->variacoes->create([
            'wod_id' => $wodId,
            'nome' => $data['nome'],
            'descricao' => $data['descricao'] ?? null,
        ]);

        if (! $variacaoId) {
            return $this->error('Erro ao criar variação', 500);
        }

        return [
            'status' => 201,
            'body' => [
                'type' => 'success',
                'message' => 'Variação criada com sucesso',
                'data' => $this->variacoes->findById($variacaoId),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarVariacao(int $wodId, int $variacaoId, int $tenantId, array $data): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $variacao = $this->variacoes->findById($variacaoId);
        if (! $variacao || (int) $variacao['wod_id'] !== $wodId) {
            return $this->error('Variação não encontrada', 404);
        }

        if (! $this->variacoes->update($variacaoId, $data)) {
            return $this->error('Erro ao atualizar variação', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Variação atualizada com sucesso',
                'data' => $this->variacoes->findById($variacaoId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function deletarVariacao(int $wodId, int $variacaoId, int $tenantId): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $variacao = $this->variacoes->findById($variacaoId);
        if (! $variacao || (int) $variacao['wod_id'] !== $wodId) {
            return $this->error('Variação não encontrada', 404);
        }

        if (! $this->variacoes->delete($variacaoId)) {
            return $this->error('Erro ao deletar variação', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Variação deletada com sucesso',
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarResultados(int $wodId, int $tenantId): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $resultados = $this->resultados->listByWod($wodId, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Resultados listados com sucesso',
                'data' => $resultados,
                'total' => count($resultados),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarResultado(int $wodId, int $tenantId, int $usuarioId, array $data): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $erros = [];
        if (empty($data['usuario_id'])) {
            $erros[] = 'ID do usuário é obrigatório';
        }
        if (empty($data['tipo_score'])) {
            $erros[] = 'Tipo de score é obrigatório';
        }

        if ($erros !== []) {
            return $this->validationError($erros);
        }

        if ($this->resultados->findByUsuarioWod((int) $data['usuario_id'], $wodId)) {
            return $this->error('Esse aluno já possui resultado registrado para esse WOD', 409);
        }

        $resultadoId = $this->resultados->create([
            'tenant_id' => $tenantId,
            'wod_id' => $wodId,
            'usuario_id' => $data['usuario_id'],
            'variacao_id' => $data['variacao_id'] ?? null,
            'tipo_score' => $data['tipo_score'],
            'valor_num' => $data['valor_num'] ?? null,
            'valor_texto' => $data['valor_texto'] ?? null,
            'observacao' => $data['observacao'] ?? null,
        ]);

        if (! $resultadoId) {
            return $this->error('Erro ao registrar resultado', 500);
        }

        return [
            'status' => 201,
            'body' => [
                'type' => 'success',
                'message' => 'Resultado registrado com sucesso',
                'data' => $this->resultados->findById($resultadoId),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarResultado(int $wodId, int $resultadoId, int $tenantId, array $data): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $resultado = $this->resultados->findById($resultadoId);
        if (! $resultado || (int) $resultado['wod_id'] !== $wodId) {
            return $this->error('Resultado não encontrado', 404);
        }

        if (! $this->resultados->update($resultadoId, $data)) {
            return $this->error('Erro ao atualizar resultado', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Resultado atualizado com sucesso',
                'data' => $this->resultados->findById($resultadoId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function deletarResultado(int $wodId, int $resultadoId, int $tenantId): array
    {
        if (! $this->wods->findById($wodId, $tenantId)) {
            return $this->error('WOD não encontrado', 404);
        }

        $resultado = $this->resultados->findById($resultadoId);
        if (! $resultado || (int) $resultado['wod_id'] !== $wodId) {
            return $this->error('Resultado não encontrado', 404);
        }

        if (! $this->resultados->delete($resultadoId)) {
            return $this->error('Erro ao deletar resultado', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Resultado deletado com sucesso',
            ],
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return array{status: int, body: array<string, mixed>}
     */
    private function validationError(array $errors): array
    {
        return [
            'status' => 422,
            'body' => [
                'type' => 'error',
                'message' => 'Validação falhou',
                'errors' => $errors,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function error(string $message, int $status, ?string $details = null): array
    {
        $body = [
            'type' => 'error',
            'message' => $message,
        ];

        if ($details !== null) {
            $body['details'] = $details;
        }

        return ['status' => $status, 'body' => $body];
    }
}
