import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, ScrollView, useWindowDimensions, TextInput } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import planoService from '../../services/planoService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';
import { authService } from '../../services/authService';

export default function PlanosScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [planos, setPlanos] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingTenants, setLoadingTenants] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [tenants, setTenants] = useState([]);
  const [selectedTenant, setSelectedTenant] = useState(null);
  const [searchText, setSearchText] = useState('');
  const [showDropdown, setShowDropdown] = useState(false);
  const [gerandoRelatorio, setGerandoRelatorio] = useState(false);

  useEffect(() => {
    checkUserAndLoadData();
  }, []);

  useEffect(() => {
    if (isSuperAdmin && selectedTenant) {
      loadPlanos();
    }
  }, [selectedTenant]);

  const checkUserAndLoadData = async () => {
    const user = await authService.getCurrentUser();
    const superAdmin = user?.papel_id === 4;
    setIsSuperAdmin(superAdmin);
    
    if (superAdmin) {
      // SuperAdmin: carregar apenas lista de tenants inicialmente
      loadTenants();
    } else {
      // Admin: carregar planos normalmente
      loadPlanos(false);
    }
  };

  const loadTenants = async () => {
    try {
      setLoadingTenants(true);
      const response = await planoService.listarTodos(null);
      setTenants(response.tenants || []);
    } catch (error) {
      console.error('❌ Erro ao carregar academias:', error);
      showError('Erro ao carregar lista de academias');
    } finally {
      setLoadingTenants(false);
    }
  };

  const loadPlanos = async (superAdmin = isSuperAdmin) => {
    try {
      setLoading(true);
      console.log('🔄 Carregando planos...', { superAdmin, selectedTenant });
      
      let response;
      if (superAdmin) {
        if (!selectedTenant) {
          setPlanos([]);
          setLoading(false);
          return;
        }
        response = await planoService.listarTodos(selectedTenant.id);
      } else {
        response = await planoService.listar();
      }
      
      console.log('✅ Resposta da API:', response);
      setPlanos(response.planos || []);
      console.log('📊 Total de planos:', response.planos?.length || 0);
    } catch (error) {
      console.error('❌ Erro ao carregar planos:', error);
      showError(error.error || 'Erro ao carregar planos');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = (id, nome) => {
    setConfirmDelete({ visible: true, id, nome });
  };

  const filteredTenants = tenants.filter(tenant =>
    tenant.nome.toLowerCase().includes(searchText.toLowerCase())
  );

  const handleSelectTenant = (tenant) => {
    setSelectedTenant(tenant);
    setSearchText(tenant.nome);
    setShowDropdown(false);
  };

  const clearSelection = () => {
    setSelectedTenant(null);
    setSearchText('');
    setPlanos([]);
  };

  const confirmDeleteAction = async () => {
    try {
      const { id } = confirmDelete;
      await planoService.desativar(id);
      showSuccess('Plano desativado com sucesso');
      setConfirmDelete({ visible: false, id: null, nome: '' });
      loadPlanos();
    } catch (error) {
      showError(error.error || 'Não foi possível desativar o plano');
    }
  };

  const cancelDelete = () => {
    setConfirmDelete({ visible: false, id: null, nome: '' });
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value);
  };

  const isReposicaoPermitida = (item) => {
    const valor = item?.permite_reposicao;
    return valor === 1 || valor === '1' || valor === true;
  };

  const handleGerarRelatorio = async () => {
    try {
      setGerandoRelatorio(true);
      const response = await planoService.relatorioPlanosECiclos();
      
      // Gerar HTML do relatório
      const html = gerarHtmlRelatorio(response);
      
      // Abrir em nova aba
      const novaJanela = window.open('', '_blank');
      novaJanela.document.write(html);
      novaJanela.document.close();
      
      showSuccess('Relatório gerado com sucesso');
    } catch (error) {
      showError(error.error || 'Erro ao gerar relatório');
    } finally {
      setGerandoRelatorio(false);
    }
  };

  const gerarHtmlRelatorio = (data) => {
    const { tenant, planos: planosRelatorio, resumo } = data;
    const dataAtual = new Date().toLocaleDateString('pt-BR', { 
      day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' 
    });

    return `
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Relatório de Planos e Ciclos - ${tenant?.nome || 'Academia'}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f97316; padding-bottom: 20px; margin-bottom: 20px; }
    .header h1 { color: #1f2937; font-size: 24px; }
    .header .info { text-align: right; color: #6b7280; font-size: 12px; }
    .resumo { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px; }
    .resumo-item { background: linear-gradient(135deg, #f97316, #fb923c); color: #fff; padding: 15px; border-radius: 8px; text-align: center; }
    .resumo-item.active { background: linear-gradient(135deg, #22c55e, #4ade80); }
    .resumo-item.inactive { background: linear-gradient(135deg, #6b7280, #9ca3af); }
    .resumo-item h3 { font-size: 28px; margin-bottom: 5px; }
    .resumo-item span { font-size: 12px; opacity: 0.9; }
    .plano { border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px; overflow: hidden; }
    .plano-header { background: #f9fafb; padding: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
    .plano-header h2 { font-size: 16px; color: #1f2937; display: flex; align-items: center; gap: 10px; }
    .plano-header .badges { display: flex; gap: 8px; }
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .badge-active { background: #dcfce7; color: #16a34a; }
    .badge-inactive { background: #fee2e2; color: #dc2626; }
    .badge-modalidade { background: #fff7ed; color: #ea580c; }
    .plano-info { padding: 15px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; background: #fafafa; }
    .plano-info-item { text-align: center; }
    .plano-info-item label { display: block; font-size: 11px; color: #6b7280; margin-bottom: 4px; text-transform: uppercase; }
    .plano-info-item strong { font-size: 14px; color: #1f2937; }
    .ciclos-header { padding: 10px 15px; background: #f3f4f6; font-size: 13px; font-weight: 600; color: #374151; border-top: 1px solid #e5e7eb; }
    .ciclos-table { width: 100%; border-collapse: collapse; }
    .ciclos-table th { background: #f9fafb; padding: 10px 15px; text-align: left; font-size: 11px; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
    .ciclos-table td { padding: 12px 15px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #374151; }
    .ciclos-table tr:last-child td { border-bottom: none; }
    .ciclos-table .valor { font-weight: 600; color: #16a34a; }
    .ciclos-table .tag { padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; }
    .tag-recorrente { background: #dbeafe; color: #2563eb; }
    .tag-avulso { background: #fef3c7; color: #d97706; }
    .tag-reposicao { background: #dcfce7; color: #16a34a; }
    .tag-sem-reposicao { background: #fee2e2; color: #dc2626; }
    .tag-ativo { background: #dcfce7; color: #16a34a; }
    .tag-inativo { background: #fee2e2; color: #dc2626; }
    .no-ciclos { padding: 20px; text-align: center; color: #9ca3af; font-style: italic; }
    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 11px; }
    @media print { body { background: #fff; padding: 0; } .container { box-shadow: none; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>📊 Relatório de Planos e Ciclos</h1>
      <div class="info">
        <strong>${tenant?.nome || 'Academia'}</strong><br>
        Gerado em: ${dataAtual}
      </div>
    </div>

    <div class="resumo">
      <div class="resumo-item">
        <h3>${resumo?.total_planos || 0}</h3>
        <span>Total de Planos</span>
      </div>
      <div class="resumo-item active">
        <h3>${resumo?.planos_ativos || 0}</h3>
        <span>Planos Ativos</span>
      </div>
      <div class="resumo-item inactive">
        <h3>${resumo?.planos_inativos || 0}</h3>
        <span>Planos Inativos</span>
      </div>
      <div class="resumo-item">
        <h3>${resumo?.total_ciclos || 0}</h3>
        <span>Total de Ciclos</span>
      </div>
    </div>

    ${(planosRelatorio || []).map(plano => `
      <div class="plano">
        <div class="plano-header">
          <h2>
            <span style="color: #f97316;">#${plano.id}</span>
            ${plano.nome}
          </h2>
          <div class="badges">
            <span class="badge badge-modalidade">${plano.modalidade_nome || 'Sem modalidade'}</span>
            <span class="badge ${plano.ativo ? 'badge-active' : 'badge-inactive'}">
              ${plano.ativo ? 'Ativo' : 'Inativo'}
            </span>
          </div>
        </div>
        <div class="plano-info">
          <div class="plano-info-item">
            <label>Valor Base</label>
            <strong>${formatCurrency(plano.valor)}</strong>
          </div>
          <div class="plano-info-item">
            <label>Checkins/Semana</label>
            <strong>${plano.checkins_semanais >= 999 ? 'Ilimitado' : plano.checkins_semanais + 'x'}</strong>
          </div>
          <div class="plano-info-item">
            <label>Duração</label>
            <strong>${plano.duracao_dias || 30} dias</strong>
          </div>
          <div class="plano-info-item">
            <label>Novos Contratos</label>
            <strong>${plano.atual ? '✅ Disponível' : '🔒 Bloqueado'}</strong>
          </div>
        </div>
        <div class="ciclos-header">Ciclos de Pagamento (${plano.ciclos?.length || 0})</div>
        ${plano.ciclos && plano.ciclos.length > 0 ? `
          <table class="ciclos-table">
            <thead>
              <tr>
                <th>Frequência</th>
                <th>Período</th>
                <th>Valor</th>
                <th>Tipo</th>
                <th>Reposição</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${plano.ciclos.map(ciclo => `
                <tr>
                  <td><strong>${ciclo.frequencia_nome || ciclo.nome || 'N/A'}</strong></td>
                  <td>${ciclo.meses || ciclo.periodo_meses || 1} ${(ciclo.meses || ciclo.periodo_meses || 1) > 1 ? 'meses' : 'mês'}</td>
                  <td class="valor">${formatCurrency(ciclo.valor)}</td>
                  <td><span class="tag ${ciclo.permite_recorrencia ? 'tag-recorrente' : 'tag-avulso'}">${ciclo.permite_recorrencia ? 'Recorrente' : 'Avulso'}</span></td>
                  <td><span class="tag ${isReposicaoPermitida(ciclo) ? 'tag-reposicao' : 'tag-sem-reposicao'}">${isReposicaoPermitida(ciclo) ? 'Permitida' : 'Não permite'}</span></td>
                  <td><span class="tag ${ciclo.ativo ? 'tag-ativo' : 'tag-inativo'}">${ciclo.ativo ? 'Ativo' : 'Inativo'}</span></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        ` : '<div class="no-ciclos">Nenhum ciclo cadastrado para este plano</div>'}
      </div>
    `).join('')}

    <div class="footer">
      Relatório gerado pelo sistema App Checkin - Painel Administrativo
    </div>
  </div>
</body>
</html>
    `;
  };

  const renderMobileCard = (plano) => (
    <View key={plano.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.cardHeaderLeft}>
          <View style={styles.cardTitleRow}>
            {plano.modalidade_icone && (
              <View style={[styles.cardIconBadge, { backgroundColor: plano.modalidade_cor || '#f97316' }]}>
                <MaterialCommunityIcons name={plano.modalidade_icone} size={16} color="#fff" />
              </View>
            )}
            <View>
              <View style={styles.idNomeRow}>
                <Text style={styles.cardId}>#{plano.id}</Text>
                <Text style={styles.cardName}>{plano.nome}</Text>
              </View>
              {isSuperAdmin && plano.academia_nome && (
                <Text style={styles.cardAcademia}>{plano.academia_nome}</Text>
              )}
              <View style={[
                styles.statusBadge,
                plano.ativo ? styles.statusActive : styles.statusInactive
              ]}>
                <Text style={[
                  styles.statusText,
                  plano.ativo ? styles.statusTextActive : styles.statusTextInactive
                ]}>
                  {plano.ativo ? 'Ativo' : 'Inativo'}
                </Text>
              </View>
            </View>
          </View>
        </View>
        <View style={styles.cardActions}>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => router.push(`/planos/${plano.id}`)}
          >
            <Feather name={isSuperAdmin ? "eye" : "edit-2"} size={18} color="#3b82f6" />
          </TouchableOpacity>
          {!isSuperAdmin && (
            <TouchableOpacity
              style={styles.cardActionButton}
              onPress={() => handleDelete(plano.id, plano.nome)}
            >
              <Feather name="trash-2" size={18} color="#ef4444" />
            </TouchableOpacity>
          )}
        </View>
      </View>

      <View style={styles.cardBody}>
        {plano.modalidade_nome && (
          <View style={styles.cardRow}>
            <Feather name="grid" size={14} color="#666" />
            <Text style={styles.cardLabel}>Modalidade:</Text>
            <Text style={styles.cardValue}>{plano.modalidade_nome}</Text>
          </View>
        )}

        <View style={styles.cardRow}>
          <Feather name="dollar-sign" size={14} color="#666" />
          <Text style={styles.cardLabel}>Valor:</Text>
          <Text style={styles.cardValue}>{formatCurrency(plano.valor)}</Text>
        </View>

        <View style={styles.cardRow}>
          <Feather name="check-circle" size={14} color="#666" />
          <Text style={styles.cardLabel}>Checkins/Semana:</Text>
          <Text style={styles.cardValue}>
            {plano.checkins_semanais >= 999 
              ? 'Ilimitado' 
              : `${plano.checkins_semanais}x`}
          </Text>
        </View>

        {plano.atual !== undefined && (
          <View style={styles.cardRow}>
            <Feather name={plano.atual ? "unlock" : "lock"} size={14} color="#666" />
            <Text style={styles.cardLabel}>Novos Contratos:</Text>
            <Text style={[styles.cardValue, !plano.atual && styles.cardValueInactive]}>
              {plano.atual ? 'Disponível' : 'Bloqueado'}
            </Text>
          </View>
        )}
      </View>
    </View>
  );

  const renderTable = () => (
    <View className="mx-4 my-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      {/* Table Header */}
      <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-3">
        {isSuperAdmin && <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colAcademia}>ACADEMIA</Text>}
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colId}>ID</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colNome}>NOME</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colModalidade}>MODALIDADE</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colValor}>VALOR</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colCheckins}>CHECKINS/SEM</Text>
        {!isSuperAdmin && <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colAtual}>NOVOS CONTR.</Text>}
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colStatus}>STATUS</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 text-right" style={styles.colAcoes}>AÇÕES</Text>
      </View>

      {/* Table Body */}
      <ScrollView className="max-h-[520px]" showsVerticalScrollIndicator={true}>
        {planos.map((plano) => (
          <View key={plano.id} className="flex-row items-center border-b border-slate-100 px-4 py-3">
            {isSuperAdmin && (
              <Text className="text-[13px] text-slate-600" style={styles.colAcademia} numberOfLines={1}>
                {plano.academia_nome || '-'}
              </Text>
            )}
            <View style={styles.colId}>
              <Text className="text-[12px] font-semibold text-slate-400">#{plano.id}</Text>
            </View>
            <View className="flex-row items-center gap-2" style={styles.colNome}>
              {plano.modalidade_icone && (
                <View style={[styles.tableIconBadge, { backgroundColor: plano.modalidade_cor || '#f97316' }]} className="h-6 w-6 items-center justify-center rounded-full">
                  <MaterialCommunityIcons name={plano.modalidade_icone} size={14} color="#fff" />
                </View>
              )}
              <Text className="text-[13px] font-semibold text-slate-800" numberOfLines={2}>
                {plano.nome}
              </Text>
            </View>
            <Text className="text-[13px] text-slate-600" style={styles.colModalidade} numberOfLines={1}>
              {plano.modalidade_nome || '-'}
            </Text>
            <Text className="text-[13px] font-semibold text-slate-700" style={styles.colValor} numberOfLines={1}>
              {formatCurrency(plano.valor)}
            </Text>
            <Text className="text-[13px] text-slate-600" style={styles.colCheckins} numberOfLines={1}>
              {plano.checkins_semanais >= 999 ? 'Ilimitado' : `${plano.checkins_semanais}x`}
            </Text>
            {!isSuperAdmin && (
              <View style={styles.colAtual}>
                <View style={[
                  styles.atualBadge,
                  plano.atual ? styles.atualAvailable : styles.atualLocked,
                ]}>
                  <Text style={[
                    styles.atualText,
                    plano.atual ? styles.atualTextAvailable : styles.atualTextLocked,
                  ]}>
                    {plano.atual ? 'Sim' : 'Não'}
                  </Text>
                </View>
              </View>
            )}
            <View style={styles.colStatus}>
              <View className={`self-start rounded-full px-2.5 py-1 ${plano.ativo ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                <Text className={`text-[11px] font-bold ${plano.ativo ? 'text-emerald-700' : 'text-rose-700'}`}>
                  {plano.ativo ? 'Ativo' : 'Inativo'}
                </Text>
              </View>
            </View>
            <View className="flex-row justify-end gap-2" style={styles.colAcoes}>
              <TouchableOpacity
                onPress={() => router.push(`/planos/${plano.id}`)}
                className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
              >
                <Feather name={isSuperAdmin ? "eye" : "edit-2"} size={16} color="#f97316" />
              </TouchableOpacity>
              {!isSuperAdmin && (
                <TouchableOpacity
                  onPress={() => handleDelete(plano.id, plano.nome)}
                  className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                >
                  <Feather name="trash-2" size={16} color="#ef4444" />
                </TouchableOpacity>
              )}
            </View>
          </View>
        ))}
      </ScrollView>
    </View>
  );

  // Renderiza o dropdown de busca de academia
  const renderAcademiaSearch = () => (
    <View style={[styles.searchCard, isMobile && styles.searchCardMobile]}>
      <View style={styles.searchCardHeader}>
        <View style={styles.searchIconContainer}>
          <Feather name="home" size={24} color="#f97316" />
        </View>
        <View style={styles.searchCardTitleContainer}>
          <Text style={styles.searchCardTitle}>Selecionar Academia</Text>
          <Text style={styles.searchCardSubtitle}>
            {selectedTenant ? `${planos.length} plano(s) encontrado(s)` : 'Digite para buscar uma academia'}
          </Text>
        </View>
      </View>

      <View style={styles.searchInputContainer}>
        <Feather name="search" size={20} color="#9ca3af" style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          placeholder="Buscar academia por nome..."
          placeholderTextColor="#9ca3af"
          value={searchText}
          onChangeText={(text) => {
            setSearchText(text);
            setShowDropdown(true);
            if (!text) {
              setSelectedTenant(null);
              setPlanos([]);
            }
          }}
          onFocus={() => setShowDropdown(true)}
        />
        {searchText.length > 0 && (
          <TouchableOpacity onPress={clearSelection} style={styles.clearButton}>
            <Feather name="x-circle" size={20} color="#9ca3af" />
          </TouchableOpacity>
        )}
      </View>

      {/* Dropdown de resultados */}
      {showDropdown && searchText.length > 0 && !selectedTenant && (
        <View style={styles.dropdownContainer}>
          <ScrollView style={styles.dropdownScroll} nestedScrollEnabled>
            {loadingTenants ? (
              <View style={styles.dropdownLoading}>
                <ActivityIndicator size="small" color="#f97316" />
                <Text style={styles.dropdownLoadingText}>Carregando academias...</Text>
              </View>
            ) : filteredTenants.length === 0 ? (
              <View style={styles.dropdownEmpty}>
                <Feather name="alert-circle" size={20} color="#9ca3af" />
                <Text style={styles.dropdownEmptyText}>Nenhuma academia encontrada</Text>
              </View>
            ) : (
              filteredTenants.map((tenant) => (
                <TouchableOpacity
                  key={tenant.id}
                  style={styles.dropdownItem}
                  onPress={() => handleSelectTenant(tenant)}
                >
                  <View style={styles.dropdownItemIcon}>
                    <Feather name="home" size={16} color="#6b7280" />
                  </View>
                  <View style={styles.dropdownItemContent}>
                    <Text style={styles.dropdownItemName}>{tenant.nome}</Text>
                    <Text style={styles.dropdownItemCity}>{tenant.cidade}/{tenant.estado}</Text>
                  </View>
                  <Feather name="chevron-right" size={16} color="#d1d5db" />
                </TouchableOpacity>
              ))
            )}
          </ScrollView>
        </View>
      )}

      {/* Academia selecionada */}
      {selectedTenant && (
        <View style={styles.selectedTenantBadge}>
          <Feather name="check-circle" size={16} color="#10b981" />
          <Text style={styles.selectedTenantText}>{selectedTenant.nome}</Text>
          <TouchableOpacity onPress={clearSelection}>
            <Feather name="x" size={16} color="#6b7280" />
          </TouchableOpacity>
        </View>
      )}
    </View>
  );

  if (!isSuperAdmin && loading && planos.length === 0) {
    return (
      <LayoutBase title="Planos" subtitle="Gerenciar planos de assinatura">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando planos...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Planos" subtitle={isSuperAdmin ? "Visualizar planos das academias" : "Gerenciar planos de assinatura"}>
      <View style={styles.container}>
        {/* Header para SuperAdmin com busca estilizada */}
        {isSuperAdmin ? (
          <View style={styles.superAdminHeader}>
            {/* Banner Header */}
            <View style={styles.superAdminBanner}>
              <View style={styles.bannerContent}>
                <View style={styles.bannerIconContainer}>
                  <View style={styles.bannerIconOuter}>
                    <View style={styles.bannerIconInner}>
                      <Feather name="layers" size={28} color="#fff" />
                    </View>
                  </View>
                </View>
                <View style={styles.bannerTextContainer}>
                  <Text style={styles.bannerTitle}>Planos das Academias</Text>
                  <Text style={styles.bannerSubtitle}>
                    Gerencie e visualize todos os planos criados pelas academias parceiras
                  </Text>
                </View>
              </View>
              <View style={styles.bannerDecoration}>
                <View style={styles.decorCircle1} />
                <View style={styles.decorCircle2} />
                <View style={styles.decorCircle3} />
              </View>
            </View>
            {/* Card de Busca */}
            {renderAcademiaSearch()}
          </View>
        ) : (
          /* Header Actions para Admin */
          <View style={[styles.header, isMobile && styles.headerMobile]}>
            <View style={styles.headerInfo}>
              <Text style={[styles.headerTitle, isMobile && styles.headerTitleMobile]}>Lista de Planos</Text>
              <Text style={styles.headerSubtitle}>{planos.length} plano(s) cadastrado(s)</Text>
            </View>
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <TouchableOpacity
                style={[styles.reportButton, isMobile && styles.addButtonMobile, gerandoRelatorio && { opacity: 0.6 }]}
                onPress={handleGerarRelatorio}
                activeOpacity={0.8}
                disabled={gerandoRelatorio}
              >
                {gerandoRelatorio ? (
                  <ActivityIndicator size={16} color="#fff" />
                ) : (
                  <Feather name="file-text" size={18} color="#fff" />
                )}
                {!isMobile && <Text style={styles.addButtonText}>{gerandoRelatorio ? 'Gerando...' : 'Relatório'}</Text>}
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.addButton, isMobile && styles.addButtonMobile]}
                onPress={() => router.push('/planos/novo')}
                activeOpacity={0.8}
              >
                <Feather name="plus" size={18} color="#fff" />
                {!isMobile && <Text style={styles.addButtonText}>Novo Plano</Text>}
              </TouchableOpacity>
            </View>
          </View>
        )}

        {/* Conteúdo */}
        {loading ? (
          <View style={styles.loadingInline}>
            <ActivityIndicator size="small" color="#f97316" />
            <Text style={styles.loadingInlineText}>Carregando planos...</Text>
          </View>
        ) : isSuperAdmin && !selectedTenant ? (
          <View style={styles.emptyContainer}>
            <Feather name="search" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>Selecione uma academia</Text>
            <Text style={styles.emptySubtext}>
              Digite o nome da academia no campo acima para visualizar seus planos
            </Text>
          </View>
        ) : planos.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="inbox" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhum plano {isSuperAdmin ? 'encontrado' : 'cadastrado'}</Text>
            <Text style={styles.emptySubtext}>
              {isSuperAdmin 
                ? 'Esta academia não possui planos cadastrados'
                : 'Clique em "Novo Plano" para começar'}
            </Text>
          </View>
        ) : (
          isMobile ? (
            <ScrollView style={styles.cardsContainer} showsVerticalScrollIndicator={false}>
              {planos.map(renderMobileCard)}
            </ScrollView>
          ) : (
            renderTable()
          )
        )}
      </View>

      <ConfirmModal
        visible={confirmDelete.visible}
        title="Desativar Plano"
        message={`Tem certeza que deseja desativar o plano "${confirmDelete.nome}"?`}
        type="danger"
        onConfirm={confirmDeleteAction}
        onCancel={cancelDelete}
      />
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 100,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#6b7280',
    fontWeight: '500',
  },
  loadingInline: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
    gap: 10,
  },
  loadingInlineText: {
    fontSize: 14,
    color: '#6b7280',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e5e5',
    flexWrap: 'wrap',
    gap: 16,
  },
  headerMobile: {
    flexDirection: 'column',
    alignItems: 'stretch',
    padding: 16,
  },
  headerInfo: {
    flex: 1,
    minWidth: 200,
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
  },
  headerTitleMobile: {
    fontSize: 20,
  },
  headerSubtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
    fontWeight: '400',
  },
  filterContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  filterContainerMobile: {
    flexDirection: 'column',
    alignItems: 'stretch',
    gap: 8,
    marginTop: 8,
  },
  filterLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
  },
  pickerWrapper: {
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#d1d5db',
    minWidth: 250,
    overflow: 'hidden',
  },
  picker: {
    height: 44,
    backgroundColor: 'transparent',
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f97316',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 8,
    gap: 8,
  },
  reportButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#3b82f6',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 8,
    gap: 8,
  },
  addButtonMobile: {
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderRadius: 50,
  },
  addButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 80,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#6b7280',
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#9ca3af',
    marginTop: 8,
  },
  // Cards Mobile
  cardsContainer: {
    flex: 1,
    padding: 16,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  cardHeaderLeft: {
    flex: 1,
  },
  cardAcademia: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  cardActions: {
    flexDirection: 'row',
    gap: 8,
  },
  cardActionButton: {
    padding: 8,
    borderRadius: 8,
    backgroundColor: '#f5f5f5',
  },
  cardBody: {
    gap: 10,
  },
  cardRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  cardLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
    minWidth: 90,
  },
  cardValue: {
    flex: 1,
    fontSize: 14,
    color: '#333',
  },
  // Tabela Desktop
  tableContainer: {
    flex: 1,
    margin: 20,
    backgroundColor: '#fff',
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f8f9fa',
    padding: 16,
    borderBottomWidth: 2,
    borderBottomColor: '#e5e5e5',
  },
  headerText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#666',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  tableBody: {
    flex: 1,
  },
  tableRow: {
    flexDirection: 'row',
    paddingVertical: 16,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    alignItems: 'center',
  },
  cellText: {
    fontSize: 14,
    color: '#333',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  colAcademia: { flex: 1.5, minWidth: 130 },
  colId: { flex: 0.6, minWidth: 60 },
  colNome: { flex: 2, minWidth: 150 },
  colModalidade: { flex: 1.5, minWidth: 120 },
  colValor: { flex: 1.2, minWidth: 100 },
  colCheckins: { flex: 1.2, minWidth: 110 },
  colAtual: { flex: 1.2, minWidth: 110 },
  colStatus: { flex: 1, minWidth: 100 },
  colAcoes: { flex: 1, minWidth: 100 },
  tableIdText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#64748b',
  },
  nomeCell: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  cellTextNome: {
    fontSize: 14,
    color: '#333',
    flex: 1,
  },
  tableIconBadge: {
    width: 24,
    height: 24,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  cardIconBadge: {
    width: 32,
    height: 32,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  idNomeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  cardId: {
    fontSize: 12,
    fontWeight: '700',
    color: '#64748b',
    backgroundColor: '#f1f5f9',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  cardName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1e293b',
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  statusActive: {
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
  },
  statusInactive: {
    backgroundColor: 'rgba(239, 68, 68, 0.1)',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  statusTextActive: {
    color: '#10b981',
  },
  statusTextInactive: {
    color: '#ef4444',
  },
  atualBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  atualAvailable: {
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
  },
  atualLocked: {
    backgroundColor: 'rgba(156, 163, 175, 0.1)',
  },
  atualText: {
    fontSize: 12,
    fontWeight: '600',
  },
  atualTextAvailable: {
    color: '#3b82f6',
  },
  atualTextLocked: {
    color: '#6b7280',
  },
  cardValueInactive: {
    color: '#9ca3af',
    fontStyle: 'italic',
  },
  actionCell: {
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'flex-end',
  },
  actionButton: {
    padding: 8,
  },
  // SuperAdmin Header e Search
  superAdminHeader: {
    backgroundColor: '#f8fafc',
  },
  superAdminBanner: {
    backgroundColor: '#f97316',
    paddingVertical: 28,
    paddingHorizontal: 24,
    position: 'relative',
    overflow: 'hidden',
  },
  bannerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 18,
    zIndex: 2,
  },
  bannerIconContainer: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconOuter: {
    width: 64,
    height: 64,
    borderRadius: 20,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconInner: {
    width: 48,
    height: 48,
    borderRadius: 14,
    backgroundColor: 'rgba(255, 255, 255, 0.25)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerTextContainer: {
    flex: 1,
  },
  bannerTitle: {
    fontSize: 26,
    fontWeight: '800',
    color: '#fff',
    letterSpacing: -0.5,
  },
  bannerSubtitle: {
    fontSize: 14,
    color: 'rgba(255, 255, 255, 0.85)',
    marginTop: 4,
    lineHeight: 20,
  },
  bannerDecoration: {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    width: 200,
    zIndex: 1,
  },
  decorCircle1: {
    position: 'absolute',
    top: -30,
    right: -30,
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
  },
  decorCircle2: {
    position: 'absolute',
    top: 40,
    right: 60,
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  decorCircle3: {
    position: 'absolute',
    bottom: -20,
    right: 20,
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(255, 255, 255, 0.06)',
  },
  searchCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 20,
    marginHorizontal: 20,
    marginTop: -24,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 4,
    zIndex: 10,
  },
  searchCardMobile: {
    padding: 16,
  },
  searchCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
    gap: 12,
  },
  searchIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 12,
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  searchCardTitleContainer: {
    flex: 1,
  },
  searchCardTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1f2937',
  },
  searchCardSubtitle: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  searchInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e5e7eb',
    paddingHorizontal: 14,
    height: 52,
  },
  searchIcon: {
    marginRight: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 16,
    color: '#1f2937',
    outlineStyle: 'none',
    height: '100%',
  },
  clearButton: {
    padding: 6,
  },
  dropdownContainer: {
    marginTop: 8,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    maxHeight: 280,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 4,
  },
  dropdownScroll: {
    maxHeight: 280,
  },
  dropdownItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    gap: 12,
  },
  dropdownItemIcon: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: '#f3f4f6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  dropdownItemContent: {
    flex: 1,
  },
  dropdownItemName: {
    fontSize: 15,
    fontWeight: '600',
    color: '#1f2937',
  },
  dropdownItemCity: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  dropdownLoading: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
    gap: 10,
  },
  dropdownLoadingText: {
    fontSize: 14,
    color: '#6b7280',
  },
  dropdownEmpty: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
    gap: 8,
  },
  dropdownEmptyText: {
    fontSize: 14,
    color: '#9ca3af',
  },
  selectedTenantBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ecfdf5',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
    marginTop: 12,
    gap: 10,
    borderWidth: 1,
    borderColor: '#a7f3d0',
  },
  selectedTenantText: {
    flex: 1,
    fontSize: 15,
    fontWeight: '600',
    color: '#059669',
  },
});
