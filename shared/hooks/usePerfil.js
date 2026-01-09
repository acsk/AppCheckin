/**
 * Hook compartilhado para gerenciar dados de perfil
 * Funciona tanto em Web quanto em Mobile
 */

import { useState, useEffect } from 'react';
import authService from '../services/authService';

export const usePerfil = (baseUrl = 'http://localhost:8080') => {
  const [usuario, setUsuario] = useState(null);
  const [carregando, setCarregando] = useState(true);
  const [editando, setEditando] = useState(false);
  const [salvando, setSalvando] = useState(false);
  const [dadosEditados, setDadosEditados] = useState({});
  const [erro, setErro] = useState(null);

  // Carregar dados do usuário
  const carregarDados = async () => {
    try {
      setCarregando(true);
      setErro(null);
      
      // Buscar dados completos do servidor
      const usuarioCompleto = await authService.fetchCompleteUser(baseUrl);
      
      console.log('✅ Dados do usuário carregados:', {
        id: usuarioCompleto?.id,
        nome: usuarioCompleto?.nome,
        email: usuarioCompleto?.email,
        cpf: usuarioCompleto?.cpf,
        telefone: usuarioCompleto?.telefone,
        cep: usuarioCompleto?.cep,
      });
      
      setUsuario(usuarioCompleto || {});
      setDadosEditados(usuarioCompleto || {});
    } catch (erro) {
      console.error('❌ Erro ao carregar usuário:', erro);
      setErro(erro.message);
      
      // Tentar carregar do cache
      const usuarioLocal = await authService.getUser();
      setUsuario(usuarioLocal || {});
      setDadosEditados(usuarioLocal || {});
    } finally {
      setCarregando(false);
    }
  };

  // Salvar perfil
  const salvarPerfil = async () => {
    try {
      setSalvando(true);
      setErro(null);
      
      const usuarioAtualizado = await authService.updateProfile(dadosEditados, baseUrl);
      
      setUsuario(usuarioAtualizado);
      setEditando(false);
      
      return true;
    } catch (erro) {
      console.error('❌ Erro ao salvar:', erro);
      setErro(erro.message);
      return false;
    } finally {
      setSalvando(false);
    }
  };

  // Cancelar edição
  const cancelarEdicao = () => {
    setDadosEditados(usuario || {});
    setEditando(false);
  };

  // Fazer logout
  const logout = async () => {
    try {
      await authService.logout();
      return true;
    } catch (erro) {
      console.error('❌ Erro ao fazer logout:', erro);
      setErro(erro.message);
      return false;
    }
  };

  // Carregar dados ao montar o hook
  useEffect(() => {
    carregarDados();
  }, [baseUrl]);

  return {
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
  };
};

export default usePerfil;
