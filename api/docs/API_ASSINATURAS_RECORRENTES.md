# API de Assinaturas Recorrentes

Documentação completa para integração do fluxo de assinaturas recorrentes com Mercado Pago.

---

## Índice

1. [Visão Geral](#visão-geral)
2. [Fluxo de Compra](#fluxo-de-compra)
3. [Endpoints](#endpoints)
4. [Exemplos de Código](#exemplos-de-código)
5. [Status das Assinaturas](#status-das-assinaturas)
6. [Webhooks](#webhooks)

---

## Visão Geral

O sistema suporta dois tipos de pagamento:

| Tipo | Ciclo | Pagamento |
|------|-------|-----------|
| **Assinatura Recorrente** | Mensal | Cartão de crédito (cobrado automaticamente todo mês) |
| **Pagamento Único** | Bimestral, Trimestral, Semestral, Anual | PIX, Boleto ou Cartão |

### Regra de Negócio
- **Ciclo mensal (1 mês)** → Cria assinatura recorrente
- **Ciclo > 1 mês** → Cria pagamento único

---

## Fluxo de Compra

```
┌─────────────────────────────────────────────────────────────────────┐
│                        FLUXO DE COMPRA                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  1. App lista planos         GET /mobile/planos                     │
│           │                                                          │
│           ▼                                                          │
│  2. Usuário escolhe plano e ciclo                                   │
│           │                                                          │
│           ▼                                                          │
│  3. App compra plano         POST /mobile/comprar-plano             │
│           │                                                          │
│           ├──► Ciclo Mensal ──► Cria Assinatura no MP               │
│           │                           │                              │
│           │                           ▼                              │
│           │                    payment_url (checkout assinatura)    │
│           │                           │                              │
│           │                           ▼                              │
│           │                    Só aceita CARTÃO DE CRÉDITO          │
│           │                                                          │
│           └──► Ciclo > 1 mês ──► Cria Pagamento Único               │
│                                       │                              │
│                                       ▼                              │
│                                payment_url (checkout normal)        │
│                                       │                              │
│                                       ▼                              │
│                                Aceita PIX, Boleto, Cartão           │
│                                                                      │
│  4. App redireciona para payment_url                                │
│           │                                                          │
│           ▼                                                          │
│  5. Usuário paga no Mercado Pago                                    │
│           │                                                          │
│           ▼                                                          │
│  6. Webhook atualiza matrícula → status = ATIVA                     │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Endpoints

### 1. Listar Planos com Ciclos

```http
GET /mobile/planos
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "success": true,
  "planos": [
    {
      "id": 1,
      "nome": "1x por Semana",
      "descricao": "Uma aula por semana",
      "valor": 0.50,
      "modalidade_nome": "Aqua Masters",
      "ciclos": [
        {
          "id": 1,
          "tipo_ciclo_id": 1,
          "nome": "Mensal",
          "meses": 1,
          "valor": 0.50,
          "valor_formatado": "R$ 0,50",
          "valor_mensal_equivalente": 0.50,
          "desconto_percentual": 0,
          "permite_recorrencia": true,
          "economia": null
        },
        {
          "id": 2,
          "tipo_ciclo_id": 3,
          "nome": "Trimestral",
          "meses": 3,
          "valor": 1.43,
          "valor_formatado": "R$ 1,43",
          "valor_mensal_equivalente": 0.48,
          "desconto_percentual": 5,
          "permite_recorrencia": false,
          "economia": "Economia de 5%"
        },
        {
          "id": 3,
          "tipo_ciclo_id": 5,
          "nome": "Semestral",
          "meses": 6,
          "valor": 2.70,
          "valor_formatado": "R$ 2,70",
          "valor_mensal_equivalente": 0.45,
          "desconto_percentual": 10,
          "permite_recorrencia": false,
          "economia": "Economia de 10%"
        },
        {
          "id": 4,
          "tipo_ciclo_id": 6,
          "nome": "Anual",
          "meses": 12,
          "valor": 5.10,
          "valor_formatado": "R$ 5,10",
          "valor_mensal_equivalente": 0.43,
          "desconto_percentual": 15,
          "permite_recorrencia": false,
          "economia": "Economia de 15%"
        }
      ]
    }
  ]
}
```

---

### 2. Comprar Plano

```http
POST /mobile/comprar-plano
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "plano_id": 1,
  "plano_ciclo_id": 1
}
```

> **Nota:** Se `plano_ciclo_id` não for enviado, usa o valor base do plano como mensal.

**Resposta Sucesso (Assinatura Mensal):**
```json
{
  "success": true,
  "message": "Matrícula criada. Complete a assinatura mensal para ativar.",
  "data": {
    "matricula_id": 31,
    "plano_id": 1,
    "plano_ciclo_id": 1,
    "plano_nome": "1x por Semana",
    "ciclo_nome": "Mensal",
    "duracao_meses": 1,
    "modalidade": "Aqua Masters",
    "valor": 0.50,
    "valor_formatado": "R$ 0,50",
    "status": "pendente",
    "data_inicio": "2026-02-07",
    "data_vencimento": "2026-03-07",
    "dia_vencimento": 7,
    "payment_url": "https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=xxx",
    "preference_id": "ddb5c80b9389408fadce18befc7a9283",
    "tipo_pagamento": "assinatura",
    "recorrente": true,
    "assinatura_id": 1,
    "mp_preapproval_id": "ddb5c80b9389408fadce18befc7a9283"
  }
}
```

**Resposta Sucesso (Pagamento Único - Trimestral/Semestral/Anual):**
```json
{
  "success": true,
  "message": "Matrícula criada com sucesso. Complete o pagamento para ativar.",
  "data": {
    "matricula_id": 32,
    "plano_id": 1,
    "plano_ciclo_id": 2,
    "plano_nome": "1x por Semana",
    "ciclo_nome": "Trimestral",
    "duracao_meses": 3,
    "modalidade": "Aqua Masters",
    "valor": 1.43,
    "valor_formatado": "R$ 1,43",
    "status": "pendente",
    "data_inicio": "2026-02-07",
    "data_vencimento": "2026-05-07",
    "dia_vencimento": 7,
    "payment_url": "https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=xxx",
    "preference_id": "123456789-abc-def",
    "tipo_pagamento": "pagamento_unico",
    "recorrente": false,
    "assinatura_id": null,
    "mp_preapproval_id": null
  }
}
```

---

### 3. Listar Minhas Assinaturas

```http
GET /mobile/assinaturas
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "success": true,
  "assinaturas": [
    {
      "id": 1,
      "status": "authorized",
      "status_label": "Ativa",
      "valor": 0.50,
      "valor_formatado": "R$ 0,50",
      "plano_nome": "1x por Semana",
      "ciclo_nome": "Mensal",
      "ciclo_meses": 1,
      "modalidade_nome": "Aqua Masters",
      "data_inicio": "2026-02-07",
      "proxima_cobranca": "2026-03-07",
      "ultima_cobranca": "2026-02-07"
    }
  ],
  "total": 1
}
```

---

### 4. Cancelar Assinatura

```http
POST /mobile/assinatura/{id}/cancelar
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "motivo": "Não preciso mais do serviço"
}
```

**Resposta Sucesso:**
```json
{
  "success": true,
  "message": "Assinatura cancelada com sucesso"
}
```

**Erros Possíveis:**
```json
// Assinatura não encontrada
{
  "error": "Assinatura não encontrada"
}

// Já cancelada
{
  "error": "Assinatura já está cancelada"
}

// Sem permissão
{
  "error": "Sem permissão para cancelar esta assinatura"
}
```

---

### 5. Verificar Status do Pagamento

```http
POST /mobile/verificar-pagamento
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "matricula_id": 31
}
```

**Resposta:**
```json
{
  "success": true,
  "matricula_id": 31,
  "status": "ativa",
  "pago": true
}
```

---

## Exemplos de Código

### React Native / JavaScript

```javascript
import api from './api'; // sua instância do axios

// ============================================
// 1. LISTAR PLANOS COM CICLOS
// ============================================
export const listarPlanos = async () => {
  try {
    const response = await api.get('/mobile/planos');
    return response.data.planos;
  } catch (error) {
    console.error('Erro ao listar planos:', error);
    throw error;
  }
};

// ============================================
// 2. COMPRAR PLANO
// ============================================
export const comprarPlano = async (planoId, planoCicloId) => {
  try {
    console.log('🛒 Iniciando compra do plano:', planoId);
    console.log('📅 Ciclo selecionado:', planoCicloId);
    
    const response = await api.post('/mobile/comprar-plano', {
      plano_id: planoId,
      plano_ciclo_id: planoCicloId
    });
    
    console.log('✅ Resposta da API:', response.data);
    
    if (response.data.success) {
      const { payment_url, tipo_pagamento, recorrente } = response.data.data;
      
      // Informar ao usuário o tipo de pagamento
      if (recorrente) {
        console.log('💳 Assinatura recorrente - Só aceita cartão de crédito');
      } else {
        console.log('💰 Pagamento único - Aceita PIX, Boleto ou Cartão');
      }
      
      // Abrir URL de pagamento
      if (payment_url) {
        // React Native
        const { Linking } = require('react-native');
        await Linking.openURL(payment_url);
        
        // Ou usar WebView / InAppBrowser
      }
      
      return response.data;
    }
    
    throw new Error(response.data.message || 'Erro ao comprar plano');
    
  } catch (error) {
    console.error('❌ Erro ao comprar plano:', error);
    throw error;
  }
};

// ============================================
// 3. LISTAR MINHAS ASSINATURAS
// ============================================
export const listarAssinaturas = async () => {
  try {
    const response = await api.get('/mobile/assinaturas');
    return response.data.assinaturas;
  } catch (error) {
    console.error('Erro ao listar assinaturas:', error);
    throw error;
  }
};

// ============================================
// 4. CANCELAR ASSINATURA
// ============================================
export const cancelarAssinatura = async (assinaturaId, motivo = '') => {
  try {
    const response = await api.post(`/mobile/assinatura/${assinaturaId}/cancelar`, {
      motivo: motivo || 'Cancelado pelo usuário'
    });
    
    if (response.data.success) {
      return response.data;
    }
    
    throw new Error(response.data.error || 'Erro ao cancelar assinatura');
    
  } catch (error) {
    console.error('Erro ao cancelar assinatura:', error);
    throw error;
  }
};

// ============================================
// 5. VERIFICAR STATUS DO PAGAMENTO
// ============================================
export const verificarPagamento = async (matriculaId) => {
  try {
    const response = await api.post('/mobile/verificar-pagamento', {
      matricula_id: matriculaId
    });
    return response.data;
  } catch (error) {
    console.error('Erro ao verificar pagamento:', error);
    throw error;
  }
};
```

### Exemplo de Tela de Seleção de Ciclo

```jsx
import React, { useState, useEffect } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Alert } from 'react-native';
import { listarPlanos, comprarPlano } from './services/api';

const SelecionarCicloScreen = ({ route, navigation }) => {
  const { planoId } = route.params;
  const [plano, setPlano] = useState(null);
  const [cicloSelecionado, setCicloSelecionado] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    carregarPlano();
  }, []);

  const carregarPlano = async () => {
    const planos = await listarPlanos();
    const planoEncontrado = planos.find(p => p.id === planoId);
    setPlano(planoEncontrado);
    
    // Selecionar mensal por padrão
    if (planoEncontrado?.ciclos?.length > 0) {
      setCicloSelecionado(planoEncontrado.ciclos[0]);
    }
  };

  const handleComprar = async () => {
    if (!cicloSelecionado) {
      Alert.alert('Atenção', 'Selecione um ciclo de pagamento');
      return;
    }

    // Avisar sobre forma de pagamento
    if (cicloSelecionado.meses === 1) {
      Alert.alert(
        'Assinatura Mensal',
        'Você será redirecionado para o Mercado Pago.\n\nATENÇÃO: Assinaturas mensais só aceitam CARTÃO DE CRÉDITO.\n\nO valor será cobrado automaticamente todo mês.',
        [
          { text: 'Cancelar', style: 'cancel' },
          { text: 'Continuar', onPress: () => realizarCompra() }
        ]
      );
    } else {
      realizarCompra();
    }
  };

  const realizarCompra = async () => {
    setLoading(true);
    try {
      const resultado = await comprarPlano(planoId, cicloSelecionado.id);
      
      if (resultado.success && resultado.data.payment_url) {
        // Salvar matricula_id para verificar depois
        await AsyncStorage.setItem('matricula_pendente', resultado.data.matricula_id.toString());
        
        // Abrir URL de pagamento
        await Linking.openURL(resultado.data.payment_url);
      }
    } catch (error) {
      Alert.alert('Erro', error.message);
    } finally {
      setLoading(false);
    }
  };

  if (!plano) return <Text>Carregando...</Text>;

  return (
    <View style={styles.container}>
      <Text style={styles.titulo}>{plano.nome}</Text>
      <Text style={styles.subtitulo}>Selecione o período:</Text>

      {plano.ciclos?.map(ciclo => (
        <TouchableOpacity
          key={ciclo.id}
          style={[
            styles.cicloCard,
            cicloSelecionado?.id === ciclo.id && styles.cicloSelecionado
          ]}
          onPress={() => setCicloSelecionado(ciclo)}
        >
          <View style={styles.cicloInfo}>
            <Text style={styles.cicloNome}>{ciclo.nome}</Text>
            <Text style={styles.cicloValor}>{ciclo.valor_formatado}</Text>
            {ciclo.economia && (
              <Text style={styles.economia}>{ciclo.economia}</Text>
            )}
          </View>
          
          {ciclo.meses === 1 && (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>Recorrente</Text>
            </View>
          )}
        </TouchableOpacity>
      ))}

      {cicloSelecionado?.meses === 1 && (
        <View style={styles.aviso}>
          <Text style={styles.avisoTexto}>
            ⚠️ Assinatura mensal: cobrado automaticamente todo mês via cartão de crédito
          </Text>
        </View>
      )}

      <TouchableOpacity
        style={[styles.botaoComprar, loading && styles.botaoDesabilitado]}
        onPress={handleComprar}
        disabled={loading}
      >
        <Text style={styles.botaoTexto}>
          {loading ? 'Processando...' : 'Continuar para Pagamento'}
        </Text>
      </TouchableOpacity>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, padding: 20 },
  titulo: { fontSize: 24, fontWeight: 'bold', marginBottom: 10 },
  subtitulo: { fontSize: 16, color: '#666', marginBottom: 20 },
  cicloCard: {
    padding: 15,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    marginBottom: 10,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center'
  },
  cicloSelecionado: {
    borderColor: '#007AFF',
    backgroundColor: '#F0F8FF'
  },
  cicloInfo: { flex: 1 },
  cicloNome: { fontSize: 18, fontWeight: '600' },
  cicloValor: { fontSize: 16, color: '#333', marginTop: 5 },
  economia: { fontSize: 14, color: '#28A745', marginTop: 3 },
  badge: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 15
  },
  badgeText: { color: '#fff', fontSize: 12 },
  aviso: {
    backgroundColor: '#FFF3CD',
    padding: 15,
    borderRadius: 10,
    marginVertical: 15
  },
  avisoTexto: { color: '#856404', fontSize: 14 },
  botaoComprar: {
    backgroundColor: '#007AFF',
    padding: 18,
    borderRadius: 10,
    alignItems: 'center',
    marginTop: 20
  },
  botaoDesabilitado: { backgroundColor: '#ccc' },
  botaoTexto: { color: '#fff', fontSize: 18, fontWeight: '600' }
});

export default SelecionarCicloScreen;
```

### Exemplo de Tela de Minhas Assinaturas

```jsx
import React, { useState, useEffect } from 'react';
import { View, Text, FlatList, TouchableOpacity, Alert, StyleSheet } from 'react-native';
import { listarAssinaturas, cancelarAssinatura } from './services/api';

const MinhasAssinaturasScreen = () => {
  const [assinaturas, setAssinaturas] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    carregarAssinaturas();
  }, []);

  const carregarAssinaturas = async () => {
    try {
      const data = await listarAssinaturas();
      setAssinaturas(data);
    } catch (error) {
      Alert.alert('Erro', 'Não foi possível carregar suas assinaturas');
    } finally {
      setLoading(false);
    }
  };

  const handleCancelar = (assinatura) => {
    Alert.alert(
      'Cancelar Assinatura',
      `Deseja realmente cancelar a assinatura de ${assinatura.plano_nome}?\n\nA cobrança automática será interrompida, mas você poderá usar o plano até o fim do período atual.`,
      [
        { text: 'Não', style: 'cancel' },
        {
          text: 'Sim, Cancelar',
          style: 'destructive',
          onPress: () => confirmarCancelamento(assinatura.id)
        }
      ]
    );
  };

  const confirmarCancelamento = async (assinaturaId) => {
    try {
      await cancelarAssinatura(assinaturaId, 'Cancelado pelo app');
      Alert.alert('Sucesso', 'Assinatura cancelada com sucesso');
      carregarAssinaturas(); // Recarregar lista
    } catch (error) {
      Alert.alert('Erro', error.message);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'authorized': return '#28A745';
      case 'pending': return '#FFC107';
      case 'paused': return '#17A2B8';
      case 'cancelled': return '#DC3545';
      default: return '#6C757D';
    }
  };

  const renderAssinatura = ({ item }) => (
    <View style={styles.card}>
      <View style={styles.header}>
        <Text style={styles.planoNome}>{item.plano_nome}</Text>
        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status) }]}>
          <Text style={styles.statusText}>{item.status_label}</Text>
        </View>
      </View>
      
      <Text style={styles.modalidade}>{item.modalidade_nome}</Text>
      <Text style={styles.valor}>{item.valor_formatado}/mês</Text>
      
      <View style={styles.datas}>
        <Text style={styles.dataLabel}>Próxima cobrança:</Text>
        <Text style={styles.dataValor}>
          {new Date(item.proxima_cobranca).toLocaleDateString('pt-BR')}
        </Text>
      </View>
      
      {item.status === 'authorized' && (
        <TouchableOpacity
          style={styles.botaoCancelar}
          onPress={() => handleCancelar(item)}
        >
          <Text style={styles.botaoCancelarTexto}>Cancelar Assinatura</Text>
        </TouchableOpacity>
      )}
    </View>
  );

  if (loading) {
    return <Text>Carregando...</Text>;
  }

  if (assinaturas.length === 0) {
    return (
      <View style={styles.empty}>
        <Text style={styles.emptyText}>Você não possui assinaturas ativas</Text>
      </View>
    );
  }

  return (
    <FlatList
      data={assinaturas}
      keyExtractor={(item) => item.id.toString()}
      renderItem={renderAssinatura}
      contentContainerStyle={styles.lista}
    />
  );
};

const styles = StyleSheet.create({
  lista: { padding: 15 },
  card: {
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 15,
    marginBottom: 15,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10
  },
  planoNome: { fontSize: 18, fontWeight: 'bold' },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 15
  },
  statusText: { color: '#fff', fontSize: 12, fontWeight: '600' },
  modalidade: { fontSize: 14, color: '#666', marginBottom: 5 },
  valor: { fontSize: 20, fontWeight: 'bold', color: '#007AFF', marginBottom: 10 },
  datas: { flexDirection: 'row', marginBottom: 15 },
  dataLabel: { fontSize: 14, color: '#666' },
  dataValor: { fontSize: 14, fontWeight: '600', marginLeft: 5 },
  botaoCancelar: {
    borderWidth: 1,
    borderColor: '#DC3545',
    borderRadius: 8,
    padding: 12,
    alignItems: 'center'
  },
  botaoCancelarTexto: { color: '#DC3545', fontWeight: '600' },
  empty: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  emptyText: { fontSize: 16, color: '#666' }
});

export default MinhasAssinaturasScreen;
```

---

## Status das Assinaturas

| Status | Label | Descrição |
|--------|-------|-----------|
| `pending` | Pendente | Aguardando primeiro pagamento |
| `authorized` | Ativa | Assinatura ativa, cobrança automática funcionando |
| `paused` | Pausada | Temporariamente pausada (não cobra) |
| `cancelled` | Cancelada | Cancelada pelo usuário ou sistema |
| `finished` | Finalizada | Período encerrado |

---

## Webhooks

O backend processa automaticamente os webhooks do Mercado Pago para:

1. **Pagamento Aprovado** (`payment.approved`)
   - Ativa a matrícula
   - Registra pagamento como PAGO

2. **Assinatura Autorizada** (`subscription_preapproval.authorized`)
   - Ativa a matrícula
   - Atualiza status da assinatura para `authorized`

3. **Assinatura Cancelada** (`subscription_preapproval.cancelled`)
   - Atualiza status da assinatura para `cancelled`

> **Nota:** O app não precisa fazer nada com webhooks, é processado pelo backend.

---

## Considerações Importantes

### Para o Mobile

1. **Assinatura Mensal só aceita Cartão de Crédito**
   - Avisar o usuário antes de redirecionar

2. **Salvar `matricula_id`** após compra
   - Para verificar status depois

3. **Verificar pagamento** quando voltar do MP
   - Chamar `/mobile/verificar-pagamento`

4. **Deep Links** (opcional)
   - Configurar `back_url` para retornar ao app

### Para o Frontend Web (Admin)

1. **Ver assinaturas de alunos**
   - Implementar listagem no painel admin

2. **Cancelar assinatura** de alunos
   - Admin pode cancelar qualquer assinatura

3. **Relatórios**
   - MRR (Monthly Recurring Revenue)
   - Churn rate
   - Assinaturas ativas vs canceladas

---

## Resumo dos Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/mobile/planos` | Lista planos com ciclos |
| POST | `/mobile/comprar-plano` | Compra plano (cria matrícula + pagamento) |
| GET | `/mobile/assinaturas` | Lista assinaturas do usuário |
| POST | `/mobile/assinatura/{id}/cancelar` | Cancela assinatura |
| POST | `/mobile/verificar-pagamento` | Verifica se pagamento foi aprovado |

---

**Última atualização:** 7 de Fevereiro de 2026
