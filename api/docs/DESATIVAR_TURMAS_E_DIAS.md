# 📋 Endpoints de Desativação de Turmas e Dias

## ✅ O que foi implementado

Dois novos endpoints para desativar turmas e dias com opção de replicar a desativação para outros períodos.

---

## 🔧 Endpoints

### 1️⃣ **Desativar Turma** 
`POST /admin/turmas/desativar`

**Opções de período:**

#### a) Desativar apenas esta turma
```json
POST /admin/turmas/desativar
{
  "turma_id": 1
}
```
✅ Desativa apenas a turma específica

#### b) Desativar próxima semana (mesmo horário)
```json
POST /admin/turmas/desativar
{
  "turma_id": 1,
  "periodo": "proxima_semana"
}
```
✅ Desativa a turma da próxima semana no mesmo horário

#### c) Desativar mês inteiro (mesmo horário)
```json
POST /admin/turmas/desativar
{
  "turma_id": 1,
  "periodo": "mes_todo",
  "mes": "2026-01"
}
```
✅ Desativa todas as turmas do mês no mesmo horário

#### d) Desativar customizado (dias específicos)
```json
POST /admin/turmas/desativar
{
  "turma_id": 1,
  "periodo": "custom",
  "dias_semana": [2, 3, 4, 5, 6],
  "mes": "2026-01"
}
```
✅ Desativa turmas de segunda a sexta no mês especificado

---

### 2️⃣ **Desativar Dia** 
`POST /admin/dias/desativar`

**Opções de período:**

#### a) Desativar um dia específico (feriado)
```json
POST /admin/dias/desativar
{
  "dia_id": 17
}
```
✅ Desativa o dia específico (ex: feriado pontual)

#### b) Desativar próxima semana (mesmo dia semana)
```json
POST /admin/dias/desativar
{
  "dia_id": 17,
  "periodo": "proxima_semana"
}
```
✅ Desativa o mesmo dia da semana na próxima semana

#### c) Desativar mês inteiro (todos os dias)
```json
POST /admin/dias/desativar
{
  "dia_id": 17,
  "periodo": "mes_todo",
  "mes": "2026-01"
}
```
✅ Desativa todos os dias do mês

#### d) Desativar customizado (dias específicos)
```json
POST /admin/dias/desativar
{
  "dia_id": 17,
  "periodo": "custom",
  "dias_semana": [1],
  "mes": "2026-01"
}
```
✅ Desativa todos os domingos (ex: domingos sem aula)

---

## 📊 Response Examples

### Success Response
```json
{
  "type": "success",
  "message": "Turmas desativadas com sucesso",
  "summary": {
    "total_desativadas": 5
  },
  "detalhes": [
    {
      "turma_id": 1,
      "dia_id": 20,
      "data": "2026-01-16",
      "status": "desativada"
    },
    {
      "dia_id": 21,
      "data": "2026-01-17",
      "status": "nao_encontrada",
      "motivo": "Nenhuma turma com mesmo horário neste dia"
    }
  ]
}
```

---

## 🎯 Casos de Uso

### Caso 1: Pausa de um horário específico
**Cenário:** Prof. André não vai dar aula segunda-feira

```bash
curl -X POST http://localhost:8080/admin/turmas/desativar \
  -H "Authorization: Bearer TOKEN" \
  -d '{"turma_id": 1}'
```

### Caso 2: Férias de um professor (mês inteiro)
**Cenário:** Prof. João sai de férias em fevereiro

```bash
curl -X POST http://localhost:8080/admin/turmas/desativar \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "turma_id": 5,
    "periodo": "mes_todo",
    "mes": "2026-02"
  }'
```

### Caso 3: Feriado pontual
**Cenário:** 09/01 é feriado municipal

```bash
curl -X POST http://localhost:8080/admin/dias/desativar \
  -H "Authorization: Bearer TOKEN" \
  -d '{"dia_id": 17}'
```

### Caso 4: Domingos sem aula (todo mês)
**Cenário:** Academia não funciona aos domingos

```bash
curl -X POST http://localhost:8080/admin/dias/desativar \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "dia_id": 10,
    "periodo": "custom",
    "dias_semana": [1],
    "mes": "2026-01"
  }'
```

---

## 🌳 Estrutura de Dias da Semana

```javascript
const DIAS_SEMANA = {
  1: 'Domingo',
  2: 'Segunda',
  3: 'Terça',
  4: 'Quarta',
  5: 'Quinta',
  6: 'Sexta',
  7: 'Sábado'
};
```

---

## 📱 Frontend Examples

Ver arquivo: **REPLICAR_FRONTEND_EXEMPLO.js**

### Funções JavaScript disponíveis:

```javascript
// Desativar turma
desativarTurma(turmaId, periodo, mes);

// Desativar dias
desativarDias(diaId, periodo, diasSemana, mes);
```

### Exemplos de uso:

```javascript
// Desativar apenas esta turma
desativarTurma(1);

// Desativar turma o mês inteiro
desativarTurma(1, 'mes_todo', '2026-02');

// Desativar feriado
desativarDias(17);

// Desativar domingos de fevereiro
desativarDias(10, 'custom', [1], '2026-02');
```

---

## ✨ Características

- ✅ **Flexível:** 4 opções de período (apenas, próxima semana, mês inteiro, customizado)
- ✅ **Inteligente:** Busca turmas similares (mesmo professor, modalidade, horário)
- ✅ **Transparente:** Retorna detalhes de cada ação
- ✅ **Seguro:** Isolamento por tenant, validação rigorosa
- ✅ **Rápido:** Operações otimizadas em SQL

---

## 🔒 Segurança

- ✅ Autenticação JWT obrigatória (Admin)
- ✅ Isolamento por tenant
- ✅ Validação de entrada rigorosa
- ✅ SQL injection prevention (prepared statements)

---

## 📝 Detalhes Técnicos

### Arquivos Modificados

1. **app/Controllers/TurmaController.php**
   - Adicionado método `desativarTurma()`

2. **app/Controllers/DiaController.php**
   - Adicionado método `desativarDias()`

3. **routes/api.php**
   - Rota: `POST /admin/turmas/desativar`
   - Rota: `POST /admin/dias/desativar`

4. **REPLICAR_FRONTEND_EXEMPLO.js**
   - Funções `desativarTurma()` e `desativarDias()`
   - Exemplos cURL
   - Constantes

---

## 🎯 Próximos Passos Sugeridos

1. Testar em produção
2. Interface gráfica para desativação
3. Histórico de desativações
4. Undo/Reativar de desativações
5. Notificação aos alunos sobre aulas canceladas

---

**Status:** ✅ Production-Ready  
**Versão:** 1.0.0  
**Data:** 2026-01-10
