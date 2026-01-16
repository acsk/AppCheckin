# 🚨 AÇÃO REQUERIDA: Executar Migrações de Banco de Dados

## O Problema

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'appcheckin.wods' doesn't exist
```

As tabelas do WOD não foram criadas no banco de dados.

---

## ✅ A Solução (2 Passos)

### PASSO 1: Executar o Script de Migrations

```bash
cd /Users/andrecabral/Projetos/AppCheckin/Backend/database/migrations
chmod +x run_wod_migrations.sh
./run_wod_migrations.sh
```

Você será solicitado a digitar a senha do MySQL. Digite e pressione Enter.

### PASSO 2: Verificar se funcionou

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

## Pronto! ✅

Agora você pode:

1. **Testar o endpoint**:
   ```bash
   ./test_wod_completo.sh
   ```

2. **Começar a implementar no frontend**:
   Leia `PASSO_A_PASSO_FRONTEND.md`

3. **Chamar o endpoint via API**:
   ```bash
   curl -X POST http://localhost:8000/admin/wods/completo \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d @exemplo_wod_completo.json
   ```

---

## Documentação

- [FALTANDO_MIGRATIONS.md](FALTANDO_MIGRATIONS.md) - Resumo do problema
- [EXECUTAR_MIGRATIONS_WOD.md](EXECUTAR_MIGRATIONS_WOD.md) - Instruções detalhadas
- [PASSO_A_PASSO_FRONTEND.md](PASSO_A_PASSO_FRONTEND.md) - Implementar no frontend

---

**Status**: ⏳ Aguardando execução das migrations
**Próximo**: Execute o script acima! 🚀
