# =========================================================
# CONTROLE DE ACESSO POR VENCIMENTO - FRONTEND GUIDE
# Data: 06/02/2026
# =========================================================

## 🎯 Problema Resolvido

**Cenário:**
- Aluno faz matrícula dia **05/02** (hoje)
- Escolhe dia de vencimento: **01** (sempre dia 1º)
- Plano: 30 dias de duração

**Problema Anterior:**
- Sistema bloquearia check-in dia **01/03** (24 dias depois)
- Aluno perderia 6 dias pagos! ❌

**Solução Implementada:**
- Check-in bloqueado apenas após **07/03** (05/02 + 30 dias) ✅
- Cobrança gerada dia **01/03** (referência para financeiro)
- Aluno tem 30 dias completos de acesso

---

## 📋 Novo Campo no Banco de Dados

### `proxima_data_vencimento`
**Função:** Controla quando o acesso será BLOQUEADO (não a cobrança)

**Cálculo:**
```
proxima_data_vencimento = data_inicio + duracao_dias_plano
```

**Exemplos:**
```
Matrícula: 05/02/2026
Plano: 30 dias
proxima_data_vencimento: 07/03/2026

Matrícula: 15/02/2026
Plano: 30 dias
proxima_data_vencimento: 17/03/2026
```

---

## 🔄 Fluxo Completo

### 1️⃣ Criar Matrícula

**Request:**
```json
POST /admin/matriculas
{
  "aluno_id": 1,
  "plano_id": 6,
  "dia_vencimento": 1,
  "data_inicio": "2026-02-05"
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
    "plano_id": 6,
    "plano_nome": "2x Semana - Teste Gratuito",
    "valor": 0.00,
    "data_inicio": "2026-02-05",
    "data_vencimento": "2026-03-07",
    "dia_vencimento": 1,
    "periodo_teste": 1,
    "data_inicio_cobranca": "2026-03-01",
    "proxima_data_vencimento": "2026-03-07"
  },
  "info": "Período teste - Cobrança iniciará em 2026-03-01. Acesso garantido até 07/03/2026"
}
```

---

### 2️⃣ Check-in do Aluno

**Verificação no Backend:**
```php
// Hoje < proxima_data_vencimento → Libera ✅
// Hoje >= proxima_data_vencimento → Bloqueia ❌
```

**Exemplo Timeline:**
```
05/02 - Matrícula criada
06/02 - Check-in OK ✅
...
01/03 - Cobrança gerada (financeiro)
01/03 - Check-in OK ✅ (ainda tem acesso!)
...
06/03 - Check-in OK ✅
07/03 - Check-in BLOQUEADO ❌ (venceu!)
```

---

### 3️⃣ Pagamento e Renovação

**Quando aluno paga:**
```
proxima_data_vencimento_antiga = 07/03/2026
proxima_data_vencimento_nova = 07/03/2026 + 30 dias = 06/04/2026
```

**Regra Importante:**
- Renovação sempre soma a partir do vencimento ANTERIOR
- Mesmo se pagar atrasado, não "perde dias"

**Exemplo:**
```
Vencimento: 07/03/2026
Pagamento: 10/03/2026 (3 dias atrasado)
Nova Data: 06/04/2026 (não 09/04)
```

---

## 🎨 Alterações no Frontend

### 1. Formulário de Matrícula - Sem Mudanças! ✅

O campo `dia_vencimento` já existe, nada precisa mudar:

```tsx
<Select name="dia_vencimento" required>
  <option value="">Selecione o dia...</option>
  {Array.from({length: 31}, (_, i) => (
    <option key={i+1} value={i+1}>Dia {i+1}</option>
  ))}
</Select>
```

---

### 2. Resposta da API - Novo Campo

**Atualizar interface TypeScript:**

```typescript
interface Matricula {
  id: number;
  aluno_id: number;
  aluno_nome: string;
  plano_id: number;
  plano_nome: string;
  valor: number;
  data_inicio: string;
  data_vencimento: string;
  
  // Campos de controle
  dia_vencimento: number;              // Dia da cobrança (1-31)
  periodo_teste: 0 | 1;                // 0=pago, 1=teste
  data_inicio_cobranca: string | null; // Quando começa cobrar
  proxima_data_vencimento: string;     // ✅ NOVO - Quando bloqueia acesso
  
  status_id: number;
  status_nome: string;
  created_at: string;
}
```

---

### 3. Exibir Data de Vencimento REAL

**Tabela de Matrículas:**

```tsx
<Table>
  <thead>
    <tr>
      <th>Aluno</th>
      <th>Plano</th>
      <th>Dia Cobrança</th>
      <th>Acesso Até</th>  {/* ✅ NOVO */}
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    {matriculas.map(m => (
      <tr key={m.id}>
        <td>{m.aluno_nome}</td>
        <td>{m.plano_nome}</td>
        <td className="text-center">
          <Badge>Dia {m.dia_vencimento}</Badge>
        </td>
        <td>
          {/* ✅ MOSTRAR DATA REAL DE BLOQUEIO */}
          <strong>{formatDate(m.proxima_data_vencimento)}</strong>
          {isProximoVencer(m.proxima_data_vencimento) && (
            <Badge variant="warning" className="ms-2">
              Vence em breve!
            </Badge>
          )}
        </td>
        <td>
          <Badge variant={getStatusColor(m.status_id)}>
            {m.status_nome}
          </Badge>
        </td>
      </tr>
    ))}
  </tbody>
</Table>
```

**Helper function:**

```typescript
const isProximoVencer = (dataVencimento: string) => {
  const hoje = new Date();
  const vencimento = new Date(dataVencimento);
  const diffDias = Math.ceil((vencimento.getTime() - hoje.getTime()) / (1000 * 60 * 60 * 24));
  return diffDias >= 0 && diffDias <= 3;
};
```

---

### 4. Card de Detalhes da Matrícula

```tsx
<Card>
  <CardHeader>
    <h3>Matrícula #{matricula.id}</h3>
  </CardHeader>
  <CardBody>
    <Row>
      <Col md={6}>
        <InfoItem label="Aluno" value={matricula.aluno_nome} />
        <InfoItem label="Plano" value={matricula.plano_nome} />
        <InfoItem label="Valor" value={formatarValor(matricula.valor)} />
      </Col>
      <Col md={6}>
        <InfoItem 
          label="Dia de Cobrança" 
          value={`Todo dia ${matricula.dia_vencimento}`}
        />
        <InfoItem 
          label="Acesso Válido Até" 
          value={formatDate(matricula.proxima_data_vencimento)}
          highlight
        />
        {matricula.periodo_teste === 1 && (
          <Alert variant="info" className="mt-3">
            🎁 <strong>Período Teste</strong><br />
            Cobrança inicia em {formatDate(matricula.data_inicio_cobranca)}
          </Alert>
        )}
      </Col>
    </Row>
  </CardBody>
</Card>
```

---

### 5. Alerta de Vencimento Próximo

**Widget no Dashboard:**

```tsx
const MatriculasVencendoWidget = () => {
  const [matriculas, setMatriculas] = useState([]);

  useEffect(() => {
    fetch('/admin/matriculas?status=ativa')
      .then(r => r.json())
      .then(data => {
        // Filtrar matrículas vencendo em 3 dias
        const vencendo = data.matriculas.filter(m => {
          const dias = calcularDiasRestantes(m.proxima_data_vencimento);
          return dias >= 0 && dias <= 3;
        });
        setMatriculas(vencendo);
      });
  }, []);

  if (matriculas.length === 0) return null;

  return (
    <Alert variant="warning">
      <AlertIcon>⚠️</AlertIcon>
      <strong>Atenção!</strong> {matriculas.length} matrícula(s) 
      vencendo nos próximos 3 dias.
      <ul className="mt-2">
        {matriculas.map(m => (
          <li key={m.id}>
            <strong>{m.aluno_nome}</strong> - 
            Vence em {formatDate(m.proxima_data_vencimento)}
          </li>
        ))}
      </ul>
    </Alert>
  );
};
```

---

### 6. Tela de Check-in (Mobile/App)

**Validação no App:**

```typescript
const verificarAcessoAluno = async (alunoId: number) => {
  const response = await fetch(`/aluno/verificar-acesso/${alunoId}`);
  const data = await response.json();
  
  if (!data.acesso_liberado) {
    // ❌ Bloqueado
    showError(
      `Acesso bloqueado!\n` +
      `Sua matrícula venceu em ${formatDate(data.vencimento)}.\n` +
      `Entre em contato com a recepção.`
    );
    return false;
  }
  
  if (data.dias_restantes <= 3) {
    // ⚠️ Aviso
    showWarning(
      `Atenção! Sua matrícula vence em ${data.dias_restantes} dia(s).\n` +
      `Renove para continuar acessando.`
    );
  }
  
  return true;
};
```

---

### 7. Tela de Financeiro

**Mostrar Diferença entre Cobrança e Bloqueio:**

```tsx
<Table>
  <thead>
    <tr>
      <th>Aluno</th>
      <th>Dia Cobrança</th>
      <th>Próxima Cobrança</th>
      <th>Acesso Até</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    {cobrancas.map(c => (
      <tr key={c.id}>
        <td>{c.aluno_nome}</td>
        <td>Dia {c.dia_vencimento}</td>
        <td>{calcularProximaCobranca(c.dia_vencimento)}</td>
        <td>
          <strong>{formatDate(c.proxima_data_vencimento)}</strong>
          {c.proxima_data_vencimento !== calcularProximaCobranca(c.dia_vencimento) && (
            <InfoIcon 
              tooltip="A cobrança é gerada no dia escolhido, mas o acesso só é bloqueado após a duração completa do plano"
            />
          )}
        </td>
        <td>{c.status_pagamento}</td>
      </tr>
    ))}
  </tbody>
</Table>
```

---

## 📊 Exemplos Práticos

### Exemplo 1: Matrícula dia 5, vence dia 1

```
📅 05/02/2026
├─ Matrícula criada
├─ dia_vencimento: 1
├─ proxima_data_vencimento: 07/03/2026
└─ Duração: 30 dias completos ✅

💰 01/03/2026
├─ Sistema gera cobrança (dia escolhido)
└─ Check-in ainda funciona! ✅

🔒 07/03/2026
└─ Acesso bloqueado (30 dias completos)
```

### Exemplo 2: Pagamento atrasado

```
📅 05/02/2026 - Matrícula
   proxima_data_vencimento: 07/03/2026

💰 01/03/2026 - Cobrança gerada
   Status: Aguardando pagamento

⏰ 08/03/2026 - Vencido (1 dia)
   Check-in bloqueado ❌

💳 10/03/2026 - Aluno paga
   proxima_data_vencimento: 09/04/2026
   (07/03 + 30 dias, não 10/03 + 30)
   ✅ Não perde dias!
```

---

## ✅ Checklist Frontend

### Obrigatório:
- [ ] Atualizar interface TypeScript com campo `proxima_data_vencimento`
- [ ] Mostrar "Acesso Até" na tabela de matrículas
- [ ] Exibir data de bloqueio no card de detalhes
- [ ] Implementar alerta de vencimento próximo (3 dias)

### Recomendado:
- [ ] Widget dashboard com matrículas vencendo
- [ ] Badge visual quando falta menos de 3 dias
- [ ] Tooltip explicando diferença entre cobrança e bloqueio
- [ ] Validação no app mobile antes do check-in

### Opcional:
- [ ] Gráfico de vencimentos do mês
- [ ] Notificação push 3 dias antes
- [ ] Email automático de aviso

---

## 🔍 Endpoints Afetados

### Sem Mudanças (já retorna o campo):
- ✅ `POST /admin/matriculas` - Criar matrícula
- ✅ `GET /admin/matriculas` - Listar matrículas
- ✅ `GET /admin/matriculas/{id}` - Buscar matrícula

### Novos Campos na Resposta:
```json
{
  "dia_vencimento": 1,
  "proxima_data_vencimento": "2026-03-07"
}
```

---

## 💡 Resumo para o Time

**O que mudou:**
- API agora calcula automaticamente `proxima_data_vencimento`
- Este campo controla o BLOQUEIO de acesso (não a cobrança)

**O que o frontend precisa fazer:**
1. Adicionar campo `proxima_data_vencimento` nas interfaces
2. Mostrar essa data como "Acesso Até" nas telas
3. Exibir alertas quando faltar 3 dias ou menos

**O que NÃO precisa mudar:**
- Formulário de matrícula (dia_vencimento já existe)
- Lógica de cálculo (backend faz automaticamente)
- Estrutura de cobrança (dia_vencimento continua valendo)

---

## 🚀 Deploy

1. ✅ Migration já aplicada no banco
2. ✅ Backend atualizado (MatriculaController)
3. ⏳ Frontend precisa atualizar interfaces e telas

**Compatibilidade:** Retrocompatível, matrículas antigas funcionam normalmente
