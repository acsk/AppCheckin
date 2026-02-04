<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Wod;
use App\Models\WodBloco;
use App\Models\WodVariacao;
use App\Models\WodResultado;
use App\Models\Modalidade;
use PDO;

class WodController
{
    private Wod $wodModel;
    private WodBloco $wodBlocoModel;
    private WodVariacao $wodVariacaoModel;
    private WodResultado $wodResultadoModel;
    private Modalidade $modalidadeModel;
    private PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->wodModel = new Wod($this->db);
        $this->wodBlocoModel = new WodBloco($this->db);
        $this->wodVariacaoModel = new WodVariacao($this->db);
        $this->wodResultadoModel = new WodResultado($this->db);
        $this->modalidadeModel = new Modalidade($this->db);
    }

    /**
     * Listar WODs
     * GET /admin/wods
     * Query params: status=published, data_inicio=2026-01-01, data_fim=2026-01-31, data=2026-01-10, modalidade_id=1
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();

        $filters = [];
        if (!empty($queryParams['status'])) {
            $filters['status'] = $queryParams['status'];
        }
        if (!empty($queryParams['data_inicio']) && !empty($queryParams['data_fim'])) {
            $filters['data_inicio'] = $queryParams['data_inicio'];
            $filters['data_fim'] = $queryParams['data_fim'];
        }
        if (!empty($queryParams['data'])) {
            $filters['data'] = $queryParams['data'];
        }
        if (!empty($queryParams['modalidade_id'])) {
            $filters['modalidade_id'] = (int)$queryParams['modalidade_id'];
        }
        if (!empty($queryParams['data'])) {
            $filters['data'] = $queryParams['data'];
        }

        $wods = $this->wodModel->listByTenant($tenantId, $filters);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WODs listados com sucesso',
            'data' => $wods,
            'total' => count($wods),
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Obter detalhes de um WOD
     * GET /admin/wods/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        // Carregar blocos e variações
        $wod['blocos'] = $this->wodBlocoModel->listByWod($wodId);
        $wod['variacoes'] = $this->wodVariacaoModel->listByWod($wodId);
        $wod['resultados'] = $this->wodResultadoModel->listByWod($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD obtido com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Criar novo WOD
     * POST /admin/wods
     */
    public function create(Request $request, Response $response): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $usuarioId = (int)$request->getAttribute('usuarioId');
        $data = $request->getParsedBody();
        $criadoPor = $usuarioId > 0 ? $usuarioId : null; // evita FK inválida quando usuário não está autenticado

        // Validar campos obrigatórios
        $erros = [];
        if (empty($data['titulo'])) $erros[] = 'Título é obrigatório';
        if (empty($data['data'])) $erros[] = 'Data é obrigatória';
        if (empty($data['modalidade_id'])) $erros[] = 'Modalidade é obrigatória';

        if (!empty($erros)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Validação falhou',
                'errors' => $erros,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        // Verificar unicidade (data + modalidade)
        if ($this->wodModel->existePorDataModalidade($data['data'], $data['modalidade_id'], $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe um WOD para essa data e modalidade',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(409);
        }

        $wodData = [
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'data' => $data['data'],
            'modalidade_id' => $data['modalidade_id'],
            'status' => $data['status'] ?? Wod::STATUS_DRAFT,
            'criado_por' => $criadoPor,
        ];

        try {
            $wodId = $this->wodModel->create($wodData, $tenantId);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar WOD',
                'details' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        if (!$wodId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar WOD',
                'details' => 'Não foi possível gerar o ID',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $wod = $this->wodModel->findById($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD criado com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
    }

    /**
     * Criar WOD Completo com blocos e atividades
     * POST /admin/wods/completo
     * 
     * Este endpoint unifica a criação de WOD com todos seus blocos e atividades em uma única requisição.
     * Ideal para criar um WOD completo pronto para publicar.
     */
    public function createCompleto(Request $request, Response $response): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $usuarioId = (int)$request->getAttribute('usuarioId');
        $data = $request->getParsedBody();
        $criadoPor = $usuarioId > 0 ? $usuarioId : null;

        // Validar campos obrigatórios
        $erros = [];
        if (empty($data['titulo'])) $erros[] = 'Título é obrigatório';
        if (empty($data['data'])) $erros[] = 'Data é obrigatória';
        if (empty($data['modalidade_id'])) $erros[] = 'Modalidade é obrigatória';
        if (empty($data['blocos']) || !is_array($data['blocos']) || count($data['blocos']) === 0) {
            $erros[] = 'Pelo menos um bloco é obrigatório';
        }

        if (!empty($erros)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Validação falhou',
                'errors' => $erros,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        // Verificar unicidade de data + modalidade
        if ($this->wodModel->existePorDataModalidade($data['data'], $data['modalidade_id'], $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe um WOD para essa data e modalidade',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(409);
        }

        // Iniciar transação
        try {
            $this->db->beginTransaction();

            // 1. Criar WOD
            $wodData = [
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'] ?? null,
                'data' => $data['data'],
                'modalidade_id' => $data['modalidade_id'],
                'status' => $data['status'] ?? Wod::STATUS_DRAFT,
                'criado_por' => $criadoPor,
            ];

            $wodId = $this->wodModel->create($wodData, $tenantId);

            if (!$wodId) {
                throw new \Exception('Erro ao criar WOD');
            }

            // 2. Criar blocos com suas especificações de atividades
            $tiposValidos = ['warmup', 'strength', 'metcon', 'accessory', 'cooldown', 'note'];
            
            foreach ($data['blocos'] as $ordem => $bloco) {
                // Sanitizar tipo: converter para minúsculas e validar
                $tipo = isset($bloco['tipo']) ? strtolower(trim($bloco['tipo'])) : 'metcon';
                if (!in_array($tipo, $tiposValidos)) {
                    $tipo = 'metcon'; // fallback para padrão se tipo inválido
                }
                
                $blocoData = [
                    'wod_id' => $wodId,
                    'ordem' => $bloco['ordem'] ?? ($ordem + 1),
                    'tipo' => $tipo,
                    'titulo' => $bloco['titulo'] ?? null,
                    'conteudo' => $bloco['conteudo'] ?? '',
                    'tempo_cap' => $bloco['tempo_cap'] ?? null,
                ];

                $blocoId = $this->wodBlocoModel->create($blocoData);

                if (!$blocoId) {
                    throw new \Exception('Erro ao criar bloco de WOD');
                }

                // Se houver atividades (variações) para este bloco
                if (!empty($bloco['atividades']) && is_array($bloco['atividades'])) {
                    foreach ($bloco['atividades'] as $atividade) {
                        // Aqui você pode salvar as atividades específicas do bloco
                        // se houver um modelo para isso
                    }
                }
            }

            // 3. Criar variações (RX, Scaled, etc) se fornecidas
            if (!empty($data['variacoes']) && is_array($data['variacoes'])) {
                foreach ($data['variacoes'] as $variacao) {
                    $variacaoData = [
                        'wod_id' => $wodId,
                        'nome' => $variacao['nome'] ?? 'RX',
                        'descricao' => $variacao['descricao'] ?? null,
                    ];

                    $this->wodVariacaoModel->create($variacaoData);
                }
            }

            // Commitar transação
            $this->db->commit();

            // Carregar WOD completo
            $wod = $this->wodModel->findById($wodId, $tenantId);
            $wod['blocos'] = $this->wodBlocoModel->listByWod($wodId);
            $wod['variacoes'] = $this->wodVariacaoModel->listByWod($wodId);
            $wod['resultados'] = [];

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'WOD completo criado com sucesso',
                'data' => $wod,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);

        } catch (\Exception $e) {
            // Reverter transação em caso de erro
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('Erro ao criar WOD completo: ' . $e->getMessage());

            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar WOD completo',
                'details' => $e->getMessage(),
                'debug' => true,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Atualizar WOD
     * PUT /admin/wods/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody();

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        // Validar data + modalidade única se foi alterada
        if (!empty($data['data']) && $data['data'] !== $wod['data']) {
            $modalidadeId = $data['modalidade_id'] ?? $wod['modalidade_id'];
            if ($this->wodModel->existePorDataModalidade($data['data'], $modalidadeId, $tenantId, $wodId)) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Já existe um WOD para essa data e modalidade',
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(409);
            }
        }

        // Processar variações se fornecidas
        if (isset($data['variacoes'])) {
            $variacoes = $data['variacoes'];
            
            // Se é um array vazio, deleta todas as variações existentes
            if (is_array($variacoes) && empty($variacoes)) {
                $this->wodVariacaoModel->deleteByWod($wodId);
            } elseif (is_array($variacoes) && !empty($variacoes)) {
                // Deleta as variações antigas e cria as novas
                $this->wodVariacaoModel->deleteByWod($wodId);
                
                foreach ($variacoes as $variacao) {
                    if (isset($variacao['nome'])) {
                        try {
                            $this->wodVariacaoModel->create([
                                'wod_id' => $wodId,
                                'nome' => $variacao['nome'],
                                'descricao' => $variacao['descricao'] ?? null,
                            ]);
                        } catch (\Exception $e) {
                            error_log('Erro ao criar variação: ' . $e->getMessage());
                        }
                    }
                }
            }
            
            // Remove 'variacoes' do array de dados para não tentar atualizar na tabela wods
            unset($data['variacoes']);
        }

        if (!$this->wodModel->update($wodId, $tenantId, $data)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $wod = $this->wodModel->findById($wodId, $tenantId);
        // Carregar blocos e variações atualizados
        $wod['blocos'] = $this->wodBlocoModel->listByWod($wodId);
        $wod['variacoes'] = $this->wodVariacaoModel->listByWod($wodId);
        $wod['resultados'] = $this->wodResultadoModel->listByWod($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD atualizado com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Deletar WOD
     * DELETE /admin/wods/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodModel->delete($wodId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD deletado com sucesso',
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Publicar WOD
     * PATCH /admin/wods/{id}/publish
     */
    public function publish(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodModel->publicar($wodId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao publicar WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $wod = $this->wodModel->findById($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD publicado com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Arquivar WOD
     * PATCH /admin/wods/{id}/archive
     */
    public function archive(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodModel->arquivar($wodId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao arquivar WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $wod = $this->wodModel->findById($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD arquivado com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Listar modalidades disponíveis para WODs
     * GET /admin/wods/modalidades
     */
    public function listarModalidades(Request $request, Response $response): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        
        $modalidades = $this->modalidadeModel->listarPorTenant($tenantId, true); // apenas ativas

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Modalidades listadas com sucesso',
            'data' => $modalidades,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Buscar WOD por data e modalidade
     * GET /admin/wods/buscar?data=2026-01-15&modalidade_id=1
     * 
     * Usado para exibir o WOD do dia em uma turma específica.
     * Frontend passa a data atual e a modalidade da turma.
     */
    public function buscarPorDataModalidade(Request $request, Response $response): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();

        // Validar parâmetros obrigatórios
        $erros = [];
        if (empty($queryParams['data'])) $erros[] = 'Parâmetro data é obrigatório';
        if (empty($queryParams['modalidade_id'])) $erros[] = 'Parâmetro modalidade_id é obrigatório';

        if (!empty($erros)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Validação falhou',
                'errors' => $erros,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        $data = $queryParams['data'];
        $modalidadeId = (int)$queryParams['modalidade_id'];

        // Buscar WOD
        $wod = $this->wodModel->findByDataModalidade($data, $modalidadeId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nenhum WOD encontrado para essa data e modalidade',
                'data' => null,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        // Carregar blocos, variações e resultados
        $wod['blocos'] = $this->wodBlocoModel->listByWod($wod['id']);
        $wod['variacoes'] = $this->wodVariacaoModel->listByWod($wod['id']);
        $wod['resultados'] = $this->wodResultadoModel->listByWod($wod['id'], $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD encontrado',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }
}
