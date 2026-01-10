# Endpoint: Replicar Turmas para Dias da Semana

## Descrição
Replica turmas de um dia específico para todos os dias da semana do mês que correspondem ao padrão solicitado.

**Requisição:** `POST /admin/turmas/replicar`

**Autenticação:** Sim (Bearer Token)

## Parâmetros

### Request Body (JSON)
```json
{
  "dia_id": 18,
  "dias_semana": [7],
  "mes": "2026-02"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `dia_id` | integer | ✅ Sim | ID do dia que contém as turmas origem para replicação |
| `dias_semana` | array[int] | ✅ Sim | Array com dias da semana destino (1=domingo, 2=segunda, ... 7=sábado) |
| `mes` | string | ❌ Não | Mês no formato YYYY-MM. Se omitido, usa o mês atual |

## Dias da Semana
- `1` = Domingo
- `2` = Segunda-feira
- `3` = Terça-feira
- `4` = Quarta-feira
- `5` = Quinta-feira
- `6` = Sexta-feira
- `7` = Sábado

## Exemplos de Uso

### Exemplo 1: Replicar turmas de quinta (09/01) para todas as quintas de janeiro
```bash
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer seu_token" \
  -d '{
    "dia_id": 17,
    "dias_semana": [5],
    "mes": "2026-01"
  }'
```

### Exemplo 2: Replicar para múltiplos dias (seg, qua, sex)
```bash
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer seu_token" \
  -d '{
    "dia_id": 18,
    "dias_semana": [2, 4, 6],
    "mes": "2026-02"
  }'
```

### Exemplo 3: Replicar para o mês atual
```bash
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer seu_token" \
  -d '{
    "dia_id": 20,
    "dias_semana": [7]
  }'
```

## Response

### Sucesso (201)
```json
{
  "type": "success",
  "message": "Replicação concluída com sucesso",
  "summary": {
    "total_solicitadas": 1,
    "total_criadas": 2,
    "total_puladas": 1,
    "dias_destino": 3
  },
  "detalhes": [
    {
      "turma_original_id": 92,
      "professor_id": 6,
      "modalidade_id": 5,
      "horario_inicio": "06:00:00",
      "horario_fim": "07:00:00",
      "criadas": 2,
      "puladas": 1,
      "detalhes_puladas": [
        {
          "dia_id": 32,
          "data": "2026-01-17",
          "razao": "Horário já ocupado"
        }
      ]
    }
  ],
  "turmas_criadas": [
    {
      "id": 198,
      "tenant_id": 5,
      "professor_id": 6,
      "modalidade_id": 5,
      "dia_id": 39,
      "horario_inicio": "06:00:00",
      "horario_fim": "07:00:00",
      "nome": "CrossFit - 6:00 - João",
      "limite_alunos": 15,
      "ativo": 1,
      "created_at": "2026-01-09 20:30:00",
      "updated_at": "2026-01-09 20:30:00"
    },
    {
      "id": 199,
      "tenant_id": 5,
      "professor_id": 6,
      "modalidade_id": 5,
      "dia_id": 46,
      "horario_inicio": "06:00:00",
      "horario_fim": "07:00:00",
      "nome": "CrossFit - 6:00 - João",
      "limite_alunos": 15,
      "ativo": 1,
      "created_at": "2026-01-09 20:30:00",
      "updated_at": "2026-01-09 20:30:00"
    }
  ]
}
```

### Erro - Validação (400)
```json
{
  "type": "error",
  "message": "dia_id e dias_semana são obrigatórios"
}
```

### Erro - Nenhuma turma encontrada (200)
```json
{
  "type": "success",
  "message": "Nenhuma turma encontrada no dia de origem",
  "summary": {
    "total_solicitadas": 0,
    "total_criadas": 0,
    "total_puladas": 0
  }
}
```

### Erro - Nenhum dia encontrado (200)
```json
{
  "type": "success",
  "message": "Nenhum dia encontrado para replicação no período",
  "summary": {
    "total_solicitadas": 1,
    "total_criadas": 0,
    "total_puladas": 0
  }
}
```

### Erro - Servidor (500)
```json
{
  "type": "error",
  "message": "Erro ao replicar turmas: [mensagem de erro]"
}
```

## Comportamento

### Validação
1. Valida se `dia_id` e `dias_semana` foram fornecidos
2. Valida se `dias_semana` contém valores entre 1 e 7
3. Verifica se há turmas no dia de origem
4. Busca dias do mês que correspondem aos dias_semana especificados

### Replicação
Para cada turma no dia de origem:
1. Itera sobre os dias encontrados
2. **Verifica se já existe turma com conflito de horário** naquele dia
3. Se não houver conflito:
   - ✅ Cria a nova turma com mesmo professor, modalidade e horário
   - Incrementa contador de turmas criadas
4. Se houver conflito:
   - ⏭️ Pula esse dia específico mas continua com os outros
   - Incrementa contador de turmas puladas
   - Registra detalhes do motivo (horário já ocupado)

### Conflito de Horário
Duas turmas têm conflito quando:
- Estão no mesmo dia (`dia_id`)
- Seus horários se sobrepõem:
  - `horario_inicio_nova < horario_fim_existente` **E**
  - `horario_fim_nova > horario_inicio_existente`

Exemplos:
- Existente: 06:00-07:00, Nova: 06:30-07:30 → ❌ Conflito
- Existente: 06:00-07:00, Nova: 07:00-08:00 → ✅ Sem conflito (não se sobrepõem)
- Existente: 06:00-07:00, Nova: 05:00-06:00 → ✅ Sem conflito (não se sobrepõem)

## Notas Importantes

1. **Excludes o dia de origem**: O dia_id fornecido é excluído da busca de dias destino para não duplicar turmas
2. **Preserva dados originais**: As turmas originais não são modificadas
3. **Força de idempotência**: Se uma turma já existe nos mesmo horário, é pulada (não causa erro)
4. **Resposta detalhada**: Sempre retorna quantas turmas foram criadas vs puladas
5. **Segurança**: Apenas turmas do tenant do usuário autenticado podem ser replicadas
