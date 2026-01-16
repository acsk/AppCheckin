# ⚠️ FALTANDO: Executar as Migrações do Banco de Dados

## 🔴 O Problema

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'appcheckin.wods' doesn't exist
```

A tabela `wods` não foi criada no banco de dados.

---

## ✅ A Solução

### Passo 1: Executar o Script de Migrations

```bash
cd /Users/andrecabral/Projetos/AppCheckin/Backend/database/migrations
chmod +x run_wod_migrations.sh
./run_wod_migrations.sh
```

Você será solicitado a digitar a senha do MySQL.

### Passo 2: Verificar se as Tabelas Foram Criadas

```bash
mysql -u root -p appcheckin
SHOW TABLES LIKE 'wod%';
```

Deve retornar:
```
wod_blocos
wod_resultados
wod_variacoes
wods
```

---

## 📝 Alternativa: Executar Manualmente

Se o script não funcionar:

```bash
mysql -u root -p appcheckin < 060_create_wods_table.sql
mysql -u root -p appcheckin < 061_create_wod_blocos_table.sql
mysql -u root -p appcheckin < 062_create_wod_variacoes_table.sql
mysql -u root -p appcheckin < 063_create_wod_resultados_table.sql
```

---

## 🎯 Resultado Esperado

Após executar as migrations:

```
✅ Table 'appcheckin.wods' created
✅ Table 'appcheckin.wod_blocos' created
✅ Table 'appcheckin.wod_variacoes' created
✅ Table 'appcheckin.wod_resultados' created

✅ Endpoint POST /admin/wods/completo FUNCIONAL
✅ Pronto para testar!
```

---

## 🧪 Testar Após as Migrations

```bash
# Opção 1: Script automático
./test_wod_completo.sh

# Opção 2: cURL manual
curl -X POST http://localhost:8000/admin/wods/completo \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d @exemplo_wod_completo.json
```

---

## 📚 Documentação Relacionada

- [EXECUTAR_MIGRATIONS_WOD.md](EXECUTAR_MIGRATIONS_WOD.md) - Instruções detalhadas
- [test_wod_completo.sh](test_wod_completo.sh) - Script de teste
- [PASSO_A_PASSO_FRONTEND.md](PASSO_A_PASSO_FRONTEND.md) - Para implementar no frontend

---

**Próximo passo**: Execute as migrations! 🚀
