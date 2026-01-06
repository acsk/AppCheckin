# Sistema de Configuração de Formas de Pagamento por Tenant

## 📋 Visão Geral

Sistema que permite a cada tenant configurar suas formas de pagamento personalizadas, incluindo taxas da operadora, parcelamento e condições específicas. O Tenant 1 (SuperAdmin) também está incluído pois é usado na gestão de contratos do sistema.

## 🗄️ Estrutura de Dados

### Tabela: `tenant_formas_pagamento`

```sql
CREATE TABLE tenant_formas_pagamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    forma_pagamento_id INT NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    
    -- Taxas da Operadora
    taxa_percentual DECIMAL(5,2) DEFAULT 0.00,
    taxa_fixa DECIMAL(10,2) DEFAULT 0.00,
    
    -- Parcelamento
    aceita_parcelamento TINYINT(1) DEFAULT 0,
    parcelas_minimas INT DEFAULT 1,
    parcelas_maximas INT DEFAULT 1,
    juros_parcelamento DECIMAL(5,2) DEFAULT 0.00,
    parcelas_sem_juros INT DEFAULT 1,
    
    -- Outras Configurações
    dias_compensacao INT DEFAULT 0,
    valor_minimo DECIMAL(10,2) DEFAULT 0.00,
    observacoes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (forma_pagamento_id) REFERENCES formas_pagamento(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tenant_forma (tenant_id, forma_pagamento_id)
);
```

### Configuração Padrão (Tenant 1 incluído)

A migration cria automaticamente configurações para **todos os tenants** (incluindo Tenant 1):

- **PIX/Pix**: 0% taxa, compensação imediata
- **Dinheiro**: 0% taxa
- **Cartão/Crédito/Débito**: 3.99% taxa, até 12x, 1.99% juros/mês, 3x sem juros
- **Boleto**: R$ 3.50 taxa fixa, 3 dias compensação, valor mínimo R$ 10
- **Transferência Bancária**: 0% taxa, 1 dia compensação
- **Cheque**: 0% taxa, 3 dias compensação

## 🔌 API Backend

### Base URL
```
/admin/formas-pagamento-config
```

### Endpoints

#### 1. Listar Formas de Pagamento
```http
GET /admin/formas-pagamento-config?apenas_ativas=1
```

**Response:**
```json
{
  "formas_pagamento": [
    {
      "id": 1,
      "tenant_id": 1,
      "forma_pagamento_id": 1,
      "forma_pagamento_nome": "Cartão de Crédito",
      "ativo": 1,
      "taxa_percentual": "3.99",
      "taxa_fixa": "0.00",
      "aceita_parcelamento": 1,
      "parcelas_minimas": 1,
      "parcelas_maximas": 12,
      "juros_parcelamento": "1.99",
      "parcelas_sem_juros": 3,
      "dias_compensacao": 30,
      "valor_minimo": "0.00",
      "observacoes": null
    }
  ]
}
```

#### 2. Buscar Forma de Pagamento
```http
GET /admin/formas-pagamento-config/{id}
```

**Response:**
```json
{
  "forma_pagamento": { /* mesmo formato acima */ }
}
```

#### 3. Atualizar Configuração
```http
PUT /admin/formas-pagamento-config/{id}
Content-Type: application/json

{
  "ativo": 1,
  "taxa_percentual": 3.99,
  "taxa_fixa": 0.50,
  "aceita_parcelamento": 1,
  "parcelas_minimas": 2,
  "parcelas_maximas": 12,
  "juros_parcelamento": 1.99,
  "parcelas_sem_juros": 3,
  "dias_compensacao": 30,
  "valor_minimo": 10.00,
  "observacoes": "Cartão com taxa promocional"
}
```

**Response:**
```json
{
  "message": "Configuração atualizada com sucesso"
}
```

#### 4. Calcular Taxas
```http
POST /admin/formas-pagamento-config/calcular-taxas
Content-Type: application/json

{
  "forma_pagamento_id": 1,
  "valor_bruto": 100.00
}
```

**Response:**
```json
{
  "valor_bruto": 100.00,
  "taxa_percentual_valor": 3.99,
  "taxa_fixa": 0.00,
  "total_taxas": 3.99,
  "valor_liquido": 96.01,
  "percentual_desconto_efetivo": 3.99
}
```

#### 5. Calcular Parcelas
```http
POST /admin/formas-pagamento-config/calcular-parcelas
Content-Type: application/json

{
  "forma_pagamento_id": 1,
  "valor_total": 300.00,
  "numero_parcelas": 6
}
```

**Response:**
```json
{
  "valor_total": 300.00,
  "numero_parcelas": 6,
  "parcelas_sem_juros": 3,
  "juros_mensal": 1.99,
  "valor_por_parcela": 55.10,
  "valor_total_com_juros": 330.60,
  "total_juros": 30.60,
  "descricao": "6x de R$ 55,10 (3x sem juros + 3x com juros de 1,99%)"
}
```

## 📱 Frontend

### Localização
```
/FrontendWeb/src/screens/formas-pagamento/FormasPagamentoConfigScreen.js
/FrontendWeb/app/formas-pagamento/index.js
```

### Funcionalidades

1. **Listagem de Formas de Pagamento**
   - Cards com informações resumidas
   - Badge de status (Ativo/Inativo)
   - Ícones por tipo (cartão/dinheiro)
   - Destaque visual para formas inativas

2. **Modal de Edição**
   - Switch para ativar/desativar
   - Configuração de taxas (% e fixa)
   - Seção de parcelamento (condicional)
   - Dias de compensação
   - Valor mínimo
   - Observações internas

3. **Validações Client-Side**
   - Conversão de tipos (string → float/int)
   - Valores padrão quando vazio
   - Feedback visual de erros

### Componentes Visuais

```jsx
// Card de Listagem
<TouchableOpacity style={styles.card}>
  <View style={styles.cardHeader}>
    <Feather name="credit-card" size={24} />
    <Text style={styles.cardTitle}>Cartão de Crédito</Text>
    <View style={styles.badge}>Ativo</View>
  </View>
  
  <View style={styles.cardBody}>
    <View style={styles.infoRow}>
      <Text>Taxa Operadora: 3.99%</Text>
    </View>
    <View style={styles.infoRow}>
      <Text>Parcelamento: Até 12x (3x sem juros)</Text>
    </View>
  </View>
</TouchableOpacity>
```

## 💳 Cálculo de Parcelas com Juros

### Fórmula de Juros Compostos

```php
// Parcelas sem juros
$valorSemJuros = $valorTotal / $numeroParcelas;

// Se tem parcelas com juros
if ($numeroParcelas > $parcelas_sem_juros) {
    $parcelasComJuros = $numeroParcelas - $parcelas_sem_juros;
    $taxaJuros = $juros_parcelamento / 100;
    
    // Juros compostos
    $valorFinal = $valorTotal * pow(1 + $taxaJuros, $parcelasComJuros);
    $valorParcela = $valorFinal / $numeroParcelas;
}
```

### Exemplo Real

**Valor:** R$ 300,00  
**Parcelas:** 6x  
**Sem Juros:** 3x  
**Juros:** 1.99% ao mês

```
Cálculo:
- 3 parcelas sem juros (base: R$ 300,00)
- 3 parcelas com juros 1.99%
- Valor final: R$ 300,00 × (1.0199)³ = R$ 318,32
- Valor por parcela: R$ 318,32 ÷ 6 = R$ 53,05
- Total de juros: R$ 18,32

Descrição: "6x de R$ 53,05 (3x sem juros + 3x com juros de 1,99%)"
```

## 🔒 Segurança e Validações

### Backend (PHP)

```php
// Validações no Controller
if ($taxa_percentual < 0) {
    return $response->withJson(['error' => 'Taxa percentual deve ser maior ou igual a 0'], 400);
}

if ($aceita_parcelamento && $parcelas_maximas <= 0) {
    return $response->withJson(['error' => 'Parcelas máximas deve ser maior que 0'], 400);
}

// Tenant Ownership
$config = $model->buscar($id, $tenantId);
if (!$config) {
    return $response->withJson(['error' => 'Configuração não encontrada'], 404);
}
```

### Middlewares Aplicados

- `AuthMiddleware`: Autenticação JWT
- `AdminMiddleware`: Apenas admins
- `TenantMiddleware`: Isolamento de dados por tenant

## 📊 Casos de Uso

### 1. Academia quer mudar taxa do cartão
```
1. Admin acessa "Formas de Pagamento"
2. Clica em "Cartão de Crédito"
3. Altera taxa_percentual de 3.99% para 2.50%
4. Salva
5. Novos contratos usarão a nova taxa
```

### 2. Academia quer desabilitar boleto
```
1. Admin acessa "Formas de Pagamento"
2. Clica em "Boleto Bancário"
3. Desativa o switch "Ativo"
4. Salva
5. Boleto não aparecerá mais nas opções de pagamento
```

### 3. Academia quer aumentar parcelamento
```
1. Admin acessa "Formas de Pagamento"
2. Clica em "Cartão de Crédito"
3. Altera parcelas_maximas de 12 para 18
4. Ajusta parcelas_sem_juros de 3 para 6
5. Salva
6. Clientes poderão parcelar em até 18x com 6x sem juros
```

## 🔄 Fluxo de Dados

```
┌─────────────┐
│   Frontend  │
│ (React RN)  │
└──────┬──────┘
       │ GET /formas-pagamento-config
       ▼
┌─────────────────────┐
│ TenantFormaPagamento│
│    Controller       │
└──────┬──────────────┘
       │ listar($tenantId)
       ▼
┌─────────────────────┐
│ TenantFormaPagamento│
│       Model         │
└──────┬──────────────┘
       │ LEFT JOIN formas_pagamento
       ▼
┌─────────────────────┐
│  tenant_formas_     │
│    pagamento        │
│   (MySQL Table)     │
└─────────────────────┘
```

## 🧪 Testes

### Teste Manual - Listagem

```bash
# Token admin do tenant 1
curl -X GET 'http://localhost:8080/admin/formas-pagamento-config' \
  -H 'Authorization: Bearer SEU_TOKEN_JWT'
```

### Teste Manual - Atualização

```bash
curl -X PUT 'http://localhost:8080/admin/formas-pagamento-config/1' \
  -H 'Authorization: Bearer SEU_TOKEN_JWT' \
  -H 'Content-Type: application/json' \
  -d '{
    "ativo": 1,
    "taxa_percentual": 2.99,
    "taxa_fixa": 0,
    "aceita_parcelamento": 1,
    "parcelas_maximas": 12,
    "juros_parcelamento": 1.50
  }'
```

### Teste Manual - Cálculo de Parcelas

```bash
curl -X POST 'http://localhost:8080/admin/formas-pagamento-config/calcular-parcelas' \
  -H 'Authorization: Bearer SEU_TOKEN_JWT' \
  -H 'Content-Type: application/json' \
  -d '{
    "forma_pagamento_id": 1,
    "valor_total": 500,
    "numero_parcelas": 10
  }'
```

## 📝 Observações Importantes

1. **Tenant 1 Incluído**: O SuperAdmin (tenant_id = 1) agora está incluído nas configurações porque é usado no sistema de contratos

2. **Unicidade**: Cada tenant pode ter apenas uma configuração por forma de pagamento (UNIQUE KEY tenant_id + forma_pagamento_id)

3. **Cascata**: Se um tenant ou forma de pagamento for excluído, as configurações serão removidas automaticamente

4. **Valores Padrão**: Na criação, todas as formas recebem configuração básica (pode ser personalizada depois)

5. **Juros Compostos**: O cálculo de parcelas usa juros compostos (mais realista que juros simples)

6. **Compensação**: Campo dias_compensacao indica quando o dinheiro estará disponível na conta

7. **Valor Mínimo**: Impede transações abaixo de um valor configurável (útil para boletos)

## 🚀 Melhorias Futuras

- [ ] Histórico de alterações (auditoria)
- [ ] Presets de configuração (E-commerce, Loja Física, Delivery)
- [ ] Simulador de parcelamento na listagem
- [ ] Comparativo entre formas de pagamento
- [ ] Limites de regulamentação (taxa máxima permitida)
- [ ] Alertas de configurações suspeitas
- [ ] Relatório de formas mais usadas
- [ ] Import/Export de configurações
- [ ] Cálculo de antecipação de recebíveis

## 📚 Referências

- **Migration**: `Backend/database/migrations/042_create_tenant_formas_pagamento.sql`
- **Model**: `Backend/app/Models/TenantFormaPagamento.php`
- **Controller**: `Backend/app/Controllers/TenantFormaPagamentoController.php`
- **Routes**: `Backend/routes/api.php` (linhas após /admin)
- **Frontend**: `FrontendWeb/src/screens/formas-pagamento/FormasPagamentoConfigScreen.js`
- **Menu**: `FrontendWeb/src/components/LayoutBase.js` (MENU array)

---

**Última Atualização**: 2024  
**Versão**: 1.0.0  
**Status**: ✅ Implementado e Funcional
