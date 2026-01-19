# 📋 PASSO A PASSO - Como Implementar no Frontend

## ✅ Para o Time de Desenvolvimento Frontend

---

## PASSO 1: Entender o Novo Endpoint

### Endpoint Criado:
```
POST /admin/wods/completo
```

### Diferença:
- **Antes**: 5-7 chamadas de API para criar um WOD
- **Depois**: 1 única chamada para criar WOD completo

**Vantagem**: Mais rápido, mais consistente, menos erros

---

## PASSO 2: Ler a Documentação Correta

Leia NESTA ORDEM:

1. **FRONTEND_QUICK_START.md** (5 minutos)
   - Entender como chamar o endpoint
   - Ver exemplos de código prontos

2. **FRONTEND_WOD_FORM.md** (15 minutos)
   - Estrutura do formulário
   - Componente React completo
   - CSS pronto

3. **exemplo_wod_completo.json** (2 minutos)
   - Ver estrutura de dados real
   - Copiar e colar em testes

---

## PASSO 3: Preparar o Formulário

### Estrutura de Dados Necessária:

```typescript
interface BlocoForm {
  ordem?: number;           // Opcional, preenchido automaticamente
  tipo: string;             // warmup, strength, metcon, etc
  titulo?: string;          // Opcional
  conteudo: string;         // OBRIGATÓRIO - descrição do bloco
  tempo_cap?: string;       // Opcional - "5 min", "20 min", etc
}

interface VariacaoForm {
  nome: string;             // "RX", "Scaled", etc
  descricao?: string;       // Opcional
}

interface WodFormData {
  titulo: string;           // OBRIGATÓRIO
  descricao?: string;       // Opcional
  data: string;             // OBRIGATÓRIO - formato YYYY-MM-DD
  status?: string;          // draft ou published (padrão: draft)
  blocos: BlocoForm[];      // OBRIGATÓRIO - mínimo 1 bloco
  variacoes?: VariacaoForm[];
}
```

---

## PASSO 4: Implementar Componentes React

### Componente Principal (Form):

```typescript
import React, { useState } from 'react';

export function CriarWodCompleto() {
  const [formData, setFormData] = useState<WodFormData>({
    titulo: '',
    descricao: '',
    data: new Date().toISOString().split('T')[0],
    status: 'draft',
    blocos: [
      { tipo: 'warmup', conteudo: '' }
    ],
    variacoes: [
      { nome: 'RX', descricao: '' }
    ]
  });

  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await createWod(formData);
  };

  return (
    // Implementação do formulário
    // Veja FRONTEND_WOD_FORM.md para código completo
  );
}
```

---

## PASSO 5: Chamar o Endpoint

### Função de Criação:

```typescript
const createWod = async (wodData: WodFormData) => {
  setIsLoading(true);
  setError(null);

  try {
    const response = await fetch('/admin/wods/completo', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(wodData)
    });

    const result = await response.json();

    if (result.type === 'success') {
      // Sucesso!
      console.log('WOD criado:', result.data);
      // Redirecionar ou mostrar sucesso
    } else {
      // Erro de validação ou lógica
      setError(result.message || 'Erro ao criar WOD');
      if (result.errors) {
        console.error('Erros de validação:', result.errors);
      }
    }
  } catch (err: any) {
    // Erro de rede
    setError(err.message);
  } finally {
    setIsLoading(false);
  }
};
```

---

## PASSO 6: Tratar Diferentes Respostas

### Sucesso (201 Created):
```typescript
if (response.status === 201) {
  const { data } = result;
  // data contém:
  // - id do WOD
  // - blocos com IDs
  // - variações com IDs
  // - todos os dados retornados
  
  // Redirecionar para página de detalhes
  navigate(`/admin/wods/${data.id}`);
}
```

### Validação Falha (422):
```typescript
if (response.status === 422) {
  const { errors } = result;
  // errors é um array:
  // ["Título é obrigatório", "Pelo menos um bloco..."]
  
  // Mostrar erros para usuário
  setError(errors.join(', '));
}
```

### Data Duplicada (409):
```typescript
if (response.status === 409) {
  const { message } = result;
  // "Já existe um WOD para essa data"
  
  // Mostrar aviso ao usuário
  setError('Já existe um WOD para esta data. Escolha outra data.');
}
```

### Erro do Servidor (500):
```typescript
if (response.status === 500) {
  const { message, details } = result;
  // Erro interno do servidor
  
  setError('Erro ao criar WOD. Contate o suporte.');
  console.error(details);
}
```

---

## PASSO 7: Validar Dados Antes de Enviar

```typescript
const validateForm = (data: WodFormData): string[] => {
  const errors: string[] = [];

  if (!data.titulo || data.titulo.trim() === '') {
    errors.push('Título é obrigatório');
  }

  if (!data.data) {
    errors.push('Data é obrigatória');
  }

  if (!data.blocos || data.blocos.length === 0) {
    errors.push('Pelo menos um bloco é obrigatório');
  }

  data.blocos.forEach((bloco, index) => {
    if (!bloco.conteudo || bloco.conteudo.trim() === '') {
      errors.push(`Bloco ${index + 1}: conteúdo é obrigatório`);
    }
  });

  return errors;
};
```

---

## PASSO 8: Adicionar Funcionalidades Extras

### Adicionar/Remover Blocos:
```typescript
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

const removeBloco = (index: number) => {
  setFormData(prev => ({
    ...prev,
    blocos: prev.blocos.filter((_, i) => i !== index)
  }));
};
```

### Adicionar/Remover Variações:
```typescript
const addVariacao = () => {
  setFormData(prev => ({
    ...prev,
    variacoes: [...prev.variacoes, { nome: '', descricao: '' }]
  }));
};

const removeVariacao = (index: number) => {
  setFormData(prev => ({
    ...prev,
    variacoes: prev.variacoes.filter((_, i) => i !== index)
  }));
};
```

---

## PASSO 9: Reordenar Blocos (Drag & Drop)

```typescript
// Usar uma biblioteca como react-beautiful-dnd ou react-dnd
// ou implementar manualmente com setas de cima/baixo

const moveBlockUp = (index: number) => {
  if (index === 0) return;
  
  const newBlocos = [...formData.blocos];
  [newBlocos[index], newBlocos[index - 1]] = 
  [newBlocos[index - 1], newBlocos[index]];
  
  // Reordenar os campos de ordem
  newBlocos.forEach((bloco, idx) => {
    bloco.ordem = idx + 1;
  });
  
  setFormData(prev => ({
    ...prev,
    blocos: newBlocos
  }));
};
```

---

## PASSO 10: Testar

### Teste 1: WOD Simples
```json
{
  "titulo": "WOD Teste",
  "data": "2026-01-20",
  "blocos": [
    { "tipo": "warmup", "conteudo": "5 min bike" }
  ]
}
```

### Teste 2: WOD Completo
Copie dados de `exemplo_wod_completo.json`

### Teste 3: Validação
Enviar sem título, sem data, sem blocos para testar erros

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

- [ ] Ler FRONTEND_QUICK_START.md
- [ ] Ler FRONTEND_WOD_FORM.md
- [ ] Analisar exemplo_wod_completo.json
- [ ] Implementar estrutura de dados (interfaces)
- [ ] Implementar componente formulário
- [ ] Implementar função de submissão
- [ ] Adicionar validações
- [ ] Tratar todas as respostas (201, 422, 409, 500)
- [ ] Adicionar feedback visual (loading, erros)
- [ ] Testar com exemplo simples
- [ ] Testar com exemplo completo
- [ ] Testar casos de erro
- [ ] Adicionar funcionalidades extras (reordenar, etc)
- [ ] Revisar UX/UI
- [ ] Fazer merge para main

---

## 📌 Pontos Importantes

1. **Token Obrigatório**: Sempre enviar `Authorization: Bearer {token}`

2. **Data em YYYY-MM-DD**: Formato correto para API

3. **Conteúdo do Bloco**: Campo obrigatório que descreve o bloco

4. **Variações Opcionais**: Se não enviar, será criada "RX" automaticamente

5. **Uma Requisição**: Tudo é salvo em uma única chamada

6. **Transação ACID**: Tudo ou nada - sem dados parciais

---

## 🎓 Resumo Rápido

| Fase | Ação | Tempo |
|------|------|-------|
| 1 | Ler docs | 20 min |
| 2 | Preparar dados | 30 min |
| 3 | Implementar form | 1-2 horas |
| 4 | Testar | 30 min |
| 5 | Revisar | 30 min |
| **Total** | | **3 horas** |

---

## 📞 Suporte

Se tiver dúvidas:
1. Consulte FRONTEND_QUICK_START.md
2. Veja exemplo em exemplo_wod_completo.json
3. Execute test_wod_completo.sh para ver funcionando
4. Leia WOD_CRIAR_COMPLETO.md para detalhes técnicos

---

## 🚀 Próximas Features (Depois)

1. Duplicar WOD existente
2. Editar WOD completo em um só lugar
3. Template de WOD
4. Bulk upload de múltiplos WODs

---

**Status**: ✅ Pronto para começar!

Boa sorte! 🎉
