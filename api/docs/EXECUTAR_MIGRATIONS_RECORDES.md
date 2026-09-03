# Executar migração de Recordes no banco

## Problema

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table '...recordes' doesn't exist
SQLSTATE[42S02]: Base table or view not found: 1146 Table '...recorde_definicoes' doesn't exist
```

O módulo de recordes usa tabelas criadas por script manual (`api/database/migrate_recordes_pessoais.php`).
Elas **não** fazem parte do `run_all_migrations.sh` e precisam ser executadas explicitamente em cada ambiente
(local, staging, produção) antes do cutover do painel para apiV2.

Tabelas criadas:

| Tabela | Descrição |
|--------|-----------|
| `recorde_definicoes` | Tipos de recorde/teste (Deadlift, 100m Crawl, etc.) |
| `recorde_definicao_metricas` | Métricas de cada definição (peso, tempo, reps) |
| `recordes` | Tentativas/PRs |
| `recorde_valores` | Valores medidos por métrica |

O script também insere **definições padrão** para todos os tenants ativos (natação, força, corrida).

---

## Produção (Hostinger / shared hosting)

No servidor, a partir da pasta da API Slim (onde está o `.env` com credenciais do MySQL):

```bash
cd /home/u304177849/domains/appcheckin.com.br/public_html/api
php database/migrate_recordes_pessoais.php
```

Saída esperada:

```
=== Migração: Recordes (Modelagem Genérica) ===

✅ Tabela recorde_definicoes criada
✅ Tabela recorde_definicao_metricas criada
✅ Tabela recordes criada
✅ Tabela recorde_valores criada
  📋 Tenant #3: N definições padrão inseridas
...
✅ Migração concluída com sucesso!
```

O script é **idempotente** (`CREATE TABLE IF NOT EXISTS` + inserts condicionais).

---

## Docker local

```bash
cd api
docker exec -i appcheckin_php php /var/www/html/database/migrate_recordes_pessoais.php
```

Ou via script:

```bash
cd api/database/migrations
chmod +x run_recordes_migration.sh
./run_recordes_migration.sh
```

---

## Verificar

```bash
mysql -u USER -p DATABASE -e "SHOW TABLES LIKE 'recorde%'; SHOW TABLES LIKE 'recordes';"
```

Deve listar: `recorde_definicoes`, `recorde_definicao_metricas`, `recorde_valores`, `recordes`.

Teste rápido na apiV2 (com JWT de admin):

```bash
curl -s -H "Authorization: Bearer TOKEN" \
  "https://apiv2.appcheckin.com.br/v2/admin/recordes/definicoes" | head
```

---

## Relacionado

- [RECORDES_PESSOAIS_API.md](RECORDES_PESSOAIS_API.md) — contrato da API
- `apiV2/docs/MIGRACAO_ROTAS_SLIM.md` — cutover painel → apiV2 (requer tabelas existentes)
