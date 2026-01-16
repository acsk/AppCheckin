# Endpoint: WOD do Dia

## Descrição
Retorna o WOD (Workout of the Day) agendado para hoje, levando em consideração a modalidade do usuário.

## Endpoint
```
GET /api/mobile/wod/hoje
```

## Headers Obrigatórios
```
Authorization: Bearer {token_jwt}
Content-Type: application/json
```

## Resposta - Sucesso (200)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "titulo": "Natação",
    "descricao": "Treino de resistência",
    "data": "2026-01-15",
    "status": "published",
    "modalidade_id": 4,
    "blocos": [
      {
        "id": 1,
        "ordem": 1,
        "tipo": "warmup",
        "titulo": "Aquecimento",
        "conteudo": "100m",
        "tempo_cap": "10 min"
      },
      {
        "id": 2,
        "ordem": 2,
        "tipo": "metcon",
        "titulo": "Treino Principal",
        "conteudo": "5 series x 100m",
        "tempo_cap": "20 min"
      }
    ],
    "variacoes": [
      {
        "id": 1,
        "nome": "RX",
        "descricao": null
      },
      {
        "id": 2,
        "nome": "Scaled",
        "descricao": "Para iniciantes"
      }
    ]
  }
}
```

## Resposta - Sem WOD Agendado (200)
```json
{
  "success": true,
  "data": null,
  "message": "Nenhum WOD agendado para hoje"
}
```

## Resposta - Erro (500)
```json
{
  "success": false,
  "error": "Erro ao carregar WOD do dia",
  "message": "Detalhes do erro..."
}
```

## Logica da Busca
1. Obtém a data de hoje
2. Obtém a modalidade do usuário logado
3. Busca um WOD publicado para:
   - **Mesmo tenant** do usuário
   - **Data de hoje**
   - **Modalidade do usuário** (se houver WOD com a modalidade do usuário)
   - **Modalidade nula** (como fallback, para WODs genéricos)

## Estrutura dos Dados

### WOD
- `id`: ID único do WOD
- `titulo`: Nome do treino (ex: "Natação", "CrossFit")
- `descricao`: Descrição detalhada
- `data`: Data do treino (YYYY-MM-DD)
- `status`: Status de publicação ("draft", "published", "archived")
- `modalidade_id`: ID da modalidade (NULL para WODs genéricos)
- `blocos`: Array de blocos do treino
- `variacoes`: Array de variações (RX, Scaled, etc)

### Bloco
- `id`: ID único
- `ordem`: Ordem de execução do bloco
- `tipo`: Tipo de bloco (warmup, strength, metcon, accessory, cooldown, note)
- `titulo`: Nome do bloco
- `conteudo`: Descrição do conteúdo
- `tempo_cap`: Tempo máximo para completar

### Variação
- `id`: ID único
- `nome`: Nome da variação (RX, Scaled, Beginner)
- `descricao`: Descrição adicional

## Exemplo de Uso (JavaScript/Fetch)

```javascript
const response = await fetch('/api/mobile/wod/hoje', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

const data = await response.json();

if (data.success && data.data) {
  console.log('WOD do dia:', data.data.titulo);
  console.log('Blocos:', data.data.blocos);
  console.log('Variações:', data.data.variacoes);
} else {
  console.log('Nenhum WOD agendado');
}
```

## Exemplo de Uso (Curl)

```bash
curl -X GET http://localhost:8080/api/mobile/wod/hoje \
  -H "Authorization: Bearer seu_token_aqui" \
  -H "Content-Type: application/json"
```
