# 🔍 Análise: Por Que o Job Não Atualiza Matrícula #58

## Resumo Executivo
O job `atualizar_pagamentos_mp.php` possui **4 problemas críticos** que impedem a atualização correta de pagamentos, especialmente para a Matrícula #58.

---

## 🔴 Problemas Identificados

### 1. **Filtro de Data Muito Restritivo**
**Linha Original:**
```php
WHERE DATE(criado_em) = CURDATE()
```

**Impacto:**
- ❌ Só processa assinaturas criadas **HOJE**
- ❌ Matrícula #58 foi criada em `2026-02-10`
- ❌ Na data de hoje (`2026-03-24`), o job IGNORA completamente essa matrícula

**Dados da Matrícula #58:**
```
ID: 58
Aluno: 9
Data Criação: 2026-02-10 17:52:15  ← 42 dias atrás!
Status ID: 1 (Ativo)
Valor: R$ 120,00
```

---

### 2. **Duplo Filtro de Data (Pagamentos)**
**Linha Original:**
```php
WHERE matricula_id = ? AND DATE(date_created) = CURDATE() AND LOWER(status) = 'pending'
```

**Impacto:**
- ❌ Mesmo que a assinatura fosse pega, só busca pagamentos criados **HOJE**
- ❌ Se o pagamento foi criado em outro dia, nunca será processado
- ❌ Problema acumulativo com o filtro anterior

---

### 3. **Campo `status_id` Não Incluído no SELECT**
**Linha Original:**
```php
SELECT id, payment_id, status, status_detail, date_created FROM pagamentos_mercadopago
```

**Mas depois faz:**
```php
$statusId = isset($pg['status_id']) ? (int)$pg['status_id'] : null; // sempre NULL!
```

**Impacto:**
- ❌ `status_id` nunca é buscado
- ❌ Variável `$statusId` sempre é NULL
- ❌ Validação na linha 99 nunca funciona:
  ```php
  if ($statusId === 6) { // NUNCA EXECUTA porque $statusId é sempre NULL
      logMsg("Pagamento... aprovado — pulando");
      continue;
  }
  ```

---

### 4. **Job Depende Inteiramente de Webhook Retornar**
**Fluxo:**
1. Job chama: `POST /api/webhooks/mercadopago/payment/{paymentId}/reprocess`
2. Espera que a API remota acionasse uma webhook
3. Espera que a webhook atualize o banco localmente
4. **MAS:** Se qualquer passo falhar, o banco permanece desatualizado

**Impacto:**
- ❌ Sem validação local da atualização
- ❌ Sem retentativas
- ❌ Sem log de sucesso/falha confiável
- ❌ Job termina considerando "sucesso" mesmo que webhook falhe

---

## ✅ Solução Implementada

### Mudanças no Job

#### 1. **Novos Parâmetros CLI**
```bash
# Específico para matrícula
php jobs/atualizar_pagamentos_mp.php --matricula-id=58

# Específico para assinatura
php jobs/atualizar_pagamentos_mp.php --assinatura-id=51

# Últimos N dias
php jobs/atualizar_pagamentos_mp.php --days=7

# Combinado
php jobs/atualizar_pagamentos_mp.php --matricula-id=58 --dry-run
```

#### 2. **Filtros Flexíveis de Data**
Agora respeita:
- Se `--matricula-id` ou `--assinatura-id` está definido → **SEM filtro de data**
- Se `--days=N` está definido → últimos N dias
- Padrão → apenas HOJE (compatível com comportamento anterior)

#### 3. **SELECT Correto de `status_id`**
```php
SELECT id, payment_id, status, status_detail, status_id, date_created
       // ↑ Agora inclui status_id
```

#### 4. **Melhor Logging e Validação HTTP**
```php
if ($httpCode >= 200 && $httpCode <= 299) {
    logMsg("✅ Reprocess payment {$paymentId} - HTTP {$httpCode} OK", $quiet);
} else {
    logMsg("⚠️  Reprocess payment {$paymentId} - HTTP {$httpCode}", $quiet);
}
```

---

## 🚀 Como Usar Agora

### Para Reprocessar Matrícula #58 Especificamente

**1. Simulação (verificar o que seria processado):**
```bash
php jobs/atualizar_pagamentos_mp.php --matricula-id=58 --dry-run
```

**2. Execução Real:**
```bash
php jobs/atualizar_pagamentos_mp.php --matricula-id=58
```

### Para Analisar o Estado Atual

```bash
php debug_matricula_58.php
```

Isso mostrará:
- ✅ Dados da Matrícula #58
- ✅ Assinaturas associadas
- ✅ Todos os pagamentos registrados
- ✅ Status do Mercado Pago vs. Banco Local
- ✅ Diagnóstico se há inconsistências

---

## 📋 Checklist para Resolver o Problema

- [ ] **Executar debug_matricula_58.php** para ver o estado atual
  ```bash
  php debug_matricula_58.php
  ```

- [ ] **Simular o reprocessamento**
  ```bash
  php jobs/atualizar_pagamentos_mp.php --matricula-id=58 --dry-run
  ```

- [ ] **Executar reprocessamento**
  ```bash
  php jobs/atualizar_pagamentos_mp.php --matricula-id=58
  ```

- [ ] **Verificar result novamente**
  ```bash
  php debug_matricula_58.php
  ```

---

## ⚠️ Nota Importante

Este job **depende de webhooks** para atualizar o banco. Se a webhook falhar:
1. O job terá executado "com sucesso"
2. MAS o banco local não será atualizado
3. Será necessário debugar o endpoint `/api/webhooks/mercadopago/payment/{paymentId}/reprocess`

**Recomendação:** Adicionar retry automático e validação local após webhook retornar.

---

## 📚 Arquivos Modificados

- ✅ [jobs/atualizar_pagamentos_mp.php](jobs/atualizar_pagamentos_mp.php) - Job melhorado
- ✅ [debug_matricula_58.php](debug_matricula_58.php) - Script de análise

