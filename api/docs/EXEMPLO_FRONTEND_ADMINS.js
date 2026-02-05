// ==================================================================
// API CLIENT - GESTÃO DE ADMINS
// ==================================================================

const API_BASE_URL = 'http://localhost:8080'; // Ajuste conforme necessário

// Helper para fazer requisições autenticadas
async function apiRequest(endpoint, options = {}) {
  const token = localStorage.getItem('token'); // Ajuste conforme seu storage
  
  const config = {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
      ...options.headers
    }
  };

  const response = await fetch(`${API_BASE_URL}${endpoint}`, config);
  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.error || data.errors?.join(', ') || 'Erro na requisição');
  }

  return data;
}

// ==================================================================
// 1. LISTAR PAPÉIS DISPONÍVEIS
// ==================================================================

/**
 * Busca a lista de papéis que podem ser atribuídos a um admin
 * @returns {Promise<Array>} Lista de papéis com id, nome e descrição
 */
export async function listarPapeis() {
  const response = await apiRequest('/superadmin/papeis');
  return response.papeis;
}

// Exemplo de uso:
// const papeis = await listarPapeis();
// papeis.forEach(papel => {
//   console.log(`${papel.id} - ${papel.nome}: ${papel.descricao}`);
// });

// ==================================================================
// 2. LISTAR ADMINS DA ACADEMIA
// ==================================================================

/**
 * Lista todos os administradores de uma academia
 * @param {number} tenantId - ID da academia
 * @returns {Promise<Object>} Objeto com academia, admins e total
 */
export async function listarAdmins(tenantId) {
  return await apiRequest(`/superadmin/academias/${tenantId}/admins`);
}

// Exemplo de uso:
// const { academia, admins, total } = await listarAdmins(1);
// admins.forEach(admin => {
//   console.log(`${admin.nome} (${admin.email})`);
//   console.log('Papéis:', admin.papeis);
// });

// ==================================================================
// 3. CRIAR ADMIN
// ==================================================================

/**
 * Cria um novo administrador para uma academia
 * @param {number} tenantId - ID da academia
 * @param {Object} dadosAdmin - Dados do admin
 * @param {string} dadosAdmin.nome - Nome completo
 * @param {string} dadosAdmin.email - Email válido
 * @param {string} dadosAdmin.senha - Senha (mínimo 6 caracteres)
 * @param {string} [dadosAdmin.telefone] - Telefone
 * @param {string} [dadosAdmin.cpf] - CPF
 * @param {Array<number>} [dadosAdmin.papeis] - IDs dos papéis [3, 2, 1]. Padrão: [3]
 * @returns {Promise<Object>} Dados do admin criado
 */
export async function criarAdmin(tenantId, dadosAdmin) {
  return await apiRequest(`/superadmin/academias/${tenantId}/admin`, {
    method: 'POST',
    body: JSON.stringify(dadosAdmin)
  });
}

// Exemplo de uso:
// const novoAdmin = await criarAdmin(1, {
//   nome: 'João Silva',
//   email: 'joao@academia.com',
//   senha: 'senha123',
//   telefone: '(11) 98765-4321',
//   cpf: '123.456.789-00',
//   papeis: [3, 2]  // Admin e Professor
// });
// console.log('Admin criado:', novoAdmin.admin.id);

// ==================================================================
// 4. ATUALIZAR ADMIN
// ==================================================================

/**
 * Atualiza dados de um administrador existente
 * @param {number} tenantId - ID da academia
 * @param {number} adminId - ID do admin
 * @param {Object} dadosAdmin - Dados a serem atualizados (todos opcionais)
 * @param {string} [dadosAdmin.nome] - Nome completo
 * @param {string} [dadosAdmin.email] - Email válido
 * @param {string} [dadosAdmin.senha] - Nova senha
 * @param {string} [dadosAdmin.telefone] - Telefone
 * @param {string} [dadosAdmin.cpf] - CPF
 * @param {Array<number>} [dadosAdmin.papeis] - IDs dos papéis (deve conter 3)
 * @returns {Promise<Object>} Dados do admin atualizado
 */
export async function atualizarAdmin(tenantId, adminId, dadosAdmin) {
  return await apiRequest(
    `/superadmin/academias/${tenantId}/admins/${adminId}`,
    {
      method: 'PUT',
      body: JSON.stringify(dadosAdmin)
    }
  );
}

// Exemplo de uso:
// const adminAtualizado = await atualizarAdmin(1, 10, {
//   nome: 'João Silva Santos',
//   papeis: [3, 2, 1]  // Adiciona papel de aluno
// });
// console.log('Admin atualizado:', adminAtualizado.admin);

// ==================================================================
// 5. DESATIVAR ADMIN
// ==================================================================

/**
 * Desativa um administrador (soft delete)
 * @param {number} tenantId - ID da academia
 * @param {number} adminId - ID do admin
 * @returns {Promise<Object>} Mensagem de sucesso
 */
export async function desativarAdmin(tenantId, adminId) {
  return await apiRequest(
    `/superadmin/academias/${tenantId}/admins/${adminId}`,
    {
      method: 'DELETE'
    }
  );
}

// Exemplo de uso:
// await desativarAdmin(1, 10);
// console.log('Admin desativado com sucesso');

// ==================================================================
// 6. REATIVAR ADMIN
// ==================================================================

/**
 * Reativa um administrador previamente desativado
 * @param {number} tenantId - ID da academia
 * @param {number} adminId - ID do admin
 * @returns {Promise<Object>} Mensagem de sucesso
 */
export async function reativarAdmin(tenantId, adminId) {
  return await apiRequest(
    `/superadmin/academias/${tenantId}/admins/${adminId}/reativar`,
    {
      method: 'POST'
    }
  );
}

// Exemplo de uso:
// await reativarAdmin(1, 10);
// console.log('Admin reativado com sucesso');

// ==================================================================
// EXEMPLO COMPLETO - COMPONENTE REACT
// ==================================================================

/*
import React, { useState, useEffect } from 'react';
import {
  listarPapeis,
  listarAdmins,
  criarAdmin,
  atualizarAdmin,
  desativarAdmin,
  reativarAdmin
} from './adminAPI';

function GestaoAdmins({ tenantId }) {
  const [admins, setAdmins] = useState([]);
  const [papeis, setPapeis] = useState([]);
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    nome: '',
    email: '',
    senha: '',
    telefone: '',
    cpf: '',
    papeis: [3]  // Admin obrigatório
  });

  // Carregar papéis e admins ao montar
  useEffect(() => {
    carregarDados();
  }, [tenantId]);

  async function carregarDados() {
    setLoading(true);
    try {
      const [papeisData, adminsData] = await Promise.all([
        listarPapeis(),
        listarAdmins(tenantId)
      ]);
      setPapeis(papeisData);
      setAdmins(adminsData.admins);
    } catch (error) {
      alert('Erro ao carregar dados: ' + error.message);
    } finally {
      setLoading(false);
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setLoading(true);
    try {
      await criarAdmin(tenantId, formData);
      alert('Admin criado com sucesso!');
      setFormData({
        nome: '',
        email: '',
        senha: '',
        telefone: '',
        cpf: '',
        papeis: [3]
      });
      carregarDados();
    } catch (error) {
      alert('Erro ao criar admin: ' + error.message);
    } finally {
      setLoading(false);
    }
  }

  function togglePapel(papelId) {
    const papeisSelecionados = formData.papeis.includes(papelId)
      ? formData.papeis.filter(id => id !== papelId)
      : [...formData.papeis, papelId];
    
    // Garantir que Admin (3) sempre esteja selecionado
    if (!papeisSelecionados.includes(3)) {
      papeisSelecionados.push(3);
    }
    
    setFormData({ ...formData, papeis: papeisSelecionados });
  }

  async function handleToggleAtivo(admin) {
    setLoading(true);
    try {
      if (admin.ativo) {
        await desativarAdmin(tenantId, admin.id);
      } else {
        await reativarAdmin(tenantId, admin.id);
      }
      carregarDados();
    } catch (error) {
      alert('Erro: ' + error.message);
    } finally {
      setLoading(false);
    }
  }

  function getNomePapeis(papeisIds) {
    return papeisIds
      .map(id => papeis.find(p => p.id === id)?.nome)
      .filter(Boolean)
      .join(', ');
  }

  return (
    <div>
      <h2>Gestão de Administradores</h2>
      
      {/* Formulário de Criação *\/}
      <form onSubmit={handleSubmit}>
        <h3>Novo Admin</h3>
        
        <input
          type="text"
          placeholder="Nome completo"
          value={formData.nome}
          onChange={e => setFormData({...formData, nome: e.target.value})}
          required
        />
        
        <input
          type="email"
          placeholder="Email"
          value={formData.email}
          onChange={e => setFormData({...formData, email: e.target.value})}
          required
        />
        
        <input
          type="password"
          placeholder="Senha (mín. 6 caracteres)"
          value={formData.senha}
          onChange={e => setFormData({...formData, senha: e.target.value})}
          required
          minLength={6}
        />
        
        <input
          type="tel"
          placeholder="Telefone"
          value={formData.telefone}
          onChange={e => setFormData({...formData, telefone: e.target.value})}
        />
        
        <input
          type="text"
          placeholder="CPF"
          value={formData.cpf}
          onChange={e => setFormData({...formData, cpf: e.target.value})}
        />
        
        <fieldset>
          <legend>Papéis</legend>
          {papeis.map(papel => (
            <label key={papel.id}>
              <input
                type="checkbox"
                checked={formData.papeis.includes(papel.id)}
                onChange={() => togglePapel(papel.id)}
                disabled={papel.id === 3}
              />
              {papel.nome} {papel.id === 3 && '(obrigatório)'}
              <small>{papel.descricao}</small>
            </label>
          ))}
        </fieldset>
        
        <button type="submit" disabled={loading}>
          {loading ? 'Salvando...' : 'Criar Admin'}
        </button>
      </form>
      
      {/* Lista de Admins *\/}
      <h3>Admins Cadastrados ({admins.length})</h3>
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Email</th>
            <th>Papéis</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          {admins.map(admin => (
            <tr key={admin.id}>
              <td>{admin.nome}</td>
              <td>{admin.email}</td>
              <td>{getNomePapeis(admin.papeis)}</td>
              <td>{admin.ativo ? '✅ Ativo' : '❌ Inativo'}</td>
              <td>
                <button onClick={() => handleToggleAtivo(admin)}>
                  {admin.ativo ? 'Desativar' : 'Reativar'}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default GestaoAdmins;
*/
