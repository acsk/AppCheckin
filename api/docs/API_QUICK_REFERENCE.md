# 🔗 Referência Rápida - Endpoints e Jobs

## 📱 Endpoints Mobile - Check-in

### 1️⃣ Registrar Check-in
```
POST /mobile/checkin
```

**Headers Obrigatórios:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Body:**
```json
{
  "turma_id": 15,
  "modalidade_id": 2,
  "horario_id": null
}
```

**Resposta Sucesso (200):**
```json
{
  "success": true,
  "message": "Check-in realizado com sucesso",
  "data": {
    "id": 456,
    "usuario_id": 11,
    "turma_id": 15,
    "modalidade_id": 2,
    "data_checkin": "2026-01-11 10:30:00"
  }
}
```

**Erros Comuns:**
- `400` - Turma/modalidade inválida
- `401` - Token inválido
- `409` - Já fez check-in nesta turma hoje

---

### 2️⃣ Listar Horários Disponíveis
```
GET /mobile/horarios-disponiveis
```

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "modalidades": [
      {
        "id": 1,
        "nome": "Natação",
        "turmas": [
          {
            "turma_id": 15,
            "dia_semana": "Segunda",
            "horario": "10:00",
            "professor": "João Silva",
            "vagas_total": 20,
            "alunos_count": 8,
            "vagas_disponiveis": 12
          }
        ]
      },
      {
        "id": 2,
        "nome": "CrossFit",
        "turmas": [...]
      }
    ]
  }
}
```

---

### 3️⃣ Listar Participantes da Turma
```
GET /mobile/turma/{turmaId}/participantes
```

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Exemplo:**
```
GET /mobile/turma/15/participantes
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "turma_id": 15,
    "participantes": [
      {
        "usuario_id": 11,
        "nome": "Carolina Ferreira",
        "foto": "https://...",
        "checkin_id": 456,
        "data_checkin": "2026-01-11 10:30:00"
      },
      {
        "usuario_id": 12,
        "nome": "Maria Silva",
        "foto": "https://...",
        "checkin_id": 457,
        "data_checkin": "2026-01-11 10:25:00"
      }
    ],
    "total": 8
  }
}
```

---

### 4️⃣ Detalhes da Turma
```
GET /mobile/turma/{turmaId}/detalhes
```

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Exemplo:**
```
GET /mobile/turma/15/detalhes
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "turma_id": 15,
    "modalidade": "Natação",
    "professor": "João Silva",
    "dias": [
      {
        "data": "2026-01-13",
        "dia_semana": "Segunda",
        "horario_inicio": "10:00",
        "horario_fim": "11:00"
      },
      {
        "data": "2026-01-15",
        "dia_semana": "Quarta",
        "horario_inicio": "10:00",
        "horario_fim": "11:00"
      }
    ],
    "participantes": [
      {
        "usuario_id": 11,
        "nome": "Carolina Ferreira"
      },
      {
        "usuario_id": 12,
        "nome": "Maria Silva"
      }
    ],
    "vagas": {
      "total": 20,
      "ocupadas": 8,
      "disponiveis": 12
    }
  }
}
```

---

## 🧹 Jobs - Limpeza de Matrículas

### Job: Limpar Matrículas Duplicadas/Sem Pagamento
```
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php
```

### Opções

**Modo Teste (Dry-Run) - NÃO ALTERA DADOS:**
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --dry-run
```

**Modo Produção - ALTERA DADOS:**
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php
```

### Saída Esperada

**Dry-Run:**
```
========================================
LIMPEZA DE MATRÍCULAS DUPLICADAS
Data/Hora: 2026-01-11 15:03:16
⚠️ MODO DRY-RUN (Nenhuma alteração será feita)
========================================

📊 Processando 3 tenant(s)...

[Tenant #1] Sistema AppCheckin
  Usuários com múltiplas matrículas: 0

[Tenant #5] Fitpro 7 - Plus
  Usuários com múltiplas matrículas: 1
    ✓ Mantendo: 2x por Semana (Data: 2026-01-11, Status: pendente, com 1 pagamento(s))
    ✗ Cancelando: 1x por semana (Data: 2026-01-11, Status: pendente, sem pagamento)

========================================
✅ CONCLUÍDO
Usuários processados: 1
Matrículas canceladas: 0
Tempo: 0.01s
⚠️ Modo DRY-RUN: Nenhuma alteração foi feita
========================================
```

### Crontab (Automático Diariamente às 5:00)

```bash
# Ver linha no crontab
crontab -l | grep limpar_matriculas_duplicadas

# Resultado esperado:
# 0 5 * * * docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php >> /var/log/appcheck/limpar_matriculas.log 2>&1
```

### Logs

```bash
# Ver últimas linhas
tail -f /var/log/appcheck/limpar_matriculas.log

# Ver com timestamp (últimas 50 linhas)
tail -50 /var/log/appcheck/limpar_matriculas.log | cat -n

# Contar cancelações
grep "Matrículas canceladas" /var/log/appcheck/limpar_matriculas.log | tail -7
```

---

## 🔌 Integrações & Dados Relacionados

### Tabelas Envolvidas

**Check-in:**
- `checkins` - Registros de check-in
- `turmas` - Classes/turmas
- `modalidades` - Tipos de aulas
- `usuarios` - Usuários do sistema
- `matriculas` - Inscrições

**Pagamentos:**
- `matriculas` - Inscrições (tem `turma_id`)
- `pagamentos_plano` - Pagamentos associados
- `planos` - Planos de aula
- `modalidades` - Tipo de modalidade

### Relacionamentos

```
usuarios → matriculas (1:N)
usuarios → checkins (1:N)
turmas → checkins (1:N)
turmas → dias (1:N)
modalidades → turmas (1:N)
modalidades → planos (1:N)
planos → matriculas (1:N)
matriculas → pagamentos_plano (1:N)
```

---

## 📋 Validações Aplicadas

### Check-in (9 Validações)

1. ✅ Turma existe
2. ✅ Turma está ativa
3. ✅ Usuário existe
4. ✅ Usuário não faltou >3x
5. ✅ Usuário tem matrícula ativa na modalidade
6. ✅ Usuário NÃO fez check-in nesta turma
7. ✅ Usuário NÃO fez check-in em OUTRA turma da MESMA modalidade no mesmo dia
8. ✅ Turma tem vagas disponíveis
9. ✅ Modalidade está ativa

### Job (Identificação de Duplicatas)

1. ✅ Usuário tem múltiplas matrículas na mesma modalidade
2. ✅ Status é `ativa` ou `pendente`
3. ✅ Verifica se tem pagamentos via `pagamentos_plano`
4. ✅ Prioriza: Com pagamento > Sem pagamento
5. ✅ Prioriza: `ativa` > `pendente`
6. ✅ Prioriza: Mais recente

---

## 🚀 Exemplos cURL

### Exemplo 1: Check-in com cURL
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."

curl -X POST http://localhost:8000/mobile/checkin \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "turma_id": 15,
    "modalidade_id": 2,
    "horario_id": null
  }'
```

### Exemplo 2: Horários Disponíveis com cURL
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."

curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/mobile/horarios-disponiveis
```

### Exemplo 3: Participantes com cURL
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."

curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/mobile/turma/15/participantes
```

### Exemplo 4: Detalhes da Turma com cURL
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."

curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/mobile/turma/15/detalhes
```

---

## 📚 Documentação Completa

- [RESUMO_FINAL.md](RESUMO_FINAL.md) - Visão geral completa
- [JOB_LIMPEZA_MATRICULAS_DOCUMENTACAO.md](JOB_LIMPEZA_MATRICULAS_DOCUMENTACAO.md) - Detalhes do job
- [CHECKLIST_IMPLANTACAO.md](CHECKLIST_IMPLANTACAO.md) - Passos para produção

---

**Data:** 11 de janeiro de 2026  
**Versão:** 1.0  
**Última Atualização:** 11 de janeiro de 2026
