# API de Credenciais de Pagamento (Mercado Pago)

Documentação para integração frontend do gerenciamento de credenciais de pagamento por tenant.

## Visão Geral

Cada tenant pode configurar suas próprias credenciais do Mercado Pago. Se não configurar, o sistema usa credenciais globais (padrão).

---

## Endpoints

### 1. Obter Credenciais

Retorna as credenciais configuradas para o tenant (valores sensíveis são mascarados).

```
GET /admin/payment-credentials
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "tenant_id": 2,
    "provider": "mercadopago",
    "environment": "production",
    "public_key_test_masked": "TEST-4****...****4709",
    "public_key_prod_masked": "APP_US****...****3422",
    "has_token_test": true,
    "has_token_prod": true,
    "is_active": true,
    "created_at": "2026-02-07 10:30:00",
    "updated_at": "2026-02-07 10:30:00"
  }
}
```

**Resposta sem credenciais configuradas:**
```json
{
  "success": true,
  "data": null,
  "message": "Nenhuma credencial configurada"
}
```

---

### 2. Salvar Credenciais

Cria ou atualiza as credenciais do tenant.

```
POST /admin/payment-credentials
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "provider": "mercadopago",
  "environment": "sandbox",
  "access_token_test": "TEST-5463428115477491-020510-...",
  "public_key_test": "TEST-44f9e009-e7e5-434f-9ff0-7923fd394709",
  "access_token_prod": "APP_USR-5463428115477491-020510-...",
  "public_key_prod": "APP_USR-3cac1a43-8526-4717-b3bf-a705e8628422",
  "webhook_secret": "opcional",
  "is_active": true
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `provider` | string | Não | Provider de pagamento. Padrão: `mercadopago` |
| `environment` | string | Não | Ambiente ativo: `sandbox` ou `production`. Padrão: `sandbox` |
| `access_token_test` | string | Não* | Token de acesso do ambiente de TESTE |
| `public_key_test` | string | Não* | Chave pública do ambiente de TESTE |
| `access_token_prod` | string | Não* | Token de acesso do ambiente de PRODUÇÃO |
| `public_key_prod` | string | Não* | Chave pública do ambiente de PRODUÇÃO |
| `webhook_secret` | string | Não | Secret para validar webhooks (opcional) |
| `is_active` | boolean | Não | Se as credenciais estão ativas. Padrão: `true` |

> **Nota:** Tokens vazios ou não enviados não sobrescrevem valores existentes.

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "message": "Credenciais cadastradas com sucesso"
}
```

ou

```json
{
  "success": true,
  "message": "Credenciais atualizadas com sucesso"
}
```

---

### 3. Testar Conexão

Testa se as credenciais estão funcionando.

```
POST /admin/payment-credentials/test
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "message": "Conexão com Mercado Pago OK",
  "data": {
    "public_key_prefix": "APP_USR-3cac1a4..."
  }
}
```

**Resposta de Erro (400):**
```json
{
  "success": false,
  "message": "Credenciais não configuradas ou inválidas"
}
```

---

## Fluxo Sugerido para o Frontend

### Tela de Configurações de Pagamento

```
┌─────────────────────────────────────────────────────────────┐
│  ⚙️ Configurações de Pagamento                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Provider: [Mercado Pago ▼]                                 │
│                                                             │
│  Ambiente Ativo:                                            │
│  ○ Sandbox (Testes)    ● Produção                          │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│  CREDENCIAIS DE TESTE                                       │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Access Token:                                              │
│  [TEST-***************************] [👁️]                    │
│  ✅ Configurado                                             │
│                                                             │
│  Public Key:                                                │
│  [TEST-44f9e009-e7e5-434f-9ff0-7923fd394709___________]    │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│  CREDENCIAIS DE PRODUÇÃO                                    │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Access Token:                                              │
│  [APP_USR-*************************] [👁️]                   │
│  ✅ Configurado                                             │
│                                                             │
│  Public Key:                                                │
│  [APP_USR-3cac1a43-8526-4717-b3bf-a705e8628422________]    │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  [🔄 Testar Conexão]              [💾 Salvar Configurações] │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Estados Visuais

| Estado | Indicador |
|--------|-----------|
| Token configurado | ✅ Badge verde + "Configurado" |
| Token não configurado | ⚠️ Badge amarelo + "Não configurado" |
| Ambiente ativo | Highlight no card correspondente |
| Teste OK | Toast verde "Conexão OK" |
| Teste falhou | Toast vermelho com mensagem de erro |

---

## Onde Obter as Credenciais do Mercado Pago

1. Acesse: https://www.mercadopago.com.br/developers/panel/app
2. Crie uma aplicação ou selecione uma existente
3. Vá em **"Credenciais"**
4. Copie:
   - **Credenciais de teste**: `Access Token` e `Public Key` com prefixo `TEST-`
   - **Credenciais de produção**: `Access Token` e `Public Key` com prefixo `APP_USR-`

---

## Segurança

- Os `access_token` são **criptografados** no banco de dados (AES-256-GCM)
- Na resposta do GET, tokens são indicados apenas como `has_token_test: true/false`
- Public keys são mascaradas na resposta (exibe início e fim)
- Apenas usuários com papel **Admin** podem acessar esses endpoints

---

## Exemplo de Integração React

```tsx
// hooks/usePaymentCredentials.ts
import { useState, useEffect } from 'react';
import api from '../services/api';

interface PaymentCredentials {
  id: number;
  tenant_id: number;
  provider: string;
  environment: 'sandbox' | 'production';
  public_key_test_masked: string | null;
  public_key_prod_masked: string | null;
  has_token_test: boolean;
  has_token_prod: boolean;
  is_active: boolean;
}

export function usePaymentCredentials() {
  const [credentials, setCredentials] = useState<PaymentCredentials | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchCredentials = async () => {
    try {
      const response = await api.get('/admin/payment-credentials');
      setCredentials(response.data.data);
    } catch (error) {
      console.error('Erro ao buscar credenciais:', error);
    } finally {
      setLoading(false);
    }
  };

  const saveCredentials = async (data: Partial<PaymentCredentials> & {
    access_token_test?: string;
    access_token_prod?: string;
    public_key_test?: string;
    public_key_prod?: string;
  }) => {
    const response = await api.post('/admin/payment-credentials', data);
    await fetchCredentials(); // Recarregar
    return response.data;
  };

  const testConnection = async () => {
    const response = await api.post('/admin/payment-credentials/test');
    return response.data;
  };

  useEffect(() => {
    fetchCredentials();
  }, []);

  return {
    credentials,
    loading,
    saveCredentials,
    testConnection,
    refetch: fetchCredentials
  };
}
```

---

## Observações

1. **Fallback Global**: Se o tenant não tiver credenciais configuradas, o sistema usa as credenciais do arquivo `.env` do servidor.

2. **Ambiente**: O campo `environment` define qual conjunto de credenciais será usado nas transações:
   - `sandbox` → usa `access_token_test` e `public_key_test`
   - `production` → usa `access_token_prod` e `public_key_prod`

3. **Webhook**: A URL do webhook é fixa: `https://api.appcheckin.com.br/api/webhooks/mercadopago` - o tenant deve configurar essa URL no painel do Mercado Pago.

---

## Changelog

| Data | Versão | Descrição |
|------|--------|-----------|
| 2026-02-07 | 1.0.0 | Versão inicial da API |
