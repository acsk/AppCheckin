# Exemplos de Integração - Assinaturas + Matrículas

## 1️⃣ Criar Matrícula COM Assinatura (Fluxo Recomendado)

### Frontend - React Component

```javascript
import { useState } from 'react';
import { matriculaService } from '../../services/matriculaService';
import { showToast } from '../../utils/toastHelper';

export function NovaMatriculaComAssinatura() {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    aluno_id: '',
    plano_id: '',
    data_inicio: new Date().toISOString().split('T')[0],
    forma_pagamento: 'cartao_credito',
    criar_assinatura: true  // ← Habilitar criação automática
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const resultado = await matriculaService.criar(formData);

      console.log('✅ Matrícula criada:', resultado.data.matricula);
      console.log('✅ Assinatura criada:', resultado.data.assinatura);

      showToast({
        type: 'success',
        title: 'Sucesso!',
        message: `Matrícula e assinatura criadas para ${resultado.data.matricula.aluno_nome}`
      });

      // Limpar formulário
      setFormData({
        ...formData,
        aluno_id: '',
        plano_id: ''
      });

      // Redirecionar para lista de matrículas
      // navigate('/matriculas');
    } catch (error) {
      showToast({
        type: 'error',
        title: 'Erro',
        message: error.message
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <h2>Nova Matrícula com Assinatura</h2>

      <div>
        <label>Aluno *</label>
        <select
          required
          value={formData.aluno_id}
          onChange={(e) => setFormData({ ...formData, aluno_id: e.target.value })}
        >
          <option value="">Selecione um aluno</option>
          {/* Mapear alunos aqui */}
        </select>
      </div>

      <div>
        <label>Plano *</label>
        <select
          required
          value={formData.plano_id}
          onChange={(e) => setFormData({ ...formData, plano_id: e.target.value })}
        >
          <option value="">Selecione um plano</option>
          {/* Mapear planos aqui */}
        </select>
      </div>

      <div>
        <label>Data de Início *</label>
        <input
          type="date"
          required
          value={formData.data_inicio}
          onChange={(e) => setFormData({ ...formData, data_inicio: e.target.value })}
        />
      </div>

      <div>
        <label>Forma de Pagamento *</label>
        <select
          required
          value={formData.forma_pagamento}
          onChange={(e) => setFormData({ ...formData, forma_pagamento: e.target.value })}
        >
          <option value="dinheiro">Dinheiro</option>
          <option value="cartao_credito">Cartão Crédito</option>
          <option value="cartao_debito">Cartão Débito</option>
          <option value="pix">PIX</option>
          <option value="boleto">Boleto</option>
        </select>
      </div>

      <div>
        <label>
          <input
            type="checkbox"
            checked={formData.criar_assinatura}
            onChange={(e) => setFormData({ ...formData, criar_assinatura: e.target.checked })}
          />
          Criar assinatura automaticamente
        </label>
      </div>

      <button type="submit" disabled={loading}>
        {loading ? 'Criando...' : 'Criar Matrícula'}
      </button>
    </form>
  );
}
```

---

## 2️⃣ Criar Assinatura para Matrícula Existente

### Frontend - Quando Matrícula Já Existe

```javascript
import { matriculaService } from '../../services/matriculaService';
import assinaturaService from '../../services/assinaturaService';
import { showToast } from '../../utils/toastHelper';

async function criarAssinaturaParaMatricula(matriculaId) {
  try {
    // Opção 1: Usar matriculaService
    const resultado = await matriculaService.criarAssinatura(matriculaId, {
      renovacoes: 12  // 12 renovações (1 ano)
    });

    console.log('✅ Assinatura criada:', resultado.data);
    showToast({
      type: 'success',
      message: 'Assinatura criada para a matrícula'
    });

    return resultado.data;
  } catch (error) {
    console.error('❌ Erro:', error);
    showToast({
      type: 'error',
      message: error.message
    });
  }
}

// Ou usando assinaturaService diretamente
async function criarAssinaturaAlternativo(matriculaId) {
  try {
    const resultado = await assinaturaService.criarDasMatricula(
      matriculaId,
      {
        data_inicio: new Date().toISOString().split('T')[0],
        renovacoes: 12
      }
    );

    console.log('✅ Assinatura criada:', resultado.data);
    return resultado.data;
  } catch (error) {
    console.error('❌ Erro:', error);
    throw error;
  }
}
```

---

## 3️⃣ Sincronizar Status (Matrícula → Assinatura)

### Caso 1: Admin Suspende Matrícula

```javascript
import { matriculaService } from '../../services/matriculaService';
import assinaturaService from '../../services/assinaturaService';

async function suspenderMatricula(matriculaId, motivo = 'Atraso em pagamento') {
  try {
    // Suspender matrícula
    const resultado = await matriculaService.suspender(matriculaId, motivo);
    console.log('✅ Matrícula suspensa');

    // Obter dados da matrícula para pegar o ID da assinatura
    const matricula = await matriculaService.buscar(matriculaId);

    if (matricula.data.assinatura_id) {
      // Sincronizar assinatura com matrícula
      await assinaturaService.sincronizarComMatricula(matricula.data.assinatura_id);
      console.log('✅ Assinatura sincronizada (agora SUSPENSA)');
    }

    return resultado.data;
  } catch (error) {
    console.error('❌ Erro ao suspender:', error);
    throw error;
  }
}
```

### Caso 2: Admin Reativa Matrícula

```javascript
async function reativarMatricula(matriculaId) {
  try {
    // Reativar matrícula
    const resultado = await matriculaService.reativar(matriculaId);
    console.log('✅ Matrícula reativada');

    // Obter dados da matrícula
    const matricula = await matriculaService.buscar(matriculaId);

    if (matricula.data.assinatura_id) {
      // Sincronizar assinatura
      await assinaturaService.sincronizarComMatricula(matricula.data.assinatura_id);
      console.log('✅ Assinatura sincronizada (agora ATIVA)');
    }

    return resultado.data;
  } catch (error) {
    console.error('❌ Erro ao reativar:', error);
    throw error;
  }
}
```

---

## 4️⃣ Verificar Sincronização

### Detectar Desincronização

```javascript
import assinaturaService from '../../services/assinaturaService';
import { showToast } from '../../utils/toastHelper';

async function verificarSincronizacao(assinaturaId) {
  try {
    const status = await assinaturaService.obterStatusSincronizacao(assinaturaId);

    if (status.data.sincronizado) {
      console.log('✅ Assinatura e matrícula sincronizadas');
      showToast({
        type: 'success',
        message: 'Dados sincronizados corretamente'
      });
    } else {
      console.warn('⚠️ Desincronização detectada!');
      console.log('Status Assinatura:', status.data.assinatura_status);
      console.log('Status Matrícula:', status.data.matricula_status);

      showToast({
        type: 'warning',
        message: 'Assinatura e matrícula desincronizadas. Resincronizando...'
      });

      // Forçar sincronização
      await assinaturaService.sincronizarComMatricula(assinaturaId);
      console.log('✅ Sincronização forçada');
    }
  } catch (error) {
    console.error('❌ Erro ao verificar sincronização:', error);
  }
}
```

---

## 5️⃣ Listar Matrículas COM Assinaturas

### Exibir Dados Relacionados

```javascript
import { matriculaService } from '../../services/matriculaService';

async function listarMatriculasComAssinaturas() {
  try {
    const resultado = await matriculaService.listarComAssinaturas({
      status: 'ativa'
    });

    console.log('📋 Matrículas com assinaturas:');
    resultado.data.matriculas.forEach(matricula => {
      console.log(`
        Aluno: ${matricula.aluno_nome}
        Plano: ${matricula.plano_nome}
        Status: ${matricula.status}
        Assinatura: ${matricula.assinatura?.id || 'Sem assinatura'}
        Vencimento: ${matricula.assinatura?.data_vencimento || 'N/A'}
      `);
    });

    return resultado.data;
  } catch (error) {
    console.error('❌ Erro ao listar:', error);
  }
}
```

---

## 6️⃣ Encontrar Assinaturas Órfãs

### Assinaturas Sem Matrícula Associada

```javascript
import assinaturaService from '../../services/assinaturaService';

async function encontrarAssinaturasOrfas() {
  try {
    const resultado = await assinaturaService.listarSemMatricula({
      status: 'ativa'
    });

    console.log(`⚠️ Encontradas ${resultado.data.total} assinaturas sem matrícula:`);

    resultado.data.assinaturas.forEach(assinatura => {
      console.log(`
        ID: ${assinatura.id}
        Aluno: ${assinatura.aluno_nome}
        Status: ${assinatura.status}
        Data Vencimento: ${assinatura.data_vencimento}
      `);
    });

    // Ação: Tentar vincular automaticamente ou alertar admin
    return resultado.data;
  } catch (error) {
    console.error('❌ Erro ao listar assinaturas órfãs:', error);
  }
}
```

---

## 7️⃣ Screen de Matrículas com Integração de Assinaturas

### Componente Completo

```javascript
import React, { useState, useEffect } from 'react';
import { matriculaService } from '../../services/matriculaService';
import assinaturaService from '../../services/assinaturaService';

export function MatriculasScreen() {
  const [matriculas, setMatriculas] = useState([]);
  const [loading, setLoading] = useState(false);
  const [expandedId, setExpandedId] = useState(null);

  useEffect(() => {
    carregarMatriculas();
  }, []);

  const carregarMatriculas = async () => {
    setLoading(true);
    try {
      const resultado = await matriculaService.listarComAssinaturas();
      setMatriculas(resultado.data.matriculas);
    } catch (error) {
      console.error('Erro ao carregar matrículas:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSuspender = async (matriculaId) => {
    try {
      await matriculaService.suspender(matriculaId, 'Suspendido pelo admin');
      console.log('✅ Matrícula suspensa e assinatura sincronizada');
      carregarMatriculas();
    } catch (error) {
      console.error('Erro ao suspender:', error);
    }
  };

  const handleCriarAssinatura = async (matriculaId) => {
    try {
      await matriculaService.criarAssinatura(matriculaId, { renovacoes: 12 });
      console.log('✅ Assinatura criada');
      carregarMatriculas();
    } catch (error) {
      console.error('Erro ao criar assinatura:', error);
    }
  };

  if (loading) return <div>Carregando...</div>;

  return (
    <div className="matriculas-container">
      <h2>Matrículas com Assinaturas</h2>

      {matriculas.map(matricula => (
        <div key={matricula.id} className="matricula-card">
          <div className="matricula-header">
            <h3>{matricula.aluno_nome}</h3>
            <span className={`status ${matricula.status}`}>
              {matricula.status.toUpperCase()}
            </span>
          </div>

          <div className="matricula-info">
            <p>Plano: <strong>{matricula.plano_nome}</strong></p>
            <p>Data de Início: <strong>{matricula.data_inicio}</strong></p>
            <p>Próx. Vencimento: <strong>{matricula.proxima_data_vencimento}</strong></p>
          </div>

          <div className="assinatura-info">
            {matricula.assinatura ? (
              <div>
                <h4>✅ Assinatura Vinculada</h4>
                <p>ID: {matricula.assinatura.id}</p>
                <p>Status: {matricula.assinatura.status}</p>
                <p>Vencimento: {matricula.assinatura.data_vencimento}</p>
              </div>
            ) : (
              <div>
                <h4>⚠️ Sem Assinatura</h4>
                <button onClick={() => handleCriarAssinatura(matricula.id)}>
                  Criar Assinatura
                </button>
              </div>
            )}
          </div>

          <div className="actions">
            {matricula.status === 'ativa' && (
              <button onClick={() => handleSuspender(matricula.id)}>
                Suspender
              </button>
            )}
            <button onClick={() => setExpandedId(expandedId === matricula.id ? null : matricula.id)}>
              {expandedId === matricula.id ? 'Recolher' : 'Expandir'}
            </button>
          </div>

          {expandedId === matricula.id && (
            <div className="expanded-info">
              <h4>Detalhes Completos</h4>
              <pre>{JSON.stringify({ matricula }, null, 2)}</pre>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}
```

---

## 8️⃣ Fluxo Completo: Novo Aluno

### Passo a Passo

```javascript
import { matriculaService } from '../../services/matriculaService';
import assinaturaService from '../../services/assinaturaService';

async function fluxoNovoAluno(dadosAluno) {
  try {
    console.log('🚀 Iniciando fluxo de novo aluno...');

    // 1️⃣ Criar aluno e matrícula com assinatura
    console.log('1️⃣ Criando matrícula com assinatura automática...');
    const resultadoMatricula = await matriculaService.criar({
      aluno_nome: dadosAluno.nome,
      aluno_cpf: dadosAluno.cpf,
      aluno_email: dadosAluno.email,
      plano_id: dadosAluno.plano_id,
      data_inicio: new Date().toISOString().split('T')[0],
      forma_pagamento: dadosAluno.forma_pagamento,
      criar_assinatura: true  // ← Automático!
    });

    const matriculaId = resultadoMatricula.data.matricula.id;
    const assinaturaId = resultadoMatricula.data.assinatura.id;

    console.log(`✅ Matrícula criada (ID: ${matriculaId})`);
    console.log(`✅ Assinatura criada (ID: ${assinaturaId})`);

    // 2️⃣ Verificar sincronização
    console.log('2️⃣ Verificando sincronização...');
    const statusSync = await assinaturaService.obterStatusSincronizacao(assinaturaId);

    if (statusSync.data.sincronizado) {
      console.log('✅ Dados sincronizados corretamente');
    } else {
      console.warn('⚠️ Forçando sincronização...');
      await assinaturaService.sincronizarComMatricula(assinaturaId);
    }

    // 3️⃣ Gerar relatório
    console.log('3️⃣ Dados finais do aluno:');
    const matriculaCompleta = await matriculaService.buscar(matriculaId);
    const assinaturaCompleta = await assinaturaService.buscar(assinaturaId);

    console.log('Matrícula:', matriculaCompleta.data);
    console.log('Assinatura:', assinaturaCompleta.data);

    return {
      sucesso: true,
      matriculaId,
      assinaturaId
    };
  } catch (error) {
    console.error('❌ Erro no fluxo:', error);
    throw error;
  }
}

// Usar:
// const resultado = await fluxoNovoAluno({
//   nome: 'João Silva',
//   cpf: '123.456.789-00',
//   email: 'joao@example.com',
//   plano_id: 2,
//   forma_pagamento: 'cartao_credito'
// });
```

---

## 🧪 Testes Úteis

### Teste 1: Criar e Verificar Sincronização

```javascript
async function testeIntegracaoCompleta() {
  console.log('🧪 Teste: Integração Assinatura + Matrícula');

  try {
    // Criar matrícula com assinatura
    const resultado = await matriculaService.criar({
      aluno_id: 1,
      plano_id: 2,
      data_inicio: '2025-01-15',
      forma_pagamento: 'cartao_credito',
      criar_assinatura: true
    });

    const assinaturaId = resultado.data.assinatura.id;
    console.log('✅ Matrícula + Assinatura criadas');

    // Suspender matrícula
    await matriculaService.suspender(resultado.data.matricula.id);
    console.log('✅ Matrícula suspensa');

    // Verificar se assinatura foi sincronizada
    const status = await assinaturaService.obterStatusSincronizacao(assinaturaId);
    
    if (status.data.assinatura_status === 'suspensa') {
      console.log('✅ TESTE PASSOU: Assinatura foi sincronizada automaticamente');
    } else {
      console.log('❌ TESTE FALHOU: Assinatura não foi sincronizada');
    }
  } catch (error) {
    console.error('❌ Erro no teste:', error);
  }
}
```

---

## 📚 Resumo de Métodos Disponíveis

| Método | Serviço | Descrição |
|--------|---------|-----------|
| `criar()` | matriculaService | Criar matrícula (com opção `criar_assinatura`) |
| `criarAssinatura()` | matriculaService | Criar assinatura para matrícula existente |
| `obterAssinatura()` | matriculaService | Obter assinatura da matrícula |
| `suspender()` | matriculaService | Suspender matrícula (sincroniza assinatura) |
| `reativar()` | matriculaService | Reativar matrícula (sincroniza assinatura) |
| `listarComAssinaturas()` | matriculaService | Listar matrículas com dados de assinatura |
| `sincronizarAssinatura()` | matriculaService | Sincronizar status manualmente |
| `criarDasMatricula()` | assinaturaService | Criar assinatura a partir de matrícula |
| `sincronizarComMatricula()` | assinaturaService | Sincronizar status com matrícula |
| `obterStatusSincronizacao()` | assinaturaService | Verificar se está sincronizado |
| `listarSemMatricula()` | assinaturaService | Encontrar assinaturas órfãs |

---

**Status**: ✅ **Exemplos de Integração Completos**

**Próximas Etapas**:
1. Implementar endpoints no backend PHP
2. Adicionar migrations SQL
3. Testar fluxos de sincronização
4. Integrar componentes no frontend
