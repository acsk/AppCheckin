# Interface Frontend - Como Estruturar o WOD

## Componentes Sugeridos

```
┌─────────────────────────────────────────────────┐
│           Form de Criação de WOD                 │
├─────────────────────────────────────────────────┤
│                                                  │
│  Titulo:  [________________]                     │
│  Data:    [2026-01-14____]                       │
│  Status:  [○ Draft  ● Published]                 │
│                                                  │
│  Descrição:  [____________________]              │
│             [____________________]               │
│                                                  │
├─────────────────────────────────────────────────┤
│  BLOCOS                            [+ Adicionar] │
├─────────────────────────────────────────────────┤
│  ┌────────────────────────────────────────────┐ │
│  │ Bloco 1: Aquecimento (warmup)         [↑↓] │ │
│  ├────────────────────────────────────────────┤ │
│  │ Título:  [Aquecimento_________]        [X] │ │
│  │ Tipo:    [warmup ▼]                       │ │
│  │ Tempo:   [5 min__]                        │ │
│  │ Conteúdo:                                 │ │
│  │ [5 min rope skip                       ]  │ │
│  │ [5 min bike                            ]  │ │
│  │ [10 air squats                         ]  │ │
│  └────────────────────────────────────────────┘ │
│                                                  │
│  ┌────────────────────────────────────────────┐ │
│  │ Bloco 2: Força (strength)             [↑↓] │ │
│  ├────────────────────────────────────────────┤ │
│  │ Título:  [Back Squat________]          [X] │ │
│  │ Tipo:    [strength ▼]                     │ │
│  │ Tempo:   [20 min_]                        │ │
│  │ Conteúdo:                                 │ │
│  │ [Find 1RM for the day               ]    │ │
│  └────────────────────────────────────────────┘ │
│                                                  │
│  ┌────────────────────────────────────────────┐ │
│  │ Bloco 3: WOD (metcon)                [↑↓] │ │
│  ├────────────────────────────────────────────┤ │
│  │ Título:  [WOD Principal_____]          [X] │ │
│  │ Tipo:    [metcon ▼]                       │ │
│  │ Tempo:   [20 min_]                        │ │
│  │ Conteúdo:                                 │ │
│  │ [20 min AMRAP:                       ]   │ │
│  │ [10 thrusters (65/95 lb)            ]   │ │
│  │ [10 box jumps (20/24 inch)          ]   │ │
│  │ [10 cal row                         ]   │ │
│  └────────────────────────────────────────────┘ │
│                                                  │
│  ┌────────────────────────────────────────────┐ │
│  │ Bloco 4: Cooldown (cooldown)         [↑↓] │ │
│  ├────────────────────────────────────────────┤ │
│  │ Título:  [Resfriamento____]            [X] │ │
│  │ Tipo:    [cooldown ▼]                     │ │
│  │ Tempo:   [5 min__]                        │ │
│  │ Conteúdo:                                 │ │
│  │ [Alongamento e mobilidade          ]    │ │
│  └────────────────────────────────────────────┘ │
│                                                  │
├─────────────────────────────────────────────────┤
│  VARIAÇÕES                         [+ Adicionar]│
├─────────────────────────────────────────────────┤
│  ┌────────────────────────────────────────────┐ │
│  │ Variação 1                             [X] │ │
│  ├────────────────────────────────────────────┤ │
│  │ Nome:  [RX________]                       │ │
│  │ Desc:  [65/95 lb thrusters, 20/24 box]   │ │
│  └────────────────────────────────────────────┘ │
│                                                  │
│  ┌────────────────────────────────────────────┐ │
│  │ Variação 2                             [X] │ │
│  ├────────────────────────────────────────────┤ │
│  │ Nome:  [Scaled___]                        │ │
│  │ Desc:  [45/65 lb, 18/20 box_________]     │ │
│  └────────────────────────────────────────────┘ │
│                                                  │
├─────────────────────────────────────────────────┤
│  [ Cancelar ]                    [ Criar WOD ]  │
└─────────────────────────────────────────────────┘
```

## Estrutura de Dados no Frontend

### Estado do Componente (React)

```typescript
interface WodBloco {
  ordem: number;
  tipo: 'warmup' | 'strength' | 'metcon' | 'accessory' | 'cooldown' | 'note';
  titulo?: string;
  conteudo: string;
  tempo_cap?: string;
  atividades?: any[];
}

interface WodVariacao {
  nome: string;
  descricao?: string;
}

interface WodFormData {
  titulo: string;
  descricao?: string;
  data: string;
  status: 'draft' | 'published';
  blocos: WodBloco[];
  variacoes: WodVariacao[];
}

// Estado inicial
const [formData, setFormData] = useState<WodFormData>({
  titulo: '',
  descricao: '',
  data: new Date().toISOString().split('T')[0],
  status: 'draft',
  blocos: [
    {
      ordem: 1,
      tipo: 'warmup',
      titulo: '',
      conteudo: '',
      tempo_cap: '5 min'
    }
  ],
  variacoes: [
    {
      nome: 'RX',
      descricao: ''
    }
  ]
});
```

## Exemplo Completo em React

```typescript
import React, { useState } from 'react';

type BlocoTipo = 'warmup' | 'strength' | 'metcon' | 'accessory' | 'cooldown' | 'note';

interface BlocoForm {
  ordem: number;
  tipo: BlocoTipo;
  titulo: string;
  conteudo: string;
  tempo_cap: string;
}

interface VariacaoForm {
  nome: string;
  descricao: string;
}

interface WodFormData {
  titulo: string;
  descricao: string;
  data: string;
  status: 'draft' | 'published';
  blocos: BlocoForm[];
  variacoes: VariacaoForm[];
}

export function CriarWodForm() {
  const [formData, setFormData] = useState<WodFormData>({
    titulo: '',
    descricao: '',
    data: new Date().toISOString().split('T')[0],
    status: 'draft',
    blocos: [
      {
        ordem: 1,
        tipo: 'warmup',
        titulo: 'Aquecimento',
        conteudo: '',
        tempo_cap: '5 min'
      }
    ],
    variacoes: [
      {
        nome: 'RX',
        descricao: ''
      }
    ]
  });

  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Atualizar campo principal
  const handleMainChange = (field: keyof WodFormData, value: any) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  // Atualizar bloco
  const handleBlocoChange = (index: number, field: string, value: any) => {
    const newBlocos = [...formData.blocos];
    newBlocos[index] = {
      ...newBlocos[index],
      [field]: value
    } as BlocoForm;
    setFormData(prev => ({
      ...prev,
      blocos: newBlocos
    }));
  };

  // Adicionar bloco
  const addBloco = () => {
    const novoBloco: BlocoForm = {
      ordem: formData.blocos.length + 1,
      tipo: 'metcon',
      titulo: '',
      conteudo: '',
      tempo_cap: '10 min'
    };
    setFormData(prev => ({
      ...prev,
      blocos: [...prev.blocos, novoBloco]
    }));
  };

  // Remover bloco
  const removeBloco = (index: number) => {
    setFormData(prev => ({
      ...prev,
      blocos: prev.blocos.filter((_, i) => i !== index)
    }));
  };

  // Atualizar variação
  const handleVariacaoChange = (index: number, field: string, value: string) => {
    const newVariacoes = [...formData.variacoes];
    newVariacoes[index] = {
      ...newVariacoes[index],
      [field]: value
    };
    setFormData(prev => ({
      ...prev,
      variacoes: newVariacoes
    }));
  };

  // Adicionar variação
  const addVariacao = () => {
    setFormData(prev => ({
      ...prev,
      variacoes: [...prev.variacoes, { nome: '', descricao: '' }]
    }));
  };

  // Remover variação
  const removeVariacao = (index: number) => {
    setFormData(prev => ({
      ...prev,
      variacoes: prev.variacoes.filter((_, i) => i !== index)
    }));
  };

  // Enviar formulário
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch('/admin/wods/completo', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify(formData)
      });

      const data = await response.json();

      if (data.type === 'success') {
        // WOD criado com sucesso
        alert('WOD criado com sucesso!');
        // Redirecionar ou atualizar lista
      } else {
        setError(data.message || 'Erro ao criar WOD');
      }
    } catch (err: any) {
      setError(err.message);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="wod-form">
      <h1>Criar WOD Completo</h1>

      {error && <div className="error">{error}</div>}

      {/* Campos Principais */}
      <div className="form-group">
        <label>Título *</label>
        <input
          type="text"
          value={formData.titulo}
          onChange={(e) => handleMainChange('titulo', e.target.value)}
          required
        />
      </div>

      <div className="form-group">
        <label>Descrição</label>
        <textarea
          value={formData.descricao}
          onChange={(e) => handleMainChange('descricao', e.target.value)}
        />
      </div>

      <div className="form-group">
        <label>Data *</label>
        <input
          type="date"
          value={formData.data}
          onChange={(e) => handleMainChange('data', e.target.value)}
          required
        />
      </div>

      <div className="form-group">
        <label>Status</label>
        <select
          value={formData.status}
          onChange={(e) => handleMainChange('status', e.target.value)}
        >
          <option value="draft">Draft</option>
          <option value="published">Publicado</option>
        </select>
      </div>

      {/* Blocos */}
      <div className="section">
        <h2>Blocos</h2>
        {formData.blocos.map((bloco, index) => (
          <div key={index} className="bloco-card">
            <div className="bloco-header">
              <h3>Bloco {index + 1}</h3>
              <button
                type="button"
                onClick={() => removeBloco(index)}
                className="btn-remove"
              >
                X
              </button>
            </div>

            <div className="form-group">
              <label>Título</label>
              <input
                type="text"
                value={bloco.titulo}
                onChange={(e) => handleBlocoChange(index, 'titulo', e.target.value)}
                placeholder="Ex: Aquecimento"
              />
            </div>

            <div className="form-group">
              <label>Tipo</label>
              <select
                value={bloco.tipo}
                onChange={(e) => handleBlocoChange(index, 'tipo', e.target.value)}
              >
                <option value="warmup">Aquecimento</option>
                <option value="strength">Força</option>
                <option value="metcon">WOD (Metcon)</option>
                <option value="accessory">Acessório</option>
                <option value="cooldown">Resfriamento</option>
                <option value="note">Nota</option>
              </select>
            </div>

            <div className="form-group">
              <label>Tempo (Cap)</label>
              <input
                type="text"
                value={bloco.tempo_cap}
                onChange={(e) => handleBlocoChange(index, 'tempo_cap', e.target.value)}
                placeholder="Ex: 5 min, 20 min"
              />
            </div>

            <div className="form-group">
              <label>Conteúdo *</label>
              <textarea
                value={bloco.conteudo}
                onChange={(e) => handleBlocoChange(index, 'conteudo', e.target.value)}
                placeholder="Descrição detalhada do bloco..."
                rows={5}
              />
            </div>
          </div>
        ))}

        <button
          type="button"
          onClick={addBloco}
          className="btn-add"
        >
          + Adicionar Bloco
        </button>
      </div>

      {/* Variações */}
      <div className="section">
        <h2>Variações</h2>
        {formData.variacoes.map((variacao, index) => (
          <div key={index} className="variacao-card">
            <div className="variacao-header">
              <h3>Variação {index + 1}</h3>
              <button
                type="button"
                onClick={() => removeVariacao(index)}
                className="btn-remove"
              >
                X
              </button>
            </div>

            <div className="form-group">
              <label>Nome</label>
              <input
                type="text"
                value={variacao.nome}
                onChange={(e) => handleVariacaoChange(index, 'nome', e.target.value)}
                placeholder="Ex: RX, Scaled, Beginner"
              />
            </div>

            <div className="form-group">
              <label>Descrição</label>
              <textarea
                value={variacao.descricao}
                onChange={(e) => handleVariacaoChange(index, 'descricao', e.target.value)}
                placeholder="Ex: 65/95 lbs, 20/24 inch box"
                rows={2}
              />
            </div>
          </div>
        ))}

        <button
          type="button"
          onClick={addVariacao}
          className="btn-add"
        >
          + Adicionar Variação
        </button>
      </div>

      {/* Botões de Ação */}
      <div className="form-actions">
        <button type="button" className="btn-cancel">
          Cancelar
        </button>
        <button
          type="submit"
          disabled={isLoading}
          className="btn-submit"
        >
          {isLoading ? 'Criando...' : 'Criar WOD'}
        </button>
      </div>
    </form>
  );
}
```

## CSS Sugerido

```css
.wod-form {
  max-width: 1000px;
  margin: 20px auto;
  padding: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.form-group {
  margin: 20px 0;
  display: flex;
  flex-direction: column;
}

.form-group label {
  margin-bottom: 8px;
  font-weight: 600;
  color: #333;
}

.form-group input,
.form-group textarea,
.form-group select {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  font-family: inherit;
}

.form-group textarea {
  resize: vertical;
  min-height: 100px;
}

.section {
  margin: 30px 0;
  padding: 20px;
  background: #f9f9f9;
  border-radius: 8px;
  border-left: 4px solid #007bff;
}

.section h2 {
  margin-top: 0;
  color: #333;
}

.bloco-card,
.variacao-card {
  margin: 15px 0;
  padding: 15px;
  background: white;
  border-radius: 6px;
  border: 1px solid #eee;
}

.bloco-header,
.variacao-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.bloco-header h3,
.variacao-header h3 {
  margin: 0;
  font-size: 18px;
}

.btn-remove {
  background: #dc3545;
  color: white;
  border: none;
  padding: 5px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
}

.btn-remove:hover {
  background: #c82333;
}

.btn-add {
  background: #28a745;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 4px;
  cursor: pointer;
  margin-top: 10px;
  font-weight: 600;
}

.btn-add:hover {
  background: #218838;
}

.form-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 30px;
}

.btn-cancel,
.btn-submit {
  padding: 12px 30px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 600;
}

.btn-cancel {
  background: #6c757d;
  color: white;
}

.btn-cancel:hover {
  background: #5a6268;
}

.btn-submit {
  background: #007bff;
  color: white;
}

.btn-submit:hover:not(:disabled) {
  background: #0056b3;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error {
  background: #f8d7da;
  color: #721c24;
  padding: 12px;
  border-radius: 4px;
  margin-bottom: 20px;
  border: 1px solid #f5c6cb;
}
```

## Dicas de Implementação

1. **Validação em Tempo Real**: Valide título, data e pelo menos 1 bloco
2. **Drag & Drop**: Permita reordenar blocos e variações
3. **Templates**: Ofereça templates pré-preenchidos
4. **Preview**: Mostre preview do WOD antes de enviar
5. **Salvamento Automático**: Salve em localStorage enquanto preenche
6. **Confirmação**: Peça confirmação antes de criar
