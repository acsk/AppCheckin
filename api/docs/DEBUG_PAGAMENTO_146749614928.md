# 🚀 Guia Rápido: Debugar Pagamento 146749614928

## Problema
O pagamento com ID **146749614928** recebeu webhook mas falhou com erro:
```
❌ "Matrícula não identificada no pagamento"
```

## Solução em 5 passos

### 1️⃣ Listar webhooks com erro
```bash
curl http://localhost:8080/api/webhooks/mercadopago/list?filtro=erro | jq '.'
```

### 2️⃣ Ver detalhes completos do webhook
```bash
# Encontrar o ID do webhook na listagem anterior
curl http://localhost:8080/api/webhooks/mercadopago/show/1 | jq '.'
```

### 3️⃣ Buscar os dados do pagamento na API do MP
```bash
curl http://localhost:8080/api/webhooks/mercadopago/payment/146749614928 | jq '.pagamento'
```

**Importante:** Verificar se tem `external_reference` no retorno!

### 4️⃣ Verificar a matrícula no banco
Se o MP retornou um `external_reference` (ex: `MAT-123-...`), buscar:

```bash
# Via SQL
docker-compose exec -T mysql mysql -u root -proot appcheckin -e "SELECT id, aluno_id, plano_id, status_id, created_at FROM matriculas WHERE id = 123 OR aluno_id = 456 ORDER BY id DESC LIMIT 5;"
```

### 5️⃣ Reprocessar o pagamento
Depois de corrigir o código (se necessário):

```bash
curl -X POST http://localhost:8080/api/webhooks/mercadopago/payment/146749614928/reprocess | jq '.'
```

---

## Checklist do que pode estar errado

- [ ] `external_reference` não foi definido na preferência
- [ ] Matrícula foi deletada após criar a preferência
- [ ] Aluno_id não corresponde
- [ ] Tenant não está correto
- [ ] Preferência não foi criada corretamente

## Ver Logs em Tempo Real

Durante o reprocessamento, acompanhe os logs:

```bash
docker-compose exec -T php tail -f /var/log/php-error.log | grep -E "Webhook|Pagamento|Matrícula"
```

Você verá algo como:
```
[Webhook MP] 📊 ATUALIZANDO PAGAMENTO
[Webhook MP] 📋 Status: approved
[Webhook MP] 💳 ID Pagamento: 146749614928
[Webhook MP] 📝 External reference: MAT-123-...
[Webhook MP] 🔍 Matrícula ID extraído: 123
[Webhook MP] ✅ Pagamento APROVADO
...
```

---

## Próximas Ações

1. ✅ Executar os 5 passos acima
2. ✅ Analisar os dados retornados
3. ✅ Corrigir o código se necessário
4. ✅ Reprocessar o pagamento
5. ✅ Verificar se a matrícula foi criada:
   ```bash
   docker-compose exec -T mysql mysql -u root -proot appcheckin -e "SELECT * FROM matriculas WHERE id = 123;"
   ```
