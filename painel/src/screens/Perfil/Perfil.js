import React from 'react';
import { usePerfil } from '../../hooks/usePerfil';
import './Perfil.css';

import { API_BASE_URL } from '../../config/api';

export default function Perfil({ baseUrl = API_BASE_URL, onLogout = null }) {
  const {
    usuario,
    carregando,
    editando,
    salvando,
    dadosEditados,
    erro,
    setEditando,
    setDadosEditados,
    carregarDados,
    salvarPerfil,
    cancelarEdicao,
    logout,
  } = usePerfil(baseUrl);

  const handleLogout = async () => {
    if (window.confirm('Deseja fazer logout?')) {
      const success = await logout();
      if (success && onLogout) {
        onLogout();
      } else if (success) {
        window.location.href = '/login';
      }
    }
  };

  const handleSalvar = async () => {
    const success = await salvarPerfil();
    if (success) {
      alert('Perfil atualizado com sucesso!');
    } else {
      alert('Erro ao atualizar perfil');
    }
  };

  if (carregando) {
    return (
      <div className="perfil-container">
        <div className="loading">
          <div className="spinner"></div>
          <p>Carregando dados...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="perfil-container">
      <div className="perfil-card">
        {/* Header */}
        <div className="perfil-header">
          <h1>Minha Conta</h1>
          <div className="header-actions">
            <button 
              className="btn-refresh"
              onClick={carregarDados}
              title="Recarregar dados"
            >
              🔄
            </button>
            <button 
              className={`btn-edit ${editando ? 'editing' : ''}`}
              onClick={() => editando ? cancelarEdicao() : setEditando(true)}
              title={editando ? 'Cancelar' : 'Editar'}
            >
              {editando ? '✕' : '✏️'}
            </button>
          </div>
        </div>

        {/* Aviso de Erro */}
        {erro && (
          <div className="alert alert-error">
            <span>⚠️ {erro}</span>
          </div>
        )}

        {/* Debug Info */}
        {(!usuario?.cpf || !usuario?.cep) && (
          <div className="alert alert-warning">
            <span>⚠️ Dados incompletos. Clique em 🔄 para recarregar.</span>
          </div>
        )}

        {/* Avatar */}
        <div className="avatar-section">
          {usuario?.foto_base64 ? (
            <img 
              src={`data:image/jpeg;base64,${usuario.foto_base64}`} 
              alt="Avatar" 
              className="avatar"
            />
          ) : (
            <img 
              src={`https://i.pravatar.cc/200?img=${usuario?.id || 1}`} 
              alt="Avatar" 
              className="avatar"
            />
          )}
        </div>

        {/* Informações */}
        <div className="info-section">
          <h2>Informações Pessoais</h2>

          <div className="form-group">
            <label>ID</label>
            <input type="text" value={usuario?.id || ''} readOnly />
          </div>

          <div className="form-group">
            <label>Nome *</label>
            {editando ? (
              <input 
                type="text"
                value={dadosEditados.nome || ''}
                onChange={(e) => setDadosEditados({ ...dadosEditados, nome: e.target.value })}
              />
            ) : (
              <div className="value-display">{usuario?.nome || '-'}</div>
            )}
          </div>

          <div className="form-group">
            <label>Email</label>
            <input type="email" value={dadosEditados.email || ''} readOnly />
          </div>

          <div className="form-group">
            <label>CPF</label>
            {editando ? (
              <input 
                type="text"
                value={dadosEditados.cpf || ''}
                onChange={(e) => setDadosEditados({ ...dadosEditados, cpf: e.target.value })}
              />
            ) : (
              <div className="value-display">{usuario?.cpf || '-'}</div>
            )}
          </div>

          <div className="form-group">
            <label>Telefone</label>
            {editando ? (
              <input 
                type="tel"
                value={dadosEditados.telefone || ''}
                onChange={(e) => setDadosEditados({ ...dadosEditados, telefone: e.target.value })}
              />
            ) : (
              <div className="value-display">{usuario?.telefone || '-'}</div>
            )}
          </div>

          <h2 style={{ marginTop: '24px' }}>Endereço</h2>

          <div className="form-group">
            <label>CEP</label>
            {editando ? (
              <input 
                type="text"
                value={dadosEditados.cep || ''}
                onChange={(e) => setDadosEditados({ ...dadosEditados, cep: e.target.value })}
              />
            ) : (
              <div className="value-display">{usuario?.cep || '-'}</div>
            )}
          </div>

          <div className="form-group">
            <label>Logradouro</label>
            {editando ? (
              <input 
                type="text"
                value={dadosEditados.logradouro || ''}
                onChange={(e) => setDadosEditados({ ...dadosEditados, logradouro: e.target.value })}
              />
            ) : (
              <div className="value-display">{usuario?.logradouro || '-'}</div>
            )}
          </div>

          <div className="form-row">
            <div className="form-group">
              <label>Número</label>
              {editando ? (
                <input 
                  type="text"
                  value={dadosEditados.numero || ''}
                  onChange={(e) => setDadosEditados({ ...dadosEditados, numero: e.target.value })}
                />
              ) : (
                <div className="value-display">{usuario?.numero || '-'}</div>
              )}
            </div>

            <div className="form-group">
              <label>Complemento</label>
              {editando ? (
                <input 
                  type="text"
                  value={dadosEditados.complemento || ''}
                  onChange={(e) => setDadosEditados({ ...dadosEditados, complemento: e.target.value })}
                />
              ) : (
                <div className="value-display">{usuario?.complemento || '-'}</div>
              )}
            </div>
          </div>

          <div className="form-group">
            <label>Bairro</label>
            {editando ? (
              <input 
                type="text"
                value={dadosEditados.bairro || ''}
                onChange={(e) => setDadosEditados({ ...dadosEditados, bairro: e.target.value })}
              />
            ) : (
              <div className="value-display">{usuario?.bairro || '-'}</div>
            )}
          </div>

          <div className="form-row">
            <div className="form-group">
              <label>Cidade</label>
              {editando ? (
                <input 
                  type="text"
                  value={dadosEditados.cidade || ''}
                  onChange={(e) => setDadosEditados({ ...dadosEditados, cidade: e.target.value })}
                />
              ) : (
                <div className="value-display">{usuario?.cidade || '-'}</div>
              )}
            </div>

            <div className="form-group">
              <label>Estado</label>
              {editando ? (
                <input 
                  type="text"
                  maxLength="2"
                  value={dadosEditados.estado || ''}
                  onChange={(e) => setDadosEditados({ ...dadosEditados, estado: e.target.value.toUpperCase() })}
                />
              ) : (
                <div className="value-display">{usuario?.estado || '-'}</div>
              )}
            </div>
          </div>
        </div>

        {/* Botões de Ação */}
        <div className="actions">
          {editando ? (
            <>
              <button 
                className="btn btn-primary"
                onClick={handleSalvar}
                disabled={salvando}
              >
                {salvando ? 'Salvando...' : 'Salvar'}
              </button>
              <button 
                className="btn btn-secondary"
                onClick={cancelarEdicao}
                disabled={salvando}
              >
                Cancelar
              </button>
            </>
          ) : (
            <>
              <button 
                className="btn btn-primary"
                onClick={() => setEditando(true)}
              >
                Editar Perfil
              </button>
              <button 
                className="btn btn-danger"
                onClick={handleLogout}
              >
                Sair da Conta
              </button>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
