import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Modal, TextInput, ScrollView, ActivityIndicator, Platform } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { showSuccess, showError } from '../utils/toast';
import api from '../services/api';
import pagamentoContratoService from '../services/pagamentoContratoService';

export default function BaixaPagamentoModal({ visible, onClose, pagamento, onSuccess }) {
  const [loading, setLoading] = useState(false);
  const [formasPagamento, setFormasPagamento] = useState([]);
  const [formData, setFormData] = useState({
    data_pagamento: '',
    forma_pagamento_id: '',
    valor: '',
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
        valor: pagamento.valor !== undefined && pagamento.valor !== null ? pagamento.valor.toString() : '',
        comprovante: '',
        observacoes: 'Baixa Manual'
      });
      loadFormasPagamento();
    }
  }, [visible, pagamento]);

  const loadFormasPagamento = async () => {
    try {
      const response = await api.get('/config/formas-pagamento-ativas');
      setFormasPagamento(response.data.formas || []);
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

    if (!formData.valor || parseFloat(formData.valor) < 0) {
      showError('Valor inválido');
      return;
    }

    try {
      setLoading(true);
      
      const pagamentoId = pagamento.pagamento_id || pagamento.id;
      console.log('📤 Enviando confirmação de pagamento ID:', pagamentoId);
      
      if (!pagamentoId) {
        showError('ID do pagamento não encontrado');
        setLoading(false);
        return;
      }
      
      await pagamentoContratoService.confirmar(pagamentoId, {
        data_pagamento: formData.data_pagamento,
        forma_pagamento_id: formData.forma_pagamento_id,
        observacoes: formData.observacoes
      });

      showSuccess('Pagamento confirmado com sucesso!');
      onSuccess && onSuccess();
      onClose();
    } catch (error) {
      showError(error.error || error.message || 'Erro ao confirmar pagamento');
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
            {/* Fatura - Informações do Pagamento em linha única */}
            <View style={styles.faturaContainer}>
              {/* Linha de separação decorativa */}
              <View style={styles.faturaHeaderLine} />
              
              {/* Informações em uma linha */}
              <View style={styles.faturaRow}>
                {/* Vencimento */}
                <View style={styles.faturaColumn}>
                  <View style={styles.faturaIconRowInline}>
                    <Feather name="calendar" size={18} color="#6b7280" />
                    <Text style={styles.faturaLabelInline}>Vencimento</Text>
                  </View>
                  <Text style={styles.faturaValueInline}>{formatarData(pagamento.data_vencimento)}</Text>
                </View>

                {/* Divisor vertical */}
                <View style={styles.verticalDivider} />

                {/* Valor Original */}
                <View style={styles.faturaColumn}>
                  <View style={styles.faturaIconRowInline}>
                    <MaterialCommunityIcons name="cash" size={20} color="#f97316" />
                    <Text style={styles.faturaLabelInline}>Valor</Text>
                  </View>
                  <Text style={[styles.faturaValueInline, styles.valorDestaqueInline]}>
                    {parseFloat(pagamento.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                  </Text>
                </View>

                {/* Divisor vertical */}
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

              {/* Linha de separação decorativa */}
              <View style={styles.faturaFooterLine} />
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
                    <Picker.Item key={forma.id} label={forma.forma_pagamento_nome} value={forma.forma_pagamento_id} />
                  ))}
                </Picker>
              </View>
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
    backgroundColor: '#f9fafb',
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#111827',
  },
  subtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
  closeButton: {
    padding: 4,
  },
  content: {
    padding: 20,
  },
  // Estilos da Fatura
  faturaContainer: {
    backgroundColor: '#ffffff',
    borderWidth: 2,
    borderColor: '#e5e7eb',
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  faturaHeaderLine: {
    height: 3,
    backgroundColor: '#f97316',
    borderRadius: 2,
    marginBottom: 16,
  },
  faturaFooterLine: {
    height: 2,
    backgroundColor: '#e5e7eb',
    borderRadius: 1,
    marginTop: 12,
  },
  // Layout em linha
  faturaRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 4,
  },
  faturaColumn: {
    flex: 1,
    alignItems: 'center',
  },
  faturaIconRowInline: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 6,
  },
  faturaLabelInline: {
    fontSize: 11,
    color: '#6b7280',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  faturaValueInline: {
    fontSize: 15,
    color: '#111827',
    fontWeight: '700',
    textAlign: 'center',
  },
  valorDestaqueInline: {
    color: '#f97316',
    fontSize: 18,
    fontWeight: '800',
  },
  verticalDivider: {
    width: 1,
    height: 40,
    backgroundColor: '#e5e7eb',
    marginHorizontal: 8,
  },
  faturaItem: {
    paddingVertical: 8,
  },
  faturaIconRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
    gap: 8,
  },
  faturaLabel: {
    fontSize: 13,
    color: '#6b7280',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  faturaValue: {
    fontSize: 16,
    color: '#111827',
    fontWeight: '700',
    marginLeft: 28,
  },
  valorDestaque: {
    color: '#f97316',
    fontSize: 20,
    fontWeight: '800',
  },
  divider: {
    height: 1,
    backgroundColor: '#e5e7eb',
    marginVertical: 8,
  },
  // Campos de formulário
  field: {
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
  // Picker/Dropdown
  pickerContainer: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    backgroundColor: '#fff',
    overflow: 'hidden',
  },
  picker: {
    height: Platform.OS === 'ios' ? 120 : 50,
    color: '#111827',
  },
  footer: {
    flexDirection: 'row',
    gap: 12,
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
    backgroundColor: '#f9fafb',
  },
  button: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderRadius: 8,
    gap: 8,
  },
  buttonCancel: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
  },
  buttonCancelText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#6b7280',
  },
  buttonConfirm: {
    backgroundColor: '#10b981',
  },
  buttonConfirmText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#fff',
  },
});
