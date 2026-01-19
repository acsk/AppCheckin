# Relacionamento WOD com Modalidade

## Resumo das Alterações

### ✅ 1. Migration de Banco de Dados
- **Arquivo**: `database/migrations/064_add_modalidade_id_to_wods.sql`
- **Alterações**:
  - Adicionada coluna `modalidade_id INT NULL` na tabela `wods`
  - Adicionada constraint `fk_wods_modalidade` (FK para `modalidades.id`)
  - Adicionado índice `idx_wods_modalidade`

### ✅ 2. Model Wod
- **Arquivo**: `app/Models/Wod.php`
- **Alterações**:
  - Método `create()`: Incluído campo `modalidade_id` no INSERT
  - Método `findById()`: Adicionado JOIN com tabela `modalidades` para retornar `modalidade_nome`
  - Método `listByTenant()`: Adicionado JOIN com tabela `modalidades`

### ✅ 3. Controller WodController
- **Arquivo**: `app/Controllers/WodController.php`
- **Alterações**:
  - Adicionada importação de `App\Models\Modalidade`
  - Adicionada propriedade `$modalidadeModel`
  - Método `create()`: Validação obrigatória de `modalidade_id`
  - Método `createCompleto()`: Validação obrigatória de `modalidade_id`
  - **Novo método**: `listarModalidades()` - Endpoint para buscar modalidades disponíveis

### ✅ 4. Rotas
- **Arquivo**: `routes/api.php`
- **Nova rota**: `GET /admin/wods/modalidades` - Lista modalidades ativas do tenant

## 📋 Estrutura da Requisição

### POST /admin/wods
```json
{
  "titulo": "WOD 01/01/2026",
  "data": "2026-01-01",
  "modalidade_id": 1,  // ⚠️ OBRIGATÓRIO
  "descricao": "Descrição opcional",
  "status": "published"
}
```

### POST /admin/wods/completo
```json
{
  "titulo": "Fran",
  "data": "2026-01-15",
  "modalidade_id": 1,  // ⚠️ OBRIGATÓRIO
  "descricao": "21-15-9 Thrusters + Pull-ups",
  "blocos": [
    {
      "ordem": 1,
      "tipo": "metcon",
      "titulo": "For Time",
      "conteudo": "21-15-9\nThrusters (95/65 lb)\nPull-ups",
      "tempo_cap": 10
    }
  ],
  "variacoes": [
    {
      "nome": "RX",
      "descricao": "95/65 lb"
    },
    {
      "nome": "Scaled",
      "descricao": "65/45 lb + Jumping Pull-ups"
    }
  ]
}
```

## 🔍 Novo Endpoint: Listar Modalidades

### GET /admin/wods/modalidades

**Resposta:**
```json
{
  "type": "success",
  "message": "Modalidades listadas com sucesso",
  "data": [
    {
      "id": 1,
      "nome": "CrossFit",
      "descricao": "Treinamento funcional de alta intensidade",
      "cor": "#f97316",
      "icone": "dumbbell",
      "ativo": 1,
      "planos_count": 3,
      "planos": [
        {
          "id": 1,
          "nome": "Plano Básico",
          "valor": "150.00",
          "checkins_semanais": 3
        }
      ]
    }
  ]
}
```

## 📱 Integração com Frontend

### Dropdown de Modalidades

No formulário de criação de WOD, adicione um dropdown que busca as modalidades:

```javascript
// 1. Buscar modalidades ao carregar o formulário
const carregarModalidades = async () => {
  const response = await fetch('/admin/wods/modalidades', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'X-Tenant-ID': tenantId
    }
  });
  const result = await response.json();
  return result.data;
};

// 2. Renderizar dropdown
<select name="modalidade_id" required>
  <option value="">Selecione uma modalidade</option>
  {modalidades.map(m => (
    <option key={m.id} value={m.id}>
      {m.nome}
    </option>
  ))}
</select>

// 3. Incluir no payload de criação
const criarWod = async (dados) => {
  const payload = {
    titulo: dados.titulo,
    data: dados.data,
    modalidade_id: parseInt(dados.modalidade_id), // ⚠️ OBRIGATÓRIO
    blocos: dados.blocos,
    variacoes: dados.variacoes
  };
  
  const response = await fetch('/admin/wods/completo', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
      'X-Tenant-ID': tenantId
    },
    body: JSON.stringify(payload)
  });
  
  return await response.json();
};
```

## ✅ Validações

### Backend
- `modalidade_id` é **obrigatório** em ambos endpoints (POST /admin/wods e POST /admin/wods/completo)
- Retorna erro 422 se não fornecido

### Mensagens de Erro
```json
{
  "type": "error",
  "message": "Validação falhou",
  "errors": [
    "Modalidade é obrigatória"
  ]
}
```

## 🎯 Benefícios

1. **Organização**: WODs agora são categorizados por modalidade
2. **Filtros**: Possível filtrar WODs por modalidade no futuro
3. **Relatórios**: Análise de WODs por tipo de treino
4. **UX**: Dropdown facilita seleção e previne erros
5. **Integridade**: FK garante que modalidade existe

## 🚀 Próximos Passos

1. ✅ Atualizar formulário de criação de WOD no frontend
2. ✅ Adicionar dropdown de modalidades
3. ⏳ Adicionar filtro por modalidade na listagem de WODs
4. ⏳ Criar dashboard com estatísticas por modalidade
5. ⏳ Permitir associar múltiplas modalidades a um WOD (opcional)

## 🧪 Teste

1. Acessar o formulário de criação de WOD
2. Verificar se dropdown de modalidades está disponível
3. Selecionar uma modalidade
4. Preencher demais campos obrigatórios
5. Submeter o formulário
6. Verificar na listagem se a modalidade aparece associada ao WOD
