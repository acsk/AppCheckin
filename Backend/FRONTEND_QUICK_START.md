# 🚀 WOD API - Quick Start para Frontend

## O que você precisa saber?

Existe um novo endpoint para **criar um WOD completo com blocos e variações em uma única requisição**.

## Endpoint

```
POST /admin/wods/completo
```

## Requisição Simples

```javascript
const wod = {
  titulo: "WOD 14/01/2026",
  data: "2026-01-14",
  blocos: [
    {
      tipo: "warmup",
      conteudo: "5 min bike"
    },
    {
      tipo: "metcon",
      conteudo: "10 min AMRAP: 5 clean, 10 box jumps",
      tempo_cap: "10 min"
    }
  ]
};

fetch('/admin/wods/completo', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(wod)
})
.then(r => r.json())
.then(data => console.log(data));
```

## Resposta em Caso de Sucesso

```json
{
  "type": "success",
  "message": "WOD completo criado com sucesso",
  "data": {
    "id": 1,
    "titulo": "WOD 14/01/2026",
    "data": "2026-01-14",
    "blocos": [
      {
        "id": 1,
        "wod_id": 1,
        "tipo": "warmup",
        "conteudo": "5 min bike"
      },
      ...
    ],
    "variacoes": [...]
  }
}
```

## Estrutura Completa

```javascript
{
  // Obrigatórios
  "titulo": "WOD 14/01/2026",
  "data": "2026-01-14",
  "blocos": [
    {
      "tipo": "warmup|strength|metcon|accessory|cooldown|note",
      "conteudo": "descrição do bloco",
      // Opcionais
      "titulo": "Aquecimento",
      "tempo_cap": "5 min",
      "ordem": 1
    }
  ],
  
  // Opcionais
  "descricao": "descrição geral",
  "status": "draft|published",
  "variacoes": [
    {
      "nome": "RX",
      "descricao": "65/95 lbs"
    }
  ]
}
```

## Validações

O servidor vai retornar **erro 422** se:
- Título estiver vazio
- Data estiver vazia
- Não houver blocos
- Data já existir

## Exemplo Completo

```javascript
const wodCompleto = {
  titulo: "WOD 14/01/2026 - Upper Body",
  descricao: "Treino focado em trem superior",
  data: "2026-01-14",
  status: "published",
  
  blocos: [
    {
      ordem: 1,
      tipo: "warmup",
      titulo: "Aquecimento",
      conteudo: "5 min bike\n10 push ups",
      tempo_cap: "5 min"
    },
    {
      ordem: 2,
      tipo: "strength",
      titulo: "Bench Press",
      conteudo: "Build to heavy single",
      tempo_cap: "15 min"
    },
    {
      ordem: 3,
      tipo: "metcon",
      titulo: "WOD Principal",
      conteudo: "15 min AMRAP:\n8 bench press\n12 dumbbell rows",
      tempo_cap: "15 min"
    },
    {
      ordem: 4,
      tipo: "cooldown",
      titulo: "Resfriamento",
      conteudo: "Alongamento"
    }
  ],
  
  variacoes: [
    {
      nome: "RX",
      descricao: "95 lbs, 35 lbs DB"
    },
    {
      nome: "Scaled",
      descricao: "65 lbs, 20 lbs DB"
    }
  ]
};

// Enviar
fetch('/admin/wods/completo', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(wodCompleto)
})
.then(response => response.json())
.then(data => {
  if (data.type === 'success') {
    console.log('WOD criado:', data.data);
    // Redirecionar para página de detalhes
  } else {
    console.error('Erro:', data.message);
    // Mostrar erro para usuário
  }
});
```

## React Hook Completo

```typescript
import React, { useState } from 'react';

type BlocoTipo = 'warmup' | 'strength' | 'metcon' | 'accessory' | 'cooldown' | 'note';

interface Bloco {
  ordem?: number;
  tipo: BlocoTipo;
  titulo?: string;
  conteudo: string;
  tempo_cap?: string;
}

interface Variacao {
  nome: string;
  descricao?: string;
}

interface WodData {
  titulo: string;
  descricao?: string;
  data: string;
  status?: 'draft' | 'published';
  blocos: Bloco[];
  variacoes?: Variacao[];
}

export function CreateWodForm() {
  const [formData, setFormData] = useState<WodData>({
    titulo: '',
    data: new Date().toISOString().split('T')[0],
    blocos: [
      { tipo: 'warmup', conteudo: '' }
    ]
  });

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('/admin/wods/completo', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
      });

      const data = await response.json();

      if (data.type === 'success') {
        alert('WOD criado com sucesso!');
        // Limpar form ou redirecionar
      } else {
        setError(data.message || 'Erro ao criar WOD');
      }
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {error && <div className="error">{error}</div>}
      
      <input
        type="text"
        placeholder="Título"
        value={formData.titulo}
        onChange={(e) => setFormData({...formData, titulo: e.target.value})}
        required
      />
      
      <input
        type="date"
        value={formData.data}
        onChange={(e) => setFormData({...formData, data: e.target.value})}
        required
      />
      
      {/* Renderizar blocos */}
      {formData.blocos.map((bloco, idx) => (
        <div key={idx}>
          <select
            value={bloco.tipo}
            onChange={(e) => {
              const newBlocos = [...formData.blocos];
              newBlocos[idx].tipo = e.target.value as BlocoTipo;
              setFormData({...formData, blocos: newBlocos});
            }}
          >
            <option value="warmup">Aquecimento</option>
            <option value="strength">Força</option>
            <option value="metcon">WOD</option>
            <option value="cooldown">Resfriamento</option>
          </select>
          
          <textarea
            placeholder="Conteúdo"
            value={bloco.conteudo}
            onChange={(e) => {
              const newBlocos = [...formData.blocos];
              newBlocos[idx].conteudo = e.target.value;
              setFormData({...formData, blocos: newBlocos});
            }}
          />
        </div>
      ))}
      
      <button type="submit" disabled={loading}>
        {loading ? 'Criando...' : 'Criar WOD'}
      </button>
    </form>
  );
}
```

## Tipos de Bloco Aceitos

| Tipo | Descrição |
|------|-----------|
| `warmup` | Aquecimento/Mobilidade |
| `strength` | Trabalho de força |
| `metcon` | Condicionamento metabolista (WOD) |
| `accessory` | Trabalho auxiliar |
| `cooldown` | Resfriamento |
| `note` | Anotação/Instruções |

## Status Possíveis

- `draft` - Rascunho (padrão)
- `published` - Publicado

## Códigos de Resposta

| Código | Significado |
|--------|-------------|
| `201` | Criado com sucesso |
| `422` | Validação falhou |
| `409` | Data duplicada |
| `500` | Erro do servidor |

## Dicas

1. **Data Obrigatória**: Formato YYYY-MM-DD
2. **Blocos Obrigatórios**: Pelo menos 1 bloco
3. **Conteúdo**: Use `\n` para quebras de linha
4. **Variações Opcionais**: Se não enviar, cria "RX" por padrão
5. **Token**: Sempre envie no header `Authorization: Bearer {token}`

## Exemplo com Erro

```javascript
// Isso vai falhar (sem blocos)
{
  titulo: "WOD Inválido",
  data: "2026-01-14",
  blocos: [] // ERRO!
}

// Resposta
{
  type: "error",
  message: "Validação falhou",
  errors: ["Pelo menos um bloco é obrigatório"]
}
```

## Arquivos de Documentação

Mais detalhes em:
- `README_WOD_UNIFICADO.md` - Resumo completo
- `WOD_CRIAR_COMPLETO.md` - Documentação técnica
- `exemplo_wod_completo.json` - Exemplo pronto
- `test_wod_completo.sh` - Testes com cURL

## Suporte

Dúvidas? Veja a documentação ou execute `test_wod_completo.sh`
