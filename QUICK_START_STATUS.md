# 🚀 QUICK START - Padronização de Status

## ⚡ Início Rápido em 5 Minutos

### 1️⃣ Executar Migrations (2 min)

```bash
cd /Users/andrecabral/Projetos/AppCheckin

# Executar script automatizado
./migrate_status.sh

# OU manualmente via Docker
docker exec -it appcheckin-mysql mysql -u root -psenha123 appcheckin < Backend/database/migrations/037_create_status_tables.sql
docker exec -it appcheckin-mysql mysql -u root -psenha123 appcheckin < Backend/database/migrations/038_add_status_id_columns.sql
```

### 2️⃣ Testar API (1 min)

```bash
# Listar status disponíveis
curl http://localhost:8080/api/status/conta-receber | jq

# Deve retornar:
# {
#   "tipo": "conta-receber",
#   "status": [
#     { "id": 1, "codigo": "pendente", "nome": "Pendente", "cor": "#f59e0b", ... },
#     { "id": 2, "codigo": "pago", "nome": "Pago", "cor": "#10b981", ... }
#   ]
# }
```

### 3️⃣ Usar no Frontend (2 min)

```javascript
// Em qualquer tela
import statusService from '../../services/statusService';
import StatusBadge from '../../components/StatusBadge';

// Listar status
const statusList = await statusService.listarStatusContaReceber();

// Exibir badge
<StatusBadge status={item.status_info} />
```

---

## 📝 Comandos Úteis

```bash
# Verificar tabelas criadas
docker exec -it appcheckin-mysql mysql -u root -psenha123 -e "SHOW TABLES FROM appcheckin LIKE 'status_%';"

# Ver dados migrados
docker exec -it appcheckin-mysql mysql -u root -psenha123 -e "SELECT * FROM appcheckin.status_conta_receber;"

# Contar registros migrados
docker exec -it appcheckin-mysql mysql -u root -psenha123 -e "SELECT status, status_id, COUNT(*) FROM appcheckin.contas_receber GROUP BY status, status_id;"
```

---

## 🎯 Próximos Passos

1. **Atualizar um Controller** (exemplo: ContasReceberController)
   - Ver: `/Backend/EXEMPLO_ATUALIZACAO_MODEL.php`
   - Adicionar JOINs nas queries
   - Retornar `status_info` nas respostas

2. **Atualizar uma Tela** (exemplo: Lista de Contas)
   - Importar `StatusBadge`
   - Trocar `<Text>{item.status}</Text>` por `<StatusBadge status={item.status_info} />`

3. **Adicionar Filtro por Status**
   - Usar `statusService.listarStatusContaReceber()`
   - Popular um `<Picker>` com os status

---

## 🆘 Ajuda Rápida

| Situação | Solução |
|----------|---------|
| Erro FK constraint | Ver dados órfãos: `SELECT DISTINCT status FROM X WHERE status NOT IN (SELECT codigo FROM status_X);` |
| status_info não aparece | Atualizar Model para incluir JOIN (ver exemplo) |
| Badge sem cor | Backend não está retornando campo `cor` |

---

## 📚 Documentação Completa

- 📖 **Guia Completo**: `SISTEMA_STATUS_PADRONIZADO.md`
- 🔧 **Exemplo de Código**: `Backend/EXEMPLO_ATUALIZACAO_MODEL.php`
- 📋 **Resumo Executivo**: `PADRONIZACAO_STATUS_RESUMO.md`

---

✅ **Sistema pronto para uso!**
