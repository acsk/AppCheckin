# 🎯 Massa de Dados para Testes do Dashboard

## 📊 Dados que serão criados

### Alunos (150 total)
- **120 alunos ativos** - com planos válidos e não vencidos
- **30 alunos inativos** - sem plano ou com plano vencido
- **12 novos alunos** - cadastrados em novembro/2025
- **8 planos vencendo** - nos próximos 7 dias

### Check-ins
- **~45 check-ins hoje** - distribuídos em diferentes horários
- **~890 check-ins no mês** - novembro/2025

### Planos (5 tipos)
1. **Plano Mensal Básico** - R$ 99,90 (30 dias)
2. **Plano Trimestral** - R$ 259,90 (90 dias)
3. **Plano Semestral** - R$ 479,90 (180 dias)
4. **Plano Anual** - R$ 899,90 (365 dias)
5. **Plano Semanal** - R$ 39,90 (7 dias)

### Receita Mensal Estimada
- **~R$ 15.000,00** - soma dos planos ativos

## 🚀 Como Executar

### Opção 1: Script Bash (Recomendado)

```bash
# Dar permissão de execução
chmod +x populate_test_data.sh

# Executar
./populate_test_data.sh
```

### Opção 2: Direto no MySQL

```bash
# Via Docker
docker exec -i $(docker ps -qf "name=mysql") mysql -uroot -proot checkin_db < Backend/database/seeds/seed_dashboard_test.sql

# Ou via cliente MySQL local
mysql -h localhost -P 3306 -u root -proot checkin_db < Backend/database/seeds/seed_dashboard_test.sql
```

### Opção 3: MySQL Workbench / phpMyAdmin

1. Abra o arquivo `Backend/database/seeds/seed_dashboard_test.sql`
2. Copie todo o conteúdo
3. Execute no seu cliente MySQL favorito

## 📝 Exemplos de Alunos Criados

### Alunos Ativos (alguns exemplos)
- Ana Silva Santos - Plano Mensal - Vence em 15 dias
- Carlos Eduardo Lima - Plano Mensal - Vence em 20 dias
- Gabriela Santos Lima - Plano Semestral - Vence em 90 dias
- Karina Souza Fernandes - Plano Anual - Vence em 180 dias

### Alunos Inativos (alguns exemplos)
- Pedro Inativo Silva - Sem plano
- Samuel Vencido Lima - Plano vencido há 5 dias
- Tatiana Expirado Santos - Plano vencido há 10 dias

### Novos Alunos (novembro/2025)
- Victor Novo Silva - Criado em 01/11/2025
- Wanda Novata Costa - Criado em 03/11/2025
- Giovana Acabou Entrar - Criado em 23/11/2025

### Planos Vencendo (próximos 7 dias)
- Hugo Vencendo Logo - Vence em 1 dia
- Iris Expirando Breve - Vence em 2 dias
- Nathan Acabando Logo - Vence em 7 dias

## 🎯 Testando o Dashboard

Após popular os dados, você pode testar:

### 1. Endpoint da API

```bash
# Login como admin
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@admin.com","senha":"admin123"}'

# Buscar estatísticas (use o token do login)
curl http://localhost:8080/admin/dashboard \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

### 2. Frontend

1. Acesse http://localhost:8100
2. Faça login como admin
3. Navegue para o Dashboard Admin
4. Veja as estatísticas preenchidas:
   - 150 Total de Alunos (120 ativos)
   - 45 Check-ins Hoje
   - 890 Check-ins do Mês
   - 8 Planos Vencendo
   - R$ 15.000,00 Receita Mensal
   - 12 Novos este mês
   - 30 Alunos inativos
   - 80% Taxa de atividade

### 3. Gerenciar Alunos

1. Acesse "Gerenciar Alunos"
2. Veja a lista de 150 alunos
3. Teste os filtros de busca
4. Veja os status dos planos (ativo, vencido, vencendo)
5. Teste criar/editar/excluir alunos

## 🧹 Limpar Dados de Teste

Se quiser limpar os dados de teste:

```sql
-- ⚠️ CUIDADO: Isso remove TODOS os dados de alunos e check-ins!

DELETE c FROM checkins c
INNER JOIN usuarios u ON c.usuario_id = u.id
WHERE u.tenant_id = 1;

DELETE FROM usuarios WHERE tenant_id = 1 AND role_id = 1;
DELETE FROM planos WHERE tenant_id = 1;
DELETE FROM horarios WHERE dia_id IN (SELECT id FROM dias WHERE tenant_id = 1);
DELETE FROM dias WHERE tenant_id = 1;
```

## 📊 Verificar Dados Criados

```sql
-- Ver resumo
SELECT 
    COUNT(*) as total_alunos,
    SUM(CASE WHEN plano_id IS NOT NULL THEN 1 ELSE 0 END) as com_plano,
    SUM(CASE WHEN plano_id IS NULL THEN 1 ELSE 0 END) as sem_plano
FROM usuarios 
WHERE tenant_id = 1 AND role_id = 1;

-- Ver check-ins hoje
SELECT COUNT(*) FROM checkins WHERE DATE(created_at) = CURDATE();

-- Ver receita mensal
SELECT SUM(p.valor) as receita
FROM usuarios u
JOIN planos p ON u.plano_id = p.id
WHERE u.tenant_id = 1 
  AND u.data_vencimento_plano >= CURDATE();
```

## ✅ Sucesso!

Agora você tem uma base de dados completa para testar todas as funcionalidades do dashboard admin!
