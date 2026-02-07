# 📱 Fluxo de Compra de Plano - Mobile App

## 🎯 Visão Geral

Guia completo do fluxo que o app mobile deve seguir quando o usuário escolhe um plano para pagar.

## ⚠️ IMPORTANTE - LEIA PRIMEIRO

**Use o endpoint específico para mobile**: `POST /mobile/comprar-plano`

✅ **Vantagens**:
- Não precisa enviar `aluno_id` (pega automaticamente do usuário logado)
- Validações automáticas (matrícula duplicada, plano inativo, etc)
- Código mais simples e direto
- Tratamento de erros específicos para mobile

❌ **NÃO use**: `POST /api/admin/matriculas` (endpoint administrativo)

---

## 📊 Fluxo Completo

```
1. Usuário visualiza planos → GET /mobile/planos-disponiveis
2. Usuário escolhe plano → Guarda plano_id
3. App cria matrícula → POST /mobile/comprar-plano
4. Backend retorna payment_url (Mercado Pago)
5. App redireciona usuário → payment_url
6. Usuário paga no Mercado Pago
7. MP notifica webhook → POST /api/webhooks/mercadopago
8. Backend ativa matrícula automaticamente
9. App verifica status → GET /mobile/planos ou /mobile/matriculas/{id}
```

---

## 1️⃣ Listar Planos Disponíveis

### Endpoint
```http
GET /mobile/planos-disponiveis
```

### Headers
```
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
```

### Resposta
```json
{
  "success": true,
  "data": {
    "planos": [
      {
        "id": 1,
        "nome": "Mensal Básico",
        "descricao": "Plano mensal com 12 check-ins por semana",
        "valor": 99.90,
        "valor_formatado": "R$ 99,90",
        "duracao_dias": 30,
        "duracao_texto": "1 mês",
        "checkins_semanais": 12,
        "modalidade": {
          "id": 1,
          "nome": "CrossFit"
        }
      }
    ],
    "total": 4
  }
}
```

---

## 2️⃣ Comprar Plano (Cria Matrícula + Gera Link de Pagamento)

### Endpoint
```http
POST /mobile/comprar-plano
```

### Headers
```
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
Content-Type: application/json
```

### Body
```json
{
  "plano_id": 1,
  "dia_vencimento": 5
}
```

### Parâmetros

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| plano_id | integer | Sim | ID do plano escolhido (da lista de planos disponíveis) |
| dia_vencimento | integer | Sim | Dia do mês para vencimento (1-31) |

**✨ Diferencial**: Este endpoint **não requer aluno_id** - busca automaticamente do usuário logado!

### Resposta
```json
{
  "success": true,
  "message": de Sucesso
```json
{
  "success": true,
  "message": "Matrícula criada com sucesso. Complete o pagamento para ativar.",
  "data": {
    "matricula_id": 456,
    "plano_id": 1,
    "plano_nome": "Mensal Básico",
    "modalidade": "CrossFit",
    "valor": 99.90,
    "valor_formatado": "R$ 99,90",
    "status": "pendente",
    "data_inicio": "2026-02-06",
    "data_vencimento": "2026-03-08",
    "dia_vencimento": 5,
    "payment_url": "https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=123456789-abc-def",
    "preference_id": "123456789-abc-def"
  }
}
```

### Erros Possíveis

#### ❌ Matrícula Ativa Já Existe (400)
```json
{
  "success": false,
  "type": "error",
  "code": "MATRICULA_ATIVA_EXISTENTE",
  "message": "Você já possui uma matrícula ativa nesta modalidade"
}
```

#### ❌ Plano Não Encontrado (404)
```json
{
  "success": false,
  "type": "error",
  "code": "PLANO_NAO_ENCONTRADO",
  "message": "Plano não encontrado ou inativo"
}
```

#### ❌ Dia de Vencimento Inválido (400)
```json
{
  "success": false,
  "type": "error",
  "code": "DIA_VENCIMENTO_INVALIDO",
  "message": "Dia de vencimento deve estar entre 1 e 31"
}
```

**IMPORTANTE**: 
- A matrícula é criada com `status = "pendente"`
- O campo `payment_url` contém o link para pagamento no Mercado Pago
- A matrícula só será ativada após pagamento ser aprovado
- O endpoint já valida se existe matrícula ativa na mesma modalidade

### Opção A: Abrir no Navegador (Recomendado)

```typescript
// React Native
import { Linking } from 'react-native';

const abrirPagamento = async (paymentUrl: string) => {
  const supported = await Linking.canOpenURL(paymentUrl);
  
  if (supported) {
    await Linking.openURL(paymentUrl);
  } else {
    Alert.alert('Erro', 'Não foi possível abrir o link de pagamento');
  }
};
```

### Opção B: WebView Interno

```typescript
// React Native
import { WebView } from 'react-native-webview';

<WebView 
  source={{ uri: paymentUrl }}
  onNavigationStateChange={(navState) => {
    // Detectar retorno do pagamento
    if (navState.url.includes('/success')) {
      // Pagamento aprovado
      verificarStatusMatricula();
    } else if (navState.url.includes('/failure')) {
      // Pagamento recusado
      mostrarErro();
    }
  }}
/>
```

### Opção C: Browser Externo (Web)

```javascript
// Web/PWA
window.open(paymentUrl, '_blank');

// Ou redirecionar na mesma aba
window.location.href = paymentUrl;
```

---

## 4️⃣ Processar Pagamento (Automático)

Após o usuário pagar no Mercado Pago:

1. **Mercado Pago envia webhook** para: `POST /api/webhooks/mercadopago`
2. **Backend processa notificação**:
   - Consulta status do pagamento na API do MP
   - Atualiza registro em `pagamentos_mercadopago`
   - Se status = `approved` → Ativa a matrícula

**✅ Resultado**: Matrícula muda de `pendente` → `ativa` automaticamente

---

## 5️⃣ Verificar Status da Matrícula

### Opção A: Listar Planos do Usuário

```http
GET /mobile/planos
```

```json
{
  "success": true,
  "data": {
    "planos": [
      {
        "id": 456,
        "nome": "Mensal Básico",
        "status": "ativa",
        "data_inicio": "2026-02-06",
        "data_vencimento": "2026-03-08",
        "dias_restantes": 30
      }
    ]
  }
}
```

### Opção B: Detalhes da Matrícula Específica

```http
GET /mobile/matriculas/456
```

```json
{
  "success": true,
  "data": {
    "matricula": {
      "id": 456,
      "status": "ativa",
      "plano_nome": "Mensal Básico",
      "valor": 99.90,
      "proxima_data_vencimento": "2026-03-08"
    },
    "pagamentos": [
      {
        "id": 1,
        "valor": 99.90,
        "status": "pago",
        "data_pagamento": "2026-02-06"
      }
    ]
  }
}
```

---

## 💻 Exemplo Completo - React Native, diaVencimento: number = 5) => {
    setLoading(true);
    
    try {
      // Criar matrícula (não precisa buscar aluno_id - automático!)
      const response = await api.post('/mobile/comprar-plano', {
        plano_id: plano.id,
        dia_vencimento: diaVencimento
      });
      
      const { matricula_id
  // 1. Buscar planos disponíveis
  const carregarPlanos = async () => {
    try {
      const response = await api.get('/mobile/planos-disponiveis');
      return response.data.data.planos;
    } catch (error) {
      Alert.alert('Erro', 'Não foi possível carregar os planos');
      return [];
    }_
  };

  // 2. Criar matrícula e obter link de pagamento
  const comprarPlano = async (plano: Plano) => {
    setLoading(true);
    
    try {
      // Buscar aluno_id do perfil do usuário
      const perfil = await api.get('/mobile/perfil');
      const alunoId = perfil.data.data.aluno_id;
      
      // Criar matrícula
      const response = await api.post('/api/admin/matriculas', {
        aluno_id: alunoId,
        plano_id: plano.id,
        dia_vencimento: 5, // Ou deixar usuário escolher
        observacoes: 'Compra via app mobile'
      });
      
      const { matricula, payment_url } = response.data.data;
      
      // Salvar ID da matrícula para consultar depois
      await AsyncStorage.setItem('matricula_pendente_id', matricula.id.toString());
      
      // 3. Abrir link de pagamento
      const supported = await Linking.canOpenURL(payment_url);
      if (supported) {
        await Linking.openURL(payment_url);
        
        // Mostrar mensagem ao usuário
        Alert.alert(
          'Pagamento em Andamento',
          'Complete o pagamento no Mercado Pago. Você será notificado quando o pagamento for aprovado.',
          [
            {
              text: 'OK',
              onPress: () => {
                // Redirecionar para tela de acompanhamento
                navigation.navigate('AcompanharPagamento', { 
                  matriculaId: matricula.id 
                });
              }
            }
          ]
        );
      }_id
                });
              }
            }
          ]
        );
      }
      
    } catch (error) {
      // Tratamento de erros específicos
      if (error.response?.data?.code === 'MATRICULA_ATIVA_EXISTENTE') {
        Alert.alert(
          'Matrícula Ativa',
          'Você já possui uma matrícula ativa nesta modalidade'
        );
      } else if (error.response?.data?.code === 'PLANO_NAO_ENCONTRADO') {
        Alert.alert('Erro', 'Este plano não está mais disponível');
      } else {
        Alert.alert('Erro', 'Não foi possível processar sua compra');
      }
  const verificarStatusPagamento = async (matriculaId: number) => {
    try {
      const response = await api.get(`/mobile/matriculas/${matriculaId}`);
      const status = response.data.data.matricula.status;
      
      if (status === 'ativa') {
        Alert.alert('Pagamento Aprovado!', 'Sua matrícula foi ativada com sucesso!');
        navigation.navigate('Home');
      } else if (status === 'pendente') {
        Alert.alert('Aguardando Pagamento', 'Seu pagamento ainda está sendo processado');
      }
    } catch (error) {
      Alert.alert('Erro', 'Não foi possível verificar o status do pagamento');
    }
  };

  return (
    <View>
      {/* Lista de planos */}
      {/* Botão para comprar */}
      <Button
        title={`Comprar - ${planoSelecionado?.valor_formatado}`}
        onPress={() => comprarPlano(planoSelecionado)}
        disabled={loading}
      />
    </View>
  );
}
```

---

## 📝 Estados da Matrícula

| Status | Descrição | Pode fazer check-in? |
|--------|-----------|---------------------|
| `pendente` | Aguardando pagamento | ❌ Não |
| `ativa` | Pago e ativo | ✅ Sim |
| `vencida` | Vencimento expirado | ❌ Não |
| `finalizada` | Matrícula encerrada | ❌ Não |

---

## ⚠️ Tratamento de Erros

### Pagamento Recusado
```typescript
// Mercado Pago redireciona para URL de falha
// Ex: /failure?payment_id=123&status=rejected

// App deve:
1. Detectar URL de falha
2. Mostrar mensagem ao usuário
3. Oferecer opção de tentar novamente
4. A matrícula permanece com status "pendente"
```

### Pagamento Pendente
```typescript
// Ex: Boleto bancário, Pix (aguardando confirmação)
// Status: "pending"

// App deve:
1. Informar que pagamento está pendente
2. Permitir consultar status depois
3. Matrícula permanece "pendente" até aprovação
```

### Usuário Abandona Pagamento
```typescript
// Usuário fecha tela do MP sem pagar

// App deve:
1. Matrícula fica como "pendente"
2. Permitir retomar pagamento depois
3. Ou cancelar matrícula e criar nova
```

---

## 🔄 Fluxo de Retentativa

Se usuário abandonar pagamento ou pagamento falhar:

```typescript
// Verificar se existe matrícula pendente
const matriculaPendente = await buscarMatriculaPendente();

if (matriculaPendente) {
  // Oferecer opções:
  Alert.alert(
    'Você tem uma compra pendente',
    'Deseja continuar o pagamento?',
    [
      {
        text: 'Sim, continuar',
        onPress: () => {
          // Gerar novo link de pagamento para mesma matrícula
          // Ou reutilizar preference_id anterior
        }
      },
      {
        text: 'Não, escolher outro plano',
        onPress: () => {
          // Cancelar/excluir matrícula pendente
          // Permitir criar nova
        }
      }
    ]
  );
}
```

---

## 🎨 UX Recomendada

### Tela de Planos
- ✅ Listar planos com cards visuais
- ✅ Destacar benefícios de cada plano
- ✅ Mostrar valor formatado e duração
- ✅ Botão "Assinar" ou "Comprar"

### Tela de Confirmação
- ✅ Resumo do plano escolhido
- ✅ Valor total a pagar
- ✅ Data de vencimento
- ✅ Botão "Confirmar e Pagar"

### Durante Pagamento
- ✅ Loading/spinner enquanto processa
- ✅ Mensagem clara sobre redirecionamento
- ✅ Indicar que voltará ao app após pagamento

### Após Pagamento
- ✅ Tela de "aguardando confirmação"
- ✅ Botão para verificar status
- ✅ Notificação push quando aprovado (opcional)

---

## 🔔 Notificações (Opcional)

### Push Notification quando pagamento aprovado

```typescript
// Backend envia notificação após webhook do MP
{
  "title": "Pagamento Aprovado! 🎉",
  "body": "Sua matrícula foi ativada. Você já pode fazer check-in!",
  "data": {
    "type": "payment_approved",
    "matricula_id": 456
  }
}

// App recebe e redireciona para tela de planos ativos
```

---

## 🧪 Teste com Cards do Mercado Pago

Para testar em ambiente sandbox:

| Status | Cartão | Código |
|--------|--------|--------|
| ✅ Aprovado | 5031 4332 1540 6351 | 123 |disponíveis para compra
- `POST /mobile/comprar-plano` - Comprar plano (cria matrícula + gera payment_url)
- `GET /mobile/planos` - Listar planos ativos do usuário
- `GET /mobile/matriculas/{id}` - Detalhes da matrícula específica
- `POST /api/webhooks/mercadopago` - Webhook do Mercado Pagouncionam**

---

## 📚 Endpoints Relacionados

### Backend
- [x] Endpoint `/mobile/planos-disponiveis` - Listar planos
- [x] Endpoint `/mobile/comprar-plano` - Criar matrícula + pagamento
- [x] Integração com Mercado Pago
- [x] Webhook para ativar matrícula após pagamento
- [x] Validação de matrícula duplicada

### Frontend Mobile
- [ ] Tela de listagem de planos disponíveis
- [ ] Tela de detalhes do plano selecionado
- [ ] Seletor de dia de vencimento (1-31)
- [ ] Tela de confirmação de compra
- [ ] Integração com endpoint `/mobile/comprar-plano`
- [ ] Redirecionamento para Mercado Pago (payment_url)
- [ ] Tela de aguardando confirmação de pagamento
- [ ] Verificação de status do pagamento
- [ ] Tratamento de erro: matrícula ativa existente
- [ ] Tratamento de erro: plano não disponível
- [ ]Endpoint Simplificado**: Use `/mobile/comprar-plano` (não precisa enviar aluno_id)
2. **Matrícula ≠ Pagamento**: A matrícula é criada ANTES do pagamento
3. **Status Pendente**: Matrícula fica pendente até pagamento ser aprovado
4. **Validação Automática**: Sistema impede matrícula duplicada na mesma modalidade
5. **Webhook Automático**: O backend ativa automaticamente via webhook do MP
6. **Não bloquear**: Usuário deve poder sair do app durante pagamento
7. **Verificação Manual**: App deve permitir verificar status depois
8. **Link de Pagamento**: `payment_url` é válido por 30 dia
- [ ] Tela de confirmação de compra
- [ ] Integração com endpoint de criar matrícula
- [ ] Redirecionamento para Mercado Pago
- [ ] Tela de aguardando confirmação
- [ ] Verificação de status do pagamento
- [ ] Tratamento de erros (recusado, pendente)
- [ ] Notificação ao usuário quando aprovado
- [ ] Atualização da tela de planos ativos
- [ ] Permitir retentativa de pagamento
- [ ] Loading states em todas as etapas
- [ ] Analytics de conversão (opcional)

---

## 🚨 Observações Importantes

1. **Matrícula ≠ Pagamento**: A matrícula é criada ANTES do pagamento
2. **Status Pendente**: Matrícula fica pendente até pagamento ser aprovado
3. **Webhook Automático**: O backend ativa automaticamente via webhook
4. **Não bloquear**: Usuário deve poder sair do app durante pagamento
5. **Verificação Manual**: App deve permitir verificar status depois

---

**Versão**: 1.0  
**Data**: 06/02/2026  
**Autor**: App Checkin Team
