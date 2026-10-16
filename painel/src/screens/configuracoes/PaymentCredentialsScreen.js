import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, TextInput, ActivityIndicator, Switch } from 'react-native';
import { Feather } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import paymentCredentialsService from '../../services/paymentCredentialsService';
import { showSuccess, showError, showLoading, dismissToast } from '../../utils/toast';

const PROVIDERS = [
  { id: 'mercadopago', nome: 'Mercado Pago' },
  { id: 'asaas', nome: 'Asaas' },
];

function formVazio(provider) {
  return {
    provider,
    environment: 'sandbox',
    access_token_test: '',
    public_key_test: '',
    access_token_prod: '',
    public_key_prod: '',
    webhook_secret: '',
    is_active: false,
  };
}

export default function PaymentCredentialsScreen() {
  const [loading, setLoading] = useState(true);
  const [salvando, setSalvando] = useState(null);
  const [testando, setTestando] = useState(null);
  const [existentes, setExistentes] = useState({
    mercadopago: null,
    asaas: null,
  });
  const [forms, setForms] = useState({
    mercadopago: formVazio('mercadopago'),
    asaas: formVazio('asaas'),
  });
  const [mostrarTokens, setMostrarTokens] = useState({
    mercadopago: { test: false, prod: false },
    asaas: { test: false, prod: false },
  });

  useEffect(() => {
    carregarCredenciais();
  }, []);

  const carregarCredenciais = async () => {
    try {
      setLoading(true);
      const response = await paymentCredentialsService.obterCredenciais();
      const lista = response.success ? (response.data?.providers || []) : [];
      const nextExistentes = { mercadopago: null, asaas: null };
      const nextForms = {
        mercadopago: formVazio('mercadopago'),
        asaas: formVazio('asaas'),
      };

      lista.forEach((row) => {
        const id = row.provider;
        if (id !== 'mercadopago' && id !== 'asaas') return;
        nextExistentes[id] = row;
        nextForms[id] = {
          ...formVazio(id),
          environment: row.environment || 'sandbox',
          is_active: !!row.is_active,
        };
      });

      setExistentes(nextExistentes);
      setForms(nextForms);
    } catch (error) {
      console.error('Erro ao carregar credenciais:', error);
      showError('Erro ao carregar credenciais');
    } finally {
      setLoading(false);
    }
  };

  const atualizarForm = (provider, patch) => {
    setForms((prev) => ({ ...prev, [provider]: { ...prev[provider], ...patch } }));
  };

  const montarPayload = (provider, extra = {}) => {
    const formData = { ...forms[provider], ...extra };
    const dados = {
      provider,
      environment: formData.environment,
      is_active: formData.is_active,
    };
    if (formData.access_token_test.trim()) dados.access_token_test = formData.access_token_test.trim();
    if (formData.public_key_test.trim()) dados.public_key_test = formData.public_key_test.trim();
    if (formData.access_token_prod.trim()) dados.access_token_prod = formData.access_token_prod.trim();
    if (formData.public_key_prod.trim()) dados.public_key_prod = formData.public_key_prod.trim();
    if (formData.webhook_secret.trim()) dados.webhook_secret = formData.webhook_secret.trim();
    return dados;
  };

  const handleSalvar = async (provider) => {
    const formData = forms[provider];
    const atual = existentes[provider];
    const isAsaas = provider === 'asaas';
    const temTokenTest = formData.access_token_test.trim() || atual?.has_token_test;
    const temTokenProd = formData.access_token_prod.trim() || atual?.has_token_prod;

    if (formData.is_active) {
      if (formData.environment === 'sandbox' && !temTokenTest) {
        showError(isAsaas
          ? 'Informe a API key de sandbox do Asaas'
          : 'Informe o Access Token de teste do Mercado Pago');
        return;
      }
      if (formData.environment === 'production' && !temTokenProd) {
        showError(isAsaas
          ? 'Informe a API key de produção do Asaas'
          : 'Informe o Access Token de produção do Mercado Pago');
        return;
      }
    }

    const toastId = showLoading('Salvando credenciais...');
    try {
      setSalvando(provider);
      const response = await paymentCredentialsService.salvarCredenciais(montarPayload(provider));
      dismissToast(toastId);
      if (response.success) {
        showSuccess(response.message || 'Credenciais salvas com sucesso');
        await carregarCredenciais();
      }
    } catch (error) {
      dismissToast(toastId);
      showError(error.response?.data?.message || 'Erro ao salvar credenciais');
    } finally {
      setSalvando(null);
    }
  };

  const handleToggleAtivo = async (provider, value) => {
    atualizarForm(provider, { is_active: value });
    const toastId = showLoading(value ? 'Ativando provedor...' : 'Desativando provedor...');
    try {
      const response = await paymentCredentialsService.salvarCredenciais(
        montarPayload(provider, { is_active: value })
      );
      dismissToast(toastId);
      if (response.success) {
        showSuccess(value ? 'Provedor ativado' : 'Provedor desativado');
        await carregarCredenciais();
      }
    } catch (error) {
      dismissToast(toastId);
      atualizarForm(provider, { is_active: !value });
      showError(error.response?.data?.message || 'Não foi possível alterar o status');
    }
  };

  const handleAlterarAmbiente = async (provider, environment) => {
    const formData = forms[provider];
    if (formData.environment === environment) {
      return;
    }

    const toastId = showLoading('Consultando chave no banco...');
    try {
      const response = await paymentCredentialsService.alterarAmbiente(provider, environment);
      dismissToast(toastId);
      if (response.success) {
        showSuccess(response.message || 'Ambiente atualizado');
        await carregarCredenciais();
      }
    } catch (error) {
      dismissToast(toastId);
      showError(error.response?.data?.message || 'Não foi possível alterar o ambiente');
    }
  };

  const handleTestarConexao = async (provider) => {
    const toastId = showLoading(provider === 'asaas' ? 'Testando Asaas...' : 'Testando Mercado Pago...');
    try {
      setTestando(provider);
      const response = await paymentCredentialsService.testarConexao(provider);
      dismissToast(toastId);
      if (response.success) {
        showSuccess(response.message || 'Conexão OK');
      }
    } catch (error) {
      dismissToast(toastId);
      showError(error.response?.data?.message || 'Erro ao testar conexão');
    } finally {
      setTestando(null);
    }
  };

  if (loading) {
    return (
      <LayoutBase title="Configurações de Pagamento" subtitle="Ative Mercado Pago e/ou Asaas">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#3b82f6" />
          <Text style={styles.loadingText}>Carregando credenciais...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Configurações de Pagamento" subtitle="Ative Mercado Pago e/ou Asaas independentemente">
      <ScrollView style={styles.container} contentContainerStyle={styles.contentContainer}>
        {PROVIDERS.map(({ id, nome }) => {
          const formData = forms[id];
          const atual = existentes[id];
          const isAsaas = id === 'asaas';
          const ambienteSandbox = formData.environment === 'sandbox';

          return (
            <View key={id} style={styles.providerBlock}>
              <View style={styles.switchRow}>
                <View style={styles.switchLabel}>
                  <Text style={styles.switchText}>{nome}</Text>
                  <Text style={styles.switchHint}>
                    {formData.is_active ? 'Ativo no app' : 'Desativado'}
                  </Text>
                </View>
                <Switch
                  value={formData.is_active}
                  onValueChange={(value) => handleToggleAtivo(id, value)}
                  trackColor={{ false: '#cbd5e1', true: '#3b82f6' }}
                  thumbColor="#fff"
                />
              </View>

              <View style={styles.section}>
                <Text style={styles.sectionTitle}>Ambiente</Text>
                <View style={styles.environmentContainer}>
                  <TouchableOpacity
                    style={[styles.environmentCard, ambienteSandbox && styles.environmentCardActive]}
                    onPress={() => handleAlterarAmbiente(id, 'sandbox')}
                  >
                    <Text style={[styles.environmentLabel, ambienteSandbox && styles.environmentLabelActive]}>
                      Sandbox
                    </Text>
                    <Text style={styles.environmentHint}>
                      {atual?.has_token_test ? 'Token salvo' : 'Sem token'}
                    </Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.environmentCard, !ambienteSandbox && styles.environmentCardActive]}
                    onPress={() => handleAlterarAmbiente(id, 'production')}
                  >
                    <Text style={[styles.environmentLabel, !ambienteSandbox && styles.environmentLabelActive]}>
                      Produção
                    </Text>
                    <Text style={styles.environmentHint}>
                      {atual?.has_token_prod ? 'Token salvo' : 'Sem token'}
                    </Text>
                  </TouchableOpacity>
                </View>
              </View>

              <View style={styles.section}>
                <View style={styles.sectionHeader}>
                  <Text style={styles.sectionTitle}>Sandbox</Text>
                  {atual?.has_token_test ? (
                    <View style={styles.badge}>
                      <Feather name="check" size={12} color="#10b981" />
                      <Text style={styles.badgeText}>Configurado</Text>
                    </View>
                  ) : null}
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.label}>{isAsaas ? 'API Key (Sandbox)' : 'Access Token (Teste)'}</Text>
                  <View style={styles.inputWithIcon}>
                    <TextInput
                      style={[styles.input, { flex: 1 }]}
                      placeholder={atual?.has_token_test ? '••••••••••••••••••••••••••' : (isAsaas ? '$aact_hmlg_…' : 'TEST-...')}
                      value={formData.access_token_test}
                      onChangeText={(value) => atualizarForm(id, { access_token_test: value })}
                      secureTextEntry={!mostrarTokens[id].test}
                      autoCapitalize="none"
                      autoCorrect={false}
                    />
                    <TouchableOpacity
                      style={styles.iconButton}
                      onPress={() => setMostrarTokens((prev) => ({
                        ...prev,
                        [id]: { ...prev[id], test: !prev[id].test },
                      }))}
                    >
                      <Feather name={mostrarTokens[id].test ? 'eye-off' : 'eye'} size={18} color="#64748b" />
                    </TouchableOpacity>
                  </View>
                </View>
                {!isAsaas && (
                  <View style={styles.inputGroup}>
                    <Text style={styles.label}>Public Key (Teste)</Text>
                    {atual?.public_key_test_masked ? (
                      <Text style={styles.maskedValue}>{atual.public_key_test_masked}</Text>
                    ) : null}
                    <TextInput
                      style={styles.input}
                      placeholder="TEST-..."
                      value={formData.public_key_test}
                      onChangeText={(value) => atualizarForm(id, { public_key_test: value })}
                      autoCapitalize="none"
                      autoCorrect={false}
                    />
                  </View>
                )}
              </View>

              <View style={styles.section}>
                <View style={styles.sectionHeader}>
                  <Text style={styles.sectionTitle}>Produção</Text>
                  {atual?.has_token_prod ? (
                    <View style={styles.badge}>
                      <Feather name="check" size={12} color="#10b981" />
                      <Text style={styles.badgeText}>Configurado</Text>
                    </View>
                  ) : null}
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.label}>{isAsaas ? 'API Key (Produção)' : 'Access Token (Produção)'}</Text>
                  <View style={styles.inputWithIcon}>
                    <TextInput
                      style={[styles.input, { flex: 1 }]}
                      placeholder={atual?.has_token_prod ? '••••••••••••••••••••••••••' : (isAsaas ? '$aact_prod_…' : 'APP_USR-...')}
                      value={formData.access_token_prod}
                      onChangeText={(value) => atualizarForm(id, { access_token_prod: value })}
                      secureTextEntry={!mostrarTokens[id].prod}
                      autoCapitalize="none"
                      autoCorrect={false}
                    />
                    <TouchableOpacity
                      style={styles.iconButton}
                      onPress={() => setMostrarTokens((prev) => ({
                        ...prev,
                        [id]: { ...prev[id], prod: !prev[id].prod },
                      }))}
                    >
                      <Feather name={mostrarTokens[id].prod ? 'eye-off' : 'eye'} size={18} color="#64748b" />
                    </TouchableOpacity>
                  </View>
                </View>
                {!isAsaas && (
                  <View style={styles.inputGroup}>
                    <Text style={styles.label}>Public Key (Produção)</Text>
                    {atual?.public_key_prod_masked ? (
                      <Text style={styles.maskedValue}>{atual.public_key_prod_masked}</Text>
                    ) : null}
                    <TextInput
                      style={styles.input}
                      placeholder="APP_USR-..."
                      value={formData.public_key_prod}
                      onChangeText={(value) => atualizarForm(id, { public_key_prod: value })}
                      autoCapitalize="none"
                      autoCorrect={false}
                    />
                  </View>
                )}
              </View>

              <View style={styles.actions}>
                <TouchableOpacity
                  style={[styles.button, styles.buttonSecondary]}
                  onPress={() => handleTestarConexao(id)}
                  disabled={testando === id}
                >
                  {testando === id ? (
                    <ActivityIndicator size="small" color="#3b82f6" />
                  ) : (
                    <>
                      <Feather name="zap" size={18} color="#3b82f6" />
                      <Text style={styles.buttonTextSecondary}>Testar</Text>
                    </>
                  )}
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.button, styles.buttonPrimary]}
                  onPress={() => handleSalvar(id)}
                  disabled={salvando === id}
                >
                  {salvando === id ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <>
                      <Feather name="save" size={18} color="#fff" />
                      <Text style={styles.buttonTextPrimary}>Salvar {nome}</Text>
                    </>
                  )}
                </TouchableOpacity>
              </View>
            </View>
          );
        })}
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  contentContainer: { padding: 20, paddingBottom: 40 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', minHeight: 400 },
  loadingText: { marginTop: 12, fontSize: 14, color: '#64748b' },
  providerBlock: {
    marginBottom: 28,
    padding: 16,
    backgroundColor: '#f8fafc',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  section: { marginBottom: 20 },
  sectionHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: '#334155', marginBottom: 12 },
  badge: {
    flexDirection: 'row', alignItems: 'center', gap: 4,
    paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, backgroundColor: '#d1fae5',
  },
  badgeText: { fontSize: 12, fontWeight: '600', color: '#10b981' },
  environmentContainer: { flexDirection: 'row', gap: 12 },
  environmentCard: {
    flex: 1, padding: 16, backgroundColor: '#fff', borderRadius: 12,
    borderWidth: 2, borderColor: '#e2e8f0', alignItems: 'center',
  },
  environmentCardActive: { borderColor: '#3b82f6', backgroundColor: '#eff6ff' },
  environmentLabel: { fontSize: 16, fontWeight: '600', color: '#64748b' },
  environmentLabelActive: { color: '#3b82f6' },
  environmentHint: { fontSize: 12, color: '#94a3b8', marginTop: 4 },
  inputGroup: { marginBottom: 16 },
  label: { fontSize: 14, fontWeight: '500', color: '#475569', marginBottom: 8 },
  input: {
    backgroundColor: '#fff', borderWidth: 1, borderColor: '#e2e8f0',
    borderRadius: 10, padding: 12, fontSize: 14, color: '#0f172a',
  },
  inputWithIcon: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  iconButton: {
    width: 40, height: 48, justifyContent: 'center', alignItems: 'center',
    backgroundColor: '#fff', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 10,
  },
  maskedValue: { fontSize: 12, color: '#64748b', marginBottom: 4, fontFamily: 'monospace' },
  switchRow: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
    padding: 16, backgroundColor: '#fff', borderRadius: 12, borderWidth: 1,
    borderColor: '#e2e8f0', marginBottom: 16,
  },
  switchLabel: { flex: 1, marginRight: 12 },
  switchText: { fontSize: 15, fontWeight: '600', color: '#0f172a', marginBottom: 4 },
  switchHint: { fontSize: 12, color: '#64748b' },
  actions: { flexDirection: 'row', gap: 12 },
  button: {
    flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
    gap: 8, padding: 16, borderRadius: 12,
  },
  buttonPrimary: { backgroundColor: '#3b82f6' },
  buttonSecondary: { backgroundColor: '#fff', borderWidth: 2, borderColor: '#3b82f6' },
  buttonTextPrimary: { fontSize: 15, fontWeight: '600', color: '#fff' },
  buttonTextSecondary: { fontSize: 15, fontWeight: '600', color: '#3b82f6' },
});
