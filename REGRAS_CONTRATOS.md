# Regras de Negócio - Contratos de Planos do Sistema

## Regra Principal: UM Contrato Ativo por Academia

Cada academia (tenant) pode ter **APENAS UM** contrato ativo por vez com um plano do sistema.

### Implementação

#### 1. **Banco de Dados** ✅
- **Migration**: `024_add_unique_constraint_tenant_plano_ativo.sql`
- **Constraint**: Índice UNIQUE usando coluna virtual `status_ativo_check`
- A coluna virtual retorna `tenant_id` quando status='ativo', senão NULL
- Como NULL não conta para UNIQUE, permite múltiplos registros inativos/cancelados
- **Garante**: Impossível ter 2 contratos ativos para a mesma academia (violação a nível de banco)

```sql
-- Coluna virtual
ALTER TABLE tenant_planos_sistema 
ADD COLUMN status_ativo_check INT GENERATED ALWAYS AS (
  CASE WHEN status = 'ativo' THEN tenant_id ELSE NULL END
) VIRTUAL;

-- Índice único
CREATE UNIQUE INDEX uk_tenant_ativo 
ON tenant_planos_sistema (status_ativo_check);
```

#### 2. **Modelo (TenantPlano.php)** ✅
- Método `criar()` verifica se já existe contrato ativo antes de inserir
- Lança exceção se tentar criar contrato ativo quando já existe um
- Mensagem clara: "Esta academia já possui um contrato ativo..."

```php
if ($this->buscarContratoAtivo($dados['tenant_id'])) {
    throw new \Exception('Esta academia já possui um contrato ativo...');
}
```

#### 3. **Controller (TenantPlanosSistemaController.php)** ✅
- Método `associarPlano()` valida antes de criar
- Retorna HTTP 409 (Conflict) com informações do contrato existente
- Sugere usar endpoint de trocar plano
- Remove a desativação automática (era comportamento incorreto)

```php
if ($contratoAtivo) {
    return response 409 com detalhes do contrato ativo
}
```

#### 4. **API - Endpoints** ✅
Todos os endpoints já existentes estão funcionando:
- `POST /academias/{id}/contratos` - Criar contrato (com validação)
- `POST /academias/{id}/trocar-plano` - Trocar plano (desativa o atual)
- `DELETE /contratos/{id}` - Cancelar contrato
- `GET /academias/{id}/contrato-ativo` - Buscar contrato ativo

## Estados de Contrato

### 1. **ativo**
- Apenas UM por academia
- Contrato atual em vigor
- Define as capacidades e recursos disponíveis

### 2. **inativo**
- Contrato que já foi válido mas foi substituído
- Mantido para histórico
- Múltiplos permitidos por academia

### 3. **cancelado**
- Contrato que foi cancelado antes do término
- Mantido para histórico e auditoria
- Múltiplos permitidos por academia

## Campo: `atual` vs `ativo`

### Plano Atual (`atual` em `planos_sistema`)
- Define se o plano está **disponível para novos contratos**
- `atual = true`: Pode ser contratado por novas academias
- `atual = false`: Não pode ser contratado, mas contratos existentes continuam válidos
- Usado para "descontinuar" um plano sem afetar quem já tem

### Plano Ativo (`ativo` em `planos_sistema`)
- Define se o plano está **ativo no sistema**
- `ativo = true`: Plano visível e utilizável
- `ativo = false`: Plano desativado/oculto

### Contrato Ativo (`status = 'ativo'` em `tenant_planos_sistema`)
- Define se o contrato está **em vigor**
- Apenas UM por academia (regra de negócio)

## Cenários de Uso

### Criar Novo Contrato
```http
POST /superadmin/academias/1/contratos
{
  "plano_sistema_id": 2,
  "forma_pagamento": "pix"
}
```
- ✅ Se academia NÃO tem contrato ativo: cria novo
- ❌ Se academia JÁ tem contrato ativo: erro 409

### Trocar de Plano
```http
POST /superadmin/academias/1/trocar-plano
{
  "plano_sistema_id": 3,
  "forma_pagamento": "cartao"
}
```
- Desativa contrato atual (status = 'inativo')
- Cria novo contrato (status = 'ativo')
- Transação atômica (rollback se falhar)

### Cancelar Contrato
```http
DELETE /superadmin/contratos/123
```
- Altera status para 'cancelado'
- Academia fica sem contrato ativo
- Pode criar novo contrato depois

## Validações

### Ao Criar Contrato
1. ✅ Academia deve existir
2. ✅ Plano deve existir
3. ✅ Plano deve estar ativo (`ativo = true`)
4. ✅ Academia não pode ter contrato ativo
5. ⚠️ Plano pode não ser atual (`atual = false`) - permite manter contratos de planos descontinuados

### Ao Trocar Plano
1. ✅ Academia deve existir
2. ✅ Academia deve ter contrato ativo
3. ✅ Novo plano deve existir e estar ativo
4. ✅ Desativa contrato atual antes de criar novo

## Benefícios

1. **Integridade dos Dados**: Impossível ter dados inconsistentes
2. **Controle de Acesso**: Fácil verificar capacidades da academia
3. **Histórico Completo**: Mantém todos os contratos anteriores
4. **Auditoria**: Rastreabilidade de todas as mudanças
5. **Performance**: Busca de contrato ativo é rápida (índice UNIQUE)

## Testes Recomendados

### Teste 1: Criar dois contratos ativos
```bash
# Deve falhar no segundo
POST /academias/1/contratos (plano 1) -> 201 OK
POST /academias/1/contratos (plano 2) -> 409 Conflict
```

### Teste 2: Trocar de plano
```bash
POST /academias/1/trocar-plano (plano 3) -> 200 OK
# Verifica que apenas plano 3 está ativo
```

### Teste 3: Múltiplos inativos
```bash
# Deve permitir múltiplos registros com status='inativo'
# para a mesma academia (histórico)
```
