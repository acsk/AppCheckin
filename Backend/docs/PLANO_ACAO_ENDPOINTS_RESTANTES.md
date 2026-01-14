# 📋 Plano de Ação - Completar Validações Multi-Tenant

**Data:** 2025-01-13
**Status:** 🔴 CRÍTICO - Ações imediatas necessárias
**Prioridade:** 🚀 ALTA

---

## 🎯 Objetivo

Expandir validação multi-tenant para **todos os endpoints críticos** de criação/atualização/deleção de dados.

---

## 📊 Endpoints por Prioridade

### 🔴 PRIORIDADE ALTÍSSIMA (Hoje)

#### 1. **ContasReceberController** - Financial Data (CRÍTICO)

**Por que:** Dados financeiros são os mais sensíveis

```
Arquivos: app/Controllers/ContasReceberController.php
Métodos: criar(), atualizar(), deletar(), ... (verificar)

├─ criar()
│  ├─ Risk: Criar conta para cliente em tenant errado
│  ├─ Solução: validarAcesso() primeira coisa
│  └─ Status: ⏳ NÃO TEM
│
├─ atualizar()
│  ├─ Risk: Modificar conta de outro tenant
│  ├─ Solução: validarAcesso() primeira coisa
│  └─ Status: ⏳ NÃO TEM
│
└─ deletar()
   ├─ Risk: Deletar conta de outro tenant
   ├─ Solução: validarAcesso() primeira coisa
   └─ Status: ⏳ NÃO TEM
```

**Estimativa:** 30 minutos por método
**Total:** ~1.5 horas

---

### 🟡 PRIORIDADE ALTA (Próximas 4 horas)

#### 2. **MatriculaController** - Update/Cancel Methods

```
Métodos: editar(), cancelar()

├─ editar()
│  ├─ Current: Atualiza matrícula sem validação tenant
│  ├─ Risk: Admin A modifica matrícula em tenant B
│  ├─ Fix: Verificar usuario_tenant + matrícula.tenant_id
│  └─ Status: ⏳ NÃO TEM
│
└─ cancelar()
   ├─ Current: Cancela matrícula sem validação tenant
   ├─ Risk: Admin A cancela matrícula em tenant B
   ├─ Fix: Verificar usuario_tenant + matrícula.tenant_id
   └─ Status: ⏳ NÃO TEM
```

**Estimativa:** 20 minutos por método
**Total:** ~40 minutos

---

#### 3. **TurmaController** - Class Management

```
Métodos: criar(), editar(), deletar()

├─ criar()
│  ├─ Current: Cria turma para tenant validado
│  ├─ Risk: Admin A cria turma em tenant B
│  ├─ Fix: validarAcesso() na criação
│  └─ Status: ⏳ Verificar
│
├─ editar()
│  └─ Status: ⏳ Verificar
│
└─ deletar()
   └─ Status: ⏳ Verificar
```

**Estimativa:** 10 minutos por método
**Total:** ~30 minutos

---

### 🟠 PRIORIDADE MÉDIA (Próximas 8 horas)

#### 4. **PagamentoController** - Payment Processing

```
Métodos: criar(), confirmar(), cancelar()

Status: ⏳ Verificar se existem
```

---

#### 5. **ConfigController** - System Settings

```
Métodos: atualizar(), criar()

Status: ⏳ Verificar se existem
```

---

## 🔧 Como Implementar

### Template para Cada Método

```php
public function criar(Request $request, Response $response): Response
{
    $userId = $request->getAttribute('userId');
    $tenantId = $request->getAttribute('tenantId');
    $data = $request->getParsedBody();
    $db = require __DIR__ . '/../../config/database.php';
    
    try {
        // ✅ PASSO 1: Validação Multi-Tenant (SEMPRE PRIMEIRA!)
        $usuarioTenantModel = new \App\Models\UsuarioTenant($db);
        $validacao = $usuarioTenantModel->validarAcesso($userId, $tenantId);
        
        if (!$validacao) {
            error_log("SEGURANÇA: Usuário $userId tentou acessar tenant $tenantId sem permissão");
            return $response->withStatus(403)->write(json_encode([
                'success' => false,
                'error' => 'Acesso negado: você não tem permissão neste tenant',
                'code' => 'INVALID_TENANT_ACCESS'
            ]));
        }
        
        // ✅ PASSO 2: Validação de Negócio (regras específicas)
        // ... resto do código ...
        
    } catch (Exception $e) {
        // log error
    }
}
```

### Para Métodos com Transação

```php
public function editar(Request $request, Response $response): Response
{
    $userId = $request->getAttribute('userId');
    $tenantId = $request->getAttribute('tenantId');
    $data = $request->getParsedBody();
    $db = require __DIR__ . '/../../config/database.php';
    
    try {
        // BEGIN dentro do try
        $db->beginTransaction();
        
        // ✅ PASSO 1: Validação Multi-Tenant
        $usuarioTenantModel = new \App\Models\UsuarioTenant($db);
        $validacao = $usuarioTenantModel->validarAcesso($userId, $tenantId);
        
        if (!$validacao) {
            $db->rollBack();
            return $response->withStatus(403)->write(...);
        }
        
        // ✅ PASSO 2: Validação adicional (ex: recurso pertence ao tenant)
        $stmt = $db->prepare("
            SELECT * FROM tabela_recurso 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$data['id'], $tenantId]);
        $recurso = $stmt->fetch();
        
        if (!$recurso) {
            $db->rollBack();
            return $response->withStatus(404);
        }
        
        // ✅ PASSO 3: Atualizar
        // ...
        
        $db->commit();
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // log error
    }
}
```

---

## 📝 Checklist de Implementação

### ContasReceberController (🔴 HOJE)

- [ ] Identificar arquivo exato de ContasReceberController
- [ ] Listar todos os métodos públicos
- [ ] Adicionar validarAcesso() em criar()
- [ ] Adicionar validarAcesso() em atualizar()
- [ ] Adicionar validarAcesso() em deletar()
- [ ] Testar cada método
- [ ] Verificar logs de segurança
- [ ] Documentar mudanças

**Responsável:** Developer
**Prazo:** 2 horas
**Status:** ⏳ NÃO INICIADO

---

### MatriculaController (🟡 HOJE +4h)

- [ ] Verificar método editar()
- [ ] Adicionar validarAcesso()
- [ ] Verificar método cancelar()
- [ ] Adicionar validarAcesso()
- [ ] Testar ambos
- [ ] Verificar transações

**Responsável:** Developer
**Prazo:** 1 hora
**Status:** ⏳ NÃO INICIADO

---

### TurmaController (🟡 HOJE +4h)

- [ ] Verificar arquivo
- [ ] Revisar métodos criar/editar/deletar
- [ ] Adicionar validações
- [ ] Testar

**Responsável:** Developer
**Prazo:** 30 minutos
**Status:** ⏳ NÃO INICIADO

---

## 🔍 Verificação Rápida

Para cada controller, executar:

```bash
# 1. Encontrar arquivo
find . -name "*Controller.php" | grep -i contas

# 2. Ver métodos públicos
grep -n "public function" app/Controllers/ContasReceberController.php

# 3. Verificar se tem validação
grep -n "validarAcesso\|UsuarioTenant" app/Controllers/ContasReceberController.php
# Se não retorna nada → PRECISA ADICIONAR

# 4. Verificar se tem validação tenant_id
grep -n "tenant_id" app/Controllers/ContasReceberController.php
# Conferir se está em WHERE clauses
```

---

## 🚨 Riscos se NÃO Implementar

### Risco 1: Vazamento de Dados Financeiros

```
Admin do Tenant 1 consegue:
- Ver contas a receber do Tenant 2
- Modificar pagamentos do Tenant 2
- Deletar registros do Tenant 2
→ 🔴 CRÍTICO
```

### Risco 2: Integridade de Dados

```
Operações sem validação tenant:
- A: UPDATE contas_receber SET status='pago'
- B: DELETE FROM contas_receber
→ Afetam dados de qualquer tenant
```

### Risco 3: Compliance/Auditoria

```
Sem validação:
- Não há log de tentativas de acesso cruzado
- Não é possível auditar quem acessou o quê
- Viola regulamentações (LGPD, GDPR)
```

---

## ✅ Success Criteria

Após implementação:

- [ ] **Segurança**
  - Todo criação/atualização/deleção bloqueia cross-tenant
  - HTTP 403 retornado para acesso indevido
  
- [ ] **Logging**
  - Cada tentativa de acesso indevido registrada em log
  - Mensagem: "SEGURANÇA: Usuário X tentou acessar tenant Y"

- [ ] **Testes**
  - Cross-tenant attempt bloqueado
  - Erro correto retornado
  - Logs gerados corretamente

- [ ] **Documentação**
  - Todos os endpoints documentados
  - Padrão de implementação seguido

---

## 📊 Timeline Estimado

```
[T+0h]    Começar ContasReceberController
[T+1.5h]  Terminar ContasReceberController
[T+2h]    Começar MatriculaController
[T+3h]    Terminar MatriculaController
[T+3.5h]  Começar TurmaController
[T+4h]    Terminar TurmaController
[T+4h]    Testar todos endpoints
[T+5h]    Revisar logs
[T+6h]    ✅ COMPLETADO
```

---

## 📞 Referências

**Documentação:**
- [VALIDACOES_MULTITENANT.md](./VALIDACOES_MULTITENANT.md)
- [SUMARIO_IMPLEMENTACAO_MULTITENANT.md](./SUMARIO_IMPLEMENTACAO_MULTITENANT.md)

**Código Exemplo:**
- [MobileController.php:1025](../app/Controllers/MobileController.php#L1025) - registrarCheckin()
- [MatriculaController.php:50](../app/Controllers/MatriculaController.php#L50) - criar()

**Modelo:**
- [UsuarioTenant.php](../app/Models/UsuarioTenant.php) - validarAcesso()

---

## 🎯 Objetivo Final

Depois de completar:

```
✅ 100% dos endpoints críticos com validação multi-tenant
✅ Impossível vazar dados entre tenants
✅ Audit trail completo de tentativas
✅ Sistema pronto para produção
✅ Compliance com LGPD/GDPR
```

---

**Prioridade:** 🔴 **CRÍTICA**
**Timeline:** 🔴 **HOJE (6 horas)**
**Bloqueador:** Nenhum
**Status:** 🚀 **Pronto para começar**

---

*Plano de Ação | Security-First Approach | Defensive Programming*
