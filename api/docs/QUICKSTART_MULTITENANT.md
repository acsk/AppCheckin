# ⚡ Quick Start - Multi-Tenant Validation Implementation

**Atalho:** Implementar validação multi-tenant em qualquer endpoint em 5 minutos

---

## 🚀 Recipe Rápido

### PASSO 1: Copiar-Colar Template

```php
// No topo do seu método public function

// ============================================================
// VALIDAÇÃO CRÍTICA: Garantir que usuário tem acesso ao tenant
// ============================================================
$usuarioTenantModel = new \App\Models\UsuarioTenant($this->db);
$usuarioTenantValido = $usuarioTenantModel->validarAcesso($userId, $tenantId);

if (!$usuarioTenantValido) {
    error_log("SEGURANÇA: Usuário $userId tentou acessar tenant $tenantId sem permissão");
    return $response->withStatus(403)->write(json_encode([
        'success' => false,
        'error' => 'Acesso negado: você não tem permissão neste tenant',
        'code' => 'INVALID_TENANT_ACCESS'
    ]));
}
```

### PASSO 2: Ajustar Variáveis

Se usando transação, mudar:
- `$this->db` → `$db`
- `$response` → seu objeto response

Se método recebe dados de usuário, garantir:
- `$userId` extraído do request
- `$tenantId` extraído do request

### PASSO 3: Testar

```bash
curl -X POST http://localhost:8000/seu-endpoint \
  -H "X-Tenant-ID: 99" \
  -H "Authorization: Bearer <token>"

# Esperado: HTTP 403 + INVALID_TENANT_ACCESS
```

---

## 📋 Checklist

- [ ] Arquivo do controller encontrado
- [ ] Método identificado
- [ ] Template copiado
- [ ] Variáveis ajustadas
- [ ] Transação ajustada (se necessário)
- [ ] Testado com tenant inválido
- [ ] Testado com tenant válido
- [ ] Logs verificados

---

## 🔗 Referências

| Arquivo | Localização | Uso |
|---------|------------|-----|
| UsuarioTenant.php | app/Models/ | Importar modelo |
| MobileController.php | app/Controllers/MobileController.php:1025 | Exemplo |
| MatriculaController.php | app/Controllers/MatriculaController.php:50 | Exemplo com transação |

---

## ❓ FAQ Rápido

**P: Qual é a ordem correta?**
R: validarAcesso() SEMPRE primeira coisa, antes de qualquer query

**P: E se usar transação?**
R: Mudar `$this->db` → `$db` e adicionar rollBack no erro

**P: Como testar se funcionou?**
R: Tentar com tenant inválido, deve retornar HTTP 403

**P: Preciso de imports?**
R: `new \App\Models\UsuarioTenant($db)` - o namespace completo tira necessidade de use

**P: E se método não recebe tenantId?**
R: Usar `$request->getAttribute('tenantId')` para extrair do header

---

## 🎯 5 Endpoints para Começar

**Prioridade 1 - Hoje:**
1. ContasReceberController::criar()
2. ContasReceberController::atualizar()
3. ContasReceberController::deletar()

**Prioridade 2 - Hoje +2h:**
4. MatriculaController::editar()
5. MatriculaController::cancelar()

---

## ✅ Exemplo Completo - Copiável

```php
<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ExemploController
{
    public function criar(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        $db = require __DIR__ . '/../../config/database.php';

        try {
            // ============================================================
            // VALIDAÇÃO CRÍTICA: Garantir que usuário tem acesso ao tenant
            // ============================================================
            $usuarioTenantModel = new \App\Models\UsuarioTenant($db);
            $usuarioTenantValido = $usuarioTenantModel->validarAcesso($userId, $tenantId);

            if (!$usuarioTenantValido) {
                error_log("SEGURANÇA: Usuário $userId tentou acessar tenant $tenantId sem permissão");
                return $response->withStatus(403)->write(json_encode([
                    'success' => false,
                    'error' => 'Acesso negado: você não tem permissão neste tenant',
                    'code' => 'INVALID_TENANT_ACCESS'
                ]));
            }

            // ============================================================
            // Resto do código - validações de negócio
            // ============================================================

            // Exemplo: validar dados
            if (empty($data['nome'])) {
                return $response->withStatus(422)->write(json_encode([
                    'error' => 'Nome é obrigatório'
                ]));
            }

            // Exemplo: INSERT
            $stmt = $db->prepare("
                INSERT INTO sua_tabela (nome, tenant_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$data['nome'], $tenantId]);

            return $response->write(json_encode([
                'success' => true,
                'message' => 'Recurso criado com sucesso'
            ]));

        } catch (Exception $e) {
            error_log($e->getMessage());
            return $response->withStatus(500)->write(json_encode([
                'error' => 'Erro ao processar requisição'
            ]));
        }
    }
}
```

---

## 🔐 Com Transação

```php
public function editar(Request $request, Response $response): Response
{
    $userId = $request->getAttribute('userId');
    $tenantId = $request->getAttribute('tenantId');
    $data = $request->getParsedBody();
    $db = require __DIR__ . '/../../config/database.php';

    try {
        $db->beginTransaction();

        // ============================================================
        // VALIDAÇÃO CRÍTICA: Garantir que usuário tem acesso ao tenant
        // ============================================================
        $usuarioTenantModel = new \App\Models\UsuarioTenant($db);
        $usuarioTenantValido = $usuarioTenantModel->validarAcesso($userId, $tenantId);

        if (!$usuarioTenantValido) {
            $db->rollBack();
            error_log("SEGURANÇA: Usuário $userId tentou acessar tenant $tenantId sem permissão");
            return $response->withStatus(403)->write(json_encode([
                'success' => false,
                'error' => 'Acesso negado: você não tem permissão neste tenant',
                'code' => 'INVALID_TENANT_ACCESS'
            ]));
        }

        // ============================================================
        // Verificar se recurso pertence ao tenant
        // ============================================================
        $stmt = $db->prepare("
            SELECT id FROM sua_tabela 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$data['id'], $tenantId]);
        $recurso = $stmt->fetch();

        if (!$recurso) {
            $db->rollBack();
            return $response->withStatus(404)->write(json_encode([
                'error' => 'Recurso não encontrado'
            ]));
        }

        // ============================================================
        // UPDATE
        // ============================================================
        $stmt = $db->prepare("
            UPDATE sua_tabela 
            SET nome = ? 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$data['nome'], $data['id'], $tenantId]);

        $db->commit();

        return $response->write(json_encode([
            'success' => true,
            'message' => 'Recurso atualizado com sucesso'
        ]));

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log($e->getMessage());
        return $response->withStatus(500)->write(json_encode([
            'error' => 'Erro ao processar requisição'
        ]));
    }
}
```

---

## ⏱️ Timing

| Ação | Tempo |
|------|-------|
| Copiar template | 1 min |
| Ajustar variáveis | 1 min |
| Testar endpoint | 1 min |
| Verificar logs | 1 min |
| **Total por método** | **~4 min** |

**3 métodos × 4 min = 12 minutos de trabalho total** (para 3 endpoints)

---

## 🎬 Go Live Checklist

Antes de fazer deploy:

- [ ] Executar teste com tenant inválido → HTTP 403
- [ ] Executar teste com tenant válido → HTTP 200/422 (depende de erro de negócio)
- [ ] Verificar logs contêm "SEGURANÇA"
- [ ] Verificar sem quebrar endpoints válidos
- [ ] Código review

---

**Tempo Total para 5 Endpoints:** ~20 minutos
**Incluindo Testes:** ~30 minutos

---

*Quick Implementation | Copy-Paste Ready | Security First*
