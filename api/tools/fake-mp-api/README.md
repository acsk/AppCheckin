# Fake Mercado Pago API

Servidor local que simula os endpoints da API do Mercado Pago para testes sem depender do sandbox real.

## Início Rápido

```bash
# Iniciar o servidor na porta 8085
chmod +x tools/fake-mp-api/start.sh
./tools/fake-mp-api/start.sh

# Ou com PHP diretamente
php -S localhost:8085 tools/fake-mp-api/server.php
```

## Configuração

Adicione ao `.env` da API:

```env
MP_FAKE_API_URL=http://localhost:8085
```

O `MercadoPagoService` detectará automaticamente e redirecionará todas as chamadas para o servidor fake.

## Endpoints Simulados

### API Mercado Pago (compatível)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/checkout/preferences` | Criar preferência de pagamento |
| `POST` | `/v1/payments` | Criar pagamento direto (PIX, cartão) |
| `GET` | `/v1/payments/{id}` | Consultar pagamento |
| `GET` | `/v1/payments/search` | Buscar pagamentos |
| `POST` | `/preapproval_plan` | Criar plano de assinatura |
| `POST` | `/preapproval` | Criar preapproval (assinatura) |
| `GET` | `/preapproval/{id}` | Consultar preapproval |
| `PUT` | `/preapproval/{id}` | Atualizar preapproval |
| `GET` | `/authorized_payments/{id}` | Consultar pagamento autorizado |

### Endpoints de Controle (testes)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/fake/webhook` | Simula envio de webhook para a API |
| `POST` | `/fake/approve-payment` | Aprova um pagamento existente |
| `POST` | `/fake/reject-payment` | Rejeita um pagamento existente |
| `GET` | `/fake/storage` | Ver todos os dados armazenados |
| `DELETE` | `/fake/storage` | Limpar todos os dados |
| `GET` | `/fake/health` | Health check |

## Exemplos de Uso

### 1. Criar preferência e ver resposta

```bash
# Criar preferência
curl -s -X POST http://localhost:8085/checkout/preferences \
  -H "Authorization: Bearer TEST_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [{"title": "Plano Mensal", "unit_price": 99.90, "quantity": 1}],
    "external_reference": "MAT-123-1234567890",
    "metadata": {"tenant_id": 1, "matricula_id": 123}
  }' | jq .
```

### 2. Criar pagamento PIX

```bash
curl -s -X POST http://localhost:8085/v1/payments \
  -H "Authorization: Bearer TEST_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_amount": 50.00,
    "payment_method_id": "pix",
    "payer": {"email": "teste@test.com", "identification": {"type": "CPF", "number": "12345678901"}},
    "external_reference": "MAT-456-1234567890"
  }' | jq .
```

### 3. Simular webhook de pagamento aprovado

```bash
# Primeiro cria um pagamento, depois simula o webhook
PAYMENT_ID=80000000001

curl -s -X POST http://localhost:8085/fake/webhook \
  -H "Content-Type: application/json" \
  -d "{
    \"payment_id\": \"${PAYMENT_ID}\",
    \"status\": \"approved\",
    \"webhook_url\": \"http://localhost:8080/api/webhooks/mercadopago\"
  }" | jq .
```

### 4. Simular pagamento rejeitado (testar novo fluxo)

```bash
PAYMENT_ID=80000000001

curl -s -X POST http://localhost:8085/fake/reject-payment \
  -H "Content-Type: application/json" \
  -d "{\"payment_id\": \"${PAYMENT_ID}\", \"reason\": \"cc_rejected_high_risk\"}" | jq .

# Depois disparar webhook
curl -s -X POST http://localhost:8085/fake/webhook \
  -H "Content-Type: application/json" \
  -d "{
    \"payment_id\": \"${PAYMENT_ID}\",
    \"status\": \"rejected\",
    \"webhook_url\": \"http://localhost:8080/api/webhooks/mercadopago\"
  }" | jq .
```

### 5. Ver storage (debug)

```bash
curl -s http://localhost:8085/fake/storage | jq .
```

## Persistência

Os dados são armazenados em `tools/fake-mp-api/storage.json` e sobrevivem reinicializações do servidor. Para limpar:

```bash
curl -X DELETE http://localhost:8085/fake/storage
# ou
rm tools/fake-mp-api/storage.json
```

## Notas

- O servidor **não** valida tokens de acesso — aceita qualquer `Bearer` token
- IDs gerados são sequenciais e únicos por tipo
- Pagamentos PIX são criados com status `pending`, cartão com `approved`
- URLs geradas (init_point, sandbox_init_point) apontam para sandbox do MP (não funcionam, são apenas ilustrativas)
