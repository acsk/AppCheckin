# Exemplo Prático: Replicar Turmas de CrossFit

## Cenário
A academia "CrossFit Premium" tem turmas de CrossFit que ocorrem toda **segunda-feira e quinta-feira** de cada mês às **6:00 - 7:00 da manhã**, com o professor João Pedro.

O diretor quer replicar rapidamente essas turmas para todos os meses subsequentes.

## Dados Iniciais

### Turmas Existentes em 2026-01-09 (quinta-feira)
```
ID: 195
Professor: Lucas Santos
Modalidade: CrossFit
Horário: 04:00 - 04:30
```

```
ID: 187
Professor: João Pedro
Modalidade: CrossFit
Horário: 06:00 - 07:00
```

```
ID: 197
Professor: João Pedro
Modalidade: CrossFit
Horário: 07:00 - 08:00
```

## Solução: Usar o Endpoint de Replicação

### Passo 1: Replicar para todas as quintas de janeiro
Queremos replicar as turmas de 2026-01-09 (quinta) para as outras quintas do mês.

**Dias de quinta em janeiro/2026:** 09, 16, 23, 30

```bash
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer seu_token_jwt" \
  -d '{
    "dia_id": 17,
    "dias_semana": [5],
    "mes": "2026-01"
  }'
```

**Resposta esperada:**
```json
{
  "type": "success",
  "message": "Replicação concluída com sucesso",
  "summary": {
    "total_solicitadas": 3,
    "total_criadas": 9,
    "total_puladas": 0,
    "dias_destino": 3
  },
  "detalhes": [
    {
      "turma_original_id": 195,
      "professor_id": 1,
      "modalidade_id": 5,
      "horario_inicio": "04:00:00",
      "horario_fim": "04:30:00",
      "criadas": 3,
      "puladas": 0
    },
    {
      "turma_original_id": 187,
      "professor_id": 6,
      "modalidade_id": 5,
      "horario_inicio": "06:00:00",
      "horario_fim": "07:00:00",
      "criadas": 3,
      "puladas": 0
    },
    {
      "turma_original_id": 197,
      "professor_id": 6,
      "modalidade_id": 5,
      "horario_inicio": "07:00:00",
      "horario_fim": "08:00:00",
      "criadas": 3,
      "puladas": 0
    }
  ],
  "turmas_criadas": [
    // 9 novas turmas criadas...
  ]
}
```

**Resultado:**
- ✅ 3 turmas da origem x 3 dias destino = 9 turmas criadas
- 0 conflitos
- Calendário de quintas completo para janeiro

---

### Passo 2: Replicar para fevereiro

```bash
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer seu_token_jwt" \
  -d '{
    "dia_id": 17,
    "dias_semana": [5],
    "mes": "2026-02"
  }'
```

**Dias de quinta em fevereiro/2026:** 05, 12, 19, 26
- 4 dias × 3 turmas = 12 novas turmas criadas

---

### Passo 3: Replicar também as segundas-feiras de janeiro

Agora queremos agendar as turmas também para as **segundas-feiras**.

Primeiro, encontramos qual era uma segunda-feira que tinha turmas. Vamos usar a segunda-feira anterior (2026-01-05, dia_id=16).

```bash
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer seu_token_jwt" \
  -d '{
    "dia_id": 16,
    "dias_semana": [2],
    "mes": "2026-01"
  }'
```

**Dias de segunda em janeiro/2026:** 05, 12, 19, 26
- 4 dias × 3 turmas = 12 novas turmas criadas

---

## Cenário com Conflitos

Suponha que há já uma turma agendada:
- 2026-01-16 (segunda) às **06:15 - 07:15** (professor diferente, aula de yoga)

Quando tentamos replicar a turma de 06:00-07:00 para essa segunda:
- **Conflito detectado!** ❌ (06:00-07:00 se sobrepõe com 06:15-07:15)
- Turma **pulada** para essa data
- **Mas continua** replicando para as outras segundas onde não há conflito

```json
{
  "type": "success",
  "message": "Replicação concluída com sucesso",
  "summary": {
    "total_solicitadas": 3,
    "total_criadas": 9,
    "total_puladas": 3,
    "dias_destino": 4
  },
  "detalhes": [
    {
      "turma_original_id": 187,
      "horario_inicio": "06:00:00",
      "horario_fim": "07:00:00",
      "criadas": 3,
      "puladas": 1,
      "detalhes_puladas": [
        {
          "dia_id": 24,
          "data": "2026-01-16",
          "razao": "Horário já ocupado"
        }
      ]
    }
  ]
}
```

**Resultado:**
- ✅ 9 turmas criadas (3 professores × 3 segundas sem conflito)
- ⏭️ 3 turmas puladas (tentativa de replicar para 2026-01-16, 1 vez por turma × 3)
- Dashboard mostra claramente o que foi pulado e por quê

---

## Vantagens dessa Abordagem

1. **Eficiência**: Replicar 3 turmas para 3 semanas em 1 request
2. **Segurança**: Conflitos são detectados automaticamente
3. **Flexibilidade**: Pode replicar para vários dias da semana por request
4. **Transparência**: Retorna detalhes de cada tentativa (criada vs pulada)
5. **Robustez**: Falha em um dia não afeta a replicação para outros dias

---

## Próximos Passos

### Opção A: Usar a API frontend
Criar um formulário no painel de controle:
- Seletor de dia origem
- Checkboxes para dias da semana (seg-dom)
- Seletor de mês
- Botão "Replicar"

### Opção B: Usar CLI/Cron
Executar via PHP-CLI para agendar automaticamente:
```bash
php replicate_turmas.php --dia-id=17 --dias-semana=2,5 --mes=2026-03
```

### Opção C: Bulk replication
Modificar endpoint para aceitar múltiplos dias de origem:
```json
{
  "dias_origem": [17, 16],
  "dias_semana": [2, 5],
  "mes": "2026-03"
}
```
