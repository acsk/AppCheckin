# Endpoints de WOD - Admin

## CRUD WODs

### Listar WODs
```
GET /admin/wods
Query Parameters:
  - status=published|draft|archived (opcional)
  - data_inicio=2026-01-01 (opcional)
  - data_fim=2026-01-31 (opcional)
  - data=2026-01-14 (opcional)

Exemplo:
GET /admin/wods?status=published
GET /admin/wods?data=2026-01-14
GET /admin/wods?data_inicio=2026-01-01&data_fim=2026-01-31

Resposta 200:
{
  "type": "success",
  "message": "WODs listados com sucesso",
  "data": [
    {
      "id": 1,
      "tenant_id": 1,
      "data": "2026-01-14",
      "titulo": "WOD 14/01/2026",
      "descricao": "Treino de força",
      "status": "published",
      "criado_por": 5,
      "criado_por_nome": "João",
      "criado_em": "2026-01-14 10:00:00",
      "atualizado_em": "2026-01-14 10:30:00"
    }
  ],
  "total": 1
}
```

### Obter Detalhes de um WOD
```
GET /admin/wods/{id}

Exemplo:
GET /admin/wods/1

Resposta 200:
{
  "type": "success",
  "message": "WOD obtido com sucesso",
  "data": {
    "id": 1,
    "tenant_id": 1,
    "data": "2026-01-14",
    "titulo": "WOD 14/01/2026",
    "descricao": "Treino de força",
    "status": "published",
    "criado_por": 5,
    "criado_por_nome": "João",
    "criado_em": "2026-01-14 10:00:00",
    "atualizado_em": "2026-01-14 10:30:00",
    "blocos": [
      {
        "id": 1,
        "wod_id": 1,
        "ordem": 1,
        "tipo": "warmup",
        "titulo": "Aquecimento",
        "conteudo": "5 min rope skip",
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
        "descricao": null,
        "criado_em": "2026-01-14 10:00:00",
        "atualizado_em": null
      }
    ],
    "resultados": []
  }
}
```

### Criar WOD
```
POST /admin/wods
Content-Type: application/json

{
  "titulo": "WOD 14/01/2026",
  "descricao": "Treino de força e resistência",
  "data": "2026-01-14",
  "status": "draft"
}

Resposta 201:
{
  "type": "success",
  "message": "WOD criado com sucesso",
  "data": {
    "id": 1,
    "tenant_id": 1,
    "data": "2026-01-14",
    "titulo": "WOD 14/01/2026",
    "descricao": "Treino de força e resistência",
    "status": "draft",
    "criado_por": 5,
    "criado_por_nome": "João",
    "criado_em": "2026-01-14 10:00:00",
    "atualizado_em": null
  }
}

Erro 409 (Conflito - Já existe WOD para a data):
{
  "type": "error",
  "message": "Já existe um WOD para essa data"
}

Erro 422 (Validação):
{
  "type": "error",
  "message": "Validação falhou",
  "errors": [
    "Título é obrigatório",
    "Data é obrigatória"
  ]
}
```

### Atualizar WOD
```
PUT /admin/wods/{id}
Content-Type: application/json

{
  "titulo": "WOD 14/01/2026 - Atualizado",
  "descricao": "Treino novo",
  "status": "published",
  "data": "2026-01-15"
}

Resposta 200:
{
  "type": "success",
  "message": "WOD atualizado com sucesso",
  "data": { ... }
}

Erro 404:
{
  "type": "error",
  "message": "WOD não encontrado"
}

Erro 409 (Se tentar mudar para uma data que já existe):
{
  "type": "error",
  "message": "Já existe um WOD para essa data"
}
```

### Deletar WOD
```
DELETE /admin/wods/{id}

Resposta 200:
{
  "type": "success",
  "message": "WOD deletado com sucesso"
}

Erro 404:
{
  "type": "error",
  "message": "WOD não encontrado"
}
```

---

## Operações Especiais

### Publicar WOD
```
PATCH /admin/wods/{id}/publish

Resposta 200:
{
  "type": "success",
  "message": "WOD publicado com sucesso",
  "data": { ... }
}
```

### Arquivar WOD
```
PATCH /admin/wods/{id}/archive

Resposta 200:
{
  "type": "success",
  "message": "WOD arquivado com sucesso",
  "data": { ... }
}
```

---

## CRUD Blocos de WOD

### Listar Blocos
```
GET /admin/wods/{wodId}/blocos

Resposta 200:
{
  "type": "success",
  "message": "Blocos listados com sucesso",
  "data": [ ... ],
  "total": 2
}
```

### Criar Bloco
```
POST /admin/wods/{wodId}/blocos
Content-Type: application/json

{
  "ordem": 1,
  "tipo": "warmup",
  "titulo": "Aquecimento",
  "conteudo": "5 min rope skip\n2 min mobilidade",
  "tempo_cap": "7 min"
}

Tipos válidos: warmup, strength, metcon, accessory, cooldown, note

Resposta 201:
{
  "type": "success",
  "message": "Bloco criado com sucesso",
  "data": { ... }
}
```

### Atualizar Bloco
```
PUT /admin/wods/{wodId}/blocos/{id}
Content-Type: application/json

{
  "conteudo": "10 min rope skip",
  "tempo_cap": "10 min"
}

Resposta 200:
{
  "type": "success",
  "message": "Bloco atualizado com sucesso",
  "data": { ... }
}
```

### Deletar Bloco
```
DELETE /admin/wods/{wodId}/blocos/{id}

Resposta 200:
{
  "type": "success",
  "message": "Bloco deletado com sucesso"
}
```

---

## CRUD Variações

### Listar Variações
```
GET /admin/wods/{wodId}/variacoes

Resposta 200:
{
  "type": "success",
  "message": "Variações listadas com sucesso",
  "data": [ ... ],
  "total": 2
}
```

### Criar Variação
```
POST /admin/wods/{wodId}/variacoes
Content-Type: application/json

{
  "nome": "RX",
  "descricao": "Versão completa do WOD"
}

Nomes sugeridos: RX, Scaled, Beginner

Resposta 201:
{
  "type": "success",
  "message": "Variação criada com sucesso",
  "data": { ... }
}

Erro 409 (Nome duplicado):
{
  "type": "error",
  "message": "Já existe uma variação com esse nome para este WOD"
}
```

### Atualizar Variação
```
PUT /admin/wods/{wodId}/variacoes/{id}
Content-Type: application/json

{
  "nome": "Scaled",
  "descricao": "Versão modificada"
}

Resposta 200:
{
  "type": "success",
  "message": "Variação atualizada com sucesso",
  "data": { ... }
}
```

### Deletar Variação
```
DELETE /admin/wods/{wodId}/variacoes/{id}

Resposta 200:
{
  "type": "success",
  "message": "Variação deletada com sucesso"
}
```

---

## CRUD Resultados/Leaderboard

### Listar Resultados (Leaderboard)
```
GET /admin/wods/{wodId}/resultados

Resposta 200:
{
  "type": "success",
  "message": "Resultados listados com sucesso",
  "data": [
    {
      "id": 1,
      "wod_id": 1,
      "usuario_id": 10,
      "usuario_nome": "Maria",
      "variacao_id": 1,
      "variacao_nome": "RX",
      "tipo_score": "time",
      "valor_num": "12.45",
      "valor_texto": null,
      "observacao": "Bom desempenho",
      "registrado_por": 5,
      "registrado_em": "2026-01-14 17:00:00",
      "atualizado_em": null
    }
  ],
  "total": 3
}
```

### Registrar Resultado
```
POST /admin/wods/{wodId}/resultados
Content-Type: application/json

{
  "usuario_id": 10,
  "variacao_id": 1,
  "tipo_score": "time",
  "valor_num": 12.45,
  "valor_texto": null,
  "observacao": "Bom desempenho"
}

Tipos de score válidos:
  - time (em minutos: 12.45)
  - reps (número de repetições)
  - weight (em kg)
  - rounds_reps (ex: "5 rounds + 10 reps")
  - distance (em metros)
  - calories (número)
  - points (número)

Resposta 201:
{
  "type": "success",
  "message": "Resultado registrado com sucesso",
  "data": { ... }
}

Erro 409 (Usuário já tem resultado):
{
  "type": "error",
  "message": "Esse aluno já possui resultado registrado para esse WOD"
}
```

### Atualizar Resultado
```
PUT /admin/wods/{wodId}/resultados/{id}
Content-Type: application/json

{
  "valor_num": 11.50,
  "observacao": "Tempo melhorado"
}

Resposta 200:
{
  "type": "success",
  "message": "Resultado atualizado com sucesso",
  "data": { ... }
}
```

### Deletar Resultado
```
DELETE /admin/wods/{wodId}/resultados/{id}

Resposta 200:
{
  "type": "success",
  "message": "Resultado deletado com sucesso"
}
```

---

## Exemplo Completo de Fluxo

```bash
# 1. Criar um WOD
curl -X POST http://localhost:8000/admin/wods \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "WOD 15/01/2026",
    "descricao": "Treino de CrossFit",
    "data": "2026-01-15",
    "status": "draft"
  }'

# 2. Adicionar blocos ao WOD
curl -X POST http://localhost:8000/admin/wods/1/blocos \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "ordem": 1,
    "tipo": "warmup",
    "titulo": "Aquecimento",
    "conteudo": "5 min rope skip\n2 min mobilidade",
    "tempo_cap": "7 min"
  }'

# 3. Adicionar variações
curl -X POST http://localhost:8000/admin/wods/1/variacoes \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "RX",
    "descricao": "Versão completa"
  }'

# 4. Publicar o WOD
curl -X PATCH http://localhost:8000/admin/wods/1/publish \
  -H "Authorization: Bearer TOKEN"

# 5. Registrar resultado
curl -X POST http://localhost:8000/admin/wods/1/resultados \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "usuario_id": 10,
    "variacao_id": 1,
    "tipo_score": "time",
    "valor_num": 12.45
  }'

# 6. Ver leaderboard
curl -X GET http://localhost:8000/admin/wods/1/resultados \
  -H "Authorization: Bearer TOKEN"
```
