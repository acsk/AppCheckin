import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
  Switch,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { useRouter, useLocalSearchParams } from 'expo-router';
import usuarioService from '../../services/usuarioService';
import planoService from '../../services/planoService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import { showSuccess, showError } from '../../utils/toast';

export default function EditarUsuarioScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const usuarioId = parseInt(id);
  
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [planos, setPlanos] = useState([]);
  const [formData, setFormData] = useState({
    nome: '',
    email: '',
    senha: '',
    telefone: '',
    plano_id: '',
    role_id: 1,
  });
  const [errors, setErrors] = useState({});

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      
      // Carregar dados do usuário
      const responseUsuario = await usuarioService.buscar(usuarioId);
      const usuario = responseUsuario.aluno;
      
      setFormData({
        nome: usuario.nome || '',
        email: usuario.email || '',
        senha: '',
        telefone: usuario.telefone || '',
        plano_id: usuario.plano_id || '',
        role_id: usuario.role_id || 1,
      });

      // Carregar planos
      const responsePlanos = await planoService.listar(true);
      setPlanos(responsePlanos.planos || []);
    } catch (error) {
      showError('Não foi possível carregar os dados do usuário');
      router.back();
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (field, value) => {
    setFormData({ ...formData, [field]: value });
    if (errors[field]) {
      setErrors({ ...errors, [field]: null });
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.nome.trim()) {
      newErrors.nome = 'Nome é obrigatório';
    }

    if (!formData.email.trim()) {
      newErrors.email = 'E-mail é obrigatório';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      newErrors.email = 'E-mail inválido';
    }

    if (formData.senha && formData.senha.length < 6) {
      newErrors.senha = 'Senha deve ter no mínimo 6 caracteres';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validateForm()) return;

    setSaving(true);
    try {
      // Remover senha do payload se estiver vazia
      const payload = { ...formData };
      if (!payload.senha) {
        delete payload.senha;
      }

      const result = await usuarioService.atualizar(usuarioId, payload);
      showSuccess('Usuário atualizado com sucesso');
      router.push('/usuarios');
    } catch (error) {
      showError(error.errors?.join('\n') || error.error || 'Não foi possível atualizar o usuário');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <LayoutBase title="Editar Usuário" subtitle="Carregando...">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando dados...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Editar Usuário" subtitle="Atualizar informações do usuário">
      {saving && <LoadingOverlay message="Salvando alterações..." />}
      
      <ScrollView style={styles.container}>
        {/* Botão Voltar */}
        <View style={styles.headerActions}>
          <TouchableOpacity 
            style={styles.backButton}
            onPress={() => router.back()}
            disabled={saving}
          >
            <Feather name="arrow-left" size={20} color="#666" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>
        </View>

        {/* Form */}
        <View style={styles.form}>
          {/* Nome - Full Width */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Nome Completo *</Text>
            <TextInput
              style={[styles.input, errors.nome && styles.inputError]}
              placeholder="Ex: João Silva"
              value={formData.nome}
              onChangeText={(value) => handleChange('nome', value)}
              editable={!saving}
            />
            {errors.nome && <Text style={styles.errorText}>{errors.nome}</Text>}
          </View>

          {/* Email e Telefone - Grid 2 colunas */}
          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>E-mail *</Text>
              <TextInput
                style={[styles.input, errors.email && styles.inputError]}
                placeholder="email@exemplo.com"
                value={formData.email}
                onChangeText={(value) => handleChange('email', value)}
                keyboardType="email-address"
                autoCapitalize="none"
                editable={!saving}
              />
              {errors.email && <Text style={styles.errorText}>{errors.email}</Text>}
            </View>

            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Telefone</Text>
              <TextInput
                style={styles.input}
                placeholder="(11) 99999-9999"
                value={formData.telefone}
                onChangeText={(value) => handleChange('telefone', value)}
                keyboardType="phone-pad"
                editable={!saving}
              />
            </View>
          </View>

          {/* Senha e Plano - Grid 2 colunas */}
          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Nova Senha (deixe em branco para manter)</Text>
              <TextInput
                style={[styles.input, errors.senha && styles.inputError]}
                placeholder="Mínimo 6 caracteres"
                value={formData.senha}
                onChangeText={(value) => handleChange('senha', value)}
                secureTextEntry
                editable={!saving}
              />
              {errors.senha && <Text style={styles.errorText}>{errors.senha}</Text>}
            </View>

            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Plano</Text>
              <View style={styles.pickerContainer}>
                <Picker
                  selectedValue={formData.plano_id}
                  onValueChange={(value) => handleChange('plano_id', value)}
                  enabled={!saving}
                  style={styles.picker}
                >
                  <Picker.Item label="Sem plano" value="" />
                  {planos.map((plano) => (
                    <Picker.Item
                      key={plano.id}
                      label={`${plano.nome} - R$ ${parseFloat(plano.valor).toFixed(2)}`}
                      value={plano.id}
                    />
                  ))}
                </Picker>
              </View>
            </View>
          </View>

          {/* Info Box */}
          <View style={styles.infoBox}>
            <Text style={styles.infoTitle}>ℹ️ Informações importantes:</Text>
            <Text style={styles.infoText}>
              • Deixe o campo senha em branco para manter a senha atual
            </Text>
            <Text style={styles.infoText}>
              • A senha, se alterada, deve ter no mínimo 6 caracteres
            </Text>
            <Text style={styles.infoText}>
              • Alterações no plano afetam o acesso do usuário
            </Text>
          </View>

          {/* Submit Button */}
          <TouchableOpacity
            style={[styles.submitButton, saving && styles.submitButtonDisabled]}
            onPress={handleSubmit}
            disabled={saving}
          >
            {saving ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.submitButtonText}>Salvar Alterações</Text>
            )}
          </TouchableOpacity>
        </View>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  headerActions: {
    padding: 20,
    paddingBottom: 0,
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 12,
    alignSelf: 'flex-start',
  },
  backButtonText: {
    fontSize: 16,
    color: '#666',
    fontWeight: '500',
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
  form: {
    padding: 20,
    paddingTop: 10,
  },
  row: {
    flexDirection: 'row',
    gap: 15,
    marginBottom: 0,
  },
  inputGroup: {
    marginBottom: 20,
  },
  halfWidth: {
    flex: 1,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
  },
  inputError: {
    borderColor: '#ef4444',
  },
  errorText: {
    color: '#ef4444',
    fontSize: 12,
    marginTop: 4,
  },
  pickerContainer: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    overflow: 'hidden',
  },
  picker: {
    height: 50,
  },
  infoBox: {
    backgroundColor: '#E3F2FD',
    padding: 15,
    borderRadius: 8,
    marginBottom: 20,
  },
  infoTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#1976D2',
    marginBottom: 10,
  },
  infoText: {
    fontSize: 13,
    color: '#1565C0',
    marginBottom: 5,
  },
  submitButton: {
    backgroundColor: '#f97316',
    padding: 16,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 10,
  },
  submitButtonDisabled: {
    backgroundColor: '#9ca3af',
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
});
