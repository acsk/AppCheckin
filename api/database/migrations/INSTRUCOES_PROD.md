# Instruções para Executar Migration em Produção

## ⚠️ IMPORTANTE: Event Scheduler

O MySQL precisa ter o `event_scheduler` ativado. **Só o administrador do servidor pode fazer isso**.

### Verificar se está ativado:
```sql
SHOW VARIABLES LIKE 'event_scheduler';
```

Se mostrar `OFF`, peça ao administrador do servidor para ativar adicionando no arquivo `my.cnf`:
```ini
[mysqld]
event_scheduler = ON
```

Ou via comando (requer privilégio SUPER):
```sql
SET GLOBAL event_scheduler = ON;
```

---

## 🚀 Execução no PHPMyAdmin (Passo a Passo)

Execute os arquivos **NA ORDEM**, um por vez:

### Passo 1: Remover evento anterior (se existir)
- Abrir arquivo: `step1_drop_event.sql`
- Copiar conteúdo
- Colar na aba SQL do PHPMyAdmin
- Clicar em "Executar"

### Passo 2: Criar evento automático
- Abrir arquivo: `step2_create_event.sql`
- Copiar conteúdo **COMPLETO** (incluindo DELIMITER)
- Colar na aba SQL do PHPMyAdmin
- Clicar em "Executar"

### Passo 3: Atualizar matrículas vencidas agora
- Abrir arquivo: `step3_update_now.sql`
- Copiar conteúdo
- Colar na aba SQL do PHPMyAdmin
- Clicar em "Executar"
- **Resultado**: Verá quantas linhas foram atualizadas

### Passo 4: Verificar se funcionou
- Abrir arquivo: `step4_verify.sql`
- Copiar conteúdo
- Colar na aba SQL do PHPMyAdmin
- Clicar em "Executar"
- **Resultado**: 
  - 1ª query mostra eventos ativos (deve aparecer `atualizar_matriculas_vencidas`)
  - 2ª query mostra matrículas com status "Vencida"

---

## 🖥️ Execução via SSH (Mais Rápido)

Se tiver acesso SSH ao servidor:

```bash
# Conectar no servidor
ssh usuario@servidor

# Navegar até a pasta da API
cd /caminho/da/api

# Executar a migration PHP (executa tudo automaticamente)
php database/migrations/add_trigger_atualizar_status_vencido.php
```

---

## ✅ Como Saber se Funcionou?

Execute este SQL:
```sql
SHOW EVENTS;
```

Deve aparecer:
- **Nome**: `atualizar_matriculas_vencidas`
- **Status**: `ENABLED`
- **Interval**: `1 DAY`

E verifique matrículas vencidas:
```sql
SELECT id, status_id, proxima_data_vencimento 
FROM matriculas 
WHERE proxima_data_vencimento < CURDATE();
```

Todas devem ter `status_id = 2` (vencida).

---

## 🔧 Troubleshooting

### Erro: "Event scheduler is disabled"
- O `event_scheduler` não está ativado
- Contate o administrador do servidor
- Precisa adicionar `event_scheduler = ON` no `my.cnf` e reiniciar MySQL

### Erro: "Access denied; you need SUPER privilege"
- Use a execução passo a passo (step1, step2, step3, step4)
- Não tente executar `SET GLOBAL` no PHPMyAdmin

### Evento não aparece no SHOW EVENTS
- Verifique se o banco de dados está selecionado
- Execute `USE nome_do_banco;` antes

### Matrículas não foram atualizadas
- Execute manualmente o `step3_update_now.sql`
- Isso atualiza as matrículas já vencidas
