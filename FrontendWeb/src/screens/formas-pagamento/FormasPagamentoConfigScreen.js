import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  Modal,
  TextInput,
  ScrollView,
  Switch,
  Platform,
  useWindowDimensions
} from 'react-native';
import LayoutBase from '../../components/LayoutBase';
import { Feather } from '@expo/vector-icons';
import api from '../../services/api';
import { showSuccess, showError } from '../../utils/toast';

export default function FormasPagamentoConfigScreen() {
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [loading, setLoading] = useState(true);
  const [formasPagamento, setFormasPagamento] = useState([]);
  const [modalVisible, setModalVisible] = useState(false);
  const [selectedForma, setSelectedForma] = useState(null);
  const [formData, setFormData] = useState({});

  useEffect(() => {
    carregarFormasPagamento();
  }, []);

  const carregarFormasPagamento = async () => {
    try {
      setLoading(true);
      const response = await api.get('/admin/formas-pagamento-config');
      const formas = response.data.formas_pagamento || [];
      
      // Ordenar por status (ativos primeiro) e depois por nome
      const formasOrdenadas = formas.sort((a, b) => {
        // Primeiro por status (ativos primeiro)
        const statusCompare = b.ativo - a.ativo;
        if (statusCompare !== 0) return statusCompare;
        
        // Depois por nome
        return a.forma_pagamento_nome.localeCompare(b.forma_pagamento_nome);
      });
      
      setFormasPagamento(formasOrdenadas);
    } catch (error) {
      console.error('Erro ao carregar formas de pagamento:', error);
      showError('Não foi possível carregar as formas de pagamento');
    } finally {
      setLoading(false);
    }
  };

  const abrirModal = (forma) => {
    setSelectedForma(forma);
    setFormData({
      ativo: forma.ativo === 1,
      taxa_percentual: String(forma.taxa_percentual || '0.00'),
      taxa_fixa: String(forma.taxa_fixa || '0.00'),
      aceita_parcelamento: forma.aceita_parcelamento === 1,
      parcelas_minimas: String(forma.parcelas_minimas || '1'),
      parcelas_maximas: String(forma.parcelas_maximas || '1'),
      juros_parcelamento: String(forma.juros_parcelamento || '0.00'),
      parcelas_sem_juros: String(forma.parcelas_sem_juros || '1'),
      dias_compensacao: String(forma.dias_compensacao || '0'),
      valor_minimo: String(forma.valor_minimo || '0.00'),
      observacoes: forma.observacoes || ''
    });
    setModalVisible(true);
  };

  const fecharModal = () => {
    setModalVisible(false);
    setSelectedForma(null);
    setFormData({});
  };

  const salvar = async () => {
    try {
      const payload = {
        ativo: formData.ativo ? 1 : 0,
        taxa_percentual: parseFloat(formData.taxa_percentual) || 0,
        taxa_fixa: parseFloat(formData.taxa_fixa) || 0,
        aceita_parcelamento: formData.aceita_parcelamento ? 1 : 0,
        parcelas_minimas: parseInt(formData.parcelas_minimas) || 1,
        parcelas_maximas: parseInt(formData.parcelas_maximas) || 1,
        juros_parcelamento: parseFloat(formData.juros_parcelamento) || 0,
        parcelas_sem_juros: parseInt(formData.parcelas_sem_juros) || 1,
        dias_compensacao: parseInt(formData.dias_compensacao) || 0,
        valor_minimo: parseFloat(formData.valor_minimo) || 0,
        observacoes: formData.observacoes || null
      };

      await api.put(`/admin/formas-pagamento-config/${selectedForma.id}`, payload);
      
      showSuccess('Configuração atualizada com sucesso');
      fecharModal();
      carregarFormasPagamento();
    } catch (error) {
      console.error('Erro ao salvar:', error);
      showError(error.response?.data?.message || 'Erro ao salvar configuração');
    }
  };

  const toggleAtivo = async (item) => {
    try {
      const novoStatus = item.ativo === 1 ? 0 : 1;
      await api.put(`/admin/formas-pagamento-config/${item.id}`, {
        ativo: novoStatus,
        taxa_percentual: parseFloat(item.taxa_percentual),
        taxa_fixa: parseFloat(item.taxa_fixa),
        aceita_parcelamento: item.aceita_parcelamento,
        parcelas_minimas: parseInt(item.parcelas_minimas),
        parcelas_maximas: parseInt(item.parcelas_maximas),
        juros_parcelamento: parseFloat(item.juros_parcelamento),
        parcelas_sem_juros: parseInt(item.parcelas_sem_juros),
        dias_compensacao: parseInt(item.dias_compensacao),
        valor_minimo: parseFloat(item.valor_minimo),
        observacoes: item.observacoes
      });
      
      showSuccess(`Forma de pagamento ${novoStatus ? 'ativada' : 'desativada'} com sucesso`);
      carregarFormasPagamento();
    } catch (error) {
      console.error('Erro ao alterar status:', error);
      showError('Não foi possível alterar o status');
    }
  };

  const renderMobileCard = (item) => (
    <View key={item.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.cardHeaderLeft}>
          <View style={styles.formaNomeContainer}>
            <Feather 
              name={item.aceita_parcelamento ? 'credit-card' : 'dollar-sign'} 
              size={20} 
              color="#f97316" 
            />
            <Text style={styles.cardName}>{item.forma_pagamento_nome}</Text>
          </View>
          <View style={[
            styles.statusBadge,
            item.ativo ? styles.statusAtivo : styles.statusInativo
          ]}>
            <Text style={styles.statusText}>
              {item.ativo ? 'Ativo' : 'Inativo'}
            </Text>
          </View>
        </View>
        <View style={styles.cardActions}>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => abrirModal(item)}
          >
            <Feather name="edit-2" size={18} color="#3b82f6" />
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => toggleAtivo(item)}
          >
            <Feather 
              name={item.ativo ? 'toggle-right' : 'toggle-left'} 
              size={20} 
              color={item.ativo ? '#16a34a' : '#ef4444'} 
            />
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.cardBody}>
        <View style={styles.cardRow}>
          <Feather name="percent" size={14} color="#666" />
          <Text style={styles.cardLabel}>Taxa:</Text>
          <Text style={styles.cardValue}>
            {parseFloat(item.taxa_percentual).toFixed(2)}%
            {parseFloat(item.taxa_fixa) > 0 && ` + R$ ${parseFloat(item.taxa_fixa).toFixed(2)}`}
          </Text>
        </View>

        <View style={styles.cardRow}>
          <Feather name="credit-card" size={14} color="#666" />
          <Text style={styles.cardLabel}>Parcelamento:</Text>
          <Text style={styles.cardValue}>
            {item.aceita_parcelamento === 1 
              ? `Até ${item.parcelas_maximas}x (${item.parcelas_sem_juros}x s/ juros)`
              : 'Não aceita'
            }
          </Text>
        </View>

        <View style={styles.cardRow}>
          <Feather name="clock" size={14} color="#666" />
          <Text style={styles.cardLabel}>Compensação:</Text>
          <Text style={styles.cardValue}>
            {item.dias_compensacao} {item.dias_compensacao === 1 ? 'dia' : 'dias'}
          </Text>
        </View>
      </View>
    </View>
  );

  const renderTable = () => (
    <View style={styles.tableContainer}>
      {/* Header da Tabela */}
      <View style={styles.tableHeader}>
        <Text style={[styles.tableHeaderText, styles.colForma]}>FORMA DE PAGAMENTO</Text>
        <Text style={[styles.tableHeaderText, styles.colStatus]}>STATUS</Text>
        <Text style={[styles.tableHeaderText, styles.colTaxa]}>TAXA OPERADORA</Text>
        <Text style={[styles.tableHeaderText, styles.colParcelamento]}>PARCELAMENTO</Text>
        <Text style={[styles.tableHeaderText, styles.colCompensacao]}>COMPENSAÇÃO</Text>
        <Text style={[styles.tableHeaderText, styles.colAcoes]}>AÇÕES</Text>
      </View>

      {/* Linhas da Tabela */}
      <ScrollView style={styles.tableBody} showsVerticalScrollIndicator={true}>
        {formasPagamento.map((item) => (
          <View key={item.id} style={styles.tableRow}>
            <View style={[styles.tableCell, styles.colForma]}>
              <View style={styles.formaNomeContainer}>
                <Feather 
                  name={item.aceita_parcelamento ? 'credit-card' : 'dollar-sign'} 
                  size={18} 
                  color="#f97316" 
                />
                <Text style={styles.cellText} numberOfLines={2}>{item.forma_pagamento_nome}</Text>
              </View>
            </View>

            <View style={[styles.tableCell, styles.colStatus]}>
              <View style={[
                styles.statusBadge,
                item.ativo ? styles.statusAtivo : styles.statusInativo
              ]}>
                <Text style={styles.statusText}>
                  {item.ativo ? 'Ativo' : 'Inativo'}
                </Text>
              </View>
            </View>

            <Text style={[styles.tableCell, styles.colTaxa]} numberOfLines={1}>
              {parseFloat(item.taxa_percentual).toFixed(2)}%
              {parseFloat(item.taxa_fixa) > 0 && ` + R$ ${parseFloat(item.taxa_fixa).toFixed(2)}`}
            </Text>

            <Text style={[styles.tableCell, styles.colParcelamento]} numberOfLines={1}>
              {item.aceita_parcelamento === 1 
                ? `Até ${item.parcelas_maximas}x (${item.parcelas_sem_juros}x s/ juros)`
                : 'Não aceita'
              }
            </Text>

            <Text style={[styles.tableCell, styles.colCompensacao]} numberOfLines={1}>
              {item.dias_compensacao} {item.dias_compensacao === 1 ? 'dia' : 'dias'}
            </Text>

            <View style={[styles.tableCell, styles.colAcoes]}>
              <View style={styles.actions}>
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={() => abrirModal(item)}
                >
                  <Feather name="edit-2" size={16} color="#3b82f6" />
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={() => toggleAtivo(item)}
                >
                  <Feather 
                    name={item.ativo ? 'toggle-right' : 'toggle-left'} 
                    size={18} 
                    color={item.ativo ? '#16a34a' : '#ef4444'} 
                  />
                </TouchableOpacity>
              </View>
            </View>
          </View>
        ))}
      </ScrollView>
    </View>
  );

  if (loading) {
    return (
      <LayoutBase title="Formas de Pagamento" subtitle="Configure as formas de pagamento aceitas e suas taxas">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando formas de pagamento...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Formas de Pagamento" subtitle="Configure as formas de pagamento aceitas e suas taxas">
      <View style={styles.container}>
        {/* Header */}
        <View style={[styles.header, isMobile && styles.headerMobile]}>
          <View>
            <Text style={[styles.headerTitle, isMobile && styles.headerTitleMobile]}>
              Configuração de Formas de Pagamento
            </Text>
            <Text style={styles.headerSubtitle}>
              {formasPagamento.length} {formasPagamento.length === 1 ? 'forma cadastrada' : 'formas cadastradas'}
            </Text>
          </View>
        </View>

        {/* Lista */}
        {formasPagamento.length === 0 ? (
          <View style={styles.emptyState}>
            <Feather name="credit-card" size={48} color="#ccc" />
            <Text style={styles.emptyText}>Nenhuma forma de pagamento disponível</Text>
          </View>
        ) : (
          isMobile ? (
            <ScrollView style={styles.cardsContainer} showsVerticalScrollIndicator={false}>
              {formasPagamento.map(renderMobileCard)}
            </ScrollView>
          ) : (
            renderTable()
          )
        )}

        {/* Modal de Edição */}
        <Modal
          visible={modalVisible}
          animationType="slide"
          transparent={true}
          onRequestClose={fecharModal}
        >
          <View style={styles.modalOverlay}>
            <View style={styles.modalContent}>
              <View style={styles.modalHeader}>
                <Text style={styles.modalTitle}>
                  {selectedForma?.forma_pagamento_nome}
                </Text>
                <TouchableOpacity onPress={fecharModal}>
                  <Feather name="x" size={24} color="#6b7280" />
                </TouchableOpacity>
              </View>

              <ScrollView style={styles.modalBody} showsVerticalScrollIndicator={false}>
                {/* Ativo */}
                <View style={styles.formGroup}>
                  <View style={styles.switchRow}>
                    <Text style={styles.label}>Ativo</Text>
                    <Switch
                      value={formData.ativo}
                      onValueChange={(value) => setFormData({ ...formData, ativo: value })}
                      trackColor={{ false: '#d1d5db', true: '#fed7aa' }}
                      thumbColor={formData.ativo ? '#f97316' : '#f4f3f4'}
                    />
                  </View>
                </View>

                {/* Taxas */}
                <Text style={styles.sectionTitle}>Taxas da Operadora</Text>
                
                <View style={styles.row}>
                  <View style={[styles.formGroup, styles.flex1]}>
                    <Text style={styles.label}>Taxa Percentual (%)</Text>
                    <TextInput
                      style={styles.input}
                      value={formData.taxa_percentual}
                      onChangeText={(text) => setFormData({ ...formData, taxa_percentual: text })}
                      keyboardType="decimal-pad"
                      placeholder="0.00"
                    />
                  </View>

                  <View style={[styles.formGroup, styles.flex1]}>
                    <Text style={styles.label}>Taxa Fixa (R$)</Text>
                    <TextInput
                      style={styles.input}
                      value={formData.taxa_fixa}
                      onChangeText={(text) => setFormData({ ...formData, taxa_fixa: text })}
                      keyboardType="decimal-pad"
                      placeholder="0.00"
                    />
                  </View>
                </View>

                {/* Parcelamento */}
                <Text style={styles.sectionTitle}>Parcelamento</Text>

                <View style={styles.formGroup}>
                  <View style={styles.switchRow}>
                    <Text style={styles.label}>Aceita Parcelamento</Text>
                    <Switch
                      value={formData.aceita_parcelamento}
                      onValueChange={(value) => setFormData({ ...formData, aceita_parcelamento: value })}
                      trackColor={{ false: '#d1d5db', true: '#fed7aa' }}
                      thumbColor={formData.aceita_parcelamento ? '#f97316' : '#f4f3f4'}
                    />
                  </View>
                </View>

                {formData.aceita_parcelamento && (
                  <>
                    <View style={styles.row}>
                      <View style={[styles.formGroup, styles.flex1]}>
                        <Text style={styles.label}>Parcelas Mínimas</Text>
                        <TextInput
                          style={styles.input}
                          value={formData.parcelas_minimas}
                          onChangeText={(text) => setFormData({ ...formData, parcelas_minimas: text })}
                          keyboardType="number-pad"
                          placeholder="1"
                        />
                      </View>

                      <View style={[styles.formGroup, styles.flex1]}>
                        <Text style={styles.label}>Parcelas Máximas</Text>
                        <TextInput
                          style={styles.input}
                          value={formData.parcelas_maximas}
                          onChangeText={(text) => setFormData({ ...formData, parcelas_maximas: text })}
                          keyboardType="number-pad"
                          placeholder="12"
                        />
                      </View>
                    </View>

                    <View style={styles.row}>
                      <View style={[styles.formGroup, styles.flex1]}>
                        <Text style={styles.label}>Parcelas Sem Juros</Text>
                        <TextInput
                          style={styles.input}
                          value={formData.parcelas_sem_juros}
                          onChangeText={(text) => setFormData({ ...formData, parcelas_sem_juros: text })}
                          keyboardType="number-pad"
                          placeholder="1"
                        />
                      </View>

                      <View style={[styles.formGroup, styles.flex1]}>
                        <Text style={styles.label}>Juros Parcelamento (%)</Text>
                        <TextInput
                          style={styles.input}
                          value={formData.juros_parcelamento}
                          onChangeText={(text) => setFormData({ ...formData, juros_parcelamento: text })}
                          keyboardType="decimal-pad"
                          placeholder="0.00"
                        />
                      </View>
                    </View>
                  </>
                )}

                {/* Outros */}
                <Text style={styles.sectionTitle}>Outras Configurações</Text>

                <View style={styles.row}>
                  <View style={[styles.formGroup, styles.flex1]}>
                    <Text style={styles.label}>Dias para Compensação</Text>
                    <TextInput
                      style={styles.input}
                      value={formData.dias_compensacao}
                      onChangeText={(text) => setFormData({ ...formData, dias_compensacao: text })}
                      keyboardType="number-pad"
                      placeholder="0"
                    />
                  </View>

                  <View style={[styles.formGroup, styles.flex1]}>
                    <Text style={styles.label}>Valor Mínimo (R$)</Text>
                    <TextInput
                      style={styles.input}
                      value={formData.valor_minimo}
                      onChangeText={(text) => setFormData({ ...formData, valor_minimo: text })}
                      keyboardType="decimal-pad"
                      placeholder="0.00"
                    />
                  </View>
                </View>

                <View style={styles.formGroup}>
                  <Text style={styles.label}>Observações</Text>
                  <TextInput
                    style={[styles.input, styles.textArea]}
                    value={formData.observacoes}
                    onChangeText={(text) => setFormData({ ...formData, observacoes: text })}
                    multiline
                    numberOfLines={4}
                    placeholder="Observações adicionais..."
                  />
                </View>
              </ScrollView>

              <View style={styles.modalFooter}>
                <TouchableOpacity style={styles.btnCancelar} onPress={fecharModal}>
                  <Text style={styles.btnCancelarText}>Cancelar</Text>
                </TouchableOpacity>
                <TouchableOpacity style={styles.btnSalvar} onPress={salvar}>
                  <Feather name="save" size={18} color="#fff" />
                  <Text style={styles.btnSalvarText}>Salvar</Text>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        </Modal>
      </View>
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
    padding: 40,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#666',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e5e5',
  },
  headerMobile: {
    padding: 16,
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
    color: '#666',
    marginTop: 4,
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
    gap: 8,
  },
  cardName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
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
    minWidth: 100,
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
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f8f9fa',
    padding: 16,
    borderBottomWidth: 2,
    borderBottomColor: '#e5e5e5',
  },
  tableHeaderText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#666',
    textTransform: 'uppercase',
  },
  tableBody: {
    flex: 1,
  },
  tableRow: {
    flexDirection: 'row',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  tableCell: {
    fontSize: 14,
    color: '#333',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  cellText: {
    fontSize: 14,
    color: '#333',
  },
  colForma: { flex: 2.5, minWidth: 180 },
  colStatus: { flex: 1, minWidth: 100 },
  colTaxa: { flex: 1.5, minWidth: 140 },
  colParcelamento: { flex: 2, minWidth: 160 },
  colCompensacao: { flex: 1.2, minWidth: 100 },
  colAcoes: { flex: 1, minWidth: 100 },
  
  formaNomeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  
  statusBadge: {
    paddingVertical: 4,
    paddingHorizontal: 12,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  statusAtivo: {
    backgroundColor: '#dcfce7',
  },
  statusInativo: {
    backgroundColor: '#fee2e2',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  
  actions: {
    flexDirection: 'row',
    gap: 12,
  },
  actionButton: {
    padding: 8,
  },
  
  emptyState: {
    padding: 60,
    alignItems: 'center',
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#999',
    marginTop: 16,
  },

  // Modal
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 16,
    width: '100%',
    maxWidth: 600,
    maxHeight: '90%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 8,
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
    backgroundColor: '#f9fafb',
  },
  modalTitle: {
    flex: 1,
    fontSize: 20,
    fontWeight: 'bold',
    color: '#111827',
  },
  modalBody: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
    marginTop: 16,
    marginBottom: 12,
  },
  formGroup: {
    marginBottom: 16,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 14,
    color: '#111827',
    backgroundColor: '#fff',
  },
  textArea: {
    height: 80,
    textAlignVertical: 'top',
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  flex1: {
    flex: 1,
  },
  modalFooter: {
    flexDirection: 'row',
    gap: 12,
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
    backgroundColor: '#f9fafb',
  },
  btnCancelar: {
    flex: 1,
    backgroundColor: '#fff',
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#d1d5db',
    alignItems: 'center',
  },
  btnCancelarText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#6b7280',
  },
  btnSalvar: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#f97316',
    paddingVertical: 12,
    borderRadius: 8,
    gap: 8,
  },
  btnSalvarText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#fff',
  },
});
