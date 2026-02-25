# Payment Gateway Simulator 💳

Simulador de gateway de pagamentos completo com Docker + PHP. Recebe pagamentos, processa (aprova, rejeita, cancela, reembolsa, etc.) e reenvia o resultado via **webhook** para a URL configurada pelo cliente.

> **Sem banco de dados!** Os dados são armazenados em arquivos JSON em `src/data/`. Ideal para ambientes de teste e homologação.

---

## 📦 Requisitos

- [Docker](https://www.docker.com/) e Docker Compose instalados

---

## 🚀 Iniciar

```bash
docker-compose up -d --build
```

| Recurso | URL |
|---------|-----|
| **Dashboard** | http://localhost:8085 |
| **API Base URL** | http://localhost:8085/api |

> A porta padrão é `8085`. Para alterar, edite `docker-compose.yml` na seção `ports`.

---

## 🔧 Como Integrar com o Cliente

### 1. Configurar a URL do Gateway

O cliente deve apontar a aplicação dele para o simulador, substituindo a URL real da API de pagamentos:

```
# Em vez de:
https://api.mercadopago.com

# Usar:
http://SEU_IP:8085
```

> Se o cliente está na **mesma máquina**, use `localhost`. Em **rede**, use o IP da máquina onde o Docker roda.

### 2. Registrar Webhook Global (o simulador reenvia resultados)

O simulador reenvia automaticamente todas as respostas de pagamento (criação, atualização, reembolso) para a URL de webhook configurada:

```bash
curl -X POST http://localhost:8085/api/webhooks \
  -H "Content-Type: application/json" \
  -d '{
    "url": "http://localhost:8080/api/webhooks/mercadopago",
    "events": ["*"],
    "description": "Webhook do cliente"
  }'
```

> **Dentro do Docker** para acessar o host, use `http://host.docker.internal:PORTA/...`

### 3. Ou usar Webhook Individual (por pagamento)

O cliente pode enviar o campo `notification_url` no body de cada pagamento:

```json
{
  "amount": 150.00,
  "payment_method": "credit_card",
  "notification_url": "https://site-do-cliente.com/webhook",
  "card": { "number": "4111111111110001" }
}
```

---

## ✨ Funcionalidades

- **Processar pagamentos** — aprova, rejeita, cancela, reembolsa, chargeback, etc.
- **Assinaturas (preapproval)** — cria, consulta, pausa, cancela assinaturas recorrentes no formato Mercado Pago
- **Preferências + Checkout** — fluxo completo: cria preferência → redireciona ao checkout → processa pagamento
- **PIX simulado** — retorna `point_of_interaction` com QR Code simulado no formato MP
- **`subscription_id`** — pagamentos gerados por assinatura incluem o campo `subscription_id`
- **Webhook automático** — reenvia o resultado para URLs registradas ao criar/alterar pagamentos
- **Webhook individual** — campo `notification_url` por pagamento
- **Cartões de teste** — números de cartão que simulam diferentes resultados
- **Forçar status** — campo `_simulate_status` na criação do pagamento
- **Regras dinâmicas** — crie regras automáticas baseadas em condições (valor, email, método, etc.)
- **Simulador manual** — altere o status de qualquer pagamento existente via API ou Dashboard
- **Dashboard visual** — interface web completa para gerenciar tudo
- **Formato Mercado Pago** — respostas idênticas à API real do MP (transaction_details, payer.identification, etc.)
- **Sem banco de dados** — armazenamento em JSON, zero dependências externas

---

## 🃏 Cartões de Teste

Use estes números de cartão para simular diferentes resultados:

| Final | Número Completo        | Status Resultante   |
|-------|------------------------|---------------------|
| 0001  | `4111 1111 1111 0001`  | ✅ Aprovado         |
| 0002  | `4111 1111 1111 0002`  | ❌ Rejeitado        |
| 0003  | `4111 1111 1111 0003`  | ⏳ Pendente         |
| 0004  | `4111 1111 1111 0004`  | 🔄 Em Processamento |
| 0005  | `4111 1111 1111 0005`  | 🚫 Cancelado        |
| 0006  | `4111 1111 1111 0006`  | ⚠️ Erro             |
| 0007  | `4111 1111 1111 0007`  | ⚡ Chargeback       |

> Qualquer outro número → **Aprovado** por padrão.

---

## 📡 API Endpoints

### Assinaturas (Preapproval) — Formato Mercado Pago

| Método   | Endpoint                        | Descrição                                             |
|----------|---------------------------------|-------------------------------------------------------|
| `POST`   | `/api/preapproval`              | Criar assinatura recorrente                           |
| `GET`    | `/api/preapproval`              | Listar assinaturas (`?status=authorized&payer_email=`) |
| `GET`    | `/api/preapproval/{id}`         | Consultar assinatura por ID                           |
| `PUT`    | `/api/preapproval/{id}`         | Atualizar assinatura (pause, cancel, reactivate)      |
| `POST`   | `/api/preapproval/{id}/pay`     | Gerar pagamento da assinatura (cobrança recorrente)   |

### Preferências + Checkout

| Método   | Endpoint                        | Descrição                                             |
|----------|---------------------------------|-------------------------------------------------------|
| `POST`   | `/api/preferences`              | Criar preferência de pagamento (retorna `payment_url`) |
| `GET`    | `/checkout/{id}`                | Página de checkout visual (HTML)                      |
| `POST`   | `/checkout/{id}/process`        | Processar pagamento do checkout                       |

### Pagamentos

| Método   | Endpoint                        | Descrição                                             |
|----------|---------------------------------|-------------------------------------------------------|
| `POST`   | `/api/payments`                 | Criar pagamento direto                                |
| `GET`    | `/api/payments`                 | Listar pagamentos (filtros: `?status=approved&limit=50`) |
| `GET`    | `/api/payments/{id}`            | Consultar pagamento por ID                            |
| `POST`   | `/api/payments/{id}/capture`    | Capturar pagamento pendente                           |
| `POST`   | `/api/payments/{id}/cancel`     | Cancelar pagamento                                    |
| `POST`   | `/api/payments/{id}/refund`     | Reembolsar (total ou parcial)                         |

### Webhooks

| Método   | Endpoint              | Descrição                        |
|----------|-----------------------|----------------------------------|
| `POST`   | `/api/webhooks`       | Registrar URL de webhook         |
| `GET`    | `/api/webhooks`       | Listar webhooks registrados      |
| `DELETE` | `/api/webhooks/{id}`  | Remover webhook                  |
| `GET`    | `/api/webhook-logs`   | Logs de envio de webhooks        |

### Simulação

| Método   | Endpoint           | Descrição                                |
|----------|--------------------|------------------------------------------|
| `POST`   | `/api/simulate`    | Forçar mudança de status de um pagamento |
| `POST`   | `/api/rules`       | Criar regra automática de simulação      |
| `GET`    | `/api/rules`       | Listar regras                            |
| `DELETE` | `/api/rules/{id}`  | Remover regra                            |

### Teste

| Método | Endpoint                       | Descrição                                     |
|--------|--------------------------------|-----------------------------------------------|
| `POST` | `/api/test-webhook-receiver`   | Endpoint para receber webhooks de teste        |
| `GET`  | `/api/test-webhook-receiver`   | Ver webhooks recebidos no endpoint de teste    |

---

## 📋 Exemplos de Uso (cURL)

### Criar Assinatura (Preapproval)

```bash
curl -X POST http://localhost:8085/api/preapproval \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Plano Mensal Premium",
    "payer_email": "joao@teste.com",
    "external_reference": "contrato_123",
    "back_url": "https://meuapp.com/sucesso",
    "auto_recurring": {
      "frequency": 1,
      "frequency_type": "months",
      "transaction_amount": 99.90,
      "currency_id": "BRL"
    },
    "notification_url": "https://meu-site.com/webhook"
  }'
```

**Resposta (formato Mercado Pago):**

```json
{
    "id": "5a7f60073cfb9081242103c19ab335ef",
    "payer_id": 671705188,
    "payer_email": "joao@teste.com",
    "status": "authorized",
    "reason": "Plano Mensal Premium",
    "external_reference": "contrato_123",
    "init_point": "http://localhost:8085/subscription/checkout/5a7f...",
    "subscription_id": "5a7f60073cfb9081242103c19ab335ef",
    "auto_recurring": {
        "frequency": 1,
        "frequency_type": "months",
        "transaction_amount": 99.9,
        "currency_id": "BRL"
    },
    "next_payment_date": "2026-03-25T19:17:54.000-04:00",
    "summarized": {
        "charged_quantity": 0,
        "charged_amount": 0,
        "semaphore": "green"
    }
}
```

### Gerar Pagamento da Assinatura (com `subscription_id`)

```bash
curl -X POST http://localhost:8085/api/preapproval/{PREAPPROVAL_ID}/pay \
  -H "Content-Type: application/json" \
  -d '{}'
```

O pagamento gerado inclui automaticamente:

```json
{
    "id": 12345678901,
    "subscription_id": "5a7f60073cfb9081242103c19ab335ef",
    "preapproval_id": "5a7f60073cfb9081242103c19ab335ef",
    "status": "approved",
    "status_detail": "accredited",
    "transaction_amount": 99.9
}
```

> 🎯 **Como saber se um pagamento veio de assinatura?** Verifique o campo `subscription_id`. Se presente, o pagamento pertence a uma assinatura.

### Pausar / Cancelar Assinatura

```bash
# Pausar
curl -X PUT http://localhost:8085/api/preapproval/{ID} \
  -H "Content-Type: application/json" \
  -d '{"status": "paused"}'

# Cancelar
curl -X PUT http://localhost:8085/api/preapproval/{ID} \
  -H "Content-Type: application/json" \
  -d '{"status": "cancelled"}'

# Reativar
curl -X PUT http://localhost:8085/api/preapproval/{ID} \
  -H "Content-Type: application/json" \
  -d '{"status": "authorized"}'
```

### Criar Preferência + Checkout

```bash
curl -X POST http://localhost:8085/api/preferences \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {"title": "Plano Premium", "unit_price": 149.90, "quantity": 1}
    ],
    "payer": {
      "name": "João Silva",
      "email": "joao@teste.com"
    },
    "back_urls": {
      "success": "https://meuapp.com/sucesso",
      "failure": "https://meuapp.com/erro"
    },
    "external_reference": "pedido_456"
  }'
```

A resposta inclui `payment_url` — abra no navegador para ver a página de checkout.

### Criar Pagamento com Cartão

```bash
curl -X POST http://localhost:8085/api/payments \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 150.00,
    "currency": "BRL",
    "payment_method": "credit_card",
    "card": {
      "number": "4111111111110001",
      "holder_name": "JOHN DOE",
      "expiration_month": 12,
      "expiration_year": 2030
    },
    "payer": {
      "name": "João Silva",
      "email": "joao@teste.com",
      "identification": {"type": "CPF", "number": "12345678900"}
    },
    "description": "Compra #12345",
    "installments": 3,
    "notification_url": "https://meu-site.com/webhook"
  }'
```

### Criar Pagamento via PIX

```bash
curl -X POST http://localhost:8085/api/payments \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 89.90,
    "payment_method": "pix",
    "payer": {
      "name": "Maria Santos",
      "email": "maria@teste.com"
    },
    "description": "Assinatura mensal"
  }'
```

A resposta inclui `point_of_interaction` com QR Code simulado:

```json
{
    "point_of_interaction": {
        "type": "PIX",
        "transaction_data": {
            "qr_code": "00020126580014br.gov.bcb.pix...",
            "qr_code_base64": "iVBORw0KGgo...",
            "ticket_url": "http://localhost:8085/pix/pay_abc123"
        }
    }
}
```

### Vincular Pagamento a uma Assinatura

Ao criar um pagamento direto, envie `subscription_id` para vinculá-lo:

```bash
curl -X POST http://localhost:8085/api/payments \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 99.90,
    "payment_method": "credit_card",
    "subscription_id": "5a7f60073cfb9081242103c19ab335ef",
    "card": {"number": "4111111111110001"}
  }'
```

### Forçar Status Específico

Use o campo `_simulate_status` para forçar o resultado:

```bash
curl -X POST http://localhost:8085/api/payments \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 50.00,
    "payment_method": "pix",
    "_simulate_status": "rejected"
  }'
```

### Consultar Pagamento

```bash
curl http://localhost:8085/api/payments/pay_abc123def456
```

### Listar Pagamentos com Filtro

```bash
curl "http://localhost:8085/api/payments?status=approved&limit=10"
```

### Capturar Pagamento Pendente

```bash
curl -X POST http://localhost:8085/api/payments/pay_abc123/capture
```

### Cancelar Pagamento

```bash
curl -X POST http://localhost:8085/api/payments/pay_abc123/cancel
```

### Reembolsar (total)

```bash
curl -X POST http://localhost:8085/api/payments/pay_abc123/refund
```

### Reembolsar (parcial)

```bash
curl -X POST http://localhost:8085/api/payments/pay_abc123/refund \
  -H "Content-Type: application/json" \
  -d '{"amount": 50.00}'
```

### Registrar Webhook

```bash
curl -X POST http://localhost:8085/api/webhooks \
  -H "Content-Type: application/json" \
  -d '{
    "url": "http://host.docker.internal:8080/api/webhooks/mercadopago",
    "events": ["*"],
    "description": "Webhook principal"
  }'
```

### Simular Mudança de Status

```bash
curl -X POST http://localhost:8085/api/simulate \
  -H "Content-Type: application/json" \
  -d '{
    "payment_id": "pay_abc123",
    "status": "refunded"
  }'
```

### Criar Regra de Simulação

```bash
curl -X POST http://localhost:8085/api/rules \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Rejeitar pagamentos com email teste@invalido.com",
    "status": "rejected",
    "conditions": {
      "payer.email": "teste@invalido.com"
    },
    "priority": 10
  }'
```

---

## 📨 Webhook — Payload Enviado

Quando um pagamento é criado ou alterado, o gateway envia um `POST` para todas as URLs registradas:

```json
{
  "id": "evt_abc123def456",
  "type": "payment.created",
  "api_version": "v1",
  "date_created": "2026-02-25T10:00:00.000-03:00",
  "data": {
    "id": "pay_abc123def456"
  },
  "payment": {
    "id": "pay_abc123def456",
    "status": "approved",
    "status_detail": "accredited",
    "amount": 150.00,
    "currency": "BRL",
    "payment_method": "credit_card",
    "card": {
      "first_six_digits": "411111",
      "last_four_digits": "0001",
      "brand": "visa"
    },
    "payer": {
      "name": "João Silva",
      "email": "joao@teste.com"
    },
    "created_at": "2026-02-25T10:00:00.000-03:00",
    "updated_at": "2026-02-25T10:00:00.000-03:00"
  }
}
```

### Headers do Webhook

| Header               | Descrição                                                              |
|----------------------|------------------------------------------------------------------------|
| `Content-Type`       | `application/json`                                                     |
| `X-Gateway-Event`    | Tipo do evento: `payment.created`, `payment.updated`, `payment.refunded` |
| `X-Gateway-Signature`| HMAC-SHA256 do body (secret: `gateway_simulator_secret`)               |
| `X-Gateway-Delivery` | ID único da entrega                                                    |
| `User-Agent`         | `PaymentGatewaySimulator/1.0`                                          |

### Validar Assinatura do Webhook (PHP)

```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_GATEWAY_SIGNATURE'] ?? '';
$expected = hash_hmac('sha256', $payload, 'gateway_simulator_secret');

if (hash_equals($expected, $signature)) {
    // Webhook válido ✅
    $data = json_decode($payload, true);
    // processar...
} else {
    // Assinatura inválida ❌
    http_response_code(401);
    echo json_encode(['error' => 'Assinatura inválida']);
}
```

---

## 🎯 Eventos do Webhook

| Evento                       | Quando é disparado                                        |
|------------------------------|-----------------------------------------------------------|
| `payment.created`            | Novo pagamento criado                                     |
| `payment.updated`            | Status alterado (captura, cancelamento, simulação manual)  |
| `payment.refunded`           | Pagamento reembolsado (total ou parcial)                  |
| `subscription_preapproval`   | Assinatura criada ou atualizada                           |
| `payment` (via preapproval)  | Pagamento gerado a partir de cobrança recorrente          |

---

## 🔗 `subscription_id` — Vínculo Pagamento ↔ Assinatura

Quando um pagamento é gerado a partir de uma assinatura (preapproval), o campo `subscription_id` vem preenchido:

```json
{
    "id": 12345678901,
    "subscription_id": "2c938084726fca480172750000000000",
    "status": "approved",
    "transaction_amount": 99.90
}
```

**Como identificar:**
- Se `subscription_id` está presente → o pagamento pertence a uma assinatura
- Se `subscription_id` é `null` → pagamento avulso

**Formas de gerar pagamentos com `subscription_id`:**
1. `POST /api/preapproval/{id}/pay` — gera automaticamente
2. `POST /api/payments` com `"subscription_id": "..."` no body — vinculação manual

---

## ⚙️ Regras de Simulação

As regras permitem definir comportamentos automáticos. Quando um pagamento for criado e as condições baterem, o status definido na regra será aplicado.

### Prioridade de resolução do status:

1. **`_simulate_status`** no body do pagamento (maior prioridade)
2. **Regras de simulação** salvas (ordenadas por prioridade)
3. **Últimos 4 dígitos do cartão** (tabela de cartões de teste)
4. **Status padrão:** `approved`

### Exemplos de condições:

```json
// Rejeitar pagamentos via boleto
{ "payment_method": "boleto" }

// Rejeitar pagamentos de um email específico
{ "payer.email": "fraudador@teste.com" }

// Deixar pendente pagamentos com valor específico
{ "amount": "999.99" }
```

> Use notação de ponto para campos aninhados: `payer.email`, `card.brand`, etc.

---

## 🗂️ Armazenamento

Os dados são salvos em arquivos JSON na pasta `src/data/`:

| Arquivo                  | Conteúdo                    |
|--------------------------|-----------------------------|
| `payments.json`          | Pagamentos criados          |
| `preapprovals.json`      | Assinaturas (preapproval)   |
| `preferences.json`       | Preferências de pagamento   |
| `webhooks.json`          | Webhooks registrados        |
| `webhook_logs.json`      | Logs de envio de webhook    |
| `simulation_rules.json`  | Regras de simulação         |
| `activity_log.json`      | Log geral de atividades     |

Para **limpar todos os dados**, basta deletar os arquivos:

```bash
rm -f src/data/*.json
```

---

## 📁 Estrutura do Projeto

```
├── docker-compose.yml                 # Configuração Docker
├── Dockerfile                         # Imagem PHP 8.3 + Apache
├── README.md                          # Esta documentação
└── src/
    ├── .htaccess                      # Rewrite para router
    ├── index.php                      # Router principal (todas as rotas)
    ├── data/                          # Dados JSON (gerado automaticamente)
    │   ├── payments.json
    │   ├── preapprovals.json          # Assinaturas recorrentes
    │   ├── preferences.json           # Preferências de checkout
    │   ├── webhooks.json
    │   ├── webhook_logs.json
    │   ├── simulation_rules.json
    │   └── activity_log.json
    └── app/
        ├── helpers.php                # Funções auxiliares
        ├── Controllers/
        │   ├── PaymentController.php      # Pagamentos + preferências + checkout
        │   ├── PreapprovalController.php  # Assinaturas (preapproval) formato MP
        │   ├── WebhookController.php      # Gestão de webhooks
        │   └── SimulatorController.php    # Simulação manual e regras
        └── Views/
            ├── checkout.php               # Página de checkout (CC, PIX, boleto)
            └── dashboard.php              # Dashboard visual
```

---

## 🛑 Parar o Simulador

```bash
docker-compose down
```

## 🔄 Reconstruir após alterações

```bash
docker-compose up -d --build
```

---

## 📄 Licença

Uso interno para simulação e testes. Não utilizar em produção.
