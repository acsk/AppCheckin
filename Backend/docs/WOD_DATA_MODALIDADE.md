# 🎯 Buscar WOD por Data e Modalidade

## Cenário de Uso

### Fluxo Completo

1. **Admin cria WOD**:
   - Data: `2026-01-15` (segunda-feira)
   - Modalidade: `CrossFit` (id: 1)
   - WOD: "Fran"

2. **Aluno abre app**:
   - Visualiza turma: `CrossFit - Segunda 18h`
   - App detecta: data atual = `2026-01-15` + modalidade da turma = `1`

3. **App busca WOD**:
   - Endpoint: `GET /admin/wods/buscar?data=2026-01-15&modalidade_id=1`
   - Retorna: WOD "Fran" completo com blocos e variações

---

## 🔍 Endpoints Disponíveis

### 1. Buscar WOD Específico (Novo)

**Endpoint:** `GET /admin/wods/buscar`

**Query Parameters:**
- `data` (obrigatório): Data no formato `YYYY-MM-DD`
- `modalidade_id` (obrigatório): ID da modalidade

**Exemplo de Requisição:**
```bash
GET /admin/wods/buscar?data=2026-01-15&modalidade_id=1
Authorization: Bearer {token}
X-Tenant-ID: 4
```

**Resposta Sucesso (200):**
```json
{
  "type": "success",
  "message": "WOD encontrado",
  "data": {
    "id": 99,
    "tenant_id": 4,
    "modalidade_id": 1,
    "data": "2026-01-15",
    "titulo": "Fran",
    "descricao": "21-15-9 Thrusters + Pull-ups",
    "status": "published",
    "criado_por": 1,
    "criado_por_nome": "Admin",
    "modalidade_nome": "CrossFit",
    "modalidade_cor": "#f97316",
    "blocos": [
      {
        "id": 150,
        "wod_id": 99,
        "ordem": 1,
        "tipo": "metcon",
        "titulo": "For Time",
        "conteudo": "21-15-9\nThrusters (95/65 lb)\nPull-ups",
        "tempo_cap": 10
      }
    ],
    "variacoes": [
      {
        "id": 80,
        "wod_id": 99,
        "nome": "RX",
        "descricao": "95/65 lb"
      },
      {
        "id": 81,
        "wod_id": 99,
        "nome": "Scaled",
        "descricao": "65/45 lb + Jumping Pull-ups"
      }
    ],
    "resultados": []
  }
}
```

**Resposta WOD Não Encontrado (404):**
```json
{
  "type": "error",
  "message": "Nenhum WOD encontrado para essa data e modalidade",
  "data": null
}
```

**Resposta Erro de Validação (422):**
```json
{
  "type": "error",
  "message": "Validação falhou",
  "errors": [
    "Parâmetro data é obrigatório",
    "Parâmetro modalidade_id é obrigatório"
  ]
}
```

---

### 2. Listar WODs (Atualizado)

**Endpoint:** `GET /admin/wods`

**Query Parameters (todos opcionais):**
- `status`: `draft`, `published`, `archived`
- `data`: Data específica `YYYY-MM-DD`
- `data_inicio` + `data_fim`: Intervalo de datas
- `modalidade_id`: Filtrar por modalidade (NOVO)

**Exemplos:**

```bash
# Listar todos WODs de CrossFit
GET /admin/wods?modalidade_id=1

# Listar WODs publicados de CrossFit em janeiro
GET /admin/wods?status=published&modalidade_id=1&data_inicio=2026-01-01&data_fim=2026-01-31

# Listar WODs de uma data específica
GET /admin/wods?data=2026-01-15&modalidade_id=1
```

---

### 3. Listar Modalidades

**Endpoint:** `GET /admin/wods/modalidades`

Retorna todas modalidades ativas do tenant para popular dropdowns.

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
      "ativo": 1
    }
  ]
}
```

---

## 📱 Integração Frontend

### Cenário: Exibir WOD do Dia na Tela de Turma

```javascript
// Componente: TurmaDetalhes.jsx
import { useEffect, useState } from 'react';

const TurmaDetalhes = ({ turma }) => {
  const [wodDoDia, setWodDoDia] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    carregarWodDoDia();
  }, [turma.modalidade_id]);

  const carregarWodDoDia = async () => {
    try {
      setLoading(true);
      
      // Data atual no formato YYYY-MM-DD
      const dataHoje = new Date().toISOString().split('T')[0];
      
      // Buscar WOD pela data e modalidade da turma
      const response = await fetch(
        `/admin/wods/buscar?data=${dataHoje}&modalidade_id=${turma.modalidade_id}`,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'X-Tenant-ID': tenantId
          }
        }
      );

      const result = await response.json();

      if (response.ok) {
        setWodDoDia(result.data);
        setError(null);
      } else if (response.status === 404) {
        // Nenhum WOD cadastrado para hoje
        setWodDoDia(null);
        setError('Nenhum WOD cadastrado para hoje');
      } else {
        throw new Error(result.message);
      }
    } catch (err) {
      console.error('Erro ao carregar WOD:', err);
      setError('Erro ao carregar WOD do dia');
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div>Carregando WOD...</div>;
  
  if (error) return (
    <div className="alert alert-warning">
      <p>{error}</p>
      <button onClick={carregarWodDoDia}>Tentar novamente</button>
    </div>
  );

  if (!wodDoDia) return (
    <div className="alert alert-info">
      Nenhum WOD cadastrado para hoje
    </div>
  );

  return (
    <div className="wod-container">
      <h2>{wodDoDia.titulo}</h2>
      <span className="badge" style={{ backgroundColor: wodDoDia.modalidade_cor }}>
        {wodDoDia.modalidade_nome}
      </span>
      
      <p className="descricao">{wodDoDia.descricao}</p>

      {/* Blocos */}
      <div className="blocos">
        {wodDoDia.blocos.map(bloco => (
          <div key={bloco.id} className={`bloco tipo-${bloco.tipo}`}>
            <h4>{bloco.titulo}</h4>
            <pre>{bloco.conteudo}</pre>
            {bloco.tempo_cap && (
              <span className="tempo-cap">Time Cap: {bloco.tempo_cap} min</span>
            )}
          </div>
        ))}
      </div>

      {/* Variações */}
      {wodDoDia.variacoes.length > 0 && (
        <div className="variacoes">
          <h4>Variações:</h4>
          {wodDoDia.variacoes.map(variacao => (
            <div key={variacao.id} className="variacao">
              <strong>{variacao.nome}:</strong> {variacao.descricao}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default TurmaDetalhes;
```

---

## 🔄 Fluxo de Dados

```
┌─────────────────┐
│   Admin Panel   │
│  (Criar WOD)    │
└────────┬────────┘
         │
         │ POST /admin/wods/completo
         │ {
         │   "data": "2026-01-15",
         │   "modalidade_id": 1,
         │   "titulo": "Fran",
         │   "blocos": [...]
         │ }
         ▼
┌─────────────────┐
│   Banco Dados   │
│   (wods table)  │
└────────┬────────┘
         │
         │
┌────────▼────────────────────────────────┐
│          App Mobile / Frontend          │
│                                         │
│  1. Usuário abre tela de turma          │
│     - Turma: CrossFit Segunda 18h      │
│     - modalidade_id: 1                  │
│                                         │
│  2. App detecta data atual              │
│     - data: 2026-01-15                  │
│                                         │
│  3. Busca WOD                           │
│     GET /admin/wods/buscar?             │
│         data=2026-01-15&                │
│         modalidade_id=1                 │
│                                         │
│  4. Exibe WOD completo                  │
│     - Título, blocos, variações         │
└─────────────────────────────────────────┘
```

---

## 🎯 Regras de Negócio

### 1. Status do WOD
- Apenas WODs com `status = 'published'` são retornados pela busca
- WODs `draft` ou `archived` não aparecem no app

### 2. Unicidade
- Pode haver apenas **1 WOD por data por modalidade por tenant**
- Constraint no banco: `UNIQUE(tenant_id, data, modalidade_id)` (opcional, mas recomendado)

### 3. Fallback
- Se não houver WOD para a data/modalidade: retornar 404
- Frontend pode exibir mensagem ou sugerir WODs de outros dias

### 4. Turmas vs WODs
- **Turmas** são recorrentes (toda segunda às 18h)
- **WODs** são únicos por data (15/01/2026)
- A turma serve como filtro (modalidade) para buscar o WOD do dia

---

## 🔧 Melhorias Futuras

### 1. Adicionar Constraint de Unicidade
```sql
ALTER TABLE wods 
ADD UNIQUE KEY uq_tenant_data_modalidade (tenant_id, data, modalidade_id);
```

### 2. Cache de WODs
Cachear WODs publicados por 1 hora para reduzir queries.

### 3. WOD Padrão
Permitir configurar WOD padrão/placeholder quando não há WOD cadastrado.

### 4. Histórico
Endpoint para listar últimos 7 dias de WODs da modalidade.

### 5. Notificações
Notificar alunos quando novo WOD é publicado para a modalidade deles.

---

## ✅ Checklist de Implementação

- [x] Adicionar `modalidade_id` na tabela `wods`
- [x] Criar método `findByDataModalidade()` no Model
- [x] Criar endpoint `buscarPorDataModalidade()` no Controller
- [x] Adicionar rota `GET /admin/wods/buscar`
- [x] Adicionar filtro `modalidade_id` no endpoint `index()`
- [ ] Atualizar formulário frontend para incluir `modalidade_id`
- [ ] Implementar tela de visualização de WOD do dia
- [ ] Adicionar constraint de unicidade (opcional)
- [ ] Implementar cache de WODs (opcional)
- [ ] Adicionar testes automatizados

---

## 🧪 Testando

### 1. Criar WOD
```bash
POST /admin/wods/completo
{
  "titulo": "Fran",
  "data": "2026-01-15",
  "modalidade_id": 1,
  "blocos": [
    {
      "ordem": 1,
      "tipo": "metcon",
      "titulo": "For Time",
      "conteudo": "21-15-9\nThrusters (95/65)\nPull-ups",
      "tempo_cap": 10
    }
  ]
}
```

### 2. Buscar WOD
```bash
GET /admin/wods/buscar?data=2026-01-15&modalidade_id=1
```

### 3. Listar WODs por Modalidade
```bash
GET /admin/wods?modalidade_id=1&status=published
```

Pronto! Agora você tem um sistema completo de WOD por data + modalidade! 🎉
