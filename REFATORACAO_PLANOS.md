# Refatoração do Sistema de Planos ✅

## 📋 Resumo das Alterações

Sistema de planos refatorado para remover campos desnecessários e unificar as telas de criação/edição.

---

## 🗄️ Alterações no Banco de Dados

### Migration 047 - Remover Campos Legados
**Arquivo:** `Backend/database/migrations/047_remove_legacy_fields_planos.sql`

**Campos Removidos:**
- ❌ `checkins_mensais` (não utilizado)
- ❌ `max_alunos` (não utilizado)

**Campos Mantidos:**
- ✅ `id`
- ✅ `tenant_id`
- ✅ `modalidade_id` (FK para modalidades)
- ✅ `checkins_semanais` (limite de checkins por semana)
- ✅ `nome`
- ✅ `descricao`
- ✅ `valor`
- ✅ `duracao_dias`
- ✅ `ativo` (status do plano)
- ✅ `atual` (disponível para novos contratos)
- ✅ `created_at`
- ✅ `updated_at`

---

## 🔧 Alterações no Backend

### 1. Modelo Plano.php
**Localização:** `Backend/app/Models/Plano.php`

**Método `create()`:**
```php
// ANTES: Incluía checkins_mensais e max_alunos
INSERT INTO planos (tenant_id, modalidade_id, nome, descricao, valor, 
    duracao_dias, checkins_mensais, max_alunos, ativo, atual)

// DEPOIS: Apenas campos necessários
INSERT INTO planos (tenant_id, modalidade_id, nome, descricao, valor, 
    duracao_dias, checkins_semanais, ativo, atual)
```

**Método `update()`:**
- ❌ Removidos: `checkins_mensais`, `max_alunos`
- ✅ Adicionado: `checkins_semanais`

**Consultas (getAll, findById, getDisponiveis):**
- Já incluíam JOIN com modalidades
- Retornam: `modalidade_nome`, `modalidade_cor`, `modalidade_icone`

### 2. PlanoController.php
**Localização:** `Backend/app/Controllers/PlanoController.php`

**Validação no método `create()`:**
```php
// ANTES
if (!isset($data['max_alunos']) || $data['max_alunos'] < 1) {
    $errors[] = 'Capacidade de alunos é obrigatória';
}

// DEPOIS
if (!isset($data['checkins_semanais']) || $data['checkins_semanais'] < 1) {
    $errors[] = 'Checkins semanais é obrigatório';
}
```

**Proteções Existentes:**
- ✅ `delete()`: Verifica se há usuários usando o plano via `countUsuarios()`
- ✅ `update()`: Verifica se há contratos via `possuiContratos()`

---

## 🎨 Alterações no Frontend

### 1. FormPlanoScreen.js (NOVA TELA UNIFICADA)
**Localização:** `FrontendWeb/src/screens/planos/FormPlanoScreen.js`

**Características:**
- ✅ Unifica criação e edição (detecta via parâmetro `id`)
- ✅ Carrega modalidades ativas no início
- ✅ Modo edição: pré-carrega dados do plano

**Campos do Formulário:**
1. **Modalidade** (Picker) - Obrigatório
2. **Nome do Plano** - Obrigatório
3. **Descrição** - Opcional
4. **Valor Mensal (R$)** - Obrigatório
5. **Checkins/Semana** - Obrigatório (999 = ilimitado)
6. **Duração do Plano** (Picker: 30/90/180/365 dias)
7. **Status** (Switch: Ativo/Inativo)
8. **Disponível para Novos Contratos** (Switch: atual)

**Validação:**
```javascript
validateForm() {
  - modalidade_id: obrigatório
  - nome: obrigatório
  - valor: >= 0
  - checkins_semanais: >= 1
}
```

**Submit:**
```javascript
dataToSend = {
  modalidade_id: int,
  nome: string,
  descricao: string,
  valor: float,
  checkins_semanais: int,
  duracao_dias: int,
  ativo: 0|1,
  atual: 0|1
}
```

### 2. PlanosScreen.js (ATUALIZADA)
**Localização:** `FrontendWeb/src/screens/planos/PlanosScreen.js`

**Card Mobile - Novos Campos:**
```javascript
- Modalidade (se disponível)
- Valor
- Checkins/Semana (999 = Ilimitado)
- Novos Contratos (Disponível/Bloqueado)
```

**Tabela Desktop - Colunas Atualizadas:**
| Coluna | Antes | Depois |
|--------|-------|--------|
| Nome | ✅ | ✅ |
| Modalidade | ❌ | ✅ |
| Valor | Valor Mensal | Valor |
| Capacidade | Capacidade de Alunos | ❌ |
| Checkins/Sem | ❌ | ✅ |
| Novos Contr. | ❌ | ✅ (Sim/Não) |
| Status | ✅ | ✅ |
| Ações | ✅ | ✅ |

**Badges Adicionados:**
```javascript
// Badge "Novos Contratos"
atualAvailable: azul (#3b82f6) - Pode criar contratos
atualLocked: cinza (#6b7280) - Apenas contratos existentes
```

### 3. Rotas Atualizadas
**Arquivos:**
- `FrontendWeb/app/planos/novo.js`
- `FrontendWeb/app/planos/[id].js`

```javascript
// ANTES
import NovoPlanoScreen from '../../src/screens/planos/NovoPlanoScreen';
import EditarPlanoScreen from '../../src/screens/planos/EditarPlanoScreen';

// DEPOIS (ambas usam a mesma tela)
import FormPlanoScreen from '../../src/screens/planos/FormPlanoScreen';
```

### 4. Arquivos que PODEM SER DELETADOS (opcional)
- ❌ `FrontendWeb/src/screens/planos/NovoPlanoScreen.js` (substituído por FormPlanoScreen)
- ❌ `FrontendWeb/src/screens/planos/EditarPlanoScreen.js` (substituído por FormPlanoScreen)

---

## 📊 Estrutura Final dos Dados

### JSON Retornado pela API (getAll/findById):
```json
{
  "id": 17,
  "tenant_id": 5,
  "modalidade_id": 4,
  "checkins_semanais": 1,
  "nome": "1x por semana",
  "descricao": null,
  "valor": "70.00",
  "duracao_dias": 30,
  "ativo": 1,
  "atual": 1,
  "created_at": "2026-01-06 20:27:14",
  "updated_at": "2026-01-06 20:27:14",
  "modalidade_nome": "Natação",
  "modalidade_cor": "#3b82f6",
  "modalidade_icone": "droplet"
}
```

### Campos do JOIN (modalidades):
- `modalidade_nome`: Nome da modalidade
- `modalidade_cor`: Cor em hexadecimal
- `modalidade_icone`: Nome do ícone

---

## 🎯 Funcionalidades Garantidas

### 1. Proteções
✅ **Exclusão/Desativação:**
- Plano com usuários: Bloqueado via `countUsuarios()`
- Mensagem de erro retornada ao frontend

✅ **Edição:**
- Plano com contratos: Validado via `possuiContratos()`
- Permite edição mas com restrições

### 2. Campo "Atual" (Novos Contratos)
- `atual = 1`: Plano disponível para novos contratos
- `atual = 0`: Plano em modo histórico (apenas contratos existentes)

**Uso:**
- Desativar plano para novos contratos sem afetar os existentes
- Criar nova versão de plano mantendo o antigo para referência

### 3. Checkins Semanais
- Valores de 1 a 998: Limite específico
- Valor 999: Ilimitado
- Display: "Ilimitado" ou "Xx" (ex: "2x", "3x")

---

## 🚀 Como Testar

### 1. Criar Novo Plano
```
1. Acessar /planos
2. Clicar em "Novo Plano"
3. Preencher:
   - Modalidade: Natação
   - Nome: 2x por semana
   - Valor: 150.00
   - Checkins/Semana: 2
   - Duração: 30 dias
   - Status: Ativo
   - Novos Contratos: Sim
4. Salvar
```

### 2. Editar Plano Existente
```
1. Na lista, clicar no ícone de editar
2. Modificar campos necessários
3. Salvar alterações
4. Verificar se mudanças foram aplicadas
```

### 3. Testar Proteções
```
1. Criar um plano e vincular a um usuário
2. Tentar desativar o plano
3. Deve exibir erro: "Não pode desativar plano com usuários"
```

### 4. Testar Campo "Atual"
```
1. Editar um plano existente
2. Desmarcar "Disponível para Novos Contratos"
3. Salvar
4. Na lista, verificar badge "Não" na coluna "Novos Contr."
5. Ao criar contrato, plano não deve aparecer na seleção
```

---

## ✅ Checklist de Verificação

- [x] Migration 047 executada com sucesso
- [x] Campos `checkins_mensais` e `max_alunos` removidos do banco
- [x] Modelo Plano.php atualizado (create/update)
- [x] PlanoController.php validação atualizada
- [x] FormPlanoScreen.js criado (unificado)
- [x] Rotas novo.js e [id].js atualizadas
- [x] PlanosScreen.js lista atualizada (cards e tabela)
- [x] Estilos atualizados (badges "atual")
- [x] Validações de proteção mantidas
- [x] JOIN com modalidades funcionando

---

## 📝 Observações Importantes

1. **Não Quebre Contratos Existentes:**
   - Campo `atual` permite desativar para novos sem afetar contratos antigos
   - Nunca delete um plano com contratos/usuários vinculados

2. **Checkins Semanais:**
   - Use 999 como convenção para "ilimitado"
   - Frontend exibe "Ilimitado" automaticamente

3. **Modalidade Obrigatória:**
   - Todo plano deve estar vinculado a uma modalidade
   - Modalidade define o tipo de serviço

4. **Backward Compatibility:**
   - API ainda funciona com códigos antigos que não usam `atual`
   - Default: `atual = 1` (disponível para novos contratos)

---

## 🎉 Resultado Final

Sistema de planos agora está:
- ✅ **Limpo:** Sem campos desnecessários
- ✅ **Padronizado:** Model e telas consistentes
- ✅ **Unificado:** Uma única tela para criar/editar
- ✅ **Completo:** Todas as informações necessárias
- ✅ **Protegido:** Validações de integridade ativas
- ✅ **Moderno:** Usa relacionamento com modalidades

**Status:** Pronto para produção! 🚀
