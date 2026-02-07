# API - Planos Disponíveis para Contratação

## Endpoint: GET /mobile/planos-disponiveis

Lista os planos pagos disponíveis para contratação no tenant selecionado.

### 📋 Descrição

Endpoint criado para ser usado no app mobile, permitindo que o usuário visualize os planos disponíveis para contratação antes de realizar a compra.

**Características:**
- ✅ Lista apenas planos **ativos** (ativo = 1)
- ✅ Lista apenas planos **pagos** (valor > 0)
- ✅ Exclui planos gratuitos/teste automaticamente
- ✅ Permite filtro opcional por modalidade
- ✅ Retorna informações formatadas para exibição no mobile

---

## 🔐 Autenticação

**Obrigatória**: Sim  
**Header**: `Authorization: Bearer {token}`  
**Middleware**: `AuthMiddleware`

---

## 📥 Request

### URL
```
GET /mobile/planos-disponiveis
```

### Query Parameters (Opcional)

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| modalidade_id | integer | Não | ID da modalidade para filtrar planos específicos |

### Headers
```
Authorization: Bearer {seu_token_jwt}
X-Tenant-ID: {tenant_id}
```

---

## 📤 Response

### ✅ Sucesso (200 OK)

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
      },
      {
        "id": 2,
        "nome": "Mensal Ilimitado",
        "descricao": "Plano mensal com check-ins ilimitados",
        "valor": 149.90,
        "valor_formatado": "R$ 149,90",
        "duracao_dias": 30,
        "duracao_texto": "1 mês",
        "checkins_semanais": 999,
        "modalidade": {
          "id": 1,
          "nome": "CrossFit"
        }
      },
      {
        "id": 3,
        "nome": "Trimestral",
        "descricao": "Plano trimestral com check-ins ilimitados",
        "valor": 399.90,
        "valor_formatado": "R$ 399,90",
        "duracao_dias": 90,
        "duracao_texto": "3 meses",
        "checkins_semanais": 999,
        "modalidade": {
          "id": 1,
          "nome": "CrossFit"
        }
      },
      {
        "id": 4,
        "nome": "Anual",
        "descricao": "Plano anual com check-ins ilimitados e desconto",
        "valor": 1299.90,
        "valor_formatado": "R$ 1.299,90",
        "duracao_dias": 365,
        "duracao_texto": "1 ano",
        "checkins_semanais": 999,
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

### ❌ Erros

#### 400 - Tenant Não Selecionado
```json
{
  "success": false,
  "type": "error",
  "code": "TENANT_NAO_SELECIONADO",
  "message": "Nenhum tenant selecionado"
}
```

#### 401 - Não Autorizado
```json
{
  "success": false,
  "type": "error",
  "code": "UNAUTHORIZED",
  "message": "Token inválido ou expirado"
}
```

#### 500 - Erro Interno
```json
{
  "success": false,
  "type": "error",
  "code": "ERRO_INTERNO",
  "message": "Erro ao buscar planos disponíveis"
}
```

---

## 🔍 Detalhes dos Campos

### Campo: `valor_formatado`
Valor já formatado para exibição no formato brasileiro (R$ 0,00)

### Campo: `duracao_texto`
Texto amigável da duração:
- 30 dias → "1 mês"
- 60 dias → "2 meses"
- 90 dias → "3 meses"
- 180 dias → "6 meses"
- 365 dias → "1 ano"
- Outros → "{n} dias" ou "{n} meses"

### Campo: `checkins_semanais`
Quantidade de check-ins permitidos por semana. 
- Valor alto (999+) geralmente indica "ilimitado"

---

## 💡 Exemplos de Uso

### 1. Listar todos os planos disponíveis

```bash
curl -X GET "http://localhost:8080/mobile/planos-disponiveis" \
  -H "Authorization: Bearer SEU_TOKEN_JWT" \
  -H "X-Tenant-ID: 1"
```

### 2. Filtrar planos por modalidade

```bash
curl -X GET "http://localhost:8080/mobile/planos-disponiveis?modalidade_id=1" \
  -H "Authorization: Bearer SEU_TOKEN_JWT" \
  -H "X-Tenant-ID: 1"
```

### 3. JavaScript/TypeScript (Fetch API)

```typescript
async function buscarPlanosDisponiveis(modalidadeId?: number) {
  const url = modalidadeId 
    ? `/mobile/planos-disponiveis?modalidade_id=${modalidadeId}`
    : '/mobile/planos-disponiveis';
  
  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'X-Tenant-ID': tenantId,
      'Content-Type': 'application/json'
    }
  });
  
  if (!response.ok) {
    throw new Error('Erro ao buscar planos');
  }
  
  const data = await response.json();
  return data.data.planos;
}

// Uso
const planos = await buscarPlanosDisponiveis();
console.log(`${planos.length} planos disponíveis`);

// Com filtro de modalidade
const planosModalidade = await buscarPlanosDisponiveis(1);
```

### 4. React Native exemplo

```tsx
import React, { useEffect, useState } from 'react';
import { View, Text, FlatList, StyleSheet } from 'react-native';

interface Plano {
  id: number;
  nome: string;
  descricao: string;
  valor_formatado: string;
  duracao_texto: string;
  checkins_semanais: number;
}

export function TelaPlanosDisponiveis() {
  const [planos, setPlanos] = useState<Plano[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function carregarPlanos() {
      try {
        const response = await fetch('/mobile/planos-disponiveis', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'X-Tenant-ID': tenantId
          }
        });
        
        const data = await response.json();
        setPlanos(data.data.planos);
      } catch (error) {
        console.error('Erro ao carregar planos:', error);
      } finally {
        setLoading(false);
      }
    }
    
    carregarPlanos();
  }, []);

  return (
    <View style={styles.container}>
      <Text style={styles.titulo}>Planos Disponíveis</Text>
      
      <FlatList
        data={planos}
        keyExtractor={(item) => item.id.toString()}
        renderItem={({ item }) => (
          <View style={styles.planoCard}>
            <Text style={styles.planoNome}>{item.nome}</Text>
            <Text style={styles.planoDescricao}>{item.descricao}</Text>
            <Text style={styles.planoValor}>{item.valor_formatado}</Text>
            <Text style={styles.planoDuracao}>{item.duracao_texto}</Text>
            <Text style={styles.planoCheckins}>
              {item.checkins_semanais} check-ins/semana
            </Text>
          </View>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 16 },
  titulo: { fontSize: 24, fontWeight: 'bold', marginBottom: 16 },
  planoCard: { 
    backgroundColor: 'white', 
    padding: 16, 
    borderRadius: 8, 
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3
  },
  planoNome: { fontSize: 18, fontWeight: 'bold' },
  planoDescricao: { fontSize: 14, color: '#666', marginTop: 4 },
  planoValor: { fontSize: 20, fontWeight: 'bold', color: '#007AFF', marginTop: 8 },
  planoDuracao: { fontSize: 14, color: '#999', marginTop: 4 },
  planoCheckins: { fontSize: 12, color: '#999', marginTop: 2 }
});
```

---

## 🗄️ Consulta SQL Executada

```sql
SELECT p.id, p.nome, p.descricao, p.valor, p.duracao_dias, p.checkins_semanais,
       m.id as modalidade_id, m.nome as modalidade_nome
FROM planos p
LEFT JOIN modalidades m ON p.modalidade_id = m.id
WHERE p.tenant_id = :tenant_id 
AND p.ativo = 1 
AND p.valor > 0
-- [AND p.modalidade_id = :modalidade_id] (se filtro aplicado)
ORDER BY p.valor ASC
```

---

## 📊 Regras de Negócio

### Planos Listados
✅ **INCLUÍDOS**:
- Planos com `ativo = 1`
- Planos com `valor > 0` (pagos)
- Do tenant selecionado no header

❌ **EXCLUÍDOS**:
- Planos com `ativo = 0` (inativos)
- Planos com `valor = 0` (gratuitos/teste)
- Planos de outros tenants

### Ordenação
Os planos são ordenados por **valor crescente** (do mais barato ao mais caro).

---

## 🔄 Diferença entre Endpoints

| Endpoint | Descrição | Uso |
|----------|-----------|-----|
| `/mobile/planos` | Planos que o **usuário já possui** (suas matrículas) | Listar matrículas ativas do usuário |
| `/mobile/planos-disponiveis` | Planos **disponíveis para compra** | Tela de compra/contratação |

---

## 🧪 Testes

### Cenário 1: Listar planos disponíveis
```bash
# Request
GET /mobile/planos-disponiveis

# Expected: Lista de planos pagos ativos
# Status: 200 OK
```

### Cenário 2: Filtrar por modalidade específica
```bash
# Request
GET /mobile/planos-disponiveis?modalidade_id=1

# Expected: Lista apenas planos da modalidade ID 1
# Status: 200 OK
```

### Cenário 3: Tenant sem planos cadastrados
```bash
# Request
GET /mobile/planos-disponiveis

# Expected: Array vazio com total = 0
# Status: 200 OK
{
  "success": true,
  "data": {
    "planos": [],
    "total": 0
  }
}
```

### Cenário 4: Sem token de autenticação
```bash
# Request
GET /mobile/planos-disponiveis
# (sem header Authorization)

# Expected: Erro de autenticação
# Status: 401 Unauthorized
```

---

## 📝 Notas Importantes

1. **Planos gratuitos/teste**: Planos com valor = 0 NÃO aparecem nesta lista
2. **Planos inativos**: Planos marcados como inativos NÃO aparecem
3. **Multi-tenant**: Respeita isolamento, mostra apenas planos do tenant selecionado
4. **Performance**: Query otimizada com JOIN apenas em modalidades
5. **Formato de valores**: Valores já formatados para Real brasileiro (R$)

---

## 🔗 Endpoints Relacionados

- `POST /api/admin/matriculas` - Criar nova matrícula usando plano disponível
- `GET /mobile/planos` - Listar planos do usuário (suas matrículas)
- `GET /mobile/matriculas/{id}` - Detalhes de uma matrícula específica
- `GET /api/admin/planos` - Gerenciar planos (admin)

---

## 🚀 Integração com Mercado Pago

Após selecionar um plano disponível, use o ID do plano para:

1. Criar uma preferência de pagamento:
```php
$mercadoPagoService = new MercadoPagoService();
$preferenciaId = $mercadoPagoService->criarPreferenciaPagamento(
    $matriculaId,
    $plano['nome'],
    $plano['valor']
);
```

2. Redirecionar usuário para pagamento no Mercado Pago

3. Processar webhook após pagamento aprovado

Veja: [docs/INTEGRACAO_MERCADO_PAGO.md](INTEGRACAO_MERCADO_PAGO.md)

---

## ✅ Checklist de Implementação Frontend

- [ ] Criar tela de listagem de planos
- [ ] Exibir cards com informações dos planos
- [ ] Adicionar filtro por modalidade (opcional)
- [ ] Implementar botão "Contratar" em cada plano
- [ ] Integrar com fluxo de pagamento Mercado Pago
- [ ] Adicionar loading state durante requisição
- [ ] Tratar erros (sem planos, erro de conexão, etc)
- [ ] Adicionar refresh/pull-to-refresh
- [ ] Implementar analytics de visualização de planos

---

**Versão**: 1.0  
**Data**: 06/02/2026  
**Autor**: App Checkin Team
