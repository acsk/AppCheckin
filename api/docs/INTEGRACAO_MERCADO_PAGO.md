# Integração Mercado Pago - Guia Completo

## 📋 O que você precisa fornecer

### 1. Credenciais do Mercado Pago

Você precisa criar uma conta no Mercado Pago e obter as credenciais:

#### 🔧 Ambiente de Teste (Sandbox)
1. Acessar: https://www.mercadopago.com.br/developers/panel/app
2. Criar aplicação
3. Obter credenciais de **TESTE**:
   - `Access Token de Teste` (começa com TEST-...)
   - `Public Key de Teste` (começa com TEST-...)

#### 🚀 Ambiente de Produção
1. Preencher formulário de produção no painel
2. Aguardar aprovação do Mercado Pago
3. Obter credenciais de **PRODUÇÃO**:
   - `Access Token de Produção`
   - `Public Key de Produção`

---

## ⚙️ Configuração

### Passo 1: Adicionar Variáveis de Ambiente

Adicione no arquivo `.env`:

```env
# Mercado Pago - Ambiente
MP_ENVIRONMENT=sandbox  # ou "production"

# Credenciais de TESTE
MP_ACCESS_TOKEN_TEST=TEST-1234567890-123456-abcdef1234567890abcdef1234567890-123456789
MP_PUBLIC_KEY_TEST=TEST-abc123def456-789012-ghi345jkl678

# Credenciais de PRODUÇÃO (quando tiver)
MP_ACCESS_TOKEN_PROD=APP_USR-1234567890-123456-abcdef1234567890abcdef1234567890-123456789
MP_PUBLIC_KEY_PROD=APP_USR-abc123def456-789012-ghi345jkl678

# URL da aplicação (para callbacks)
APP_URL=http://localhost:8080
```

### Passo 2: Executar Migration

```bash
# Via Docker
docker exec appcheckin_mysql mysql -u appcheckin_user -psenha appcheckin < database/migrations/create_table_pagamentos_mercadopago.sql

# Ou via PHPMyAdmin
# Copiar e executar o conteúdo do arquivo create_table_pagamentos_mercadopago.sql
```

### Passo 3: Adicionar Rota de Webhook

Adicionar em `routes/api.php`:

```php
use App\Controllers\MercadoPagoWebhookController;

// Webhook Mercado Pago (sem autenticação - MP precisa acessar)
$app->post('/api/webhooks/mercadopago', [MercadoPagoWebhookController::class, 'processarWebhook']);
```

### Passo 4: Configurar Webhook no Mercado Pago

1. Acessar: https://www.mercadopago.com.br/developers/panel/app
2. Ir em **Webhooks**
3. Adicionar URL de notificação:
   ```
   https://seu-dominio.com/api/webhooks/mercadopago
   ```
4. Selecionar eventos:
   - ✅ Payments (Pagamentos)
   - ✅ Chargebacks (Estornos)

---

## 🎯 Como Usar

### Exemplo 1: Gerar Link de Pagamento para Matrícula

```php
use App\Services\MercadoPagoService;

$mercadoPago = new MercadoPagoService();

// Dados da matrícula
$dadosPagamento = [
    'tenant_id' => 1,
    'matricula_id' => 123,
    'aluno_id' => 45,
    'usuario_id' => 67,
    'aluno_nome' => 'João Silva',
    'aluno_email' => 'joao@email.com',
    'aluno_telefone' => '11999999999',
    'plano_nome' => '3x Semana',
    'descricao' => 'Matrícula Mensal - Natação',
    'valor' => 150.00,
    'max_parcelas' => 12,
    'academia_nome' => 'Academia Fitness Pro'
];

try {
    $preferencia = $mercadoPago->criarPreferenciaPagamento($dadosPagamento);
    
    // Retornar link de pagamento ao frontend
    echo json_encode([
        'success' => true,
        'payment_url' => $preferencia['init_point'], // Link para o usuário pagar
        'preference_id' => $preferencia['id']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

### Exemplo 2: Adicionar ao Controller de Matrícula

Adicionar no `MatriculaController.php` após criar matrícula:

```php
public function criar(Request $request, Response $response): Response
{
    // ... código existente de criação de matrícula ...
    
    // Se matrícula for paga (não teste), gerar link de pagamento
    if ($periodoTeste != 1 && $valorMatricula > 0) {
        try {
            $mercadoPago = new \App\Services\MercadoPagoService();
            
            $dadosPagamento = [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'aluno_id' => $alunoId,
                'usuario_id' => $usuarioId,
                'aluno_nome' => $usuario['nome'],
                'aluno_email' => $usuario['email'],
                'plano_nome' => $plano['nome'],
                'valor' => $valorMatricula,
                'max_parcelas' => 12
            ];
            
            $preferencia = $mercadoPago->criarPreferenciaPagamento($dadosPagamento);
            
            // Salvar preference_id na matrícula
            $stmtUpdatePref = $db->prepare("
                UPDATE pagamentos_mercadopago 
                SET preference_id = ? 
                WHERE matricula_id = ?
            ");
            $stmtUpdatePref->execute([$preferencia['id'], $matriculaId]);
            
            // Adicionar link de pagamento na resposta
            $matricula['payment_url'] = $preferencia['init_point'];
            
        } catch (Exception $e) {
            error_log("Erro ao gerar link de pagamento: " . $e->getMessage());
        }
    }
    
    // ... resto do código ...
}
```

---

## 🌐 Frontend - Como Usar

### Opção 1: Redirecionar para Checkout Mercado Pago

```javascript
// Após criar matrícula, redirecionar para pagamento
const criarMatricula = async (dados) => {
  const response = await api.post('/admin/matriculas', dados);
  
  if (response.data.payment_url) {
    // Redirecionar para página de pagamento do Mercado Pago
    window.location.href = response.data.payment_url;
  }
};
```

### Opção 2: Abrir em Modal/Popup

```javascript
const abrirPagamento = (paymentUrl) => {
  window.open(
    paymentUrl,
    'Mercado Pago',
    'width=800,height=600,scrollbars=yes'
  );
};
```

### Opção 3: Integração com Checkout Pro (JavaScript)

```html
<!-- Incluir SDK do Mercado Pago -->
<script src="https://sdk.mercadopago.com/js/v2"></script>

<script>
const mp = new MercadoPago('PUBLIC_KEY_AQUI', {
  locale: 'pt-BR'
});

// Criar checkout
const checkout = mp.checkout({
  preference: {
    id: 'PREFERENCE_ID_AQUI'
  },
  autoOpen: true
});
</script>
```

---

## 📊 Fluxo de Pagamento

```
1. Usuário cria matrícula → Status: PENDENTE
2. Sistema gera link de pagamento (Mercado Pago)
3. Usuário paga no Mercado Pago
4. Mercado Pago envia webhook para: /api/webhooks/mercadopago
5. Sistema recebe notificação e consulta status do pagamento
6. Se aprovado → Matrícula muda para: ATIVA
7. Se recusado/cancelado → Matrícula continua: PENDENTE
```

---

## 🔔 Processar Notificações (Webhooks)

O sistema já está preparado para receber notificações automáticas:

**Endpoint**: `POST /api/webhooks/mercadopago`

Quando um pagamento muda de status, o Mercado Pago envia:

```json
{
  "type": "payment",
  "data": {
    "id": "123456789"
  }
}
```

O sistema:
1. Busca informações do pagamento
2. Atualiza tabela `pagamentos_mercadopago`
3. Se status = `approved` → Ativa matrícula automaticamente

---

## 📋 Status de Pagamento

| Status MP | Descrição | Ação Sistema |
|-----------|-----------|--------------|
| `approved` | Aprovado | ✅ Ativar matrícula |
| `pending` | Pendente (aguardando) | ⏳ Manter pendente |
| `in_process` | Em processamento | ⏳ Manter pendente |
| `rejected` | Recusado | ❌ Manter pendente |
| `cancelled` | Cancelado | ❌ Manter pendente |
| `refunded` | Reembolsado | ⚠️ Desativar matrícula |
| `charged_back` | Estornado | ⚠️ Desativar matrícula |

---

## 🧪 Testar Integração

### Cartões de Teste

Para ambiente sandbox, use estes cartões:

**Aprovado:**
- Número: `5031 4332 1540 6351`
- CVV: 123
- Validade: 11/25
- Nome: APRO

**Recusado:**
- Número: `5031 4332 1540 6351`
- CVV: 123
- Validade: 11/25
- Nome: OTHE

### Testar PIX

No sandbox, qualquer QR Code gerado será aprovado automaticamente após 5 segundos.

---

## 🚨 Troubleshooting

### Webhook não recebe notificações

1. Verificar se URL está acessível publicamente
2. Não pode ser `localhost` - usar ngrok ou túnel:
   ```bash
   ngrok http 8080
   ```
3. Configurar URL no painel do Mercado Pago

### Erro "Access Token inválido"

1. Verificar se copiou token completo
2. Verificar se está usando token de TESTE no ambiente sandbox
3. Verificar se token não expirou

### Pagamento não ativa matrícula

1. Verificar logs: `docker logs appcheckin_php`
2. Verificar tabela `pagamentos_mercadopago`
3. Verificar se webhook foi recebido

---

## 📚 Documentação Oficial

- API Reference: https://www.mercadopago.com.br/developers/pt/reference
- Checkout Pro: https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/landing
- Webhooks: https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks

---

## ✅ Checklist de Implementação

- [ ] Criar conta no Mercado Pago
- [ ] Obter credenciais de teste
- [ ] Adicionar variáveis no `.env`
- [ ] Executar migration da tabela
- [ ] Adicionar rota de webhook
- [ ] Configurar webhook no painel MP
- [ ] Testar com cartão de teste
- [ ] Implementar no frontend
- [ ] Testar fluxo completo
- [ ] Solicitar credenciais de produção
- [ ] Trocar para ambiente production

---

## 🎁 Próximos Passos (Opcional)

1. **Assinaturas Recorrentes**: Cobrar mensalmente automático
2. **Split de Pagamento**: Dividir pagamento entre múltiplas contas
3. **QR Code PIX**: Gerar QR Code para pagamento instantâneo
4. **Cartão Salvo**: Permitir salvar cartão para próximas compras
5. **Marketplace**: Gerenciar pagamentos de múltiplos tenants
