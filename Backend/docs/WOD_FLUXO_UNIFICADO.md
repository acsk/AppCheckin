# Fluxo Unificado de Criação de WOD

## Visão Geral

Antes você precisava:
1. Criar o WOD com `POST /admin/wods`
2. Obter o ID do WOD criado
3. Criar cada bloco com `POST /admin/wods/{id}/blocos`
4. Obter o ID de cada bloco
5. Criar cada variação com `POST /admin/wods/{id}/variacoes`
6. Publicar o WOD com `PATCH /admin/wods/{id}/publish`

**Agora com o novo endpoint, você faz TUDO em uma única requisição!**

## Novo Endpoint

```
POST /admin/wods/completo
```

## Estrutura Simplificada

```
{
  WOD (base)
  ├── blocos
  │   ├── Bloco 1 (Warmup)
  │   │   └── atividades (informação para o frontend)
  │   ├── Bloco 2 (Strength)
  │   │   └── atividades (informação para o frontend)
  │   ├── Bloco 3 (WOD Principal)
  │   │   └── atividades (informação para o frontend)
  │   └── Bloco 4 (Cooldown)
  │       └── atividades (informação para o frontend)
  │
  └── variacoes
      ├── RX
      ├── Scaled
      └── Modificado
}
```

## Fluxo de Processamento

```
┌─────────────────────────────────────────────────┐
│ Frontend envia WOD COMPLETO                      │
│ POST /admin/wods/completo                        │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│ 1. Validações                                    │
│    - Título obrigatório?                         │
│    - Data obrigatória?                           │
│    - Pelo menos 1 bloco?                         │
│    - Data não duplicada?                         │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│ 2. Inicia Transação                              │
│    (Tudo ou nada - consistência garantida)       │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│ 3. Cria WOD Base                                 │
│    - ID gerado automaticamente                   │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│ 4. Cria Todos os Blocos                          │
│    - Para cada bloco do array                    │
│    - Salva em ordem sequencial                   │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│ 5. Cria Todas as Variações                       │
│    - Para cada variação fornecida                │
│    - Se nenhuma, cria "RX" por padrão            │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│ 6. Confirma Transação                            │
│    - Commit no banco de dados                    │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│ 7. Retorna WOD Completo                          │
│    - Status 201 Created                          │
│    - Incluindo todos os blocos e variações       │
└─────────────────────────────────────────────────┘
```

## Exemplo de Requisição Frontend

### JavaScript/TypeScript

```typescript
// Dados do WOD a criar
const wodCompleto = {
  titulo: "WOD 14/01/2026",
  descricao: "Treino de força e resistência",
  data: "2026-01-14",
  status: "published",
  
  // Array de blocos
  blocos: [
    {
      ordem: 1,
      tipo: "warmup",
      titulo: "Aquecimento",
      conteudo: "5 min rope skip\n5 min bike\n10 air squats",
      tempo_cap: "5 min"
    },
    {
      ordem: 2,
      tipo: "strength",
      titulo: "Back Squat",
      conteudo: "Find 1RM for the day",
      tempo_cap: "20 min"
    },
    {
      ordem: 3,
      tipo: "metcon",
      titulo: "WOD Principal",
      conteudo: "20 min AMRAP:\n10 thrusters\n10 box jumps",
      tempo_cap: "20 min"
    },
    {
      ordem: 4,
      tipo: "cooldown",
      titulo: "Resfriamento",
      conteudo: "Alongamento e mobilidade",
      tempo_cap: "5 min"
    }
  ],
  
  // Array de variações
  variacoes: [
    {
      nome: "RX",
      descricao: "65/95 lb thrusters, 20/24 inch box"
    },
    {
      nome: "Scaled",
      descricao: "45/65 lb thrusters, 18/20 inch box"
    }
  ]
};

// Fazer a requisição
fetch('/admin/wods/completo', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify(wodCompleto)
})
.then(response => response.json())
.then(data => {
  if (data.type === 'success') {
    console.log('WOD criado:', data.data);
    // Redirecionar ou mostrar sucesso
  } else {
    console.error('Erro:', data.message, data.errors);
  }
})
.catch(error => console.error('Erro na requisição:', error));
```

### React Hook

```typescript
const [isLoading, setIsLoading] = useState(false);
const [error, setError] = useState<string | null>(null);

const createWodCompleto = async (wodData) => {
  setIsLoading(true);
  setError(null);
  
  try {
    const response = await fetch('/admin/wods/completo', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify(wodData)
    });
    
    const result = await response.json();
    
    if (result.type === 'success') {
      return result.data; // Retorna o WOD criado
    } else {
      throw new Error(result.message);
    }
  } catch (err) {
    setError(err.message);
    throw err;
  } finally {
    setIsLoading(false);
  }
};

// Usar no componente
<button 
  onClick={() => createWodCompleto(wodData)}
  disabled={isLoading}
>
  {isLoading ? 'Criando...' : 'Criar WOD'}
</button>
```

## Tratamento de Erros

### Erro 422 - Validação
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

### Erro 409 - Data Duplicada
```json
{
  "type": "error",
  "message": "Já existe um WOD para essa data"
}
```

### Erro 500 - Erro Interno
```json
{
  "type": "error",
  "message": "Erro ao criar WOD completo",
  "details": "detalhes do erro"
}
```

## Benefícios

✅ **Uma única requisição** - Antes eram 5+ requisições
✅ **Transação atômica** - Tudo salva ou nada salva
✅ **Consistência garantida** - Sem dados parciais
✅ **Mais rápido** - Menos round trips
✅ **Simples para o frontend** - Estrutura clara e organizada
✅ **Escalável** - Pronto para futuros campos

## Próximos Passos (Opcional)

Para ainda melhorar, podemos:

1. **Adicionar método GET `/admin/wods/template`** - Retorna template vazio para facilitar preenchimento

2. **Adicionar duplicação** - Endpoint `POST /admin/wods/{id}/duplicar` que duplica um WOD existente

3. **Adicionar atividades como tabela** - Se precisar rastrear atividades separadamente

4. **Adicionar histórico de revisões** - Guardar versões anteriores do WOD

5. **Adicionar bulk upload** - Criar múltiplos WODs de uma vez
