# Endpoint de Criação Completa de WOD

## Descrição
O endpoint `POST /admin/wods/completo` permite criar um WOD completo com todos os seus blocos e atividades em uma única requisição. Este é o endpoint ideal para o frontend usar ao criar um WOD pronto para publicar.

## Rota
```
POST /admin/wods/completo
```

## Headers Obrigatórios
```
Authorization: Bearer {token}
Content-Type: application/json
```

## Body da Requisição

```json
{
  "titulo": "WOD 14/01/2026",
  "descricao": "Treino de força e resistência",
  "data": "2026-01-14",
  "status": "published",
  "blocos": [
    {
      "ordem": 1,
      "tipo": "warmup",
      "titulo": "Aquecimento",
      "conteudo": "5 min rope skip\n5 min bike\n10 air squats\n10 push ups",
      "tempo_cap": "5 min",
      "atividades": [
        {
          "nome": "Rope Skip",
          "duracao": "5 min"
        },
        {
          "nome": "Bike",
          "duracao": "5 min"
        }
      ]
    },
    {
      "ordem": 2,
      "tipo": "strength",
      "titulo": "Força",
      "conteudo": "Back Squat\nFind 1RM for the day",
      "tempo_cap": "20 min",
      "atividades": [
        {
          "nome": "Back Squat",
          "series": "5",
          "repeticoes": "3"
        }
      ]
    },
    {
      "ordem": 3,
      "tipo": "metcon",
      "titulo": "WOD Principal",
      "conteudo": "20 min AMRAP:\n10 thrusters (65/95 lb)\n10 box jumps (20/24 inch)\n10 cal row",
      "tempo_cap": "20 min",
      "atividades": [
        {
          "nome": "Thrusters",
          "repeticoes": "10",
          "peso_rx": "95 lb"
        },
        {
          "nome": "Box Jumps",
          "repeticoes": "10",
          "altura_rx": "24 inch"
        },
        {
          "nome": "Calorie Row",
          "repeticoes": "10"
        }
      ]
    },
    {
      "ordem": 4,
      "tipo": "cooldown",
      "titulo": "Resfriamento",
      "conteudo": "Alongamento e mobilidade\n5 min de relaxamento",
      "tempo_cap": "5 min",
      "atividades": []
    }
  ],
  "variacoes": [
    {
      "nome": "RX",
      "descricao": "65/95 lb thrusters, 20/24 inch box jumps"
    },
    {
      "nome": "Scaled",
      "descricao": "45/65 lb thrusters, 18/20 inch box jumps"
    },
    {
      "nome": "Modificado",
      "descricao": "Kettlebell thrusters, step ups"
    }
  ]
}
```

## Campos Obrigatórios
- **titulo**: string (máx 255 caracteres)
- **data**: string no formato YYYY-MM-DD
- **blocos**: array com pelo menos 1 bloco

## Campos Opcionais
- **descricao**: string (descrição geral do WOD)
- **status**: 'draft' ou 'published' (padrão: 'draft')
- **variacoes**: array com diferentes variações (RX, Scaled, etc)

## Estrutura do Bloco

### Campos Obrigatórios do Bloco
- **tipo**: 'warmup', 'strength', 'metcon', 'accessory', 'cooldown' ou 'note'
- **conteudo**: string com a descrição do bloco

### Campos Opcionais do Bloco
- **ordem**: número (padrão: índice + 1)
- **titulo**: string (título do bloco)
- **tempo_cap**: string (tempo máximo para o bloco)
- **atividades**: array com atividades específicas do bloco

## Respostas

### Sucesso (201 Created)
```json
{
  "type": "success",
  "message": "WOD completo criado com sucesso",
  "data": {
    "id": 1,
    "tenant_id": 1,
    "data": "2026-01-14",
    "titulo": "WOD 14/01/2026",
    "descricao": "Treino de força e resistência",
    "status": "published",
    "criado_por": 5,
    "criado_por_nome": "João",
    "criado_em": "2026-01-14 10:00:00",
    "atualizado_em": "2026-01-14 10:00:00",
    "blocos": [
      {
        "id": 1,
        "wod_id": 1,
        "ordem": 1,
        "tipo": "warmup",
        "titulo": "Aquecimento",
        "conteudo": "5 min rope skip\n5 min bike\n10 air squats\n10 push ups",
        "tempo_cap": "5 min",
        "criado_em": "2026-01-14 10:00:00",
        "atualizado_em": null
      },
      {
        "id": 2,
        "wod_id": 1,
        "ordem": 2,
        "tipo": "strength",
        "titulo": "Força",
        "conteudo": "Back Squat\nFind 1RM for the day",
        "tempo_cap": "20 min",
        "criado_em": "2026-01-14 10:00:00",
        "atualizado_em": null
      },
      {
        "id": 3,
        "wod_id": 1,
        "ordem": 3,
        "tipo": "metcon",
        "titulo": "WOD Principal",
        "conteudo": "20 min AMRAP:\n10 thrusters (65/95 lb)\n10 box jumps (20/24 inch)\n10 cal row",
        "tempo_cap": "20 min",
        "criado_em": "2026-01-14 10:00:00",
        "atualizado_em": null
      },
      {
        "id": 4,
        "wod_id": 1,
        "ordem": 4,
        "tipo": "cooldown",
        "titulo": "Resfriamento",
        "conteudo": "Alongamento e mobilidade\n5 min de relaxamento",
        "tempo_cap": "5 min",
        "criado_em": "2026-01-14 10:00:00",
        "atualizado_em": null
      }
    ],
    "variacoes": [
      {
        "id": 1,
        "wod_id": 1,
        "nome": "RX",
        "descricao": "65/95 lb thrusters, 20/24 inch box jumps",
        "criado_em": "2026-01-14 10:00:00",
        "atualizado_em": null
      },
      {
        "id": 2,
        "wod_id": 1,
        "nome": "Scaled",
        "descricao": "45/65 lb thrusters, 18/20 inch box jumps",
        "criado_em": "2026-01-14 10:00:00",
        "atualizado_em": null
      },
      {
        "id": 3,
        "wod_id": 1,
        "nome": "Modificado",
        "descricao": "Kettlebell thrusters, step ups",
        "criado_em": "2026-01-14 10:00:00",
        "atualizado_em": null
      }
    ],
    "resultados": []
  }
}
```

### Validação Falha (422 Unprocessable Entity)
```json
{
  "type": "error",
  "message": "Validação falhou",
  "errors": [
    "Título é obrigatório",
    "Pelo menos um bloco é obrigatório"
  ]
}
```

### Data Duplicada (409 Conflict)
```json
{
  "type": "error",
  "message": "Já existe um WOD para essa data"
}
```

### Erro Interno (500 Internal Server Error)
```json
{
  "type": "error",
  "message": "Erro ao criar WOD completo",
  "details": "descrição do erro"
}
```

## Fluxo de Operação

1. **Validação Inicial**: Verifica se título, data e blocos foram fornecidos
2. **Verificação de Unicidade**: Valida se não existe outro WOD para a mesma data
3. **Transação de Banco**: Inicia uma transação de banco de dados
4. **Criação de WOD**: Cria o WOD base
5. **Criação de Blocos**: Cria todos os blocos na ordem especificada
6. **Criação de Atividades**: Se fornecidas, cria as atividades de cada bloco
7. **Criação de Variações**: Cria as variações (RX, Scaled, etc)
8. **Variação Padrão**: Se não houver variações, cria a variação padrão "RX"
9. **Confirmação**: Confirma a transação
10. **Retorno**: Retorna o WOD completo com todos os dados

## Exemplos de Uso com cURL

### Exemplo 1: WOD Simples com 3 Blocos
```bash
curl -X POST https://api.example.com/admin/wods/completo \
  -H "Authorization: Bearer seu_token" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "WOD do Dia",
    "data": "2026-01-14",
    "status": "draft",
    "blocos": [
      {
        "ordem": 1,
        "tipo": "warmup",
        "titulo": "Aquecimento",
        "conteudo": "5 min de bicicleta"
      },
      {
        "ordem": 2,
        "tipo": "metcon",
        "titulo": "WOD Principal",
        "conteudo": "10 min AMRAP",
        "tempo_cap": "10 min"
      },
      {
        "ordem": 3,
        "tipo": "cooldown",
        "titulo": "Resfriamento",
        "conteudo": "Alongamento"
      }
    ]
  }'
```

### Exemplo 2: WOD Completo com Variações
```bash
curl -X POST https://api.example.com/admin/wods/completo \
  -H "Authorization: Bearer seu_token" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "WOD de Força",
    "descricao": "Treino focado em força máxima",
    "data": "2026-01-15",
    "status": "published",
    "blocos": [
      {
        "ordem": 1,
        "tipo": "warmup",
        "titulo": "Aquecimento",
        "conteudo": "5 min bike\n10 PVC pass throughs"
      },
      {
        "ordem": 2,
        "tipo": "strength",
        "titulo": "Front Squat",
        "conteudo": "Front Squat - Build to heavy single",
        "tempo_cap": "15 min"
      },
      {
        "ordem": 3,
        "tipo": "metcon",
        "titulo": "Finisher",
        "conteudo": "3 rounds:\n5 Front Squats\n10 Pull-ups",
        "tempo_cap": "12 min"
      }
    ],
    "variacoes": [
      {
        "nome": "RX",
        "descricao": "95/135 lbs"
      },
      {
        "nome": "Scaled",
        "descricao": "65/95 lbs"
      }
    ]
  }'
```

## Notas Importantes

1. **Transações**: O endpoint usa transações de banco de dados para garantir consistência. Se algo falhar, tudo é revertido.

2. **Variações Padrão**: Se nenhuma variação for fornecida, a variação "RX" é criada automaticamente.

3. **Blocos**: A ordem dos blocos é importante. O campo `ordem` pode ser omitido e será preenchido automaticamente.

4. **Tipos de Bloco**: Valores válidos são 'warmup', 'strength', 'metcon', 'accessory', 'cooldown' e 'note'.

5. **Status**: O WOD pode ser criado como 'draft' ou 'published'. Se não especificado, será 'draft'.

6. **Atividades**: O campo `atividades` dentro de cada bloco é preservado no JSON para referência, mas atualmente não cria registros separados no banco de dados. Pode ser expandido no futuro para criar uma tabela de atividades.
