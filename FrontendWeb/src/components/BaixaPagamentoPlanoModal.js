import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Modal, TextInput, ScrollView, ActivityIndicator } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { showSuccess, showError } from '../utils/toast';
import api from '../services/api';

export default function BaixaPagamentoPlanoModal({ visible, onClose, pagamento, onSuccess }) {
  const [loading, setLoading] = useState(false);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [formasPagamento, setFormasPagamento] = useState([]);
  const [formData, setFormData] = useState({
    data_pagamento: '',
    forma_pagamento_id: '',
    comprovante: '',
    observacoes: ''
  });

  useEffect(() => {
    if (visible && pagamento) {
      // Preencher dados do pagamento
      const hoje = new Date().toISOString().split('T')[0];
      setFormData({
        data_pagamento: hoje,
        forma_pagamento_id: pagamento.forma_pagamento_id || '',
        comprovante: '',
        observacoes: ''
      });
      loadFormasPagamento();
    }
  }, [visible, pagamento]);

  const loadFormasPagamento = async () => {
    try {
      const response = await api.get('/formas-pagamento');
      console.log('Formas de pagamento:', response.data);
      const formas = response.data.formas || [];
      setFormasPagamento(formas);
    } catch (error) {
      console.error('Erro ao carregar formas de pagamento:', error);
    }
  };

  const handleConfirmar = async () => {
    if (!formData.data_pagamento) {
      showError('Data de pagamento é obrigatória');
      return;
    }

    if (!formData.forma_pagamento_id) {
      showError('Selecione a forma de pagamento');
      return;
    }

    // Abrir modal de confirmação ao invés de submeter direto
    setShowConfirmModal(true);
  };

  const handleConfirmarFinal = async () => {
    try {
      setLoading(true);
      setShowConfirmModal(false);
      
      const payload = {
        data_pagamento: formData.data_pagamento,
        forma_pagamento_id: formData.forma_pagamento_id,
        comprovante: formData.comprovante,
        observacoes: formData.observacoes
      };
      
      console.log('Enviando dados de pagamento:', payload);
      
      await api.post(`/admin/pagamentos-plano/${pagamento.id}/confirmar`, payload);

      showSuccess('Pagamento confirmado! Próximo pagamento gerado automaticamente.');
      onSuccess && onSuccess();
      onClose();
    } catch (error) {
      showError(error.error || 'Erro ao confirmar pagamento');
    } finally {
      setLoading(false);
    }
  };

  const formatarData = (data) => {
    if (!data) return '';
    const [ano, mes, dia] = data.split('-');
    return `${dia}/${mes}/${ano}`;
  };

  if (!pagamento) return null;

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
    >
      <View style={styles.overlay}>
        <View style={styles.modal}>
          {/* Header */}
          <View style={styles.header}>
            <View style={{ flex: 1 }}>
              <Text style={styles.title}>Confirmar Pagamento</Text>
              <Text style={styles.subtitle}>Pagamento #{pagamento.id}</Text>
            </View>
            <TouchableOpacity onPress={onClose} style={styles.closeButton}>
              <Feather name="x" size={24} color="#6b7280" />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.content}>
            {/* Fatura - Informações do Pagamento */}
            <View style={styles.faturaContainer}>
              <View style={styles.faturaHeaderLine} />
              
              <View style={styles.faturaRow}>
                {/* Vencimento */}
                <View style={styles.faturaColumn}>
                  <View style={styles.faturaIconRowInline}>
                    <Feather name="calendar" size={18} color="#6b7280" />
                    <Text style={styles.faturaLabelInline}>Vencimento</Text>
                  </View>
                  <Text style={styles.faturaValueInline}>{formatarData(pagamento.data_vencimento)}</Text>
                </View>

                <View style={styles.verticalDivider} />

                {/* Valor */}
                <View style={styles.faturaColumn}>
                  <View style={styles.faturaIconRowInline}>
                    <MaterialCommunityIcons name="cash" size={20} color="#3b82f6" />
                    <Text style={styles.faturaLabelInline}>Valor</Text>
                  </View>
                  <Text style={[styles.faturaValueInline, styles.valorDestaqueInline]}>
                    {parseFloat(pagamento.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                  </Text>
                </View>

                <View style={styles.verticalDivider} />

                {/* Data de Pagamento */}
                <View style={styles.faturaColumn}>
                  <View style={styles.faturaIconRowInline}>
                    <Feather name="calendar" size={18} color="#10b981" />
                    <Text style={styles.faturaLabelInline}>Pagamento</Text>
                  </View>
                  <Text style={styles.faturaValueInline}>{formatarData(formData.data_pagamento)}</Text>
                </View>
              </View>

              <View style={styles.faturaFooterLine} />
            </View>

            {/* Aluno e Plano */}
            <View style={styles.infoCard}>
              <View style={styles.infoRow}>
                <Feather name="user" size={16} color="#6b7280" />
                <Text style={styles.infoLabel}>Aluno:</Text>
                <Text style={styles.infoValue}>{pagamento.aluno_nome}</Text>
              </View>
              <View style={styles.infoRow}>
                <Feather name="package" size={16} color="#6b7280" />
                <Text style={styles.infoLabel}>Plano:</Text>
                <Text style={styles.infoValue}>{pagamento.plano_nome}</Text>
              </View>
            </View>

            {/* Forma de Pagamento - OBRIGATÓRIA */}
            <View style={styles.field}>
              <Text style={styles.label}>Forma de Pagamento *</Text>
              <View style={styles.pickerContainer}>
                <Picker
                  selectedValue={formData.forma_pagamento_id}
                  onValueChange={(itemValue) => setFormData({ ...formData, forma_pagamento_id: itemValue })}
                  style={styles.picker}
                >
                  <Picker.Item label="Selecione a forma de pagamento" value="" />
                  {formasPagamento.map((forma) => (
                    <Picker.Item 
                      key={forma.id} 
                      label={forma.nome} 
                      value={forma.id} 
                    />
                  ))}
                </Picker>
              </View>
            </View>

            {/* Data do Pagamento */}
            <View style={styles.field}>
              <Text style={styles.label}>Data do Pagamento *</Text>
              <input
                type="date"
                style={{
                  borderWidth: 1,
                  borderColor: '#d1d5db',
                  borderRadius: 8,
                  padding: 12,
                  fontSize: 14,
                  backgroundColor: '#fff',
                  width: '100%',
                  fontFamily: 'system-ui, -apple-system, sans-serif',
                }}
                value={formData.data_pagamento}
                onChange={(e) => setFormData({ ...formData, data_pagamento: e.target.value })}
                max={new Date().toISOString().split('T')[0]}
              />
            </View>

            {/* Comprovante */}
            <View style={styles.field}>
              <Text style={styles.label}>Comprovante (opcional)</Text>
              <TextInput
                style={styles.input}
                value={formData.comprovante}
                onChangeText={(text) => setFormData({ ...formData, comprovante: text })}
                placeholder="Link ou identificador do comprovante"
              />
            </View>

            {/* Observações */}
            <View style={styles.field}>
              <Text style={styles.label}>Observações</Text>
              <TextInput
                style={[styles.input, styles.textArea]}
                value={formData.observacoes}
                onChangeText={(text) => setFormData({ ...formData, observacoes: text })}
                placeholder="Informações adicionais sobre o pagamento"
                multiline
                numberOfLines={3}
              />
            </View>

            {/* Aviso sobre geração automática */}
            <View style={styles.alertBox}>
              <Feather name="info" size={16} color="#3b82f6" />
              <Text style={styles.alertText}>
                Ao confirmar este pagamento, o próximo será gerado automaticamente.
              </Text>
            </View>
          </ScrollView>

          <View style={styles.footer}>
            <TouchableOpacity
              style={[styles.button, styles.buttonCancel]}
              onPress={onClose}
              disabled={loading}
            >
              <Text style={styles.buttonCancelText}>Cancelar</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.button, styles.buttonConfirm]}
              onPress={handleConfirmar}
              disabled={loading}
            >
              {loading ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <>
                  <Feather name="check" size={18} color="#fff" />
                  <Text style={styles.buttonConfirmText}>Confirmar Pagamento</Text>
                </>
              )}
            </TouchableOpacity>
          </View>
        </View>

        {/* Modal de Confirmação */}
        {showConfirmModal && (
          <View style={styles.confirmOverlay}>
            <View style={styles.confirmModal}>
              <View style={styles.confirmHeader}>
                <Feather name="alert-circle" size={48} color="#f59e0b" />
                <Text style={styles.confirmTitulo}>Confirmar Pagamento</Text>
              </View>
              
              <View style={styles.confirmResumo}>
                <View style={styles.confirmItem}>
                  <Text style={styles.confirmLabel}>Parcela</Text>
                  <Text style={styles.confirmValor}>{pagamento.numero_parcela}</Text>
                </View>
                <View style={styles.confirmItem}>
                  <Text style={styles.confirmLabel}>Vencimento</Text>
                  <Text style={styles.confirmValor}>{formatarData(pagamento.data_vencimento)}</Text>
                </View>
                <View style={styles.confirmItem}>
                  <Text style={styles.confirmLabel}>Valor</Text>
                  <Text style={[styles.confirmValor, { color: '#10b981', fontSize: 20, fontWeight: '700' }]}>
                    {parseFloat(pagamento.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                  </Text>
                </View>
              </View>
              
              <Text style={styles.confirmTexto}>
                Deseja realmente confirmar o recebimento deste pagamento?
              </Text>
              
              <View style={styles.confirmBotoes}>
                <TouchableOpacity
                  style={styles.confirmBotaoCancelar}
                  onPress={() => setShowConfirmModal(false)}
                  disabled={loading}
                >
                  <Text style={styles.confirmBotaoCancelarText}>Cancelar</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.confirmBotaoConfirmar}
                  onPress={handleConfirmarFinal}
                  disabled={loading}
                >
                  {loading ? (
                    <ActivityIndicator color="#fff" size="small" />
                  ) : (
                    <>
                      <Feather name="check" size={18} color="#fff" />
                      <Text style={styles.confirmBotaoConfirmarText}>Sim, Confirmar</Text>
                    </>
                  )}
                </TouchableOpacity>
              </View>
            </View>
          </View>
        )}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modal: {
    backgroundColor: '#fff',
    borderRadius: 16,
    width: '100%',
    maxWidth: 700,
    maxHeight: '90%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 8,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  title: {
    fontSize: 20,
    fontWeight: '600',
    color: '#111827',
  },
  subtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
  closeButton: {
    padding: 8,
  },
  content: {
    padding: 20,
    maxHeight: 500,
  },
  faturaContainer: {
    backgroundColor: '#f9fafb',
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  faturaHeaderLine: {
    height: 2,
    backgroundColor: '#3b82f6',
    marginBottom: 16,
    borderRadius: 1,
  },
  faturaRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  faturaColumn: {
    flex: 1,
    alignItems: 'center',
  },
  faturaIconRowInline: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginBottom: 4,
  },
  faturaLabelInline: {
    fontSize: 12,
    color: '#6b7280',
    fontWeight: '500',
  },
  faturaValueInline: {
    fontSize: 14,
    color: '#111827',
    fontWeight: '600',
  },
  valorDestaqueInline: {
    color: '#3b82f6',
    fontSize: 16,
  },
  verticalDivider: {
    width: 1,
    height: 40,
    backgroundColor: '#e5e7eb',
    marginHorizontal: 8,
  },
  faturaFooterLine: {
    height: 2,
    backgroundColor: '#3b82f6',
    marginTop: 16,
    borderRadius: 1,
  },
  infoCard: {
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    padding: 12,
    marginBottom: 20,
    gap: 8,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  infoLabel: {
    fontSize: 14,
    color: '#6b7280',
    fontWeight: '500',
  },
  infoValue: {
    fontSize: 14,
    color: '#111827',
    fontWeight: '600',
    flex: 1,
  },
  field: {
    marginBottom: 16,
  },
  label: {
    fontSize: 14,
    fontWeight: '500',
    color: '#374151',
    marginBottom: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 14,
    backgroundColor: '#fff',
  },
  textArea: {
    height: 80,
    textAlignVertical: 'top',
  },
  pickerContainer: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    backgroundColor: '#fff',
  },
  picker: {
    height: 50,
  },
  alertBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#dbeafe',
    padding: 12,
    borderRadius: 8,
    marginTop: 8,
  },
  alertText: {
    flex: 1,
    fontSize: 13,
    color: '#1e40af',
  },
  footer: {
    flexDirection: 'row',
    gap: 12,
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  button: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 8,
  },
  buttonCancel: {
    backgroundColor: '#f3f4f6',
  },
  buttonCancelText: {
    color: '#374151',
    fontSize: 14,
    fontWeight: '600',
  },
  buttonConfirm: {
    backgroundColor: '#3b82f6',
  },
  buttonConfirmText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  confirmOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 9999,
  },
  confirmModal: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 24,
    width: '90%',
    maxWidth: 400,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.3,
    shadowRadius: 12,
    elevation: 10,
  },
  confirmHeader: {
    alignItems: 'center',
    marginBottom: 24,
  },
  confirmTitulo: {
    fontSize: 20,
    fontWeight: '700',
    color: '#111827',
    marginTop: 12,
  },
  confirmResumo: {
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    padding: 16,
    marginBottom: 20,
    gap: 12,
  },
  confirmItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  confirmLabel: {
    fontSize: 14,
    color: '#6b7280',
    fontWeight: '500',
  },
  confirmValor: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  confirmTexto: {
    fontSize: 14,
    color: '#4b5563',
    textAlign: 'center',
    marginBottom: 24,
    lineHeight: 20,
  },
  confirmBotoes: {
    flexDirection: 'row',
    gap: 12,
  },
  confirmBotaoCancelar: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: '#f3f4f6',
    alignItems: 'center',
  },
  confirmBotaoCancelarText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#6b7280',
  },
  confirmBotaoConfirmar: {
    flex: 1,
    flexDirection: 'row',
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: '#10b981',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  confirmBotaoConfirmarText: {
    fontSize: 15,
    fontWeight: '700',
    color: '#fff',
  },
});
