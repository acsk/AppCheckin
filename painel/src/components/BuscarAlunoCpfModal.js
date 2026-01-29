import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  Modal,
  ActivityIndicator,
  ScrollView,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { mascaraCPF, mascaraTelefone, apenasNumeros } from '../utils/masks';
import { showError, showSuccess } from '../utils/toast';
import alunoService from '../services/alunoService';

export default function BuscarAlunoCpfModal({ visible, onClose, onAlunoAssociado, onCriarNovoAluno }) {
  const [cpf, setCpf] = useState('');
  const [loading, setLoading] = useState(false);
  const [associando, setAssociando] = useState(false);
  const [resultado, setResultado] = useState(null);
  const [searched, setSearched] = useState(false);

  const handleBuscar = async () => {
    const cpfLimpo = apenasNumeros(cpf);
    
    if (cpfLimpo.length !== 11) {
      showError('CPF deve conter 11 dígitos');
      return;
    }

    setLoading(true);
    setSearched(false);
    setResultado(null);
    
    try {
      const data = await alunoService.buscarPorCpf(cpfLimpo);
      setResultado(data);
      setSearched(true);
    } catch (error) {
      console.error('Erro ao buscar aluno:', error);
      showError(error.error || 'Erro ao buscar aluno');
      setResultado(null);
      setSearched(false);
    } finally {
      setLoading(false);
    }
  };

  const handleAssociar = async () => {
    if (!resultado?.aluno?.id) return;

    setAssociando(true);
    try {
      await alunoService.associar(resultado.aluno.id);
      showSuccess('Aluno associado com sucesso!');
      onAlunoAssociado(resultado.aluno);
      handleClose();
    } catch (error) {
      console.error('Erro ao associar aluno:', error);
      showError(error.error || 'Erro ao associar aluno');
    } finally {
      setAssociando(false);
    }
  };

  const handleCriarNovo = () => {
    const cpfLimpo = apenasNumeros(cpf);
    // Limpa o estado mas não chama onClose para não redirecionar
    setCpf('');
    setResultado(null);
    setSearched(false);
    onCriarNovoAluno(cpfLimpo);
  };

  const handleClose = () => {
    setCpf('');
    setResultado(null);
    setSearched(false);
    onClose();
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={handleClose}
    >
      <View style={styles.overlay}>
        <View style={styles.modal}>
          {/* Header */}
          <View style={styles.header}>
            <Text style={styles.title}>Buscar Aluno por CPF</Text>
            <TouchableOpacity onPress={handleClose}>
              <Feather name="x" size={24} color="#666" />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.content}>
            {/* Campo CPF com botão Buscar */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>CPF *</Text>
              <View style={styles.inputRow}>
                <TextInput
                  style={styles.input}
                  placeholder="000.000.000-00"
                  placeholderTextColor="#999"
                  value={cpf}
                  onChangeText={(value) => setCpf(mascaraCPF(value))}
                  keyboardType="numeric"
                  maxLength={14}
                  editable={!loading && !associando}
                />
                <TouchableOpacity
                  style={[styles.searchButton, loading && styles.buttonDisabled]}
                  onPress={handleBuscar}
                  disabled={loading || associando || cpf.length < 14}
                >
                  {loading ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <>
                      <Feather name="search" size={18} color="#fff" />
                      <Text style={styles.buttonText}>Buscar</Text>
                    </>
                  )}
                </TouchableOpacity>
              </View>
            </View>

            {/* Aluno não encontrado */}
            {searched && !resultado?.found && (
              <View style={styles.notFoundBox}>
                <Feather name="user-x" size={48} color="#999" />
                <Text style={styles.notFoundTitle}>Aluno não encontrado</Text>
                <Text style={styles.notFoundText}>
                  Nenhum aluno foi encontrado com este CPF.
                </Text>
                <TouchableOpacity
                  style={styles.createButton}
                  onPress={handleCriarNovo}
                >
                  <Feather name="user-plus" size={18} color="#fff" />
                  <Text style={styles.buttonText}>Cadastrar Novo Aluno</Text>
                </TouchableOpacity>
              </View>
            )}

            {/* Aluno já associado ao tenant atual */}
            {resultado?.found && resultado?.ja_associado && (
              <View style={styles.alreadyAssociatedBox}>
                <Feather name="alert-circle" size={48} color="#f59e0b" />
                <Text style={styles.alreadyAssociatedTitle}>Aluno já cadastrado</Text>
                
                {/* Resumo dos dados do aluno */}
                <View style={styles.userInfoWarning}>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabelWarning}>Nome:</Text>
                    <Text style={styles.infoValueWarning}>{resultado.aluno.nome}</Text>
                  </View>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabelWarning}>Email:</Text>
                    <Text style={styles.infoValueWarning}>{resultado.aluno.email}</Text>
                  </View>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabelWarning}>Telefone:</Text>
                    <Text style={styles.infoValueWarning}>
                      {resultado.aluno.telefone ? mascaraTelefone(resultado.aluno.telefone) : 'Não informado'}
                    </Text>
                  </View>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabelWarning}>CPF:</Text>
                    <Text style={styles.infoValueWarning}>{mascaraCPF(resultado.aluno.cpf)}</Text>
                  </View>
                </View>

                <Text style={styles.alreadyAssociatedText}>
                  Este aluno já está cadastrado nesta academia.
                </Text>
                <TouchableOpacity
                  style={styles.closeButton}
                  onPress={handleClose}
                >
                  <Text style={styles.closeButtonText}>Entendi</Text>
                </TouchableOpacity>
              </View>
            )}

            {/* Aluno Encontrado - Pode associar */}
            {resultado?.found && resultado?.pode_associar && (
              <View style={styles.foundBox}>
                <View style={styles.foundHeader}>
                  <Feather name="check-circle" size={32} color="#10b981" />
                  <Text style={styles.foundTitle}>Aluno Encontrado!</Text>
                </View>

                <View style={styles.userInfo}>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabel}>Nome:</Text>
                    <Text style={styles.infoValue}>{resultado.aluno.nome}</Text>
                  </View>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabel}>Email:</Text>
                    <Text style={styles.infoValue}>{resultado.aluno.email}</Text>
                  </View>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabel}>Telefone:</Text>
                    <Text style={styles.infoValue}>
                      {resultado.aluno.telefone ? mascaraTelefone(resultado.aluno.telefone) : 'Não informado'}
                    </Text>
                  </View>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabel}>CPF:</Text>
                    <Text style={styles.infoValue}>{mascaraCPF(resultado.aluno.cpf)}</Text>
                  </View>
                </View>

                {/* Tenants associados */}
                {resultado.tenants && resultado.tenants.length > 0 && (
                  <View style={styles.tenantsBox}>
                    <Text style={styles.tenantsTitle}>Academias onde está cadastrado:</Text>
                    {resultado.tenants.map((tenant, index) => (
                      <View key={index} style={styles.tenantItem}>
                        <Feather name="home" size={14} color="#666" />
                        <Text style={styles.tenantName}>{tenant.nome}</Text>
                      </View>
                    ))}
                  </View>
                )}

                <Text style={styles.associateInfo}>
                  Deseja associar este aluno à sua academia?
                </Text>

                <View style={styles.buttonRow}>
                  <TouchableOpacity
                    style={styles.cancelButton}
                    onPress={handleClose}
                    disabled={associando}
                  >
                    <Text style={styles.cancelButtonText}>Cancelar</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.associateButton, associando && styles.buttonDisabled]}
                    onPress={handleAssociar}
                    disabled={associando}
                  >
                    {associando ? (
                      <ActivityIndicator size="small" color="#fff" />
                    ) : (
                      <>
                        <Feather name="link" size={18} color="#fff" />
                        <Text style={styles.buttonText}>Associar Aluno</Text>
                      </>
                    )}
                  </TouchableOpacity>
                </View>
              </View>
            )}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modal: {
    backgroundColor: '#fff',
    borderRadius: 16,
    width: '100%',
    maxWidth: 500,
    maxHeight: '90%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 12,
    elevation: 8,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  content: {
    padding: 20,
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
  },
  inputRow: {
    flexDirection: 'row',
    gap: 12,
  },
  input: {
    flex: 1,
    height: 48,
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 16,
    fontSize: 16,
    color: '#111827',
    backgroundColor: '#fff',
  },
  searchButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#f97316',
    paddingHorizontal: 20,
    borderRadius: 8,
    gap: 8,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  buttonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  notFoundBox: {
    alignItems: 'center',
    padding: 24,
    backgroundColor: '#f9fafb',
    borderRadius: 12,
  },
  notFoundTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#374151',
    marginTop: 16,
  },
  notFoundText: {
    fontSize: 14,
    color: '#6b7280',
    textAlign: 'center',
    marginTop: 8,
    marginBottom: 20,
  },
  createButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#10b981',
    paddingVertical: 14,
    paddingHorizontal: 24,
    borderRadius: 8,
    gap: 8,
  },
  alreadyAssociatedBox: {
    alignItems: 'center',
    padding: 24,
    backgroundColor: '#fffbeb',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#fcd34d',
  },
  alreadyAssociatedTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#92400e',
    marginTop: 16,
    marginBottom: 16,
  },
  userInfoWarning: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 16,
    width: '100%',
    marginBottom: 16,
  },
  infoLabelWarning: {
    fontSize: 14,
    fontWeight: '600',
    color: '#92400e',
    width: 80,
  },
  infoValueWarning: {
    flex: 1,
    fontSize: 14,
    color: '#78350f',
  },
  alreadyAssociatedText: {
    fontSize: 14,
    color: '#92400e',
    textAlign: 'center',
    marginBottom: 20,
  },
  bold: {
    fontWeight: '700',
  },
  closeButton: {
    backgroundColor: '#f59e0b',
    paddingVertical: 12,
    paddingHorizontal: 32,
    borderRadius: 8,
  },
  closeButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  foundBox: {
    backgroundColor: '#f0fdf4',
    borderRadius: 12,
    padding: 20,
    borderWidth: 1,
    borderColor: '#86efac',
  },
  foundHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 16,
  },
  foundTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#166534',
  },
  userInfo: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 16,
    marginBottom: 16,
  },
  infoRow: {
    flexDirection: 'row',
    marginBottom: 8,
  },
  infoLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
    width: 80,
  },
  infoValue: {
    flex: 1,
    fontSize: 14,
    color: '#111827',
  },
  tenantsBox: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 16,
    marginBottom: 16,
  },
  tenantsTitle: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
    marginBottom: 8,
    textTransform: 'uppercase',
  },
  tenantItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 4,
  },
  tenantName: {
    fontSize: 14,
    color: '#374151',
  },
  associateInfo: {
    fontSize: 14,
    color: '#166534',
    textAlign: 'center',
    marginBottom: 16,
    fontWeight: '500',
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 12,
  },
  cancelButton: {
    flex: 1,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  cancelButtonText: {
    color: '#374151',
    fontSize: 14,
    fontWeight: '600',
  },
  associateButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#10b981',
    paddingVertical: 14,
    borderRadius: 8,
    gap: 8,
  },
});
