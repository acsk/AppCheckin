import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, TextInput, ActivityIndicator, Switch, Alert } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { professorService } from '../../services/professorService';
import { showSuccess, showError } from '../../utils/toast';
import LayoutBase from '../../components/LayoutBase';

export default function FormProfessorScreen({ professorId }) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [professor, setProfessor] = useState(null);
  const [formData, setFormData] = useState({
    nome: '',
    email: '',
    telefone: '',
    cpf: '',
    foto_url: '',
    ativo: true
  });

  useEffect(() => {
    if (professorId) {
      carregarProfessor();
    }
  }, [professorId]);

  const carregarProfessor = async () => {
    try {
      setLoading(true);
      const data = await professorService.buscarPorId(professorId);
      setProfessor(data);
      setFormData({
        nome: data.nome || '',
        email: data.email || '',
        telefone: data.telefone || '',
        cpf: data.cpf || '',
        foto_url: data.foto_url || '',
        ativo: data.ativo ?? true
      });
    } catch (error) {
      showError('Erro ao carregar professor');
      console.error(error);
      setTimeout(() => router.back(), 1500);
    } finally {
      setLoading(false);
    }
  };

  const validarFormulario = () => {
    if (!formData.nome.trim()) {
      showError('Nome é obrigatório');
      return false;
    }

    if (formData.cpf && formData.cpf.length < 11) {
      showError('CPF inválido');
      return false;
    }

    return true;
  };

  const handleSubmit = async () => {
    if (!validarFormulario()) return;

    try {
      setSubmitting(true);
      if (professorId) {
        await professorService.atualizar(professorId, formData);
        showSuccess('Professor atualizado com sucesso');
      } else {
        await professorService.criar(formData);
        showSuccess('Professor criado com sucesso');
      }
      setTimeout(() => router.back(), 1500);
    } catch (error) {
      console.error('Erro ao salvar:', error);
      let mensagemErro = 'Erro ao salvar professor';
      
      if (error.response?.data) {
        const data = error.response.data;
        if (data.message) {
          const match = data.message.match(/\d+ (.*?)$/) || data.message.match(/: (.*)/);
          mensagemErro = match ? match[1] : data.message;
        } else if (data.error) {
          mensagemErro = data.error;
        }
      }
      
      showError(mensagemErro);
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <LayoutBase title={professorId ? 'Editar Professor' : 'Novo Professor'}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#3b82f6" />
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title={professorId ? 'Editar Professor' : 'Novo Professor'}>
      <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
        {/* Formulário */}
        <View style={styles.formContainer}>
          {/* Nome */}
          <View style={styles.field}>
            <Text style={styles.label}>Nome *</Text>
            <View style={styles.inputContainer}>
              <Feather name="user" size={18} color="#6b7280" style={styles.inputIcon} />
              <TextInput
                style={styles.input}
                placeholder="Nome completo"
                value={formData.nome}
                onChangeText={(text) => setFormData({ ...formData, nome: text })}
                editable={!submitting}
              />
            </View>
          </View>

          {/* Email */}
          <View style={styles.field}>
            <Text style={styles.label}>Email</Text>
            <View style={styles.inputContainer}>
              <Feather name="mail" size={18} color="#6b7280" style={styles.inputIcon} />
              <TextInput
                style={styles.input}
                placeholder="email@exemplo.com"
                value={formData.email}
                onChangeText={(text) => setFormData({ ...formData, email: text })}
                keyboardType="email-address"
                editable={!submitting}
              />
            </View>
          </View>

          {/* CPF */}
          <View style={styles.field}>
            <Text style={styles.label}>CPF</Text>
            <View style={styles.inputContainer}>
              <Feather name="credit-card" size={18} color="#6b7280" style={styles.inputIcon} />
              <TextInput
                style={styles.input}
                placeholder="123.456.789-00"
                value={formData.cpf}
                onChangeText={(text) => setFormData({ ...formData, cpf: text })}
                keyboardType="numeric"
                editable={!submitting}
              />
            </View>
          </View>

          {/* Telefone */}
          <View style={styles.field}>
            <Text style={styles.label}>Telefone</Text>
            <View style={styles.inputContainer}>
              <Feather name="phone" size={18} color="#6b7280" style={styles.inputIcon} />
              <TextInput
                style={styles.input}
                placeholder="(11) 99999-9999"
                value={formData.telefone}
                onChangeText={(text) => setFormData({ ...formData, telefone: text })}
                keyboardType="phone-pad"
                editable={!submitting}
              />
            </View>
          </View>

          {/* Foto URL */}
          <View style={styles.field}>
            <Text style={styles.label}>Foto (URL)</Text>
            <View style={styles.inputContainer}>
              <Feather name="image" size={18} color="#6b7280" style={styles.inputIcon} />
              <TextInput
                style={styles.input}
                placeholder="https://..."
                value={formData.foto_url}
                onChangeText={(text) => setFormData({ ...formData, foto_url: text })}
                editable={!submitting}
              />
            </View>
          </View>

          {/* Status */}
          <View style={styles.statusField}>
            <View style={styles.statusContent}>
              <Feather name="check-circle" size={18} color="#6b7280" />
              <Text style={styles.statusLabel}>Professor Ativo</Text>
            </View>
            <Switch
              value={formData.ativo}
              onValueChange={(value) => setFormData({ ...formData, ativo: value })}
              disabled={submitting}
              trackColor={{ false: '#d1d5db', true: '#86efac' }}
              thumbColor={formData.ativo ? '#10b981' : '#9ca3af'}
            />
          </View>

          {/* Botões */}
          <View style={styles.buttonContainer}>
            <TouchableOpacity
              style={[styles.button, styles.buttonCancel]}
              onPress={() => router.back()}
              disabled={submitting}
            >
              <Feather name="x" size={18} color="#6b7280" />
              <Text style={styles.buttonCancelText}>Cancelar</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.button, styles.buttonSubmit, submitting && styles.buttonDisabled]}
              onPress={handleSubmit}
              disabled={submitting}
            >
              {submitting ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <>
                  <Feather name="save" size={18} color="#fff" />
                  <Text style={styles.buttonSubmitText}>
                    {professorId ? 'Atualizar' : 'Criar'} Professor
                  </Text>
                </>
              )}
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb'
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center'
  },
  formContainer: {
    padding: 20,
    gap: 20
  },
  field: {
    gap: 8
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151'
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    paddingHorizontal: 12,
    backgroundColor: '#fff'
  },
  inputIcon: {
    marginRight: 8
  },
  input: {
    flex: 1,
    paddingVertical: 12,
    fontSize: 14,
    color: '#111827'
  },
  statusField: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 12,
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb'
  },
  statusContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12
  },
  statusLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: '#374151'
  },
  buttonContainer: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 20,
    marginBottom: 40
  },
  button: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1
  },
  buttonCancel: {
    backgroundColor: '#f3f4f6',
    borderColor: '#d1d5db'
  },
  buttonCancelText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280'
  },
  buttonSubmit: {
    backgroundColor: '#3b82f6',
    borderColor: '#3b82f6'
  },
  buttonSubmitText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#fff'
  },
  buttonDisabled: {
    opacity: 0.7
  }
});
