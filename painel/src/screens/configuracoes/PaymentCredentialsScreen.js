import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, TextInput, ActivityIndicator, Switch } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import paymentCredentialsService from '../../services/paymentCredentialsService';
import { showSuccess, showError, showLoading, dismissToast } from '../../utils/toast';

export default function PaymentCredentialsScreen() {
  const [loading, setLoading] = useState(true);
  const [salvando, setSalvando] = useState(false);
  const [testando, setTestando] = useState(false);
  
  const [credenciaisExistentes, setCredenciaisExistentes] = useState(null);
  
  const [formData, setFormData] = useState({
    provider: 'mercadopago',
    environment: 'sandbox',
    access_token_test: '',
    public_key_test: '',
    access_token_prod: '',
    public_key_prod: '',
    webhook_secret: '',
    is_active: true,
  });

  const [mostrarTokens, setMostrarTokens] = useState({
    test: false,
    prod: false,
  });

  const [mostrarKeys, setMostrarKeys] = useState({
    test: false,
    prod: false,
  });

  useEffect(() => {
    carregarCredenciais();
  }, []);

  const carregarCredenciais = async () => {
    try {
      setLoading(true);
      const response = await paymentCredentialsService.obterCredenciais();
      
      if (response.success && response.data) {
        setCredenciaisExistentes(response.data);
        setFormData(prev => ({
          ...prev,
          provider: response.data.provider || 'mercadopago',
          environment: response.data.environment || 'sandbox',
          is_active: response.data.is_active ?? true,
          // Não preencher os tokens por segurança
          // Usuário precisa redigitar se quiser alterar
        }));
      }
    } catch (error) {
      console.error('Erro ao carregar credenciais:', error);
      showError('Erro ao carregar credenciais');
    } finally {
      setLoading(false);
    }
  };

  const handleSalvar = async () => {
    // Validação básica - verificar se existem credenciais salvas OU campos preenchidos
    const temPublicKeyTest = formData.public_key_test.trim() || credenciaisExistentes?.public_key_test_masked;
    const temPublicKeyProd = formData.public_key_prod.trim() || credenciaisExistentes?.public_key_prod_masked;
    
    if (!temPublicKeyTest && !temPublicKeyProd) {
      showError('Preencha ao menos uma Public Key (Teste ou Produção)');
      return;
    }

    if (formData.environment === 'sandbox' && !temPublicKeyTest) {
      showError('Public Key de Teste é obrigatória para ambiente Sandbox');
      return;
    }

    if (formData.environment === 'production' && !temPublicKeyProd) {
      showError('Public Key de Produção é obrigatória para ambiente Produção');
      return;
    }

    const toastId = showLoading('Salvando credenciais...');
    
    try {
      setSalvando(true);
      
      // Montar dados - apenas enviar campos preenchidos
      const dados = {
        provider: formData.provider,
        environment: formData.environment,
        is_active: formData.is_active,
      };

      // Adicionar apenas campos não vazios
      if (formData.access_token_test.trim()) {
        dados.access_token_test = formData.access_token_test.trim();
      }
      if (formData.public_key_test.trim()) {
        dados.public_key_test = formData.public_key_test.trim();
      }
      if (formData.access_token_prod.trim()) {
        dados.access_token_prod = formData.access_token_prod.trim();
      }
      if (formData.public_key_prod.trim()) {
        dados.public_key_prod = formData.public_key_prod.trim();
      }
      if (formData.webhook_secret.trim()) {
        dados.webhook_secret = formData.webhook_secret.trim();
      }

      const response = await paymentCredentialsService.salvarCredenciais(dados);
      
      dismissToast(toastId);
      
      if (response.success) {
        showSuccess(response.message || 'Credenciais salvas com sucesso');
        await carregarCredenciais();
        
        // Limpar campos de senha após salvar
        setFormData(prev => ({
          ...prev,
          access_token_test: '',
          access_token_prod: '',
        }));
      }
    } catch (error) {
      dismissToast(toastId);
      console.error('Erro ao salvar:', error);
      const mensagem = error.response?.data?.message || 'Erro ao salvar credenciais';
      showError(mensagem);
    } finally {
      setSalvando(false);
    }
  };

  const handleTestarConexao = async () => {
    const toastId = showLoading('Testando conexão com Mercado Pago...');
    
    try {
      setTestando(true);
      const response = await paymentCredentialsService.testarConexao();
      
      dismissToast(toastId);
      
      if (response.success) {
        showSuccess(response.message || 'Conexão OK');
      }
    } catch (error) {
      dismissToast(toastId);
      console.error('Erro ao testar:', error);
      const mensagem = error.response?.data?.message || 'Erro ao testar conexão';
      showError(mensagem);
    } finally {
      setTestando(false);
    }
  };

  const ambienteSandbox = formData.environment === 'sandbox';

  if (loading) {
    return (
      <LayoutBase title="Configurações de Pagamento" subtitle="Configure suas credenciais do Mercado Pago">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#3b82f6" />
          <Text style={styles.loadingText}>Carregando credenciais...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Configurações de Pagamento" subtitle="Configure suas credenciais do Mercado Pago">
      <ScrollView style={styles.container} contentContainerStyle={styles.contentContainer}>
      {/* Provider (fixo por enquanto) */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Provider de Pagamento</Text>
        <View style={styles.providerCard}>
          <MaterialCommunityIcons name="bank" size={24} color="#3b82f6" />
          <Text style={styles.providerText}>Mercado Pago</Text>
        </View>
      </View>

      {/* Ambiente */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Ambiente Ativo</Text>
        <View style={styles.environmentContainer}>
          <TouchableOpacity
            style={[
              styles.environmentCard,
              ambienteSandbox && styles.environmentCardActive,
            ]}
            onPress={() => setFormData(prev => ({ ...prev, environment: 'sandbox' }))}
            activeOpacity={0.7}
          >
            <View style={[
              styles.environmentIcon,
              ambienteSandbox && styles.environmentIconActive,
            ]}>
              <Feather name="cloud" size={20} color={ambienteSandbox ? '#fff' : '#64748b'} />
            </View>
            <Text style={[
              styles.environmentLabel,
              ambienteSandbox && styles.environmentLabelActive,
            ]}>Sandbox</Text>
            <Text style={[
              styles.environmentSub,
              ambienteSandbox && styles.environmentSubActive,
            ]}>Ambiente de Testes</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.environmentCard,
              !ambienteSandbox && styles.environmentCardActive,
            ]}
            onPress={() => setFormData(prev => ({ ...prev, environment: 'production' }))}
            activeOpacity={0.7}
          >
            <View style={[
              styles.environmentIcon,
              !ambienteSandbox && styles.environmentIconActive,
            ]}>
              <Feather name="check-circle" size={20} color={!ambienteSandbox ? '#fff' : '#64748b'} />
            </View>
            <Text style={[
              styles.environmentLabel,
              !ambienteSandbox && styles.environmentLabelActive,
            ]}>Produção</Text>
            <Text style={[
              styles.environmentSub,
              !ambienteSandbox && styles.environmentSubActive,
            ]}>Transações Reais</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Credenciais de Teste */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Credenciais de Teste</Text>
          {credenciaisExistentes?.has_token_test && (
            <View style={styles.badge}>
              <Feather name="check" size={12} color="#10b981" />
              <Text style={styles.badgeText}>Configurado</Text>
            </View>
          )}
        </View>

        <View style={styles.inputGroup}>
          <Text style={styles.label}>Access Token (Teste)</Text>
          <View style={styles.inputWithIcon}>
            <TextInput
              style={[styles.input, { flex: 1 }]}
              placeholder={credenciaisExistentes?.has_token_test ? '••••••••••••••••••••••••••' : 'TEST-5463428115477491-...'}
              value={formData.access_token_test}
              onChangeText={(value) => setFormData(prev => ({ ...prev, access_token_test: value }))}
              secureTextEntry={!mostrarTokens.test}
              autoCapitalize="none"
              autoCorrect={false}
            />
            <TouchableOpacity
              style={styles.iconButton}
              onPress={() => setMostrarTokens(prev => ({ ...prev, test: !prev.test }))}
            >
              <Feather name={mostrarTokens.test ? 'eye-off' : 'eye'} size={18} color="#64748b" />
            </TouchableOpacity>
          </View>
          {credenciaisExistentes?.has_token_test && (
            <Text style={styles.hint}>Deixe vazio para manter o token atual</Text>
          )}
        </View>

        <View style={styles.inputGroup}>
          <Text style={styles.label}>Public Key (Teste)</Text>
          {credenciaisExistentes?.public_key_test_masked && !mostrarKeys.test && (
            <Text style={styles.maskedValue}>{credenciaisExistentes.public_key_test_masked}</Text>
          )}
          <View style={styles.inputWithIcon}>
            <TextInput
              style={[styles.input, { flex: 1 }]}
              placeholder="TEST-44f9e009-e7e5-434f-9ff0-7923fd394709"
              value={formData.public_key_test}
              onChangeText={(value) => setFormData(prev => ({ ...prev, public_key_test: value }))}
              secureTextEntry={!mostrarKeys.test && credenciaisExistentes?.public_key_test_masked}
              autoCapitalize="none"
              autoCorrect={false}
            />
            {credenciaisExistentes?.public_key_test_masked && (
              <TouchableOpacity
                style={styles.iconButton}
                onPress={() => setMostrarKeys(prev => ({ ...prev, test: !prev.test }))}
              >
                <Feather name={mostrarKeys.test ? 'eye-off' : 'eye'} size={18} color="#64748b" />
              </TouchableOpacity>
            )}
          </View>
          {credenciaisExistentes?.public_key_test_masked && (
            <Text style={styles.hint}>Deixe vazio para manter a chave atual</Text>
          )}
        </View>
      </View>

      {/* Credenciais de Produção */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Credenciais de Produção</Text>
          {credenciaisExistentes?.has_token_prod && (
            <View style={styles.badge}>
              <Feather name="check" size={12} color="#10b981" />
              <Text style={styles.badgeText}>Configurado</Text>
            </View>
          )}
        </View>

        <View style={styles.inputGroup}>
          <Text style={styles.label}>Access Token (Produção)</Text>
          <View style={styles.inputWithIcon}>
            <TextInput
              style={[styles.input, { flex: 1 }]}
              placeholder={credenciaisExistentes?.has_token_prod ? '••••••••••••••••••••••••••' : 'APP_USR-5463428115477491-...'}
              value={formData.access_token_prod}
              onChangeText={(value) => setFormData(prev => ({ ...prev, access_token_prod: value }))}
              secureTextEntry={!mostrarTokens.prod}
              autoCapitalize="none"
              autoCorrect={false}
            />
            <TouchableOpacity
              style={styles.iconButton}
              onPress={() => setMostrarTokens(prev => ({ ...prev, prod: !prev.prod }))}
            >
              <Feather name={mostrarTokens.prod ? 'eye-off' : 'eye'} size={18} color="#64748b" />
            </TouchableOpacity>
          </View>
          {credenciaisExistentes?.has_token_prod && (
            <Text style={styles.hint}>Deixe vazio para manter o token atual</Text>
          )}
        </View>

        <View style={styles.inputGroup}>
          <Text style={styles.label}>Public Key (Produção)</Text>
          {credenciaisExistentes?.public_key_prod_masked && !mostrarKeys.prod && (
            <Text style={styles.maskedValue}>{credenciaisExistentes.public_key_prod_masked}</Text>
          )}
          <View style={styles.inputWithIcon}>
            <TextInput
              style={[styles.input, { flex: 1 }]}
              placeholder="APP_USR-3cac1a43-8526-4717-b3bf-a705e8628422"
              value={formData.public_key_prod}
              onChangeText={(value) => setFormData(prev => ({ ...prev, public_key_prod: value }))}
              secureTextEntry={!mostrarKeys.prod && credenciaisExistentes?.public_key_prod_masked}
              autoCapitalize="none"
              autoCorrect={false}
            />
            {credenciaisExistentes?.public_key_prod_masked && (
              <TouchableOpacity
                style={styles.iconButton}
                onPress={() => setMostrarKeys(prev => ({ ...prev, prod: !prev.prod }))}
              >
                <Feather name={mostrarKeys.prod ? 'eye-off' : 'eye'} size={18} color="#64748b" />
              </TouchableOpacity>
            )}
          </View>
          {credenciaisExistentes?.public_key_prod_masked && (
            <Text style={styles.hint}>Deixe vazio para manter a chave atual</Text>
          )}
        </View>
      </View>

      {/* Webhook Secret (Opcional) */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Webhook (Opcional)</Text>
        <View style={styles.inputGroup}>
          <Text style={styles.label}>Webhook Secret</Text>
          <TextInput
            style={styles.input}
            placeholder="Opcional - Secret para validar webhooks"
            value={formData.webhook_secret}
            onChangeText={(value) => setFormData(prev => ({ ...prev, webhook_secret: value }))}
            autoCapitalize="none"
            autoCorrect={false}
          />
        </View>
      </View>

      {/* Status Ativo */}
      <View style={styles.section}>
        <View style={styles.switchRow}>
          <View style={styles.switchLabel}>
            <Text style={styles.switchText}>Credenciais Ativas</Text>
            <Text style={styles.switchHint}>Desative para usar credenciais globais do sistema</Text>
          </View>
          <Switch
            value={formData.is_active}
            onValueChange={(value) => setFormData(prev => ({ ...prev, is_active: value }))}
            trackColor={{ false: '#cbd5e1', true: '#3b82f6' }}
            thumbColor="#fff"
          />
        </View>
      </View>

      {/* Info Box */}
      <View style={styles.infoBox}>
        <Feather name="info" size={18} color="#3b82f6" />
        <View style={styles.infoTexts}>
          <Text style={styles.infoTitle}>Onde obter as credenciais?</Text>
          <Text style={styles.infoText}>
            Acesse: https://www.mercadopago.com.br/developers/panel/app
          </Text>
          <Text style={styles.infoText}>
            Crie uma aplicação e copie as credenciais de teste e produção
          </Text>
        </View>
      </View>

      {/* Botões de Ação */}
      <View style={styles.actions}>
        <TouchableOpacity
          style={[styles.button, styles.buttonSecondary]}
          onPress={handleTestarConexao}
          disabled={testando || !credenciaisExistentes}
          activeOpacity={0.7}
        >
          {testando ? (
            <ActivityIndicator size="small" color="#3b82f6" />
          ) : (
            <>
              <Feather name="zap" size={18} color="#3b82f6" />
              <Text style={styles.buttonTextSecondary}>Testar Conexão</Text>
            </>
          )}
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.button, styles.buttonPrimary]}
          onPress={handleSalvar}
          disabled={salvando}
          activeOpacity={0.7}
        >
          {salvando ? (
            <ActivityIndicator size="small" color="#fff" />
          ) : (
            <>
              <Feather name="save" size={18} color="#fff" />
              <Text style={styles.buttonTextPrimary}>Salvar Configurações</Text>
            </>
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
  contentContainer: {
    padding: 20,
    paddingBottom: 40,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    minHeight: 400,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
    color: '#64748b',
  },
  section: {
    marginBottom: 24,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#334155',
    marginBottom: 12,
  },
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    backgroundColor: '#d1fae5',
  },
  badgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#10b981',
  },
  providerCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 16,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  providerText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#0f172a',
  },
  environmentContainer: {
    flexDirection: 'row',
    gap: 12,
  },
  environmentCard: {
    flex: 1,
    padding: 16,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e2e8f0',
    alignItems: 'center',
  },
  environmentCardActive: {
    borderColor: '#3b82f6',
    backgroundColor: '#eff6ff',
  },
  environmentIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: '#f1f5f9',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  environmentIconActive: {
    backgroundColor: '#3b82f6',
  },
  environmentLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: '#64748b',
    marginBottom: 4,
  },
  environmentLabelActive: {
    color: '#3b82f6',
  },
  environmentSub: {
    fontSize: 12,
    color: '#94a3b8',
  },
  environmentSubActive: {
    color: '#60a5fa',
  },
  inputGroup: {
    marginBottom: 16,
  },
  label: {
    fontSize: 14,
    fontWeight: '500',
    color: '#475569',
    marginBottom: 8,
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 10,
    padding: 12,
    fontSize: 14,
    color: '#0f172a',
  },
  inputWithIcon: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  iconButton: {
    width: 40,
    height: 48,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 10,
  },
  hint: {
    marginTop: 4,
    fontSize: 12,
    color: '#94a3b8',
    fontStyle: 'italic',
  },
  maskedValue: {
    fontSize: 12,
    color: '#64748b',
    marginBottom: 4,
    fontFamily: 'monospace',
  },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  switchLabel: {
    flex: 1,
    marginRight: 12,
  },
  switchText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#0f172a',
    marginBottom: 4,
  },
  switchHint: {
    fontSize: 12,
    color: '#64748b',
  },
  infoBox: {
    flexDirection: 'row',
    gap: 12,
    padding: 16,
    backgroundColor: '#eff6ff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#bfdbfe',
    marginBottom: 24,
  },
  infoTexts: {
    flex: 1,
  },
  infoTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1e40af',
    marginBottom: 6,
  },
  infoText: {
    fontSize: 13,
    color: '#3b82f6',
    marginBottom: 2,
  },
  actions: {
    flexDirection: 'row',
    gap: 12,
  },
  button: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    padding: 16,
    borderRadius: 12,
  },
  buttonPrimary: {
    backgroundColor: '#3b82f6',
  },
  buttonSecondary: {
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#3b82f6',
  },
  buttonTextPrimary: {
    fontSize: 15,
    fontWeight: '600',
    color: '#fff',
  },
  buttonTextSecondary: {
    fontSize: 15,
    fontWeight: '600',
    color: '#3b82f6',
  },
});
