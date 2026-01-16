# 🔧 Como Executar as Migrações de WOD

## Problema
A tabela `wods` não existe no banco de dados. Precisamos executar as migrações para criar as tabelas necessárias.

## Solução: Executar as Migrações

### Opção 1: Script Automático (Recomendado)

```bash
cd /Users/andrecabral/Projetos/AppCheckin/Backend/database/migrations
chmod +x run_wod_migrations.sh
./run_wod_migrations.sh
```

### Opção 2: MySQL Manual

Se a opção 1 não funcionar, execute manualmente:

```bash
# Conectar ao MySQL
mysql -u root -p appcheckin

# Executar as migrations
source 060_create_wods_table.sql;
source 061_create_wod_blocos_table.sql;
source 062_create_wod_variacoes_table.sql;
source 063_create_wod_resultados_table.sql;

# Verificar se foram criadas
SHOW TABLES LIKE 'wod%';
```

### Opção 3: Com Docker

Se estiver usando Docker:

```bash
docker exec -i seu_container_mysql mysql -u root -p"sua_senha" appcheckin < /path/para/060_create_wods_table.sql
docker exec -i seu_container_mysql mysql -u root -p"sua_senha" appcheckin < /path/para/061_create_wod_blocos_table.sql
docker exec -i seu_container_mysql mysql -u root -p"sua_senha" appcheckin < /path/para/062_create_wod_variacoes_table.sql
docker exec -i seu_container_mysql mysql -u root -p"sua_senha" appcheckin < /path/para/063_create_wod_resultados_table.sql
```

---

## Tabelas Criadas

### 1. `wods` (WOD Principal)
```sql
CREATE TABLE wods (
  id INT PRIMARY KEY,
  tenant_id INT NOT NULL,
  data DATE NOT NULL (UNIQUE per tenant),
  titulo VARCHAR(120) NOT NULL,
  descricao TEXT,
  status ENUM('draft','published','archived'),
  criado_por INT,
  criado_em DATETIME,
  atualizado_em DATETIME
);
```

### 2. `wod_blocos` (Blocos do WOD)
```sql
CREATE TABLE wod_blocos (
  id INT PRIMARY KEY,
  wod_id INT NOT NULL,
  ordem INT,
  tipo ENUM('warmup','strength','metcon','accessory','cooldown','note'),
  titulo VARCHAR(120),
  conteudo TEXT NOT NULL,
  tempo_cap VARCHAR(20),
  criado_em DATETIME,
  atualizado_em DATETIME
);
```

### 3. `wod_variacoes` (Variações - RX, Scaled, etc)
```sql
CREATE TABLE wod_variacoes (
  id INT PRIMARY KEY,
  wod_id INT NOT NULL,
  nome VARCHAR(40) NOT NULL,
  descricao TEXT,
  criado_em DATETIME,
  atualizado_em DATETIME
);
```

### 4. `wod_resultados` (Resultados/Leaderboard)
```sql
CREATE TABLE wod_resultados (
  id INT PRIMARY KEY,
  wod_id INT NOT NULL,
  usuario_id INT NOT NULL,
  variacao_id INT,
  resultado VARCHAR(50),
  tempo_total VARCHAR(20),
  repeticoes INT,
  peso DECIMAL(10,2),
  nota TEXT,
  criado_em DATETIME,
  atualizado_em DATETIME
);
```

---

## Verificar se as Tabelas Foram Criadas

```bash
# Conectar ao MySQL
mysql -u root -p appcheckin

# Verificar
SHOW TABLES LIKE 'wod%';

# Deve retornar:
# wod_blocos
# wod_resultados
# wod_variacoes
# wods
```

---

## Próximos Passos

Após executar as migrações:

1. ✅ Tabelas criadas
2. ✅ Endpoint `/admin/wods/completo` pronto
3. ✅ Frontend pode começar a usar

---

## Troubleshooting

### Erro: "Access Denied"
```bash
# Use a senha correta
mysql -u root -p appcheckin
# Digite a senha quando solicitado
```

### Erro: "Database does not exist"
```bash
# Crie o banco primeiro
mysql -u root -p
CREATE DATABASE appcheckin;
# Depois execute as migrations
```

### Erro: "Table already exists"
Isso é OK! As migrations usam `CREATE TABLE IF NOT EXISTS`
Significa que as tabelas já foram criadas.

---

## Status Esperado Após Execução

```
✅ Table 'wods' created
✅ Table 'wod_blocos' created  
✅ Table 'wod_variacoes' created
✅ Table 'wod_resultados' created

Agora o endpoint POST /admin/wods/completo funcionará!
```

---

## 🚀 Depois de Executar as Migrações

1. Testar o endpoint:
   ```bash
   ./test_wod_completo.sh
   ```

2. Ou criar um WOD via API:
   ```bash
   curl -X POST http://localhost:8000/admin/wods/completo \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d @exemplo_wod_completo.json
   ```

3. Frontend pode implementar o formulário usando `PASSO_A_PASSO_FRONTEND.md`

---

**Status**: Após executar estas migrations, o endpoint estará totalmente funcional! 🎉
