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
import { showError, showSuccess } from '../utils/toast';
import { API_BASE_URL } from '../config/api';

export default function BuscarProfessorCpfModal({ visible, onClose, onProfessorEncontrado, onCriarNovoProfessor }) {
  const [cpf, setCpf] = useState('');
  const [loading, setLoading] = useState(false);
  const [professor, setProfessor] = useState(null);
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
      const response = await fetch(`${API_BASE_URL}/admin/professores/global/cpf/${cpfLimpo}`, {
        headers: {
          'Authorization': `Bearer ${await getToken()}`,
        },
      });
      
      const data = await response.json();
      
      if (!response.ok) {
        // Tratamento de erros HTTP (400, 404, etc)
        showError(data.error || data.message || 'Erro ao buscar professor');
        setProfessor(null);
        setSearched(false);
        return;
      }
      
      if (data.professor) {
        setProfessor(data);
        setSearched(true);
      } else {
        setProfessor(null);
        setSearched(true);
      }
    } catch (error) {
      console.error('Erro ao buscar professor:', error);
      showError('Erro ao buscar professor');
    } finally {
      setLoading(false);
    }
  };

  const handleAssociar = async () => {
    if (professor && professor.professor) {
      try {
        setLoading(true);
        
        // Criar associação do professor
        const response = await fetch(`${API_BASE_URL}/admin/professores`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${await getToken()}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            nome: professor.professor.nome,
            email: professor.professor.email,
            cpf: apenasNumeros(cpf),
            telefone: professor.professor.telefone,
          }),
        });

        const data = await response.json();

        if (!response.ok) {
          showError(data.error || data.message || 'Erro ao associar professor');
          return;
        }

        showSuccess('Professor associado com sucesso!');
        onProfessorEncontrado(professor.professor);
        handleClose();
      } catch (error) {
        console.error('Erro ao associar professor:', error);
        showError('Erro ao associar professor');
      } finally {
        setLoading(false);
      }
    }
  };

  const handleCriarNovo = () => {
    onCriarNovoProfessor(apenasNumeros(cpf));
    handleClose();
  };

  const handleClose = () => {
    setCpf('');
    setProfessor(null);
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
            <View style={styles.headerIcon}>
              <Feather name="search" size={24} color="#f97316" />
            </View>
            <View style={styles.headerText}>
              <Text style={styles.title}>Buscar Professor por CPF</Text>
              <Text style={styles.subtitle}>Digite o CPF para verificar se o professor já existe no sistema</Text>
            </View>
            <TouchableOpacity onPress={handleClose} style={styles.closeButton}>
              <Feather name="x" size={20} color="#64748b" />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
            {/* Input CPF */}
            <View style={styles.inputContainer}>
              <Text style={styles.label}>CPF do Professor</Text>
              <View style={styles.inputRow}>
                <TextInput
                  style={styles.input}
                  placeholder="000.000.000-00"
                  placeholderTextColor="#999"
                  value={cpf}
                  onChangeText={(text) => setCpf(mascaraCPF(text))}
                  keyboardType="numeric"
                  maxLength={14}
                  editable={!loading}
                />
                <TouchableOpacity
                  style={[styles.searchButton, loading && styles.searchButtonDisabled]}
                  onPress={handleBuscar}
                  disabled={loading}
                >
                  {loading ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Feather name="search" size={18} color="#fff" />
                  )}
                </TouchableOpacity>
              </View>
            </View>

            {/* Resultado da Busca */}
            {searched && !professor && (
              <View style={styles.notFoundContainer}>
                <View style={styles.notFoundIcon}>
                  <Feather name="user-x" size={48} color="#94a3b8" />
                </View>
                <Text style={styles.notFoundTitle}>Professor não encontrado</Text>
                <Text style={styles.notFoundText}>
                  Não encontramos nenhum professor com este CPF no sistema.
                </Text>
                <TouchableOpacity
                  style={styles.createButton}
                  onPress={handleCriarNovo}
                >
                  <Feather name="user-plus" size={18} color="#fff" />
                  <Text style={styles.createButtonText}>Criar Novo Professor</Text>
                </TouchableOpacity>
              </View>
            )}

            {/* Professor Encontrado */}
            {professor && professor.professor && (
              <View style={styles.professorContainer}>
                <View style={styles.professorBadge}>
                  <Feather name="check-circle" size={14} color="#22c55e" />
                  <Text style={styles.professorBadgeText}>Professor Encontrado</Text>
                </View>

                <View style={styles.professorInfo}>
                  <View style={styles.professorRow}>
                    <View style={styles.professorField}>
                      <Text style={styles.professorFieldLabel}>Nome</Text>
                      <Text style={styles.professorFieldValue}>{professor.professor.nome}</Text>
                    </View>
                  </View>

                  {professor.professor.email && (
                    <View style={styles.professorRow}>
                      <View style={styles.professorField}>
                        <Text style={styles.professorFieldLabel}>E-mail</Text>
                        <Text style={styles.professorFieldValue}>{professor.professor.email}</Text>
                      </View>
                    </View>
                  )}

                  {professor.professor.telefone && (
                    <View style={styles.professorRow}>
                      <View style={styles.professorField}>
                        <Text style={styles.professorFieldLabel}>Telefone</Text>
                        <Text style={styles.professorFieldValue}>{professor.professor.telefone}</Text>
                      </View>
                    </View>
                  )}

                  <View style={styles.professorRow}>
                    <View style={styles.professorField}>
                      <Text style={styles.professorFieldLabel}>CPF</Text>
                      <Text style={styles.professorFieldValue}>{mascaraCPF(cpf)}</Text>
                    </View>
                  </View>
                </View>

                <View style={styles.associateInfo}>
                  <View style={styles.associateIcon}>
                    <Feather name="info" size={16} color="#0ea5e9" />
                  </View>
                  <Text style={styles.associateText}>
                    Este professor já existe no sistema. Você pode associá-lo à sua academia ou criar um novo cadastro.
                  </Text>
                </View>

                <View style={styles.actionButtons}>
                  <TouchableOpacity
                    style={styles.cancelButton}
                    onPress={handleCriarNovo}
                  >
                    <Text style={styles.cancelButtonText}>Criar Novo</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.associateButton, loading && styles.associateButtonDisabled]}
                    onPress={handleAssociar}
                    disabled={loading}
                  >
                    {loading ? (
                      <ActivityIndicator size="small" color="#fff" />
                    ) : (
                      <>
                        <Feather name="link" size={18} color="#fff" />
                        <Text style={styles.associateButtonText}>Associar Professor</Text>
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
    backgroundColor: 'rgba(15, 23, 42, 0.7)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modal: {
    backgroundColor: '#fff',
    borderRadius: 20,
    width: '100%',
    maxWidth: 600,
    maxHeight: '90%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.3,
    shadowRadius: 30,
    elevation: 20,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    padding: 24,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  headerIcon: {
    width: 48,
    height: 48,
    borderRadius: 12,
    backgroundColor: '#fff7ed',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  headerText: {
    flex: 1,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 13,
    color: '#64748b',
    lineHeight: 18,
  },
  closeButton: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: '#f1f5f9',
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 8,
  },
  content: {
    maxHeight: 500,
  },
  inputContainer: {
    padding: 24,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#334155',
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
    borderColor: '#e2e8f0',
    borderRadius: 10,
    paddingHorizontal: 16,
    fontSize: 15,
    color: '#1e293b',
    backgroundColor: '#fff',
  },
  searchButton: {
    width: 48,
    height: 48,
    borderRadius: 10,
    backgroundColor: '#f97316',
    alignItems: 'center',
    justifyContent: 'center',
  },
  searchButtonDisabled: {
    opacity: 0.6,
  },
  notFoundContainer: {
    alignItems: 'center',
    padding: 32,
  },
  notFoundIcon: {
    width: 96,
    height: 96,
    borderRadius: 48,
    backgroundColor: '#f1f5f9',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 24,
  },
  notFoundTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: 8,
  },
  notFoundText: {
    fontSize: 14,
    color: '#64748b',
    textAlign: 'center',
    marginBottom: 24,
    lineHeight: 20,
  },
  createButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 10,
    backgroundColor: '#f97316',
  },
  createButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#fff',
  },
  professorContainer: {
    padding: 24,
  },
  professorBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 20,
    backgroundColor: '#dcfce7',
  },
  professorBadgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#166534',
  },
  professorInfo: {
    backgroundColor: '#f8fafc',
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
  },
  professorRow: {
    marginBottom: 12,
  },
  professorField: {
    flex: 1,
  },
  professorFieldLabel: {
    fontSize: 11,
    fontWeight: '600',
    color: '#64748b',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  professorFieldValue: {
    fontSize: 15,
    fontWeight: '500',
    color: '#1e293b',
  },
  associateInfo: {
    flexDirection: 'row',
    gap: 12,
    padding: 16,
    borderRadius: 12,
    backgroundColor: '#e0f2fe',
    marginBottom: 24,
  },
  associateIcon: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: '#0ea5e9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  associateText: {
    flex: 1,
    fontSize: 13,
    color: '#075985',
    lineHeight: 18,
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 12,
  },
  cancelButton: {
    flex: 1,
    height: 48,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#64748b',
  },
  associateButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    height: 48,
    borderRadius: 10,
    backgroundColor: '#22c55e',
  },
  associateButtonDisabled: {
    opacity: 0.6,
  },
  associateButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#fff',
  },
});
