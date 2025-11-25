# Bloqueio de Alteração de Plano - Validação Implementada

## 🔒 Como Funciona

O sistema **impede** a alteração de plano quando o aluno atende TODOS os critérios abaixo:

### Critérios de Bloqueio

1. ✅ Possui matrícula **ATIVA**
2. ✅ A matrícula está **DENTRO DO PERÍODO** (data_vencimento >= hoje)
3. ✅ Possui pelo menos 1 **PAGAMENTO CONFIRMADO** no período atual

### Lógica Implementada

```php
// 1. Verifica se existe matrícula ativa com plano diferente
if ($matriculaAtiva && $matriculaAtiva['plano_id'] != $planoId) {
    
    // 2. Verifica se a matrícula está dentro do período
    if ($dataVencimentoMatricula >= $hoje) {
        
        // 3. Verifica se tem pagamento ativo
        SELECT COUNT(*) FROM contas_receber 
        WHERE usuario_id = ? 
          AND status = 'pago'
          AND data_vencimento <= CURDATE()
          AND DATE_ADD(data_vencimento, INTERVAL intervalo_dias DAY) >= CURDATE()
        
        // 4. Se SIM, BLOQUEIA a alteração
        if (tem_pagamento > 0) {
            return error 400: "Não é possível alterar o plano..."
        }
    }
}
```

## 📋 Exemplos de Cenários

### ❌ BLOQUEADO - Cenário 1
**Aluno:** Amanda Freitas  
**Plano Atual:** Mensal Ilimitado (R$ 149,90)  
**Vencimento:** 24/12/2025  
**Status:** ATIVO (pagamento confirmado)  
**Ação:** Tentar mudar para Plano Anual  
**Resultado:** ❌ **BLOQUEADO**  
**Mensagem:** "Não é possível alterar o plano enquanto o aluno estiver ativo. O plano atual vence em 24/12/2025. Aguarde o vencimento ou cancele a matrícula atual."

### ✅ PERMITIDO - Cenário 2
**Aluno:** Amanda Freitas  
**Plano Atual:** Mensal Ilimitado  
**Vencimento:** 24/12/2025  
**Status:** PENDENTE (sem pagamento)  
**Ação:** Tentar mudar para Plano Anual  
**Resultado:** ✅ **PERMITIDO** (não tem pagamento confirmado)

### ✅ PERMITIDO - Cenário 3
**Aluno:** Amanda Freitas  
**Plano Atual:** Mensal Ilimitado  
**Vencimento:** 20/11/2025 (vencido)  
**Status:** VENCIDO  
**Ação:** Tentar mudar para Plano Anual  
**Resultado:** ✅ **PERMITIDO** (matrícula vencida)

### ✅ PERMITIDO - Cenário 4
**Aluno:** Amanda Freitas  
**Plano Atual:** Mensal Ilimitado  
**Vencimento:** 24/12/2025  
**Status:** ATIVO  
**Ação:** **RENOVAR** o mesmo plano (Mensal Ilimitado)  
**Resultado:** ✅ **PERMITIDO** (renovação do mesmo plano é sempre permitida)

## 🧪 Como Testar

### 1. Criar Matrícula Ativa
```sql
-- Criar matrícula para Amanda Freitas
INSERT INTO matriculas (tenant_id, usuario_id, plano_id, data_matricula, data_inicio, data_vencimento, valor, status)
VALUES (1, 20, 2, CURDATE(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 149.90, 'ativa');

-- Criar conta a receber
INSERT INTO contas_receber (tenant_id, usuario_id, plano_id, valor, data_vencimento, status, referencia_mes)
VALUES (1, 20, 2, 149.90, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'pago', DATE_FORMAT(CURDATE(), '%Y-%m'));
```

### 2. Tentar Alterar o Plano via API

**Request:**
```bash
curl -X POST http://localhost:8080/admin/matriculas \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "usuario_id": 20,
    "plano_id": 4
  }'
```

**Response Esperada (BLOQUEADO):**
```json
{
  "error": "Não é possível alterar o plano enquanto o aluno estiver ativo. O plano atual vence em 24/12/2025. Aguarde o vencimento ou cancele a matrícula atual."
}
```
**Status Code:** `400 Bad Request`

### 3. Via Interface Web

1. Acessar **Gerenciar Alunos**
2. Localizar aluno com matrícula ativa (badge "Ativo" verde)
3. Clicar em **Matricular** novamente
4. Selecionar plano diferente do atual
5. Clicar em **Salvar**
6. Verificar mensagem de erro em **toast vermelho**

## 🔓 Como Desbloquear

Para permitir a alteração de plano, você deve:

### Opção 1: Aguardar Vencimento
- Esperar a matrícula vencer naturalmente
- Sistema liberará automaticamente após data_vencimento

### Opção 2: Cancelar Matrícula
```bash
curl -X POST http://localhost:8080/admin/matriculas/{id}/cancelar \
  -H "Authorization: Bearer TOKEN" \
  -d '{"motivo": "Upgrade antecipado"}'
```

### Opção 3: Renovar o Mesmo Plano
- Renovação do mesmo plano é sempre permitida
- Sistema entende como continuidade, não alteração

## 💡 Regras de Negócio

### Por que bloquear?

1. **Evita perda de receita:** Aluno não pode trocar para plano mais barato no meio do período pago
2. **Integridade de pagamentos:** Mantém histórico de contas consistente
3. **Controle financeiro:** Evita reembolsos ou ajustes retroativos complexos

### Exceções Permitidas

- ✅ Renovação do mesmo plano
- ✅ Upgrade após vencimento
- ✅ Downgrade após vencimento
- ✅ Alteração se não há pagamento confirmado
- ✅ Cancelamento manual + nova matrícula

## 📊 Consulta SQL para Verificar Status

```sql
SELECT 
    u.id,
    u.nome,
    u.plano_id,
    p.nome as plano_atual,
    m.data_vencimento,
    m.status as status_matricula,
    CASE 
        WHEN m.data_vencimento >= CURDATE() THEN 'ATIVO'
        ELSE 'VENCIDO'
    END as periodo,
    (SELECT COUNT(*) FROM contas_receber cr 
     WHERE cr.usuario_id = u.id 
       AND cr.status = 'pago'
       AND cr.data_vencimento <= CURDATE()
       AND DATE_ADD(cr.data_vencimento, INTERVAL cr.intervalo_dias DAY) >= CURDATE()
    ) as tem_pagamento_ativo,
    CASE 
        WHEN m.data_vencimento >= CURDATE() 
         AND EXISTS (
             SELECT 1 FROM contas_receber cr 
             WHERE cr.usuario_id = u.id 
               AND cr.status = 'pago'
               AND cr.data_vencimento <= CURDATE()
               AND DATE_ADD(cr.data_vencimento, INTERVAL cr.intervalo_dias DAY) >= CURDATE()
         )
        THEN '🔒 BLOQUEADO'
        ELSE '✅ LIBERADO'
    END as pode_alterar_plano
FROM usuarios u
LEFT JOIN planos p ON u.plano_id = p.id
LEFT JOIN matriculas m ON m.usuario_id = u.id AND m.status = 'ativa'
WHERE u.tenant_id = 1 
  AND u.role_id = 1
ORDER BY pode_alterar_plano, u.nome;
```

## 🎯 Mensagens de Erro

### Backend (`MatriculaController.php`)
```php
"Não é possível alterar o plano enquanto o aluno estiver ativo. 
O plano atual vence em {data}. Aguarde o vencimento ou cancele a matrícula atual."
```

### Frontend (Toast)
- **Tipo:** Danger (vermelho)
- **Duração:** 5 segundos
- **Posição:** Centro-topo
- **Texto:** Mesma mensagem do backend

## 🔍 Logs e Debug

### Verificar no Console do Navegador
```javascript
// Erro 400 com mensagem
{
  "error": "Não é possível alterar o plano..."
}
```

### Verificar no Backend (PHP)
```php
// Log automático no erro
error_log("Tentativa de alteração bloqueada - Usuario ID: {$usuarioId}");
```

## ✅ Checklist de Implementação

- [x] Validação no backend (MatriculaController)
- [x] Verificação de matrícula ativa
- [x] Verificação de período válido (data_vencimento >= hoje)
- [x] Verificação de pagamento confirmado
- [x] Mensagem de erro personalizada com data
- [x] Frontend exibindo erro via toast
- [x] Teste com cenários reais
- [x] Documentação completa

---

**Data de Implementação:** 25/11/2025  
**Arquivo:** `Backend/app/Controllers/MatriculaController.php` (linhas 70-94)  
**Status:** ✅ Implementado e Testado
