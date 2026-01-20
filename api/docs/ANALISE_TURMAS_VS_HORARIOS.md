# ⚠️ ANÁLISE: Arquitetura de Turmas vs Horarios - Inconsistência Estrutural

## 📋 Problema Identificado

Existe uma **desconexão entre o modelo de dados e como está sendo utilizado**:

### 1. Estrutura do Banco de Dados

#### Tabela `turmas`
```sql
id, tenant_id, professor_id, modalidade_id, dia_id,
horario_inicio, horario_fim, nome, limite_alunos, ativo
-- ❌ NÃO TEM: tolerancia_minutos, tolerancia_antes_minutos
```

#### Tabela `horarios` (Vazia)
```sql
id, dia_id, hora, horario_inicio, horario_fim, 
limite_alunos, tolerancia_minutos, tolerancia_antes_minutos, ativo
-- ⚠️ Coluna 'hora' é redundante (já tem horario_inicio e horario_fim)
-- 📊 Status: VAZIA (0 registros)
```

---

## 🔍 O Que Está Acontecendo

### Frontend envia:
```json
{
  "nome": "Natação - 05:00 - Marcela Oliveira",
  "dia_id": 17,
  "horario_inicio": "05:00:00",
  "horario_fim": "06:00:00",
  "limite_alunos": 5,
  "tolerancia_minutos": 10,
  "tolerancia_antes_minutos": 960
}
```

### TurmaController recebe e insere em `turmas`:
```php
// Linha 166-177 do TurmaController.php
INSERT INTO turmas (
    tenant_id, professor_id, modalidade_id, dia_id, 
    horario_inicio, horario_fim, nome, limite_alunos, ativo
) VALUES (...)
// ❌ tolerancia_minutos e tolerancia_antes_minutos SÃO IGNORADOS
```

### Banco de Dados:
```
✅ turmas: RECEBE horario_inicio, horario_fim
❌ turmas: NÃO RECEBE tolerancia_minutos, tolerancia_antes_minutos
❌ horarios: NUNCA É PREENCHIDA (vazia)
```

---

## 📊 Análise dos Controllers

| Controller | Uso | Status |
|-----------|-----|--------|
| **TurmaController** | Criar/Listar/Atualizar turmas | ✅ Ativo |
| **HorarioController** | Deveria gerenciar horarios.php | ❌ Não utilizado |
| **DiaController** | Gerencia dias | ✅ Ativo |

---

## 🎯 Qual é a Intenção Original?

Observando a estrutura, parece que havia um plano de ter:

1. **dias** → Datas (2026-01-20)
2. **horarios** → Horários por dia (múltiplos horários por dia)
   - Ex: 05:00-06:00, 06:00-07:00, 07:00-08:00
   - Com tolerâncias específicas por horário
3. **turmas** → Atribuição de professor/modalidade a um horário

**Fluxo esperado:**
```
Dia (2026-01-20) → Horário (05:00-06:00) → Turma (Natação com Prof. Marcela)
```

---

## ❌ O Que Está Errado Agora

### 1. Dados de Tolerância Perdidos
```javascript
// Frontend envia
{tolerancia_minutos: 10, tolerancia_antes_minutos: 960}
// ↓
// TurmaController ignora
// ↓
// Banco de dados: PERDIDO ❌
```

### 2. Relação `turmas.horario_id` → Nunca Usada
```sql
-- turmas.horario_id referencia horarios.id
-- MAS horarios está vazia, então:
-- - CONSTRAINT FOREIGN KEY falha se tentar preencher
-- - OU está NULL em todos os registros
```

### 3. HorarioController Existe Mas Não É Usado
```php
// app/Controllers/HorarioController.php
// Tem métodos, mas nenhuma rota aponta para ele
// (verificar routes/api.php)
```

---

## 🔧 Soluções Possíveis

### Opção 1: Usar Apenas `turmas` (Simplificar)
```sql
ALTER TABLE turmas ADD COLUMN (
    tolerancia_minutos INT DEFAULT 10,
    tolerancia_antes_minutos INT DEFAULT 480
);
-- Remover: turmas.horario_id
-- Remover: tabela horarios (já não é usada)
-- Usar: TurmaController para tudo
```
**Resultado:** Uma única tabela com tudo, simples e funcional.

### Opção 2: Usar Ambas (Arquitetura Completa)
```sql
-- Implementar horarios como "templates"
-- horarios: Define horários e tolerâncias disponíveis
-- turmas: Associa professor/modalidade a um horário
-- Vantagem: Reutilizar mesma tolerância em múltiplas turmas
-- Desvantagem: Mais complexo
```

### Opção 3: Híbrida (Recomendada)
```sql
-- turmas: PRINCIPAL (já está em uso)
-- horarios: OPCIONAL para consultas (relatórios, gráficos)
-- Adicionar em turmas: tolerancia_minutos, tolerancia_antes_minutos
-- Manter: horario_id NULL ou remover
```

---

## 📝 Recomendação

**Usar Opção 1 (Simplificar para `turmas`)**

### Passos:
1. Adicionar colunas de tolerância em `turmas`:
   ```sql
   ALTER TABLE turmas ADD COLUMN (
       tolerancia_minutos INT NOT NULL DEFAULT 10,
       tolerancia_antes_minutos INT NOT NULL DEFAULT 480
   );
   ```

2. Remover `horario_id` de `turmas` (redundante):
   ```sql
   ALTER TABLE turmas DROP FOREIGN KEY fk_turmas_horario;
   ALTER TABLE turmas DROP COLUMN horario_id;
   ```

3. Marcar `horarios` como legacy (ou remover):
   ```sql
   -- Deixar como histórico, ou
   DROP TABLE horarios;
   ```

4. Atualizar `TurmaController`:
   ```php
   // Agora salva tolerancias
   'tolerancia_minutos' => $data['tolerancia_minutos'] ?? 10,
   'tolerancia_antes_minutos' => $data['tolerancia_antes_minutos'] ?? 480,
   ```

5. Remover/Desativar `HorarioController` (não será mais usado)

---

## 📚 Arquivos Envolvidos

### Banco de Dados
- `database/migrations/001_create_tables.sql` - Define horarios
- `database/migrations/002_adjust_horarios_for_classes.sql` - Adiciona tolerancia
- `database/migrations/055_create_turmas_table.sql` - Define turmas

### Controllers
- `app/Controllers/TurmaController.php` - ✅ Em uso, ignorando tolerancia
- `app/Controllers/HorarioController.php` - ❌ Não utilizado

### Models
- `app/Models/Turma.php` - Insere em turmas
- `app/Models/Horario.php` - Não utilizado

---

## ✅ Conclusão

**O sistema está funcionando, mas com dados incompletos:**

- ✅ Turmas são criadas corretamente
- ✅ Horarios (início/fim) são salvos em turmas
- ❌ **Tolerâncias são PERDIDAS** (não salvas em nenhum lugar)
- ❌ Tabela `horarios` está vazia e sem propósito claro
- ❌ `HorarioController` não é usado

**Recomendação:** Consolidar tudo em `turmas` e adicionar os campos de tolerância que estão faltando.

