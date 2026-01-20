# 📊 Análise de Uso: Model Horario e Tabela horarios

## Status Geral

**🎯 Resumo Executivo:**
- ❌ **Model Horario**: Órfão (existe mas não é usado)
- ❌ **HorarioController**: Nunca existiu
- ⚠️ **Tabela horarios**: Vazia porque ninguém a alimenta

---

## 1️⃣ Model Horario (app/Models/Horario.php)

### Status: ❌ ORPHANED (Órfão)

#### Quem NÃO está usando:
```
❌ DiaController         → Usa TurmaModel (refatorado)
❌ CheckinController    → Usa TurmaModel (refatorado)
❌ MobileController     → Usa TurmaModel (não usa Horario)
❌ Nenhum outro Controller → Sem referências ativas
```

#### Quem ainda referencia:
```
✓ TurmaController_old.php → Arquivo LEGADO/BACKUP (não está em uso)
```

### Conclusão:
O arquivo `app/Models/Horario.php` **não está sendo utilizado** por nenhuma classe controller ativa. É um **arquivo legado** que pode ser deletado com segurança.

---

## 2️⃣ HorarioController

### Status: ❌ NÃO EXISTE

- Nenhum arquivo chamado `HorarioController.php` foi encontrado
- Nunca existiu no projeto ativo
- Pode ter existido em versões antigas (não relevante agora)

---

## 3️⃣ Tabela "horarios" - Por Que Está Vazia?

### ⚠️ RAIZ DO PROBLEMA

**A tabela horarios NÃO está sendo alimentada porque:**

#### Única Fonte de Dados: PlanejamentoController::gerarHorarios()

```php
// app/Controllers/PlanejamentoController.php
public function gerarHorarios(Request $request, Response $response, array $args)
{
    // Este método existe mas NÃO ESTÁ SENDO CHAMADO
    // Razão: Ninguém chama a rota POST /admin/planejamentos/{id}/gerar-horarios
}
```

#### Rota Disponível MAS SEM USO:
```
POST /admin/planejamentos/{id}/gerar-horarios
```

#### SQL Que DEVERIA Executar:
```sql
INSERT INTO horarios (dia_id, hora, vagas, ativo, tenant_id)
VALUES (...)
```

---

## 4️⃣ Quem Deveria Alimentar a Tabela?

### Opção A: Chamada Manual via API ❌ NÃO ESTÁ ACONTECENDO

```bash
# Frontend ou Admin deveria chamar:
POST /admin/planejamentos
POST /admin/planejamentos/{id}/gerar-horarios

# Mas ninguém está chamando
```

### Opção B: Job/Cron Automático ❌ NÃO EXISTE

```bash
# Pasta jobs/ não tem nenhum script que:
# - Cria planejamentos automaticamente
# - Chama gerarHorarios()
```

### Opção C: Função Interna ❌ NÃO IMPLEMENTADA

```php
// Nenhuma rota chama gerarHorarios() automaticamente
// Nenhuma seeder popula horarios
```

---

## 5️⃣ Achados Principais

### ✅ O Que Está Certo

1. **Refatoração Completa**
   - ✓ DiaController usa TurmaModel (não Horario)
   - ✓ CheckinController usa TurmaModel (não Horario)
   - ✓ Todas as APIs ativas funcionam sem Horario
   - ✓ TurmaModel é a fonte única de verdade

2. **Arquitetura Consolidada**
   - ✓ Não há redundância de dados
   - ✓ Códigos legados isolados (TurmaController_old.php)
   - ✓ APIs retornam dados corretos

### ❌ O Que Está Errado

1. **Tabela horarios Vazia**
   - ✗ Ninguém alimenta a tabela
   - ✗ PlanejamentoController::gerarHorarios() nunca é chamado
   - ✗ Tabela pode ser descontinuada

2. **Código Orphaned**
   - ✗ Model Horario existe mas não é usado
   - ✗ PlanejamentoController pode nunca ser chamado
   - ✗ Código legado não foi limpo

---

## 6️⃣ Recomendações

### 🔴 Curto Prazo (Imediato)

#### Opção 1: Manter horarios (Se necessário)
```bash
1. Criar um seeder ou script para popular horarios
2. Chamar PlanejamentoController::gerarHorarios()
3. OU criar um job/cron para isso
```

#### Opção 2: Abandonar horarios (Recomendado ✅)
```bash
1. Deletar: app/Models/Horario.php
2. Deletar: app/Controllers/PlanejamentoController.php (se não usado)
3. Deletar: app/Models/PlanejamentoHorario.php
4. Remover rota de planejamentos (routes/api.php)
5. Manter apenas: TurmaModel (que já substitui tudo)
```

### 🟡 Médio Prazo

Se optar por manter horarios:

```bash
1. Criar job que popula horarios automaticamente
2. Documentar o fluxo de criação de planejamentos
3. Testar integração frontend → PlanejamentoController
```

### 🟢 Longo Prazo

```bash
1. Consolidar completamente em TurmaModel
2. Deprecar HorarioModel
3. Remover tabela horarios do banco
```

---

## 7️⃣ Checklist de Decisão

### A Tabela horarios É Realmente Necessária?

- [ ] Alguma rota retorna dados de horarios?
  - ✓ SIM → `/mobile/horarios`, `/admin/dias/{id}/horarios`
  - ✗ MAS: Estas rotas retornam dados de TurmaModel, NÃO de horarios

- [ ] Alguma rota insere em horarios?
  - ✓ SIM → PlanejamentoController::gerarHorarios()
  - ✗ MAS: Ninguém chama este método

- [ ] O TurmaModel substitui todas as funcionalidades?
  - ✓ **SIM! Totalmente.**

### Conclusão:
**A tabela horarios pode ser descontinuada com segurança.**

---

## 8️⃣ Próximos Passos

### Recomendação: ✅ DELETAR (Opção Mais Limpa)

```bash
# 1. Deletar arquivos
rm app/Models/Horario.php
rm app/Models/PlanejamentoHorario.php
rm app/Controllers/PlanejamentoController.php

# 2. Atualizar rotas
# Remover de routes/api.php:
# - use App\Controllers\PlanejamentoController;
# - /planejamentos/*

# 3. Remover tabela do banco (após backup)
DROP TABLE horarios;
```

### Alternativa: Manter e Documentar

```bash
# Se precisar manter:
# 1. Criar script: jobs/populate_horarios.php
# 2. Chamar em cron: "0 */6 * * * php jobs/populate_horarios.php"
# 3. Documentar fluxo em: docs/FLUXO_PLANEJAMENTO_HORARIOS.md
```

---

## 9️⃣ Evidências Técnicas

### Nenhuma Referência Ativa ao Model Horario

```bash
# Comando executado:
grep -r "use App\\Models\\Horario" app/Controllers/*.php

# Resultado:
# Nenhum match em Controllers ativos
# Apenas em TurmaController_old.php (legado)
```

### Nenhum INSERT em horarios

```bash
# Comando executado:
grep -r "INSERT INTO horarios" app/

# Resultado:
# Encontrado apenas em: app/Models/PlanejamentoHorario.php
# (arquivo não usado)
```

### Rotas de Planejamento Existem Mas Não São Documentadas

```php
// routes/api.php linha 313-319
// POST   /admin/planejamentos
// GET    /admin/planejamentos
// GET    /admin/planejamentos/{id}
// PUT    /admin/planejamentos/{id}
// DELETE /admin/planejamentos/{id}
// POST   /admin/planejamentos/{id}/gerar-horarios

// ⚠️ Nenhuma documentação sobre usar estas rotas
// ⚠️ Sem exemplos no frontend
```

---

## 🔟 Conclusão Final

### 📊 Status da Consolidação

| Item | Status | Ação |
|------|--------|------|
| Model Horario | ❌ Orphaned | DELETE |
| HorarioController | ❌ Não existe | N/A |
| Tabela horarios | ⚠️ Vazia | DELETE ou POPULATE |
| TurmaModel | ✅ Ativo | MANTER |
| APIs Ativas | ✅ Funcionando | MANTER |

### 🎯 Recomendação Final

**✅ DELETAR** Model Horario, PlanejamentoHorario e PlanejamentoController para:
- Reduzir código legado
- Simplificar arquitetura
- Eliminar confusão
- Manter apenas TurmaModel como fonte de verdade

---

## 📚 Documentação Relacionada

- [CONSOLIDACAO_COMPLETA_HORARIOS.md](CONSOLIDACAO_COMPLETA_HORARIOS.md) - Refatoração completa
- [CONCLUSAO_FINAL.md](CONCLUSAO_FINAL.md) - Validação de testes
- [STATUS_REMOCAO_HORARIOS.md](STATUS_REMOCAO_HORARIOS.md) - Status de progresso

---

**Criado:** 20 de janeiro de 2026  
**Status:** ✅ ANÁLISE COMPLETA
