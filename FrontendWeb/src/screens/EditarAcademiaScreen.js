import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
} from 'react-native';
import { Picker } from '@react-native-picker/picker';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { superAdminService } from '../services/superAdminService';
import LayoutBase from '../components/LayoutBase';
import LoadingOverlay from '../components/LoadingOverlay';
import { showSuccess, showError } from '../utils/toast';

export default function EditarAcademiaScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const academiaId = parseInt(id);
  console.log('🏁 EditarAcademiaScreen iniciado, academiaId:', academiaId);
  
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [planos, setPlanos] = useState([]);
  const [formData, setFormData] = useState({
    nome: '',
    email: '',
    telefone: '',
    endereco: '',
    plano_id: '',
    ativo: true,
  });

  useEffect(() => {
    console.log('🏋️ EditarAcademiaScreen montado, academiaId:', academiaId);
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      // Carregar dados da academia
      const response = await superAdminService.buscarAcademia(academiaId);
      const academia = response.academia;
      
      setFormData({
        nome: academia.nome || '',
        email: academia.email || '',
        telefone: academia.telefone || '',
        endereco: academia.endereco || '',
        plano_id: academia.plano_id || '',
        ativo: academia.ativo === 1 || academia.ativo === true,
      });

      // Carregar planos
      setPlanos([
        { id: 1, nome: 'Básico', valor: 'R$ 99,90/mês' },
        { id: 2, nome: 'Profissional', valor: 'R$ 199,90/mês' },
        { id: 3, nome: 'Premium', valor: 'R$ 299,90/mês' },
      ]);
    } catch (error) {
      Alert.alert('Erro', 'Não foi possível carregar os dados da academia');
      router.back();
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (field, value) => {
    setFormData({ ...formData, [field]: value });
  };

  const validateForm = () => {
    console.log('🔍 Validando formulário...', formData);
    
    if (!formData.nome.trim()) {
      console.log('❌ Nome vazio');
      showError('Nome da academia é obrigatório');
      return false;
    }
    if (!formData.email.trim()) {
      console.log('❌ Email vazio');
      showError('E-mail é obrigatório');
      return false;
    }
    if (!formData.email.includes('@')) {
      console.log('❌ Email inválido');
      showError('E-mail inválido');
      return false;
    }
    
    console.log('✅ Validação OK');
    return true;
  };

  const handleSubmit = async () => {
    console.log('🔘 Botão clicado!');
    console.log('📝 Dados do formulário:', formData);
    
    if (!validateForm()) {
      console.log('❌ Validação falhou');
      return;
    }

    console.log('✅ Validação passou');
    setSaving(true);
    
    try {
      console.log('📤 Enviando para API...');
      const result = await superAdminService.atualizarAcademia(academiaId, formData);
      console.log('✅ Resultado:', result);
      
      showSuccess(result);
      router.push('/academias');
    } catch (error) {
      console.error('❌ Erro completo:', error);
      showError(error.errors?.join('\n') || error.error || 'Não foi possível atualizar a academia');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#007AFF" />
        <Text style={styles.loadingText}>Carregando...</Text>
      </View>
    );
  }

  console.log('🎨 Renderizando EditarAcademiaScreen, saving:', saving);

  return (
    <LayoutBase title="Editar Academia" subtitle="Atualizar informações">
      {saving && <LoadingOverlay message="Atualizando academia..." />}
      
      <View style={styles.container}>
        {/* Form */}
        <View style={styles.form}>
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Nome da Academia *</Text>
            <TextInput
              style={styles.input}
              placeholder="Ex: Academia Fitness Pro"
              value={formData.nome}
              onChangeText={(value) => handleChange('nome', value)}
              editable={!saving}
            />
            </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>E-mail *</Text>
            <TextInput
              style={styles.input}
              placeholder="contato@academia.com"
              value={formData.email}
              onChangeText={(value) => handleChange('email', value)}
              keyboardType="email-address"
              autoCapitalize="none"
              editable={!saving}
            />
            </View>

          <View style={styles.inputGroup}>
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

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Endereço</Text>
            <TextInput
              style={[styles.input, styles.textArea]}
              placeholder="Rua, número, bairro, cidade, estado"
              value={formData.endereco}
              onChangeText={(value) => handleChange('endereco', value)}
              multiline
              numberOfLines={3}
              editable={!saving}
            />
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Plano</Text>
            <View style={styles.pickerContainer}>
              <Picker
                selectedValue={formData.plano_id}
                onValueChange={(value) => handleChange('plano_id', value)}
                enabled={!saving}
                style={styles.picker}
              >
                <Picker.Item label="Nenhum plano selecionado" value="" />
                {planos.map((plano) => (
                  <Picker.Item
                    key={plano.id}
                    label={`${plano.nome} - ${plano.valor}`}
                    value={plano.id}
                  />
                ))}
              </Picker>
            </View>
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Status</Text>
            <View style={styles.pickerContainer}>
              <Picker
                selectedValue={formData.ativo}
                onValueChange={(value) => handleChange('ativo', value)}
                enabled={!saving}
                style={styles.picker}
              >
                <Picker.Item label="Ativa" value={true} />
                <Picker.Item label="Inativa" value={false} />
              </Picker>
            </View>
          </View>

          {/* Submit Button */}
          <TouchableOpacity
            style={[styles.submitButton, saving && styles.submitButtonDisabled]}
            onPress={handleSubmit}
            disabled={saving}
            activeOpacity={0.7}
          >
            {saving ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.submitButtonText}>Salvar Alterações</Text>
            )}
          </TouchableOpacity>
        </View>
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'transparent',
    paddingVertical: 100,
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  form: {
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    borderRadius: 12,
    padding: 20,
    marginHorizontal: 20,
    marginVertical: 10,
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2b1a04',
    marginBottom: 8,
  },
  input: {
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.2)',
    borderRadius: 10,
    padding: 12,
    fontSize: 16,
    color: '#2b1a04',
  },
  textArea: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  pickerContainer: {
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.2)',
    borderRadius: 10,
    overflow: 'hidden',
  },
  picker: {
    height: 50,
    color: '#2b1a04',
  },
  submitButton: {
    backgroundColor: '#f97316',
    padding: 16,
    borderRadius: 10,
    alignItems: 'center',
    marginTop: 10,
    marginBottom: 20,
  },
  submitButtonDisabled: {
    backgroundColor: '#fbbf24',
    opacity: 0.6,
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
});
