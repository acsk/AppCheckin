# Implementação Backend - Assinaturas + Matrículas

## 📁 Estrutura de Arquivos

```
backend/
├── app/
│   ├── Controllers/
│   │   ├── MatriculaController.php        (MODIFICAR)
│   │   └── AssinaturaController.php       (JÁ EXISTE)
│   │
│   ├── Models/
│   │   ├── Matricula.php
│   │   └── Assinatura.php
│   │
│   └── Middleware/
│       └── TenantMiddleware.php
│
├── routes/
│   └── api.php                             (MODIFICAR)
│
└── database/
    └── migrations/
        └── integracao_assinaturas_matriculas.sql
```

---

## 🔧 Modificações em MatriculaController.php

### 1. Adicionar Método: `criar()` Modificado

```php
<?php

namespace App\Controllers;

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;

class MatriculaController extends BaseController
{
    /**
     * POST /admin/matriculas
     * Criar matrícula com opção de criar assinatura automaticamente
     */
    public function criar(Request $request, Response $response, array $args)
    {
        try {
            $body = $request->getParsedBody();
            $tenantId = $request->getAttribute('tenant_id');
            $usuarioId = $request->getAttribute('usuario_id');

            // ✅ Validações
            if (empty($body['aluno_id']) || empty($body['plano_id'])) {
                return $this->error($response, 'Aluno e plano são obrigatórios', 400);
            }

            // Verificar se aluno existe
            $stmt = $this->db->prepare("
                SELECT id FROM alunos WHERE id = ? AND academia_id = ?
            ");
            $stmt->execute([$body['aluno_id'], $tenantId]);
            if (!$stmt->fetch()) {
                return $this->error($response, 'Aluno não encontrado', 404);
            }

            // Verificar se plano existe
            $stmt = $this->db->prepare("
                SELECT id, valor, ciclo_tipo FROM planos WHERE id = ? AND academia_id = ?
            ");
            $stmt->execute([$body['plano_id'], $tenantId]);
            $plano = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$plano) {
                return $this->error($response, 'Plano não encontrado', 404);
            }

            // Verificar se já tem matrícula ativa
            $stmt = $this->db->prepare("
                SELECT id FROM matriculas 
                WHERE aluno_id = ? AND academia_id = ? AND status IN ('ativa', 'suspensa')
                LIMIT 1
            ");
            $stmt->execute([$body['aluno_id'], $tenantId]);
            if ($stmt->fetch()) {
                return $this->error($response, 'Aluno já possui matrícula ativa nesta academia', 409);
            }

            // Preparar dados da matrícula
            $dataInicio = $body['data_inicio'] ?? date('Y-m-d');
            $formaPagamento = $body['forma_pagamento'] ?? 'dinheiro';
            $proximaDataVencimento = $this->calcularDataVencimento(
                $dataInicio,
                $plano['ciclo_tipo']
            );

            // ✅ Iniciar transação
            $this->db->beginTransaction();

            try {
                // 1️⃣ Criar matrícula
                $sqlMatricula = "
                    INSERT INTO matriculas 
                    (aluno_id, academia_id, plano_id, data_inicio, 
                     proxima_data_vencimento, forma_pagamento, status, criado_por, criado_em, atualizado_em)
                    VALUES (?, ?, ?, ?, ?, ?, 'ativa', ?, NOW(), NOW())
                ";

                $stmtMatricula = $this->db->prepare($sqlMatricula);
                $stmtMatricula->execute([
                    $body['aluno_id'],
                    $tenantId,
                    $body['plano_id'],
                    $dataInicio,
                    $proximaDataVencimento,
                    $formaPagamento,
                    $usuarioId
                ]);

                $matriculaId = $this->db->lastInsertId();

                // 2️⃣ Criar assinatura automaticamente (se solicitado)
                $assinatura = null;
                if ($body['criar_assinatura'] !== false) {
                    $sqlAssinatura = "
                        INSERT INTO assinaturas 
                        (matricula_id, aluno_id, academia_id, plano_id, 
                         status, data_inicio, data_vencimento, 
                         valor_mensal, forma_pagamento, ciclo_tipo,
                         permite_recorrencia, renovacoes_restantes,
                         criado_por, criado_em, atualizado_em)
                        VALUES (?, ?, ?, ?, 'ativa', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ";

                    $stmtAssinatura = $this->db->prepare($sqlAssinatura);
                    $stmtAssinatura->execute([
                        $matriculaId,
                        $body['aluno_id'],
                        $tenantId,
                        $body['plano_id'],
                        $dataInicio,
                        $proximaDataVencimento,
                        $plano['valor'],
                        $formaPagamento,
                        $plano['ciclo_tipo'],
                        true,  // permite_recorrencia
                        $body['renovacoes'] ?? 0,
                        $usuarioId
                    ]);

                    $assinaturaId = $this->db->lastInsertId();
                    $assinatura = ['id' => $assinaturaId];

                    // 3️⃣ Vincular assinatura à matrícula
                    $updateMatricula = "
                        UPDATE matriculas SET assinatura_id = ? WHERE id = ?
                    ";
                    $stmtUpdate = $this->db->prepare($updateMatricula);
                    $stmtUpdate->execute([$assinaturaId, $matriculaId]);
                }

                // ✅ Confirmar transação
                $this->db->commit();

                // Retornar dados criados
                $response->getBody()->write(json_encode([
                    'type' => 'success',
                    'message' => $assinatura 
                        ? 'Matrícula e assinatura criadas com sucesso'
                        : 'Matrícula criada com sucesso',
                    'data' => [
                        'matricula' => [
                            'id' => $matriculaId,
                            'aluno_id' => $body['aluno_id'],
                            'plano_id' => $body['plano_id'],
                            'status' => 'ativa',
                            'data_inicio' => $dataInicio,
                            'proxima_data_vencimento' => $proximaDataVencimento
                        ],
                        'assinatura' => $assinatura
                    ]
                ]));

                return $response->withStatus(201);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /admin/matriculas/{id}/assinatura
     * Criar assinatura para matrícula existente
     */
    public function criarAssinatura(Request $request, Response $response, array $args)
    {
        try {
            $matriculaId = $args['id'];
            $body = $request->getParsedBody();
            $tenantId = $request->getAttribute('tenant_id');
            $usuarioId = $request->getAttribute('usuario_id');

            // ✅ Validações
            $stmt = $this->db->prepare("
                SELECT m.*, p.valor, p.ciclo_tipo 
                FROM matriculas m
                JOIN planos p ON m.plano_id = p.id
                WHERE m.id = ? AND m.academia_id = ?
            ");
            $stmt->execute([$matriculaId, $tenantId]);
            $matricula = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$matricula) {
                return $this->error($response, 'Matrícula não encontrada', 404);
            }

            // Verificar se já tem assinatura
            if ($matricula['assinatura_id']) {
                return $this->error($response, 'Esta matrícula já possui assinatura', 409);
            }

            // ✅ Iniciar transação
            $this->db->beginTransaction();

            try {
                // Criar assinatura
                $sqlAssinatura = "
                    INSERT INTO assinaturas 
                    (matricula_id, aluno_id, academia_id, plano_id, 
                     status, data_inicio, data_vencimento, 
                     valor_mensal, forma_pagamento, ciclo_tipo,
                     permite_recorrencia, renovacoes_restantes,
                     criado_por, criado_em, atualizado_em)
                    VALUES (?, ?, ?, ?, 'ativa', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ";

                $stmtAssinatura = $this->db->prepare($sqlAssinatura);
                $stmtAssinatura->execute([
                    $matriculaId,
                    $matricula['aluno_id'],
                    $tenantId,
                    $matricula['plano_id'],
                    $matricula['data_inicio'],
                    $matricula['proxima_data_vencimento'],
                    $matricula['valor'],
                    $matricula['forma_pagamento'],
                    $matricula['ciclo_tipo'],
                    true,
                    $body['renovacoes'] ?? 0,
                    $usuarioId
                ]);

                $assinaturaId = $this->db->lastInsertId();

                // Atualizar matrícula com referência à assinatura
                $updateStmt = $this->db->prepare("
                    UPDATE matriculas SET assinatura_id = ?, atualizado_em = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$assinaturaId, $matriculaId]);

                $this->db->commit();

                $response->getBody()->write(json_encode([
                    'type' => 'success',
                    'message' => 'Assinatura criada para matrícula',
                    'data' => [
                        'assinatura' => [
                            'id' => $assinaturaId,
                            'matricula_id' => $matriculaId,
                            'status' => 'ativa'
                        ]
                    ]
                ]));

                return $response->withStatus(201);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /admin/matriculas/{id}/suspender
     * Suspender matrícula e sincronizar com assinatura
     */
    public function suspender(Request $request, Response $response, array $args)
    {
        try {
            $matriculaId = $args['id'];
            $body = $request->getParsedBody();
            $tenantId = $request->getAttribute('tenant_id');

            // Buscar matrícula
            $stmt = $this->db->prepare("
                SELECT * FROM matriculas WHERE id = ? AND academia_id = ?
            ");
            $stmt->execute([$matriculaId, $tenantId]);
            $matricula = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$matricula) {
                return $this->error($response, 'Matrícula não encontrada', 404);
            }

            $this->db->beginTransaction();

            try {
                // Suspender matrícula
                $updateMatricula = "
                    UPDATE matriculas 
                    SET status = 'suspensa', atualizado_em = NOW()
                    WHERE id = ?
                ";
                $this->db->prepare($updateMatricula)->execute([$matriculaId]);

                // Se tem assinatura, sincronizar status
                if ($matricula['assinatura_id']) {
                    $updateAssinatura = "
                        UPDATE assinaturas 
                        SET status = 'suspensa', atualizado_em = NOW()
                        WHERE id = ?
                    ";
                    $this->db->prepare($updateAssinatura)->execute([$matricula['assinatura_id']]);

                    // Registrar sincronização
                    $this->registrarSincronizacao(
                        $matricula['assinatura_id'],
                        $matriculaId,
                        'ativa',
                        'suspensa',
                        'suspender'
                    );
                }

                $this->db->commit();

                $response->getBody()->write(json_encode([
                    'type' => 'success',
                    'message' => 'Matrícula suspensa' . 
                        ($matricula['assinatura_id'] ? ' e assinatura sincronizada' : ''),
                    'data' => ['status' => 'suspensa']
                ]));

                return $response->withStatus(200);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /admin/matriculas/{id}/reativar
     * Reativar matrícula e sincronizar com assinatura
     */
    public function reativar(Request $request, Response $response, array $args)
    {
        try {
            $matriculaId = $args['id'];
            $tenantId = $request->getAttribute('tenant_id');

            $stmt = $this->db->prepare("
                SELECT * FROM matriculas WHERE id = ? AND academia_id = ?
            ");
            $stmt->execute([$matriculaId, $tenantId]);
            $matricula = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$matricula) {
                return $this->error($response, 'Matrícula não encontrada', 404);
            }

            $this->db->beginTransaction();

            try {
                // Reativar matrícula
                $updateMatricula = "
                    UPDATE matriculas 
                    SET status = 'ativa', atualizado_em = NOW()
                    WHERE id = ?
                ";
                $this->db->prepare($updateMatricula)->execute([$matriculaId]);

                // Se tem assinatura, sincronizar
                if ($matricula['assinatura_id']) {
                    $updateAssinatura = "
                        UPDATE assinaturas 
                        SET status = 'ativa', atualizado_em = NOW()
                        WHERE id = ?
                    ";
                    $this->db->prepare($updateAssinatura)->execute([$matricula['assinatura_id']]);

                    $this->registrarSincronizacao(
                        $matricula['assinatura_id'],
                        $matriculaId,
                        'suspensa',
                        'ativa',
                        'reativar'
                    );
                }

                $this->db->commit();

                $response->getBody()->write(json_encode([
                    'type' => 'success',
                    'message' => 'Matrícula reativada' . 
                        ($matricula['assinatura_id'] ? ' e assinatura sincronizada' : ''),
                    'data' => ['status' => 'ativa']
                ]));

                return $response->withStatus(200);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 500);
        }
    }

    /**
     * GET /admin/matriculas
     * Listar matrículas com opção de incluir assinaturas
     */
    public function listar(Request $request, Response $response)
    {
        try {
            $params = $request->getQueryParams();
            $tenantId = $request->getAttribute('tenant_id');
            $incluirAssinaturas = $params['incluir_assinaturas'] === 'true';

            $sql = "
                SELECT 
                    m.*,
                    a.nome as aluno_nome,
                    p.nome as plano_nome
            ";

            if ($incluirAssinaturas) {
                $sql .= ",
                    asn.id as assinatura_id,
                    asn.status as assinatura_status,
                    asn.data_vencimento as assinatura_vencimento
                ";
            }

            $sql .= "
                FROM matriculas m
                JOIN alunos a ON m.aluno_id = a.id
                JOIN planos p ON m.plano_id = p.id
            ";

            if ($incluirAssinaturas) {
                $sql .= "LEFT JOIN assinaturas asn ON m.assinatura_id = asn.id";
            }

            $sql .= " WHERE m.academia_id = ?";

            // Filtros opcionais
            if (!empty($params['status'])) {
                $sql .= " AND m.status = ?";
            }

            $stmt = $this->db->prepare($sql);
            $bindings = [$tenantId];

            if (!empty($params['status'])) {
                $bindings[] = $params['status'];
            }

            $stmt->execute($bindings);
            $matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => ['matriculas' => $matriculas]
            ]));

            return $response->withStatus(200);

        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 500);
        }
    }

    /**
     * Helper: Registrar sincronização
     */
    private function registrarSincronizacao($assinaturaId, $matriculaId, $statusAntigo, $statusNovo, $tipo)
    {
        $sql = "
            INSERT INTO assinatura_sincronizacoes 
            (assinatura_id, matricula_id, status_anterior_matricula, 
             status_novo_matricula, tipo_sincronizacao, criado_em)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$assinaturaId, $matriculaId, $statusAntigo, $statusNovo, 'automatica']);
    }

    /**
     * Helper: Calcular data de vencimento
     */
    private function calcularDataVencimento($dataInicio, $cicloTipo)
    {
        $data = new \DateTime($dataInicio);

        switch ($cicloTipo) {
            case 'semanal':
                $data->add(new \DateInterval('P7D'));
                break;
            case 'mensal':
                $data->add(new \DateInterval('P1M'));
                break;
            case 'trimestral':
                $data->add(new \DateInterval('P3M'));
                break;
            case 'semestral':
                $data->add(new \DateInterval('P6M'));
                break;
            case 'anual':
                $data->add(new \DateInterval('P1Y'));
                break;
            default:
                $data->add(new \DateInterval('P1M'));
        }

        return $data->format('Y-m-d');
    }
}
```

---

## 🛣️ Modificações em routes/api.php

```php
<?php

use Slim\Routing\RouteCollectorProxy;
use App\Controllers\MatriculaController;
use App\Controllers\AssinaturaController;

// ============================================
// ROTAS DE MATRÍCULAS COM ASSINATURAS
// ============================================

$app->group('/admin/matriculas', function (RouteCollectorProxy $group) {
    // Listar matrículas
    $group->get('', [MatriculaController::class, 'listar']);

    // Buscar matrícula específica
    $group->get('/{id}', [MatriculaController::class, 'buscar']);

    // Criar matrícula (com opção de criar assinatura automaticamente)
    $group->post('', [MatriculaController::class, 'criar']);

    // Criar assinatura para matrícula existente
    $group->post('/{id}/assinatura', [MatriculaController::class, 'criarAssinatura']);

    // Obter assinatura da matrícula
    $group->get('/{id}/assinatura', [MatriculaController::class, 'obterAssinatura']);

    // Suspender matrícula (e assinatura associada)
    $group->post('/{id}/suspender', [MatriculaController::class, 'suspender']);

    // Reativar matrícula (e assinatura associada)
    $group->post('/{id}/reativar', [MatriculaController::class, 'reativar']);

    // Sincronizar assinatura com status da matrícula
    $group->post('/{id}/sincronizar-assinatura', [MatriculaController::class, 'sincronizarAssinatura']);

    // ... outros endpoints de matrícula

})->add(new AuthMiddleware())
  ->add(new TenantMiddleware());

// ============================================
// ROTAS DE ASSINATURAS COM MATRICULAS
// ============================================

$app->group('/admin/assinaturas', function (RouteCollectorProxy $group) {
    // Listar assinaturas (com opção de incluir dados de matrícula)
    $group->get('', [AssinaturaController::class, 'listar']);

    // Buscar assinatura
    $group->get('/{id}', [AssinaturaController::class, 'buscar']);

    // Sincronizar status com matrícula
    $group->post('/{id}/sincronizar-matricula', [AssinaturaController::class, 'sincronizarComMatricula']);

    // Obter status de sincronização
    $group->get('/{id}/status-sincronizacao', [AssinaturaController::class, 'obterStatusSincronizacao']);

    // Listar assinaturas sem matrícula associada (órfãs)
    $group->get('/sem-matricula', [AssinaturaController::class, 'listarSemMatricula']);

    // ... outros endpoints de assinatura

})->add(new AuthMiddleware())
  ->add(new TenantMiddleware());
```

---

## ✅ Checklist de Implementação

```
Backend
├── [x] Modificar MatriculaController::criar() para incluir criar_assinatura
├── [x] Adicionar MatriculaController::criarAssinatura()
├── [x] Adicionar MatriculaController::suspender()
├── [x] Adicionar MatriculaController::reativar()
├── [x] Adicionar MatriculaController::listar() com incluir_assinaturas
├── [x] Registrar rotas em api.php
├── [x] Adicionar métodos de sincronização em AssinaturaController
├── [ ] Executar migrations SQL
├── [ ] Testar endpoints com Postman/Insomnia
└── [ ] Verificar triggers de sincronização

Frontend
├── [x] Atualizar matriculaService com novos métodos
├── [ ] Adicionar rota de assinaturas em navigation
├── [ ] Integrar AssinaturasScreen com dados de matrículas
├── [ ] Testar fluxos de criação e sincronização
└── [ ] Adicionar validações de formulário
```

---

**Status**: ✅ **Implementação Backend Documentada**

**Próximas Etapas**:
1. Executar migrations SQL no banco de dados
2. Testar endpoints com Postman/Insomnia
3. Validar triggers de sincronização automática
4. Integrar frontend com novos endpoints
