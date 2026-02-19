import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, ScrollView, useWindowDimensions, TextInput, Switch } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import alunoService from '../../services/alunoService';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import { showSuccess, showError } from '../../utils/toast';
import { mascaraTelefone } from '../../utils/masks';

export default function AlunosScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [alunos, setAlunos] = useState([]);
  const [alunosFiltrados, setAlunosFiltrados] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchText, setSearchText] = useState('');
  
  // Novos estados para paginação e filtros
  const [apenasAtivos, setApenasAtivos] = useState(false);
  const [paginaAtual, setPaginaAtual] = useState(1);
  const [totalPaginas, setTotalPaginas] = useState(1);
  const [totalAlunos, setTotalAlunos] = useState(0);
  const [porPagina] = useState(110);

  useEffect(() => {
    ensureAdminAccess();
  }, []);

  useEffect(() => {
    loadAlunos();
  }, [paginaAtual, apenasAtivos]);

  const ensureAdminAccess = async () => {
    try {
      const user = await authService.getCurrentUser();
      if (!user || ![3, 4].includes(user.papel_id)) {
        showError('Acesso restrito aos administradores');
        router.replace('/');
      }
    } catch (error) {
      router.replace('/');
    }
  };

  const loadAlunos = useCallback(async (termo = searchText) => {
    try {
      setLoading(true);
      const response = await alunoService.listar({ 
        busca: termo,
        apenasAtivos,
        pagina: paginaAtual,
        porPagina
      });
      
      const lista = Array.isArray(response) ? response : (response.alunos || []);
      setAlunos(lista);
      setAlunosFiltrados(lista);
      
      // Atualizar dados de paginação
      if (response.total_paginas) {
        setTotalPaginas(response.total_paginas);
        setTotalAlunos(response.total || lista.length);
      } else {
        setTotalPaginas(1);
        setTotalAlunos(response.total || lista.length);
      }
    } catch (error) {
      console.error('Erro ao carregar alunos:', error);
      showError('Não foi possível carregar os alunos');
    } finally {
      setLoading(false);
    }
  }, [apenasAtivos, paginaAtual, porPagina, searchText]);

  const handleSearchChange = (text) => {
    setSearchText(text);
  };

  const handleSearch = () => {
    setPaginaAtual(1);
    loadAlunos(searchText.trim());
  };

  const handleClearSearch = () => {
    setSearchText('');
    setPaginaAtual(1);
    loadAlunos('');
  };

  const handleToggleApenasAtivos = (value) => {
    setApenasAtivos(value);
    setPaginaAtual(1);
  };

  const handlePaginaAnterior = () => {
    if (paginaAtual > 1) {
      setPaginaAtual(prev => prev - 1);
    }
  };

  const handleProximaPagina = () => {
    if (paginaAtual < totalPaginas) {
      setPaginaAtual(prev => prev + 1);
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { 
      day: '2-digit', 
      month: '2-digit', 
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const formatCurrency = (value) => {
    if (!value) return '-';
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value);
  };



  const renderMobileCard = (aluno) => (
    <View key={aluno.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.cardHeaderLeft}>
          <Text style={styles.cardName}>{aluno.nome}</Text>
          <View style={styles.badgesRow}>
            <View style={[
              styles.statusBadge,
              aluno.ativo ? styles.statusAtivo : styles.statusInativo,
            ]}>
              <Text style={[styles.statusText, aluno.ativo ? styles.statusTextAtivo : styles.statusTextInativo]}>
                {aluno.ativo ? 'Ativo' : 'Inativo'}
              </Text>
            </View>
            {aluno.pagamento_ativo !== null && (
              <View style={[
                styles.statusBadge,
                aluno.pagamento_ativo === true ? styles.pagamentoAtivo : styles.pagamentoInativo,
              ]}>
                <Text style={[styles.statusText, aluno.pagamento_ativo === true ? styles.pagamentoTextAtivo : styles.pagamentoTextInativo]}>
                  {aluno.pagamento_ativo === true ? 'Em dia' : 'Pendente'}
                </Text>
              </View>
            )}
          </View>
        </View>
        <View style={styles.cardActions}>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => router.push(`/alunos/${aluno.id}`)}
          >
            <Feather name="eye" size={18} color="#6366f1" />
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => router.push(`/alunos/${aluno.id}?edit=true`)}
          >
            <Feather name="edit-2" size={18} color="#f97316" />
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => handleToggleStatus(aluno)}
          >
            <Feather
              name={aluno.ativo ? 'toggle-right' : 'toggle-left'}
              size={20}
              color={aluno.ativo ? '#16a34a' : '#ef4444'}
            />
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.cardBody}>
        {!!aluno.telefone && (
          <View style={styles.cardRow}>
            <Feather name="phone" size={14} color="#666" />
            <Text style={styles.cardLabel}>Telefone:</Text>
            <Text style={styles.cardValue}>{mascaraTelefone(aluno.telefone)}</Text>
          </View>
        )}
        {aluno.plano && (
          <View style={styles.cardRow}>
            <Feather name="credit-card" size={14} color="#666" />
            <Text style={styles.cardLabel}>Plano:</Text>
            <Text style={styles.cardValue}>{aluno.plano.nome} - {formatCurrency(aluno.plano.valor)}</Text>
          </View>
        )}
      </View>
    </View>
  );

  const renderTable = () => (
    <View className="mx-4 my-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-3">
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colNome}>NOME</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colPlano}>PLANO</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colStatus}>STATUS</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colPagamento}>PGTO</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 text-right" style={styles.colAcoes}>AÇÕES</Text>
      </View>

      <ScrollView className="max-h-[520px]" showsVerticalScrollIndicator={true}>
        {alunosFiltrados.map((aluno) => (
          <View key={aluno.id} className="flex-row items-center border-b border-slate-100 px-4 py-3">
            <View style={styles.colNome}>
              <Text className="text-[13px] font-semibold text-slate-800" numberOfLines={1}>{aluno.nome}</Text>
              {aluno.telefone && (
                <Text className="text-[11px] text-slate-400" numberOfLines={1}>{mascaraTelefone(aluno.telefone)}</Text>
              )}
            </View>
            <View style={styles.colPlano}>
              {aluno.plano ? (
                <View>
                  <Text className="text-[12px] font-medium text-slate-700" numberOfLines={1}>{aluno.plano.nome}</Text>
                  <Text className="text-[11px] text-slate-400">{formatCurrency(aluno.plano.valor)}</Text>
                </View>
              ) : (
                <Text className="text-[12px] text-slate-400 italic">Sem plano</Text>
              )}
            </View>
            <View style={styles.colStatus}>
              <View className={`self-start rounded-full px-2.5 py-1 ${aluno.ativo ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                <Text className={`text-[11px] font-bold ${aluno.ativo ? 'text-emerald-700' : 'text-rose-700'}`}>
                  {aluno.ativo ? 'Ativo' : 'Inativo'}
                </Text>
              </View>
            </View>
            <View style={styles.colPagamento}>
              {aluno.pagamento_ativo === null ? (
                <Text className="text-[11px] text-slate-400 italic">Sem plano</Text>
              ) : (
                <View className={`self-start rounded-full px-2.5 py-1 ${aluno.pagamento_ativo === true ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                  <Text className={`text-[11px] font-bold ${aluno.pagamento_ativo === true ? 'text-emerald-700' : 'text-rose-700'}`}>
                    {aluno.pagamento_ativo === true ? 'Em dia' : 'Pendente'}
                  </Text>
                </View>
              )}
            </View>
            <View className="flex-row justify-end gap-2" style={styles.colAcoes}>
              <TouchableOpacity
                className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                onPress={() => router.push(`/alunos/${aluno.id}`)}
                title="Ver detalhes"
              >
                <Feather name="eye" size={16} color="#6366f1" />
              </TouchableOpacity>
              <TouchableOpacity
                className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                onPress={() => router.push(`/alunos/${aluno.id}?edit=true`)}
                title="Editar"
              >
                <Feather name="edit-2" size={16} color="#f97316" />
              </TouchableOpacity>
            </View>
          </View>
        ))}
      </ScrollView>
    </View>
  );

  const renderPaginacao = () => {
    if (totalPaginas <= 1) return null;
    
    return (
      <View style={styles.paginacaoContainer}>
        <TouchableOpacity
          style={[styles.paginacaoButton, paginaAtual === 1 && styles.paginacaoButtonDisabled]}
          onPress={handlePaginaAnterior}
          disabled={paginaAtual === 1}
        >
          <Feather name="chevron-left" size={18} color={paginaAtual === 1 ? '#9ca3af' : '#f97316'} />
          <Text style={[styles.paginacaoButtonText, paginaAtual === 1 && styles.paginacaoButtonTextDisabled]}>Anterior</Text>
        </TouchableOpacity>
        
        <View style={styles.paginacaoInfo}>
          <Text style={styles.paginacaoText}>Página {paginaAtual} de {totalPaginas}</Text>
          <Text style={styles.paginacaoTotal}>{totalAlunos} aluno{totalAlunos !== 1 ? 's' : ''}</Text>
        </View>
        
        <TouchableOpacity
          style={[styles.paginacaoButton, paginaAtual === totalPaginas && styles.paginacaoButtonDisabled]}
          onPress={handleProximaPagina}
          disabled={paginaAtual === totalPaginas}
        >
          <Text style={[styles.paginacaoButtonText, paginaAtual === totalPaginas && styles.paginacaoButtonTextDisabled]}>Próxima</Text>
          <Feather name="chevron-right" size={18} color={paginaAtual === totalPaginas ? '#9ca3af' : '#f97316'} />
        </TouchableOpacity>
      </View>
    );
  };

  return (
    <LayoutBase title="Alunos" subtitle="Gerenciar alunos">
      <View style={styles.container}>
        <View style={styles.bannerContainer}>
          <View style={styles.banner}>
            <View style={styles.bannerContent}>
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <Feather name="users" size={28} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>Alunos</Text>
                <Text style={styles.bannerSubtitle}>Gerencie todos os alunos</Text>
              </View>
            </View>
            <View style={styles.bannerDecoration}>
              <View style={styles.decorCircle1} />
              <View style={styles.decorCircle2} />
              <View style={styles.decorCircle3} />
            </View>
          </View>

          <View style={[styles.searchCard, isMobile && styles.searchCardMobile]}>
            <View style={styles.searchCardHeader}>
              <View style={styles.searchCardInfo}>
                <View style={styles.searchCardIconContainer}>
                  <Feather name="search" size={20} color="#f97316" />
                </View>
                <View>
                  <Text style={styles.searchCardTitle}>Buscar Alunos</Text>
                  <Text style={styles.searchCardSubtitle}>
                    {totalAlunos} {totalAlunos === 1 ? 'aluno encontrado' : 'alunos encontrados'}
                  </Text>
                </View>
              </View>
              <TouchableOpacity
                style={[styles.addButton, isMobile && styles.addButtonMobile]}
                onPress={() => router.push('/alunos/novo')}
              >
                <Feather name="plus" size={18} color="#fff" />
                {!isMobile && <Text style={styles.addButtonText}>Novo Aluno</Text>}
              </TouchableOpacity>
            </View>

            <View style={styles.filtersRow}>
              <View style={[styles.searchInputContainer, { flex: 1 }]}>
                <Feather name="search" size={20} color="#9ca3af" style={styles.searchInputIcon} />
                <TextInput
                  style={styles.searchInput}
                  placeholder="Buscar por nome, email, CPF ou telefone..."
                  placeholderTextColor="#9ca3af"
                  value={searchText}
                  onChangeText={handleSearchChange}
                  onSubmitEditing={handleSearch}
                  returnKeyType="search"
                />
                {searchText.length > 0 && (
                  <TouchableOpacity onPress={handleClearSearch} style={styles.clearButton}>
                    <Feather name="x-circle" size={20} color="#9ca3af" />
                  </TouchableOpacity>
                )}
                <TouchableOpacity onPress={handleSearch} style={styles.searchButton}>
                  <Feather name="search" size={18} color="#fff" />
                </TouchableOpacity>
              </View>
            </View>

            <View style={styles.filterChipsRow}>
              <TouchableOpacity
                style={[styles.filterChip, apenasAtivos && styles.filterChipActive]}
                onPress={() => handleToggleApenasAtivos(!apenasAtivos)}
              >
                <Feather 
                  name={apenasAtivos ? 'check-square' : 'square'} 
                  size={14} 
                  color={apenasAtivos ? '#16a34a' : '#6b7280'} 
                />
                <Text style={[styles.filterChipText, apenasAtivos && styles.filterChipTextActive]}>
                  Apenas ativos
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>

        {loading && (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={styles.loadingText}>Carregando alunos...</Text>
          </View>
        )}

        {!loading && alunosFiltrados.length === 0 && (
          <View style={styles.emptyState}>
            <Feather name="users" size={48} color="#ccc" />
            <Text style={styles.emptyText}>
              {searchText ? 'Nenhum aluno encontrado' : 'Nenhum aluno cadastrado'}
            </Text>
            <Text style={styles.emptySubtext}>
              {searchText ? 'Tente buscar com outros termos' : 'Clique em "Novo Aluno" para começar'}
            </Text>
          </View>
        )}

        {!loading && alunosFiltrados.length > 0 && (
          <>
            {isMobile ? (
              <ScrollView style={styles.cardsContainer} showsVerticalScrollIndicator={false}>
                {alunosFiltrados.map(renderMobileCard)}
              </ScrollView>
            ) : (
              renderTable()
            )}
            {renderPaginacao()}
          </>
        )}
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  bannerContainer: {
    padding: 16,
    gap: 16,
  },
  banner: {
    backgroundColor: '#f97316',
    borderRadius: 16,
    padding: 20,
    overflow: 'hidden',
  },
  bannerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
    zIndex: 2,
  },
  bannerIconContainer: {
    width: 54,
    height: 54,
    borderRadius: 14,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconOuter: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconInner: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerTextContainer: {
    flex: 1,
  },
  bannerTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: '#fff',
  },
  bannerSubtitle: {
    marginTop: 4,
    fontSize: 14,
    color: 'rgba(255,255,255,0.9)',
  },
  bannerDecoration: {
    position: 'absolute',
    right: 0,
    top: 0,
    bottom: 0,
    width: 140,
  },
  decorCircle1: {
    position: 'absolute',
    top: -40,
    right: -40,
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  decorCircle2: {
    position: 'absolute',
    bottom: -30,
    right: 10,
    width: 90,
    height: 90,
    borderRadius: 45,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  decorCircle3: {
    position: 'absolute',
    top: 20,
    right: 50,
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: 'rgba(255,255,255,0.15)',
  },
  searchCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 6,
    elevation: 2,
  },
  searchCardMobile: {
    padding: 14,
  },
  searchCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
    gap: 12,
  },
  searchCardInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  searchCardIconContainer: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: '#fff7ed',
    alignItems: 'center',
    justifyContent: 'center',
  },
  searchCardTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },
  searchCardSubtitle: {
    marginTop: 2,
    fontSize: 12,
    color: '#6b7280',
  },
  searchInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    paddingHorizontal: 12,
  },
  searchInputIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    height: 40,
    fontSize: 14,
    color: '#111827',
  },
  clearButton: {
    padding: 4,
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#f97316',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
  },
  addButtonMobile: {
    paddingHorizontal: 12,
  },
  addButtonText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 13,
  },
  loadingContainer: {
    padding: 40,
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#666',
  },
  emptyState: {
    alignItems: 'center',
    padding: 40,
  },
  emptyText: {
    marginTop: 12,
    fontSize: 16,
    fontWeight: '600',
    color: '#444',
  },
  emptySubtext: {
    marginTop: 6,
    fontSize: 13,
    color: '#888',
  },
  cardsContainer: {
    paddingHorizontal: 16,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#eee',
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  cardHeaderLeft: {
    flex: 1,
    marginRight: 12,
  },
  cardActions: {
    flexDirection: 'row',
    gap: 6,
  },
  cardActionButton: {
    padding: 6,
  },
  cardName: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 8,
  },
  statusBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  statusAtivo: {
    backgroundColor: '#d1fae5',
  },
  statusInativo: {
    backgroundColor: '#fee2e2',
  },
  statusText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#111827',
  },
  cardBody: {
    marginTop: 10,
    gap: 8,
  },
  cardRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  cardLabel: {
    fontSize: 12,
    color: '#666',
  },
  cardValue: {
    fontSize: 12,
    color: '#111827',
    flex: 1,
  },
  colNome: {
    flex: 1.5,
  },
  colEmail: {
    flex: 1.5,
  },
  colPlano: {
    flex: 1.2,
  },
  colCheckins: {
    flex: 0.8,
  },
  colStatus: {
    flex: 0.7,
  },
  colPagamento: {
    flex: 0.7,
  },
  colAcoes: {
    width: 130,
  },
  filtersRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  filterChipsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  filterChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    backgroundColor: '#f9fafb',
  },
  filterChipActive: {
    borderColor: '#16a34a',
    backgroundColor: '#f0fdf4',
  },
  filterChipText: {
    fontSize: 12,
    fontWeight: '500',
    color: '#6b7280',
  },
  filterChipTextActive: {
    color: '#16a34a',
  },
  searchButton: {
    backgroundColor: '#f97316',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    marginLeft: 8,
  },
  paginacaoContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 16,
    paddingHorizontal: 16,
    gap: 16,
  },
  paginacaoButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#f97316',
    backgroundColor: '#fff',
  },
  paginacaoButtonDisabled: {
    borderColor: '#e5e7eb',
    backgroundColor: '#f9fafb',
  },
  paginacaoButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#f97316',
  },
  paginacaoButtonTextDisabled: {
    color: '#9ca3af',
  },
  paginacaoInfo: {
    alignItems: 'center',
  },
  paginacaoText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
  },
  paginacaoTotal: {
    fontSize: 11,
    color: '#6b7280',
  },
  badgesRow: {
    flexDirection: 'row',
    gap: 6,
    flexWrap: 'wrap',
  },
  statusTextAtivo: {
    color: '#16a34a',
  },
  statusTextInativo: {
    color: '#ef4444',
  },
  pagamentoAtivo: {
    backgroundColor: '#dbeafe',
  },
  pagamentoInativo: {
    backgroundColor: '#fef3c7',
  },
  pagamentoTextAtivo: {
    color: '#2563eb',
  },
  pagamentoTextInativo: {
    color: '#d97706',
  },
});
