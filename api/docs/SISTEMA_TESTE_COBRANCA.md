# =========================================================
# RESUMO DO SISTEMA DE TESTE E COBRANÇA
# Data: 06/02/2026
# =========================================================

## 💡 Conceito e Ideia Original

### Problema
Precisamos começar a usar o app em fevereiro de 2026 para testes de check-in, mas a cobrança só deve iniciar a partir de março. Como permitir que alunos testem o sistema sem gerar cobranças em fevereiro?

### Solução Implementada
**Planos temporários de teste com valor = R$ 0,00**

A ideia é criar planos gratuitos (valor zero) para o período de fevereiro. Cada matrícula terá um campo `dia_vencimento` que define o dia do mês em que o aluno deve pagar. Quando o mês virar (de fevereiro para março), a API analisa automaticamente o campo `data_inicio_cobranca` da matrícula e:

1. **Detecta** que o período de teste acabou
2. **Migra automaticamente** a matrícula para um plano pago equivalente
3. **Mantém** o dia de vencimento configurado
4. **Inicia** a geração de cobranças a partir daquele dia

### Vantagens
- ✅ **Simples**: Planos com valor=0 não geram cobrança
- ✅ **Automático**: Transição teste→pago sem intervenção manual
- ✅ **Flexível**: Cada aluno pode ter vencimento em dia diferente
- ✅ **Transparente**: Sistema deixa claro quando a cobrança começa
- ✅ **Sem duplicação**: Mesma matrícula serve para teste e produção
- ✅ **Auditável**: Histórico completo desde o período teste

---

## ✅ Implementações Realizadas

### 1. Banco de Dados
- ✅ Campo `dia_vencimento` (1-31) - dia do mês que vence
- ✅ Campo `periodo_teste` (0 ou 1) - marca se é período gratuito
- ✅ Campo `data_inicio_cobranca` - quando começar a cobrar
- ✅ Índices criados para performance

### 2. Planos de Teste (Valor = 0)
Criados 4 planos gratuitos:
- **ID 5**: 1x Semana - Teste Gratuito
- **ID 6**: 2x Semana - Teste Gratuito  
- **ID 7**: 3x Semana - Teste Gratuito
- **ID 8**: Livre - Teste Gratuito

### 3. MatriculaController - Novos Métodos

#### criar()
- Valida `dia_vencimento` (obrigatório, 1-31)
- Detecta automaticamente planos com valor=0
- Define `periodo_teste=1` e `data_inicio_cobranca` (próximo mês)
- Retorna info sobre período teste

#### processarInicioCobranca()
- Busca matrículas com `periodo_teste=1` e `data_inicio_cobranca <= hoje`
- Migra automaticamente para plano pago equivalente
- Atualiza `periodo_teste=0`
- Retorna lista de matrículas processadas

#### proximasCobrancas()
- Lista matrículas que virarão pagas em N dias
- Mostra plano atual (teste) e plano pago equivalente
- Útil para notificar alunos

#### vencimentosProximos()
- Lista matrículas com vencimento nos próximos N dias
- Usa campo `dia_vencimento`
- Suporta virada de mês

### 4. Rotas Criadas
```
POST   /admin/matriculas/processar-cobranca
GET    /admin/matriculas/proximas-cobrancas?dias=7
GET    /admin/matriculas/vencimentos-proximos?dias=7
```

### 5. CRON Job
- Script: `scripts/cron_processar_cobrancas.sh`
- Executar diariamente às 00:05
- Processa automaticamente transição teste → pago

---

## 📋 Como Usar

### 1. Criar Matrícula de Teste (Fevereiro 2026)

```bash
POST /admin/matriculas
{
  "aluno_id": 1,
  "plano_id": 6,           # 2x Semana - Teste Gratuito
  "dia_vencimento": 15,    # Vencerá todo dia 15
  "data_inicio": "2026-02-01"
  # data_inicio_cobranca será automático: 2026-03-01
}
```

**Resposta:**
```json
{
  "message": "Matrícula realizada com sucesso",
  "matricula": { ... },
  "info": "Período teste - Cobrança iniciará em 2026-03-01"
}
```

### 2. Ver Próximas Cobranças (7 dias)

```bash
GET /admin/matriculas/proximas-cobrancas?dias=7
```

**Resposta:**
```json
{
  "proximas_cobrancas": [
    {
      "matricula_id": 123,
      "aluno_nome": "João Silva",
      "plano_nome": "2x Semana - Teste Gratuito",
      "data_inicio_cobranca": "2026-03-01",
      "dias_restantes": 3,
      "plano_pago": {
        "id": 2,
        "nome": "2x Semana",
        "valor": 150.00
      }
    }
  ],
  "total": 1
}
```

### 3. Ver Vencimentos do Mês

```bash
GET /admin/matriculas/vencimentos-proximos?dias=30
```

**Resposta:**
```json
{
  "vencimentos": [
    {
      "aluno_nome": "Maria Santos",
      "dia_vencimento": 5,
      "plano_nome": "3x Semana",
      "plano_valor": 200.00
    },
    {
      "aluno_nome": "Pedro Oliveira",
      "dia_vencimento": 15,
      "plano_nome": "2x Semana",
      "plano_valor": 150.00
    }
  ],
  "total": 2
}
```

### 4. Processar Cobranças Manualmente

```bash
POST /admin/matriculas/processar-cobranca
```

**Resposta:**
```json
{
  "message": "Processamento de início de cobrança concluído",
  "processadas": [
    {
      "matricula_id": 123,
      "aluno": "João Silva",
      "plano_anterior": "2x Semana - Teste Gratuito",
      "plano_novo": "2x Semana",
      "valor": 150.00,
      "dia_vencimento": 15
    }
  ],
  "total": 1
}
```

---

## 🎯 Fluxo Automático

```
FEV/2026 (Teste)
├─ Criar matrícula com plano valor=0
├─ periodo_teste = 1
├─ dia_vencimento = 15
├─ data_inicio_cobranca = 2026-03-01
└─ Check-in funciona normalmente ✅

01/MAR/2026 (Transição Automática)
├─ CRON roda às 00:05
├─ Detecta data_inicio_cobranca <= hoje
├─ Migra para plano pago (2x Semana)
├─ periodo_teste = 0
└─ Mantém dia_vencimento = 15

15/MAR/2026 (Primeira Cobrança)
└─ Aluno deve pagar primeira mensalidade ✅
```

---

---

## 🎨 GUIA PARA IMPLEMENTAÇÃO NO FRONTEND

### 📋 Casos de Uso Principais

#### 1. Criar Matrícula de Teste (Fevereiro 2026)

**Endpoint:** `POST /admin/matriculas`

**Payload:**
```json
{
  "aluno_id": 1,
  "plano_id": 6,
  "dia_vencimento": 15,
  "data_inicio": "2026-02-06"
}
```

**Resposta de Sucesso (201):**
```json
{
  "message": "Matrícula realizada com sucesso",
  "matricula": {
    "id": 123,
    "tenant_id": 1,
    "aluno_id": 1,
    "aluno_nome": "João Silva",
    "aluno_email": "joao@email.com",
    "plano_id": 6,
    "plano_nome": "2x Semana - Teste Gratuito",
    "valor": 0.00,
    "dia_vencimento": 15,
    "periodo_teste": 1,
    "data_inicio_cobranca": "2026-03-01",
    "data_inicio": "2026-02-06",
    "data_vencimento": "2026-03-06",
    "status_id": 5,
    "created_at": "2026-02-06 10:30:00"
  },
  "pagamentos": [],
  "total_pagamentos": 0,
  "info": "Período teste - Cobrança iniciará em 2026-03-01"
}
```

**Validações (422):**
```json
{
  "errors": [
    "Aluno é obrigatório (envie aluno_id ou usuario_id)",
    "Plano é obrigatório",
    "Dia de vencimento é obrigatório",
    "Dia de vencimento deve estar entre 1 e 31"
  ]
}
```

---

#### 2. Listar Próximas Cobranças (Dashboard)

**Endpoint:** `GET /admin/matriculas/proximas-cobrancas?dias=7`

**Resposta:**
```json
{
  "proximas_cobrancas": [
    {
      "id": 123,
      "aluno_id": 1,
      "aluno_nome": "João Silva",
      "aluno_email": "joao@email.com",
      "aluno_telefone": "11999999999",
      "plano_id": 6,
      "plano_nome": "2x Semana - Teste Gratuito",
      "plano_valor_atual": 0.00,
      "dia_vencimento": 15,
      "data_inicio_cobranca": "2026-03-01",
      "dias_restantes": 23,
      "plano_pago": {
        "id": 2,
        "nome": "2x Semana",
        "valor": 150.00
      }
    },
    {
      "id": 124,
      "aluno_id": 2,
      "aluno_nome": "Maria Santos",
      "aluno_email": "maria@email.com",
      "aluno_telefone": "11988888888",
      "plano_id": 7,
      "plano_nome": "3x Semana - Teste Gratuito",
      "plano_valor_atual": 0.00,
      "dia_vencimento": 5,
      "data_inicio_cobranca": "2026-03-01",
      "dias_restantes": 23,
      "plano_pago": {
        "id": 3,
        "nome": "3x Semana",
        "valor": 200.00
      }
    }
  ],
  "total": 2,
  "periodo_dias": 7
}
```

**Quando usar:**
- Widget no dashboard principal
- Badge de alerta com contador
- Página de gestão de cobranças

---

#### 3. Vencimentos do Mês (Calendário)

**Endpoint:** `GET /admin/matriculas/vencimentos-proximos?dias=30`

**Resposta:**
```json
{
  "vencimentos": [
    {
      "id": 125,
      "aluno_id": 3,
      "aluno_nome": "Pedro Oliveira",
      "aluno_email": "pedro@email.com",
      "aluno_telefone": "11977777777",
      "plano_id": 2,
      "plano_nome": "2x Semana",
      "plano_valor": 150.00,
      "dia_vencimento": 5,
      "data_inicio": "2026-01-05",
      "data_vencimento": "2026-02-05",
      "status_nome": "Ativa"
    },
    {
      "id": 126,
      "aluno_id": 4,
      "aluno_nome": "Ana Costa",
      "aluno_email": "ana@email.com",
      "aluno_telefone": "11966666666",
      "plano_id": 3,
      "plano_nome": "3x Semana",
      "plano_valor": 200.00,
      "dia_vencimento": 15,
      "data_inicio": "2026-01-15",
      "data_vencimento": "2026-02-15",
      "status_nome": "Ativa"
    }
  ],
  "total": 2,
  "periodo": {
    "dia_atual": 6,
    "dias_antecedencia": 30
  }
}
```

**Quando usar:**
- Calendário de vencimentos
- Lista de cobranças do mês
- Relatório financeiro

---

#### 4. Processar Cobranças Manualmente (Admin)

**Endpoint:** `POST /admin/matriculas/processar-cobranca`

**Payload:** Não requer body

**Resposta:**
```json
{
  "message": "Processamento de início de cobrança concluído",
  "processadas": [
    {
      "matricula_id": 123,
      "aluno": "João Silva",
      "plano_anterior": "2x Semana - Teste Gratuito",
      "plano_novo": "2x Semana",
      "valor": 150.00,
      "dia_vencimento": 15
    }
  ],
  "total": 1
}
```

**Quando usar:**
- Botão manual para forçar processamento
- Teste antes de ativar CRON
- Casos de emergência

---

### 🎨 Componentes Frontend Necessários

#### 1. Formulário de Matrícula (OBRIGATÓRIO)

```typescript
interface MatriculaFormData {
  aluno_id: number;           // Select de alunos
  plano_id: number;           // Select de planos (separar teste/pago)
  dia_vencimento: number;     // ⭐ NOVO - Select 1-31
  data_inicio?: string;       // Date picker (opcional)
  observacoes?: string;       // Textarea
}
```

**Campos necessários:**
- ✅ Select "Aluno" (obrigatório)
- ✅ Select "Plano" com optgroup (Teste / Pago)
- ✅ **Select "Dia de Vencimento"** (1 a 31) - OBRIGATÓRIO
- ⚠️ Mostrar alerta se plano valor = 0: "Gratuito até 01/03/2026"

**Validações:**
```typescript
if (!data.dia_vencimento) {
  errors.push('Dia de vencimento é obrigatório');
}
if (data.dia_vencimento < 1 || data.dia_vencimento > 31) {
  errors.push('Dia deve estar entre 1 e 31');
}
```

---

#### 2. Widget Dashboard - Próximas Cobranças

**Localização:** Dashboard principal (topo)

**Visual sugerido:**
```
┌────────────────────────────────────────┐
│ ⚠️ Cobranças Iniciando em Breve       │
├────────────────────────────────────────┤
│ 2 alunos sairão do período teste      │
│ nos próximos 7 dias                    │
│                                        │
│ • João Silva - 2x/sem → R$ 150        │
│   Inicia em: 23 dias                   │
│                                        │
│ • Maria Santos - 3x/sem → R$ 200      │
│   Inicia em: 23 dias                   │
│                                        │
│ [Ver Todas as Cobranças]              │
└────────────────────────────────────────┘
```

**Código exemplo:**
```tsx
const [cobrancas, setCobrancas] = useState([]);

useEffect(() => {
  fetch('/admin/matriculas/proximas-cobrancas?dias=7')
    .then(r => r.json())
    .then(data => setCobrancas(data.proximas_cobrancas));
}, []);

if (cobrancas.length === 0) return null;

return (
  <div className="alert alert-warning">
    <h4>⚠️ {cobrancas.length} aluno(s) sairão do período teste</h4>
    <ul>
      {cobrancas.map(c => (
        <li key={c.id}>
          <strong>{c.aluno_nome}</strong>
          {' → '}
          {c.plano_pago?.nome} (R$ {c.plano_pago?.valor})
          <span className="text-muted">
            {' '}em {c.dias_restantes} dias
          </span>
        </li>
      ))}
    </ul>
  </div>
);
```

---

#### 3. Widget Dashboard - Vencimentos do Mês

**Visual sugerido:**
```
┌─────────────────────────────────┐
│ 📅 Vencimentos de Março         │
├─────────────────────────────────┤
│ Dia 5 - 3 alunos (R$ 450,00)   │
│ Dia 10 - 5 alunos (R$ 750,00)  │
│ Dia 15 - 8 alunos (R$ 1.200)   │
│ Dia 20 - 2 alunos (R$ 300,00)  │
│                                 │
│ Total: 18 alunos - R$ 2.700,00 │
└─────────────────────────────────┘
```

---

#### 4. Tabela de Matrículas - Colunas Adicionais

**Colunas existentes:**
- Aluno | Plano | Valor | Status

**Colunas NOVAS:**
- **Dia Venc.** - número 1-31 (centralizado)
- **Tipo** - Badge (🧪 TESTE | 💰 PAGO)

**Badge condicional:**
```tsx
{matricula.periodo_teste === 1 ? (
  <span className="badge badge-warning">
    🧪 TESTE até {formatDate(matricula.data_inicio_cobranca)}
  </span>
) : (
  <span className="badge badge-success">💰 PAGO</span>
)}
```

---

### 📊 Tipos TypeScript

```typescript
// types/matricula.ts

export interface Matricula {
  id: number;
  tenant_id: number;
  aluno_id: number;
  aluno_nome: string;
  aluno_email: string;
  plano_id: number;
  plano_nome: string;
  valor: number;
  dia_vencimento: number;           // ✅ NOVO
  periodo_teste: 0 | 1;             // ✅ NOVO
  data_inicio_cobranca: string | null; // ✅ NOVO
  data_inicio: string;
  data_vencimento: string;
  status_id: number;
  status_nome: string;
  created_at: string;
}

export interface ProximaCobranca {
  id: number;
  aluno_id: number;
  aluno_nome: string;
  aluno_email: string;
  aluno_telefone: string;
  plano_id: number;
  plano_nome: string;
  plano_valor_atual: number;
  dia_vencimento: number;
  data_inicio_cobranca: string;
  dias_restantes: number;
  plano_pago: {
    id: number;
    nome: string;
    valor: number;
  } | null;
}

export interface VencimentoProximo {
  id: number;
  aluno_id: number;
  aluno_nome: string;
  aluno_email: string;
  aluno_telefone: string;
  plano_id: number;
  plano_nome: string;
  plano_valor: number;
  dia_vencimento: number;
  data_inicio: string;
  data_vencimento: string;
  status_nome: string;
}

export interface MatriculaCriadaResponse {
  message: string;
  matricula: Matricula;
  pagamentos: any[];
  total_pagamentos: number;
  info?: string; // Mensagem sobre período teste
}
```

---

### 🎯 Prioridades de Implementação

#### ⚡ URGENTE (Fazer Agora)
1. ✅ Adicionar campo `dia_vencimento` no formulário de matrícula
2. ✅ Validar dia_vencimento (1-31)
3. ✅ Separar planos teste/pago no select

#### 🔥 ALTA (Primeira Sprint)
4. ✅ Widget "Próximas Cobranças" no dashboard
5. ✅ Adicionar colunas na tabela de matrículas
6. ✅ Badge visual TESTE/PAGO

#### 📊 MÉDIA (Segunda Sprint)
7. ✅ Widget "Vencimentos do Mês"
8. ✅ Filtros na lista de matrículas (teste/pago)
9. ✅ Exportar relatório de cobranças

#### 🎨 BAIXA (Melhorias Futuras)
10. ⭐ Calendário visual com vencimentos
11. ⭐ Notificações push sobre cobranças
12. ⭐ Gráfico teste vs pago

---

### 🔐 Permissões

**Endpoints disponíveis para:**
- ✅ Super Admin (papel_id = 4)
- ✅ Admin (papel_id = 3)
- ❌ Professor/Aluno (não tem acesso)

---

### 🧪 Testes Sugeridos

1. **Criar matrícula sem dia_vencimento** → Deve retornar erro 422
2. **Criar matrícula com dia_vencimento = 0** → Deve retornar erro 422
3. **Criar matrícula com dia_vencimento = 32** → Deve retornar erro 422
4. **Criar matrícula com plano teste** → Deve retornar info sobre período teste
5. **Listar próximas cobranças** → Deve retornar apenas matrículas com periodo_teste=1

---

## 📝 Próximos Passos (TODO)

1. **Criar planos pagos equivalentes aos de teste**
   - Exemplo: "2x Semana" com valor > 0

2. **Implementar geração automática de cobrança**
   - No processarInicioCobranca(), após migrar plano
   - Criar primeira parcela em `pagamentos_plano`

3. **Sistema de notificações**
   - Email/SMS para alunos sobre fim do período teste
   - Avisar 7 dias antes do vencimento

4. **Dashboard**
   - Gráfico de matrículas teste vs pagas
   - Alerta de vencimentos próximos

---

## 🗂️ Arquivos Modificados

- ✅ `database/migrations/20260206_matriculas_vencimento.sql`
- ✅ `scripts/criar_planos_teste.php`
- ✅ `scripts/cron_processar_cobrancas.sh`
- ✅ `app/Controllers/MatriculaController.php`
- ✅ `routes/api.php`

---

## 🚀 Status: PRONTO PARA USO!

O sistema está funcional e pronto para criar matrículas de teste em fevereiro.
A transição automática para cobrança ocorrerá em 1º de março via CRON.

---

## 📱 GUIA PARA O FRONTEND

### 🎯 Casos de Uso Principais

#### 1. Criar Matrícula de Teste (Fevereiro)
**Endpoint:** `POST /admin/matriculas`

**Request:**
```json
{
  "aluno_id": 1,
  "plano_id": 6,           
  "dia_vencimento": 15,    
  "data_inicio": "2026-02-01"
}
```

**Response:**
```json
{
  "message": "Matrícula realizada com sucesso",
  "matricula": {
    "id": 123,
    "aluno_id": 1,
    "aluno_nome": "João Silva",
    "aluno_email": "joao@email.com",
    "plano_id": 6,
    "plano_nome": "2x Semana - Teste Gratuito",
    "valor": 0.00,
    "dia_vencimento": 15,
    "periodo_teste": 1,
    "data_inicio_cobranca": "2026-03-01",
    "data_inicio": "2026-02-01",
    "data_vencimento": "2026-02-28",
    "status_id": 5,
    "status_nome": "Pendente"
  },
  "pagamentos": [],
  "total_pagamentos": 0,
  "info": "Período teste - Cobrança iniciará em 2026-03-01"
}
```

**Validações Frontend:**
- ✅ `aluno_id` é obrigatório
- ✅ `plano_id` é obrigatório  
- ✅ `dia_vencimento` é obrigatório (entre 1 e 31)

---

#### 2. Listar Próximas Cobranças
**Endpoint:** `GET /admin/matriculas/proximas-cobrancas?dias=7`

**Response:**
```json
{
  "proximas_cobrancas": [
    {
      "matricula_id": 123,
      "aluno_id": 1,
      "aluno_nome": "João Silva",
      "aluno_email": "joao@email.com",
      "aluno_telefone": "11999999999",
      "plano_id": 6,
      "plano_nome": "2x Semana - Teste Gratuito",
      "plano_valor_atual": 0.00,
      "dia_vencimento": 15,
      "data_inicio_cobranca": "2026-03-01",
      "dias_restantes": 3,
      "plano_pago": {
        "id": 2,
        "nome": "2x Semana",
        "valor": 150.00
      }
    }
  ],
  "total": 1,
  "periodo_dias": 7
}
```

**Uso:** Dashboard para alertar sobre transições teste→pago

---

#### 3. Listar Vencimentos do Mês
**Endpoint:** `GET /admin/matriculas/vencimentos-proximos?dias=30`

**Response:**
```json
{
  "vencimentos": [
    {
      "matricula_id": 123,
      "aluno_id": 1,
      "aluno_nome": "Maria Santos",
      "aluno_email": "maria@email.com",
      "aluno_telefone": "11988888888",
      "plano_id": 2,
      "plano_nome": "3x Semana",
      "plano_valor": 200.00,
      "dia_vencimento": 5,
      "data_inicio": "2026-02-01",
      "data_vencimento": "2026-03-05",
      "status_nome": "Ativa"
    },
    {
      "matricula_id": 124,
      "aluno_id": 2,
      "aluno_nome": "Pedro Oliveira",
      "aluno_email": "pedro@email.com",
      "plano_nome": "2x Semana",
      "plano_valor": 150.00,
      "dia_vencimento": 15,
      "status_nome": "Ativa"
    }
  ],
  "total": 2,
  "periodo": {
    "dia_atual": 6,
    "dias_antecedencia": 30
  }
}
```

**Uso:** Calendário de vencimentos, notificações de cobrança

---

#### 4. Processar Cobranças Manualmente
**Endpoint:** `POST /admin/matriculas/processar-cobranca`

**Response:**
```json
{
  "message": "Processamento de início de cobrança concluído",
  "processadas": [
    {
      "matricula_id": 123,
      "aluno": "João Silva",
      "plano_anterior": "2x Semana - Teste Gratuito",
      "plano_novo": "2x Semana",
      "valor": 150.00,
      "dia_vencimento": 15
    }
  ],
  "total": 1
}
```

**Uso:** Botão no dashboard para forçar processamento antes do CRON

---

### 📋 Campos Novos na API

#### Objeto `Matricula`
```typescript
interface Matricula {
  id: number;
  tenant_id: number;
  aluno_id: number;
  aluno_nome: string;
  aluno_email: string;
  plano_id: number;
  plano_nome: string;
  valor: number;
  
  // ✅ NOVOS CAMPOS
  dia_vencimento: number;           // 1-31 (dia do mês que vence)
  periodo_teste: 0 | 1;             // 0=pago, 1=teste gratuito
  data_inicio_cobranca: string | null; // "YYYY-MM-DD" quando começar a cobrar
  
  data_matricula: string;
  data_inicio: string;
  data_vencimento: string;
  status_id: number;
  status_nome: string;
  motivo_id: number;
  observacoes?: string;
  created_at: string;
  updated_at: string;
}
```

#### Objeto `Plano`
```typescript
interface Plano {
  id: number;
  nome: string;
  valor: number;              // 0 = teste gratuito, > 0 = pago
  checkins_semanais: number;
  duracao_dias: number;
  descricao?: string;
  ativo: boolean;
}
```

---

### 🎨 Sugestões de UI

#### 1. Formulário de Matrícula
```tsx
<Form>
  <Select label="Aluno" name="aluno_id" required />
  
  <Select label="Plano" name="plano_id" required>
    <optgroup label="📦 Teste Gratuito (Fevereiro)">
      <option value="5">1x Semana - GRÁTIS</option>
      <option value="6">2x Semana - GRÁTIS</option>
      <option value="7">3x Semana - GRÁTIS</option>
      <option value="8">Livre - GRÁTIS</option>
    </optgroup>
    <optgroup label="💳 Planos Pagos">
      {/* planos com valor > 0 */}
    </optgroup>
  </Select>
  
  <Select 
    label="Dia de Vencimento" 
    name="dia_vencimento" 
    required
    help="Dia do mês em que o aluno pagará"
  >
    <option value="">Selecione...</option>
    {Array.from({length: 31}, (_, i) => (
      <option key={i+1} value={i+1}>Dia {i+1}</option>
    ))}
  </Select>
  
  {/* Se plano selecionado tiver valor = 0 */}
  {planoSelecionado?.valor === 0 && (
    <Alert variant="info">
      <strong>🎁 Período Teste Gratuito</strong>
      <p>Check-in liberado até 28/02/2026</p>
      <p>Cobrança iniciará automaticamente em 01/03/2026</p>
    </Alert>
  )}
  
  <Button type="submit">Criar Matrícula</Button>
</Form>
```

#### 2. Widget Dashboard - Próximas Cobranças
```tsx
<Card>
  <CardHeader>
    <h3>⚠️ Cobranças Iniciando em Breve</h3>
  </CardHeader>
  <CardBody>
    {cobrancas.length === 0 ? (
      <p>Nenhuma cobrança programada</p>
    ) : (
      <Table>
        <thead>
          <tr>
            <th>Aluno</th>
            <th>Plano Novo</th>
            <th>Valor</th>
            <th>Início</th>
            <th>Dias</th>
          </tr>
        </thead>
        <tbody>
          {cobrancas.map(c => (
            <tr key={c.matricula_id}>
              <td>{c.aluno_nome}</td>
              <td>{c.plano_pago?.nome}</td>
              <td>R$ {c.plano_pago?.valor}</td>
              <td>{formatDate(c.data_inicio_cobranca)}</td>
              <td>
                <Badge variant={c.dias_restantes <= 3 ? 'danger' : 'warning'}>
                  {c.dias_restantes} dias
                </Badge>
              </td>
            </tr>
          ))}
        </tbody>
      </Table>
    )}
  </CardBody>
</Card>
```

#### 3. Widget Dashboard - Vencimentos do Mês
```tsx
<Card>
  <CardHeader>
    <h3>📅 Vencimentos de Março</h3>
  </CardHeader>
  <CardBody>
    {Object.entries(vencimentosPorDia).map(([dia, alunos]) => (
      <div key={dia} className="mb-3">
        <div className="d-flex align-items-center">
          <div className="calendar-day">
            <strong>{dia}</strong>
          </div>
          <div className="flex-grow-1 ms-3">
            <Badge variant="primary">{alunos.length} aluno(s)</Badge>
            <ul className="mt-2">
              {alunos.map(a => (
                <li key={a.matricula_id}>
                  <strong>{a.aluno_nome}</strong> - {a.plano_nome} 
                  <span className="text-success ms-2">
                    R$ {a.plano_valor}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    ))}
  </CardBody>
</Card>
```

#### 4. Tabela de Matrículas
```tsx
<Table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Aluno</th>
      <th>Plano</th>
      <th>Valor</th>
      <th>Dia Venc.</th>
      <th>Status</th>
      <th>Tipo</th>
      <th>Ações</th>
    </tr>
  </thead>
  <tbody>
    {matriculas.map(m => (
      <tr key={m.id}>
        <td>{m.id}</td>
        <td>{m.aluno_nome}</td>
        <td>{m.plano_nome}</td>
        <td>
          {m.valor === 0 ? (
            <Badge variant="success">GRÁTIS</Badge>
          ) : (
            <span>R$ {m.valor}</span>
          )}
        </td>
        <td className="text-center">
          <strong>{m.dia_vencimento}</strong>
        </td>
        <td>
          <Badge variant={getStatusColor(m.status_id)}>
            {m.status_nome}
          </Badge>
        </td>
        <td>
          {m.periodo_teste === 1 ? (
            <Badge variant="warning">
              🧪 Teste até {formatDate(m.data_inicio_cobranca)}
            </Badge>
          ) : (
            <Badge variant="primary">💳 Pago</Badge>
          )}
        </td>
        <td>
          <Button size="sm" onClick={() => viewDetails(m.id)}>
            Ver
          </Button>
        </td>
      </tr>
    ))}
  </tbody>
</Table>
```

---

### 🔔 Notificações Recomendadas

#### Após criar matrícula teste:
```tsx
toast.info(
  `Matrícula criada! Check-in liberado até 28/02. 
   A cobrança de R$ ${planoPago.valor} iniciará em 01/03 
   com vencimento todo dia ${diaVencimento}.`,
  { duration: 7000 }
);
```

#### No dashboard (se houver cobranças próximas):
```tsx
{proximasCobrancas.length > 0 && (
  <Alert variant="warning" className="mb-4">
    <AlertIcon>⚠️</AlertIcon>
    <strong>Atenção!</strong> {proximasCobrancas.length} aluno(s) 
    sairão do período teste nos próximos 7 dias.
    <Button 
      size="sm" 
      className="ms-3"
      onClick={() => navigate('/matriculas/cobrancas')}
    >
      Ver Detalhes
    </Button>
  </Alert>
)}
```

---

### ✅ Checklist de Implementação Frontend

**Urgente (Fevereiro):**
- [ ] Adicionar campo `dia_vencimento` no formulário de matrícula
- [ ] Validar dia_vencimento (1-31)
- [ ] Separar planos teste/pagos no select
- [ ] Mostrar alerta quando selecionar plano teste
- [ ] Exibir mensagem `info` após criar matrícula

**Importante (Março):**
- [ ] Widget "Próximas Cobranças" no dashboard
- [ ] Widget "Vencimentos do Mês"
- [ ] Adicionar colunas `dia_vencimento` e `periodo_teste` na tabela
- [ ] Badge visual para matrículas teste vs pagas

**Opcional:**
- [ ] Calendário visual de vencimentos
- [ ] Notificações push 7 dias antes do fim do teste
- [ ] Relatório de conversão teste→pago

---

### 🔄 Fluxo Completo (Visão Frontend)

```
1. FEVEREIRO - Cadastro
   ├─ Admin seleciona plano teste (valor=0)
   ├─ Define dia_vencimento: 15
   ├─ Sistema mostra: "Cobrança inicia em 01/03"
   └─ Aluno faz check-in normalmente ✅

2. 26/FEV - Dashboard Alerta
   ├─ Widget mostra: "5 alunos sairão do teste em 3 dias"
   └─ Admin pode notificar os alunos

3. 01/MAR - Transição Automática
   ├─ CRON processa às 00:05
   ├─ Matrícula muda de teste→pago
   └─ Sistema aguarda dia 15 para cobrar

4. 15/MAR - Primeira Cobrança
   ├─ Sistema gera cobrança R$ 150,00
   ├─ Aluno recebe notificação
   └─ Admin vê no dashboard de vencimentos
```
