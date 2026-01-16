# Resumo das Mudanças - Endpoint Unificado de WOD

## O que foi feito?

Criei um **novo endpoint unificado** que permite criar um WOD **completo** (com blocos e atividades) em uma única requisição, ao invés de fazer múltiplas chamadas de API.

## Novo Endpoint

```
POST /admin/wods/completo
```

## Arquivo Modificado

### 1. WodController.php
- **Adicionado método**: `createCompleto()`
- **Localização**: `/Users/andrecabral/Projetos/AppCheckin/Backend/app/Controllers/WodController.php`
- **Funcionalidade**: 
  - Recebe dados completos do WOD com blocos e variações
  - Valida os dados
  - Cria tudo em uma transação de banco de dados (tudo ou nada)
  - Retorna o WOD completo com todos os detalhes

### 2. routes/api.php
- **Modificado**: Adicionada rota para o novo endpoint
- **Localização**: `/Users/andrecabral/Projetos/AppCheckin/Backend/routes/api.php` (linha ~315)
- **Rota**: `$group->post('/wods/completo', [WodController::class, 'createCompleto']);`

## Fluxo da Requisição

```
Cliente (Frontend)
    ↓
POST /admin/wods/completo
    ↓
Validações (título, data, blocos)
    ↓
Inicia Transação de Banco
    ↓
1. Cria WOD base
2. Cria blocos em ordem
3. Cria variações (ou "RX" por padrão)
    ↓
Confirma Transação
    ↓
Retorna WOD Completo (201 Created)
```

## Como Usar no Frontend

### JavaScript/Fetch

```javascript
const wodCompleto = {
  titulo: "WOD 14/01/2026",
  descricao: "Treino de força",
  data: "2026-01-14",
  status: "published",
  
  blocos: [
    {
      ordem: 1,
      tipo: "warmup",
      titulo: "Aquecimento",
      conteudo: "5 min bike\n10 push-ups",
      tempo_cap: "5 min"
    },
    {
      ordem: 2,
      tipo: "metcon",
      titulo: "WOD Principal",
      conteudo: "10 min AMRAP",
      tempo_cap: "10 min"
    },
    {
      ordem: 3,
      tipo: "cooldown",
      titulo: "Resfriamento",
      conteudo: "Alongamento"
    }
  ],
  
  variacoes: [
    { nome: "RX", descricao: "95 lbs" },
    { nome: "Scaled", descricao: "65 lbs" }
  ]
};

fetch('/admin/wods/completo', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify(wodCompleto)
})
.then(r => r.json())
.then(data => {
  if (data.type === 'success') {
    console.log('WOD criado:', data.data);
  }
});
```

## Dados Retornados (Sucesso - 201)

```json
{
  "type": "success",
  "message": "WOD completo criado com sucesso",
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
    "atualizado_em": "2026-01-14 10:00:00",
    "blocos": [
      {
        "id": 1,
        "wod_id": 1,
        "ordem": 1,
        "tipo": "warmup",
        "titulo": "Aquecimento",
        "conteudo": "5 min bike\n10 push-ups",
        "tempo_cap": "5 min",
        "criado_em": "2026-01-14 10:00:00"
      },
      // ... outros blocos
    ],
    "variacoes": [
      {
        "id": 1,
        "wod_id": 1,
        "nome": "RX",
        "descricao": "95 lbs",
        "criado_em": "2026-01-14 10:00:00"
      },
      // ... outras variações
    ],
    "resultados": []
  }
}
```

## Tratamento de Erros

### Validação (422)
```json
{
  "type": "error",
  "message": "Validação falhou",
  "errors": ["Título é obrigatório", "Pelo menos um bloco é obrigatório"]
}
```

### Data Duplicada (409)
```json
{
  "type": "error",
  "message": "Já existe um WOD para essa data"
}
```

### Erro Interno (500)
```json
{
  "type": "error",
  "message": "Erro ao criar WOD completo",
  "details": "descrição do erro"
}
```

## Estrutura de Dados Aceitos

### Campos Obrigatórios
- `titulo`: string
- `data`: string (YYYY-MM-DD)
- `blocos`: array (mínimo 1 bloco)

### Campos Opcionais
- `descricao`: string
- `status`: 'draft' ou 'published' (padrão: 'draft')
- `variacoes`: array de variações

### Estrutura do Bloco
```javascript
{
  ordem: number,           // padrão: índice + 1
  tipo: string,           // warmup, strength, metcon, accessory, cooldown, note
  titulo: string,         // opcional
  conteudo: string,       // obrigatório
  tempo_cap: string,      // opcional (ex: "5 min", "20 min")
  atividades: array       // opcional (informação para frontend)
}
```

### Estrutura da Variação
```javascript
{
  nome: string,           // ex: "RX", "Scaled", "Beginner"
  descricao: string       // opcional
}
```

## Documentação Completa

Para documentação mais detalhada, veja os arquivos criados:

1. **[WOD_CRIAR_COMPLETO.md](WOD_CRIAR_COMPLETO.md)** - Documentação técnica completa
2. **[WOD_FLUXO_UNIFICADO.md](WOD_FLUXO_UNIFICADO.md)** - Explicação visual do fluxo
3. **[exemplo_wod_completo.json](exemplo_wod_completo.json)** - Exemplo pronto para usar
4. **[test_wod_completo.sh](test_wod_completo.sh)** - Script de teste com cURL

## Comparação: Antes vs Depois

### ANTES (5+ requisições)
```
1. POST /admin/wods → cria WOD
2. POST /admin/wods/1/blocos → cria bloco 1
3. POST /admin/wods/1/blocos → cria bloco 2
4. POST /admin/wods/1/blocos → cria bloco 3
5. POST /admin/wods/1/variacoes → cria variação RX
6. POST /admin/wods/1/variacoes → cria variação Scaled
7. PATCH /admin/wods/1/publish → publica
```

### DEPOIS (1 requisição)
```
1. POST /admin/wods/completo → cria tudo de uma vez!
```

## Benefícios

✅ **Uma única requisição** - Mais rápido
✅ **Transação atômica** - Garantia de consistência
✅ **Sem dados parciais** - Tudo ou nada
✅ **Interface simples** - Fácil de usar no frontend
✅ **Rotas antigas ainda funcionam** - Compatibilidade mantida

## Próximos Passos (Opcional)

Se precisar, podemos adicionar:
- Endpoint de duplicação: `POST /admin/wods/{id}/duplicar`
- Endpoint de template: `GET /admin/wods/template`
- Edição completa: `PUT /admin/wods/{id}/completo`
- Bulk upload de WODs

## Arquivos Gerados

```
/Backend/
├── WOD_CRIAR_COMPLETO.md          ← Documentação técnica
├── WOD_FLUXO_UNIFICADO.md          ← Explicação visual
├── exemplo_wod_completo.json       ← Exemplo JSON
└── test_wod_completo.sh            ← Script de teste
```

## ⚠️ ANTES DE USAR: Executar Migrações

**IMPORTANTE**: As tabelas do banco de dados ainda não foram criadas!

### Execute as Migrations:

```bash
cd database/migrations
chmod +x run_wod_migrations.sh
./run_wod_migrations.sh
```

Veja [EXECUTAR_MIGRATIONS_WOD.md](EXECUTAR_MIGRATIONS_WOD.md) para instruções detalhadas.

---

## Status

✅ Endpoint implementado
✅ Rotas adicionadas
✅ Documentação criada
✅ Exemplos fornecidos
⚠️  **FALTANDO**: Executar migrações do banco de dados
🔜 Após migrations: Pronto para uso!
