# Documentação: Tolerância de Check-in em Turmas

## Overview
O sistema permite configurar **tolerância de check-in** através da tabela `horarios`, definindo a janela de tempo em que os alunos podem realizar check-in para aulas de um determinado dia.

## Campos de Tolerância

### Tabela `horarios`
```sql
- tolerancia_antes_minutos INT (default: 480) - Minutos antes do início que o check-in fica disponível
- tolerancia_minutos INT (default: 10) - Minutos após o início que o check-in fica disponível
```

**Padrão:** 8 horas antes até 10 minutos após o início da aula
- Exemplo: Aula às 12:15 → Check-in disponível de 04:15 a 12:25

## Endpoints

### 1. POST `/admin/turmas` - Criar Turma
Opcionalmente, pode incluir parâmetros de tolerância:

```json
{
  "nome": "Natação - 12:15",
  "professor_id": 1,
  "modalidade_id": 4,
  "dia_id": 18,
  "horario_inicio": "12:15:00",
  "horario_fim": "13:15:00",
  "limite_alunos": 20,
  "tolerancia_antes_minutos": 480,
  "tolerancia_minutos": 10
}
```

**Nota:** Se `tolerancia_antes_minutos` ou `tolerancia_minutos` forem fornecidos, o sistema atualiza automaticamente esses valores na tabela `horarios` para o dia associado. Isso afetará **todas as turmas daquele dia**.

**Resposta (201):**
```json
{
  "type": "success",
  "message": "Turma criada com sucesso",
  "turma": {
    "id": 650,
    "nome": "Natação - 12:15",
    "professor_nome": "Carlos Silva",
    "modalidade_nome": "Natação",
    "horario_inicio": "12:15:00",
    "horario_fim": "13:15:00",
    "limite_alunos": 20,
    "ativo": true
  }
}
```

### 2. PUT `/admin/turmas/{id}` - Atualizar Turma
Permite atualizar a tolerância de check-in para um dia específico:

```json
{
  "tolerancia_antes_minutos": 360,
  "tolerancia_minutos": 15
}
```

**Nota:** Ao atualizar a tolerância de uma turma, o sistema atualiza automaticamente os valores de tolerância na tabela `horarios` para o dia associado. Isso afetará todas as turmas daquele dia.

**Resposta (200):**
```json
{
  "type": "success",
  "message": "Turma atualizada com sucesso",
  "turma": {
    "id": 650,
    "nome": "Natação - 12:15"
  }
}
```

### 3. GET `/mobile/horarios-disponiveis?data=2026-01-13` - Listar Horários Disponíveis
**RETORNA TOLERÂNCIA:** A resposta inclui os valores de tolerância da tabela `horarios`:

```json
{
  "success": true,
  "data": {
    "dia": {
      "id": 21,
      "data": "2026-01-13",
      "ativo": true
    },
    "turmas": [
      {
        "id": 650,
        "nome": "Natação - 12:15 - Carlos Silva",
        "professor": {
          "id": 1,
          "nome": "Carlos Silva"
        },
        "modalidade": {
          "id": 4,
          "nome": "Natação",
          "icone": "swim",
          "cor": "#3b82f6"
        },
        "horario": {
          "inicio": "12:15:00",
          "fim": "13:15:00",
          "tolerancia_antes_minutos": 480,
          "tolerancia_minutos": 10
        },
        "limite_alunos": 5,
        "alunos_inscritos": 1,
        "vagas_disponiveis": 4,
        "ativo": true
      }
    ],
    "total": 4
  }
}
```

### 4. POST `/mobile/registrar-checkin` - Registrar Check-in
**VALIDAÇÃO:** Usa automaticamente a tolerância configurada em `horarios` para validar a janela:

```json
{
  "turma_id": 650,
  "data": "2026-01-13"
}
```

**Resposta de Sucesso (201):**
```json
{
  "success": true,
  "message": "Check-in realizado com sucesso!",
  "data": {
    "checkin_id": 123,
    "turma": {
      "id": 650,
      "nome": "Natação - 12:15",
      "professor": "Carlos Silva",
      "modalidade": "Natação"
    }
  }
}
```

**Resposta de Erro (400) - Fora da Janela:**
```json
{
  "success": false,
  "error": "Check-in só pode ser feito a partir de 8h antes do início da aula"
}
```

ou

```json
{
  "success": false,
  "error": "Check-in não permitido. Prazo limite: 10 minutos após o início"
}
```

## Como Configurar Tolerância

A tolerância é configurada através da tabela `horarios` que está associada a cada dia.

**Exemplo SQL para alterar a tolerância de um dia:**
```sql
UPDATE horarios 
SET tolerancia_antes_minutos = 360,  -- 6 horas antes
    tolerancia_minutos = 15           -- 15 minutos depois
WHERE dia_id = 18 AND ativo = 1;
```

## Validação de Tolerância

A validação ocorre em 2 níveis:

### 1. Model Layer (`Horario::podeRealizarCheckinComDados()`)
- Valida a janela de tempo usando os dados da turma + tolerância de `horarios`
- Usa timezone: `America/Sao_Paulo`
- Retorna motivo descritivo se bloqueado

### 2. Controller Layer (`MobileController::registrarCheckin()`)
- Busca o horário associado ao dia da turma
- Valida antes de criar o check-in
- Retorna erro 400 se fora da janela
- Registra logs detalhados de validação

## Exemplos de Uso no Frontend

### Calcular Janela de Check-in
```typescript
interface Turma {
  id: number;
  horario: { 
    inicio: string; 
    tolerancia_antes_minutos: number;
    tolerancia_minutos: number;
  };
}

function calcularJanelaCheckin(turma: Turma, dataTurma: Date) {
  const [h, m] = turma.horario.inicio.split(':');
  const inicioAula = new Date(dataTurma);
  inicioAula.setHours(parseInt(h), parseInt(m), 0);
  
  const abreCheckin = new Date(inicioAula);
  abreCheckin.setMinutes(abreCheckin.getMinutes() - turma.horario.tolerancia_antes_minutos);
  
  const fechaCheckin = new Date(inicioAula);
  fechaCheckin.setMinutes(fechaCheckin.getMinutes() + turma.horario.tolerancia_minutos);
  
  return { abreCheckin, fechaCheckin, inicioAula };
}
```

### Determinar Status do Check-in
```typescript
function statusCheckin(turma: Turma, dataTurma: Date) {
  const agora = new Date();
  const { abreCheckin, fechaCheckin } = calcularJanelaCheckin(turma, dataTurma);
  
  if (agora < abreCheckin) {
    const diff = abreCheckin.getTime() - agora.getTime();
    const horas = Math.floor(diff / 3600000);
    const mins = Math.floor((diff % 3600000) / 60000);
    return {
      status: 'nao_disponivel',
      mensagem: `Abre em ${horas}h${mins}min`
    };
  }
  
  if (agora > fechaCheckin) {
    return {
      status: 'fechado',
      mensagem: 'Check-in encerrado'
    };
  }
  
  const diff = fechaCheckin.getTime() - agora.getTime();
  const mins = Math.floor(diff / 60000);
  return {
    status: 'aberto',
    mensagem: `Aberto - Fecha em ${mins}min`
  };
}
```

## Valores de Tolerância Padrão

Todos os registros na tabela `horarios` têm padrão de:
- **tolerancia_antes_minutos**: 480 (8 horas)
- **tolerancia_minutos**: 10 (10 minutos)

Estes valores podem ser alterados conforme necessário na tabela `horarios`.

## Cálculo da Janela

Para uma aula às **12:15** com tolerância padrão:

- **Abre**: 12:15 - 480 min = **04:15**
- **Fecha**: 12:15 + 10 min = **12:25**
- **Janela de Check-in**: 04:15 até 12:25 (8 horas e 10 minutos)

---

**Última Atualização**: 12 de janeiro de 2026
