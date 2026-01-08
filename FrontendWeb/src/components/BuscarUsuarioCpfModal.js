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
import { mascaraCPF, apenasNumeros } from '../utils/masks';
import { showError } from '../utils/toast';

export default function BuscarUsuarioCpfModal({ visible, onClose, onUsuarioEncontrado, onCriarNovoUsuario }) {
  const [cpf, setCpf] = useState('');
  const [loading, setLoading] = useState(false);
  const [usuario, setUsuario] = useState(null);
  const [searched, setSearched] = useState(false);

  const handleBuscar = async () => {
    const cpfLimpo = apenasNumeros(cpf);
    
    if (cpfLimpo.length !== 11) {
      showError('CPF deve conter 11 dígitos');
      return;
    }

    setLoading(true);
    setSearched(false);
    
    try {
      const response = await fetch(`http://localhost:8080/tenant/usuarios/buscar-cpf/${cpfLimpo}`, {
        headers: {
          'Authorization': `Bearer ${await getToken()}`,
        },
      });
      
      const data = await response.json();
      
      if (!response.ok) {
        // Tratamento de erros HTTP (400, 404, etc)
        showError(data.error || data.message || 'Erro ao buscar usuário');
        setUsuario(null);
        setSearched(false);
        return;
      }
      
      if (data.found) {
        setUsuario(data);
        setSearched(true);
      } else {
        setUsuario(null);
        setSearched(true);
      }
    } catch (error) {
      console.error('Erro ao buscar usuário:', error);
      showError('Erro ao buscar usuário');
    } finally {
      setLoading(false);
    }
  };

  const handleAssociar = () => {
    if (usuario && usuario.usuario) {
      onUsuarioEncontrado(usuario.usuario);
      // Não chamar handleClose aqui para não limpar o estado
      // A tela pai (FormUsuarioScreen) controla o fechamento
    }
  };

  const handleCriarNovo = () => {
    onCriarNovoUsuario();
    handleClose();
  };

  const handleClose = () => {
    setCpf('');
    setUsuario(null);
    setSearched(false);
    onClose();
  };

  const getToken = async () => {
    const AsyncStorage = require('@react-native-async-storage/async-storage').default;
    return await AsyncStorage.getItem('@appcheckin:token');
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
            <Text style={styles.title}>Buscar Usuário por CPF</Text>
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
                  editable={!loading}
                />
                <TouchableOpacity
                  style={[styles.searchButton, loading && styles.buttonDisabled]}
                  onPress={handleBuscar}
                  disabled={loading || cpf.length < 14}
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

            {/* Resultado da Busca */}
            {searched && !usuario && (
              <View style={styles.notFoundBox}>
                <Feather name="user-x" size={48} color="#999" />
                <Text style={styles.notFoundTitle}>Usuário não encontrado</Text>
                <Text style={styles.notFoundText}>
                  Nenhum usuário foi encontrado com este CPF.
                </Text>
                <TouchableOpacity
                  style={styles.createButton}
                  onPress={handleCriarNovo}
                >
                  <Feather name="user-plus" size={18} color="#fff" />
                  <Text style={styles.buttonText}>Cadastrar Novo Usuário</Text>
                </TouchableOpacity>
              </View>
            )}

            {/* Usuário Encontrado */}
            {usuario && usuario.found && (
              <View style={styles.foundBox}>
                <View style={styles.foundHeader}>
                  <Feather name="check-circle" size={32} color="#10b981" />
                  <Text style={styles.foundTitle}>Usuário Encontrado!</Text>
                </View>

                <View style={styles.userInfo}>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabel}>Nome:</Text>
                    <Text style={styles.infoValue}>{usuario.usuario.nome}</Text>
                  </View>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabel}>Email:</Text>
                    <Text style={styles.infoValue}>{usuario.usuario.email}</Text>
                  </View>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabel}>Telefone:</Text>
                    <Text style={styles.infoValue}>{usuario.usuario.telefone || 'Não informado'}</Text>
                  </View>
                  <View style={styles.infoRow}>
                    <Text style={styles.infoLabel}>CPF:</Text>
                    <Text style={styles.infoValue}>{mascaraCPF(usuario.usuario.cpf)}</Text>
                  </View>
                </View>

                {/* Tenants associados */}
                {usuario.tenants && usuario.tenants.length > 0 && (
                  <View style={styles.tenantsBox}>
                    <Text style={styles.tenantsTitle}>Academias associadas:</Text>
                    {usuario.tenants.map((tenant, index) => (
                      <View key={index} style={styles.tenantItem}>
                        <Feather name="home" size={14} color="#666" />
                        <Text style={styles.tenantName}>{tenant.nome}</Text>
                        <View style={[
                          styles.statusBadge,
                          tenant.status === 'ativo' && styles.statusAtivo,
                          tenant.status === 'inativo' && styles.statusInativo,
                        ]}>
                          <Text style={styles.statusText}>{tenant.status}</Text>
                        </View>
                      </View>
                    ))}
                  </View>
                )}

                {/* Ações */}
                {usuario.ja_associado ? (
                  <View style={styles.warningBox}>
                    <Feather name="alert-triangle" size={20} color="#f59e0b" />
                    <Text style={styles.warningText}>
                      Este usuário já está associado a esta academia
                    </Text>
                  </View>
                ) : (
                  <TouchableOpacity
                    style={styles.associateButton}
                    onPress={handleAssociar}
                  >
                    <Feather name="user-check" size={18} color="#fff" />
                    <Text style={styles.buttonText}>Associar à Academia</Text>
                  </TouchableOpacity>
                )}
              </View>
            )}

            {/* Botão Cancelar */}
            <TouchableOpacity
              style={styles.cancelButton}
              onPress={handleClose}
              disabled={loading}
            >
              <Feather name="x" size={18} color="#666" />
              <Text style={styles.cancelButtonText}>Cancelar e Voltar</Text>
            </TouchableOpacity>
          </ScrollView>
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
    maxWidth: 600,
    maxHeight: '90%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
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
    fontSize: 20,
    fontWeight: 'bold',
    color: '#1f2937',
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
    alignItems: 'center',
  },
  input: {
    flex: 1,
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: '#1f2937',
  },
  searchButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#3b82f6',
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 8,
    minWidth: 120,
  },
  cancelButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    paddingVertical: 14,
    paddingHorizontal: 24,
    borderRadius: 8,
    gap: 8,
    borderWidth: 2,
    borderColor: '#e5e5e5',
    marginTop: 20,
  },
  cancelButtonText: {
    color: '#666',
    fontSize: 16,
    fontWeight: '600',
  },
  createButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#10b981',
    padding: 14,
    borderRadius: 8,
    marginTop: 15,
  },
  associateButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#f97316',
    padding: 14,
    borderRadius: 8,
    marginTop: 15,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  notFoundBox: {
    alignItems: 'center',
    padding: 30,
    backgroundColor: '#f9fafb',
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e5e7eb',
    borderStyle: 'dashed',
  },
  notFoundTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#6b7280',
    marginTop: 10,
    marginBottom: 5,
  },
  notFoundText: {
    fontSize: 14,
    color: '#9ca3af',
    textAlign: 'center',
  },
  foundBox: {
    backgroundColor: '#f0fdf4',
    borderRadius: 12,
    padding: 20,
    borderWidth: 2,
    borderColor: '#10b981',
  },
  foundHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 20,
  },
  foundTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#10b981',
  },
  userInfo: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 15,
    marginBottom: 15,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  infoLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
  },
  infoValue: {
    fontSize: 14,
    color: '#1f2937',
    fontWeight: '500',
  },
  tenantsBox: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 15,
    marginBottom: 15,
  },
  tenantsTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 10,
  },
  tenantItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  tenantName: {
    flex: 1,
    fontSize: 14,
    color: '#4b5563',
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  statusAtivo: {
    backgroundColor: '#d1fae5',
  },
  statusInativo: {
    backgroundColor: '#fee2e2',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  warningBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#fef3c7',
    padding: 12,
    borderRadius: 8,
    marginTop: 15,
  },
  warningText: {
    flex: 1,
    fontSize: 14,
    color: '#92400e',
    fontWeight: '500',
  },
});
