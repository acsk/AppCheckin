# Funcionalidade de Baixa de Parcelas com Geração Automática

## 📋 Resumo
Sistema de baixa de parcelas de pagamentos de matrículas com geração automática da próxima parcela baseada no prazo do plano contratado (30, 60 ou 90 dias).

## 🎯 Objetivo
Ao dar baixa em uma parcela de pagamento, o sistema deve automaticamente criar a próxima parcela com vencimento calculado baseado na duração do plano (campo `duracao_dias` da tabela `planos`).

## 🔧 Implementação Técnica

### Arquivo Modificado
- **Controller**: `app/Controllers/MatriculaController.php`
- **Método**: `darBaixaConta()`
- **Linhas**: 764-895

### Estrutura de Dados

#### Tabelas Envolvidas
1. **pagamentos_plano** - Armazena as parcelas de pagamento
   - `id` - ID da parcela
   - `tenant_id` - Academia
   - `matricula_id` - Referência à matrícula
   - `usuario_id` - Aluno
   - `plano_id` - Plano contratado
   - `valor` - Valor da parcela
   - `data_vencimento` - Data de vencimento
   - `data_pagamento` - Data em que foi paga
   - `status_pagamento_id` - Status (1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado)
   - `forma_pagamento_id` - Forma de pagamento utilizada
   - `observacoes` - Observações
   - `criado_por` - Admin que criou
   - `baixado_por` - Admin que deu baixa
   - `tipo_baixa_id` - Tipo de baixa (1=Manual, 2=Automática)

2. **planos** - Informações dos planos
   - `duracao_dias` - Duração em dias (30, 60, 90, 365, etc)

3. **matriculas** - Matrículas dos alunos
   - Status é atualizado para 'ativa' quando primeiro pagamento é baixado

### Fluxo de Execução

#### 1. Recebimento da Requisição
```
POST /admin/matriculas/contas/{id}/baixa
```

**Parâmetros URL:**
- `{id}` - ID da parcela em `pagamentos_plano`

**Body (JSON):**
```json
{
  "data_pagamento": "2026-01-15",
  "forma_pagamento_id": 2,
  "observacoes": ""
}
```

#### 2. Busca do Pagamento
```sql
SELECT pp.*, m.plano_id, p.duracao_dias
FROM pagamentos_plano pp
INNER JOIN matriculas m ON pp.matricula_id = m.id
INNER JOIN planos p ON pp.plano_id = p.id
WHERE pp.id = ? AND pp.tenant_id = ?
```

**Validações:**
- Verifica se pagamento existe
- Verifica se pertence ao tenant correto
- Verifica se não está já pago (status_pagamento_id = 2)

#### 3. Atualização do Pagamento Atual
```sql
UPDATE pagamentos_plano 
SET status_pagamento_id = 2,
    data_pagamento = ?,
    forma_pagamento_id = ?,
    observacoes = ?,
    baixado_por = ?,
    tipo_baixa_id = 1,
    updated_at = NOW()
WHERE id = ?
```

#### 4. Atualização da Matrícula
Se a matrícula estava com status 'pendente', é atualizada para 'ativa':
```sql
UPDATE matriculas 
SET status = 'ativa',
    status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
    updated_at = NOW()
WHERE id = ? 
AND status = 'pendente'
```

#### 5. Criação da Próxima Parcela (NOVO)
**Cálculo da Data:**
```php
$duracaoDias = (int) $pagamento['duracao_dias']; // 30, 60, 90, etc
$dataVencimentoAtual = new \DateTime($pagamento['data_vencimento']);
$proximoVencimento = $dataVencimentoAtual->add(new \DateInterval("P{$duracaoDias}D"));
```

**Inserção:**
```sql
INSERT INTO pagamentos_plano (
    tenant_id,
    matricula_id,
    usuario_id,
    plano_id,
    valor,
    data_vencimento,
    status_pagamento_id,
    observacoes,
    criado_por,
    created_at
) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())
```

**Valores:**
- `status_pagamento_id = 1` (Aguardando)
- `data_vencimento` = data_atual + duracao_dias
- `observacoes` = "Pagamento gerado automaticamente após confirmação"
- Mantém mesmo valor, tenant, matrícula, usuário e plano

## 📊 Exemplo de Uso

### Cenário
- Aluno: Carolina Ferreira (ID: 11)
- Plano: 1x por semana - CrossFit (ID: 23)
- Duração: 30 dias
- Valor: R$ 110,00

### Execução

#### Parcela Atual
```
ID: 5
Data Vencimento: 2026-01-15
Status: Aguardando (1)
Valor: R$ 110,00
```

#### Requisição de Baixa
```bash
POST /admin/matriculas/contas/5/baixa
Content-Type: application/json

{
  "data_pagamento": "2026-01-15",
  "forma_pagamento_id": 2,
  "observacoes": ""
}
```

#### Resultado

**Parcela Atual Atualizada:**
```
ID: 5
Data Vencimento: 2026-01-15
Data Pagamento: 2026-01-15
Status: Pago (2)
Forma Pagamento: Pix (2)
Baixado Por: Jonas (9)
```

**Nova Parcela Criada Automaticamente:**
```
ID: 6
Data Vencimento: 2026-02-14  ← (2026-01-15 + 30 dias)
Status: Aguardando (1)
Valor: R$ 110,00
Criado Por: Jonas (9)
Observações: "Pagamento gerado automaticamente após confirmação"
```

### Resposta da API
```json
{
  "message": "Baixa realizada com sucesso",
  "pagamento": {
    "id": 5,
    "tenant_id": 5,
    "matricula_id": 31,
    "usuario_id": 11,
    "plano_id": 23,
    "valor": "110.00",
    "data_vencimento": "2026-01-15",
    "data_pagamento": "2026-01-15",
    "status_pagamento_id": 2,
    "forma_pagamento_id": 2,
    "baixado_por": 9,
    "tipo_baixa_id": 1
  },
  "proxima_parcela": {
    "id": 6,
    "data_vencimento": "2026-02-14",
    "valor": "110.00",
    "status": "Aguardando"
  }
}
```

## 🔒 Segurança e Tratamento de Erros

### Validações Implementadas
1. ✅ Verificação se pagamento existe
2. ✅ Verificação de tenant_id (isolamento entre academias)
3. ✅ Verificação se pagamento já foi baixado (evita duplicação)
4. ✅ Try-catch na criação da próxima parcela (não falha operação principal se erro)

### Tratamento de Erros

#### Erro: Pagamento não encontrado
```json
{
  "error": "Pagamento não encontrado"
}
```
**Status HTTP:** 404

#### Erro: Pagamento já pago
```json
{
  "error": "Pagamento já está marcado como pago"
}
```
**Status HTTP:** 400

#### Erro na Criação da Próxima Parcela
- Erro é logado: `error_log("Erro ao criar próxima parcela: ...")`
- Operação principal (baixa) continua normalmente
- Campo `proxima_parcela` retorna `null` na resposta

## 🔄 Ciclo de Vida das Parcelas

```
┌─────────────────────────────────────────────────────────┐
│  Matrícula Criada                                       │
│  └─> Primeira parcela criada (status: Aguardando)      │
└─────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│  Admin dá baixa na parcela                              │
│  ├─> Parcela atual: status = Pago                       │
│  ├─> Matrícula: status = Ativa (se estava Pendente)    │
│  └─> Nova parcela criada automaticamente                │
│      - Data venc = data atual + duracao_dias            │
│      - Status = Aguardando                              │
└─────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│  Ciclo se repete para próximas parcelas                 │
│  (Pagamento recorrente automático)                      │
└─────────────────────────────────────────────────────────┘
```

## 📝 Logs e Debug

O sistema registra logs importantes:
```php
error_log("Próxima parcela criada com sucesso: ID " . $proximaParcela['id']);
error_log("Erro ao criar próxima parcela: " . $e->getMessage());
```

Para visualizar logs no Docker:
```bash
docker logs appcheckin_php --tail 100 | grep -i "próxima parcela"
```

## 🎨 Frontend

A próxima parcela deve aparecer automaticamente na lista de parcelas com status "Aguardando", pronta para ser baixada no próximo período de pagamento.

## 📅 Data de Implementação
**15 de janeiro de 2026**

## 👤 Desenvolvedor
Implementado via GitHub Copilot

---

## 🔗 Referências

### Rotas Relacionadas
- `POST /admin/matriculas/contas/{id}/baixa` - Dar baixa em parcela
- `GET /admin/matriculas/{id}/contas` - Listar parcelas de uma matrícula

### Tabelas do Banco
- `pagamentos_plano` - Parcelas de pagamento
- `planos` - Planos com duracao_dias
- `matriculas` - Matrículas dos alunos
- `status_pagamento` - Status dos pagamentos
- `formas_pagamento` - Formas de pagamento
- `tipos_baixa` - Tipos de baixa (Manual/Automática)
