import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Modal,
  Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import * as SecureStore from "expo-secure-store";
import PasswordRecoveryModal from "../../components/PasswordRecoveryModal";
import { authService } from "../../src/services/authService";
import { colors } from "../../src/theme/colors";
import AsyncStorage from "../../src/utils/storage";

export default function LoginScreen() {
  const router = useRouter();
  const LOGIN_EMAIL_KEY = "@appcheckin:login_email";
  const LOGIN_PASSWORD_KEY = "@appcheckin:login_password";
  const LOGIN_REMEMBER_KEY = "@appcheckin:login_remember";
  const [email, setEmail] = useState(__DEV__ ? "andrecabrall@gmail.com" : "");
  const [senha, setSenha] = useState(__DEV__ ? "123456" : "");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");
  const [showRecoveryModal, setShowRecoveryModal] = useState(false);
  const [tenantSelectionVisible, setTenantSelectionVisible] = useState(false);
  const [tenantOptions, setTenantOptions] = useState([]);
  const [pendingTenantLogin, setPendingTenantLogin] = useState(null);
  const [selectingTenantId, setSelectingTenantId] = useState(null);
  const [credentialsHydrated, setCredentialsHydrated] = useState(false);
  const [rememberMe, setRememberMe] = useState(true);

  const getPassword = async () => {
    if (Platform.OS === "web") {
      return await AsyncStorage.getItem(LOGIN_PASSWORD_KEY);
    }

    try {
      return await SecureStore.getItemAsync(LOGIN_PASSWORD_KEY);
    } catch (error) {
      console.warn("⚠️ Erro ao ler senha segura:", error);
      return null;
    }
  };

  const setPassword = async (value) => {
    if (Platform.OS === "web") {
      await AsyncStorage.setItem(LOGIN_PASSWORD_KEY, value);
      return;
    }

    try {
      await SecureStore.setItemAsync(LOGIN_PASSWORD_KEY, value);
    } catch (error) {
      console.warn("⚠️ Erro ao salvar senha segura:", error);
    }
  };

  const removePassword = async () => {
    if (Platform.OS === "web") {
      await AsyncStorage.removeItem(LOGIN_PASSWORD_KEY);
      return;
    }

    try {
      await SecureStore.deleteItemAsync(LOGIN_PASSWORD_KEY);
    } catch (error) {
      console.warn("⚠️ Erro ao remover senha segura:", error);
    }
  };

  useEffect(() => {
    let isMounted = true;

    const loadSavedCredentials = async () => {
      try {
        const rememberValue = await AsyncStorage.getItem(LOGIN_REMEMBER_KEY);
        const rememberEnabled =
          rememberValue === null ? true : rememberValue === "true";

        if (!isMounted) return;
        setRememberMe(rememberEnabled);

        if (!rememberEnabled) {
          return;
        }

        const [savedEmail, savedPassword] = await Promise.all([
          AsyncStorage.getItem(LOGIN_EMAIL_KEY),
          getPassword(),
        ]);

        if (!isMounted) return;

        if (savedEmail) setEmail(savedEmail);
        if (savedPassword) setSenha(savedPassword);
      } catch (error) {
        console.warn("⚠️ Erro ao carregar credenciais salvas:", error);
      } finally {
        if (isMounted) setCredentialsHydrated(true);
      }
    };

    loadSavedCredentials();

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    if (!credentialsHydrated) return;
    if (!rememberMe) return;

    const persistCredentials = async () => {
      try {
        if (email?.trim()) {
          await AsyncStorage.setItem(LOGIN_EMAIL_KEY, email.trim());
        } else {
          await AsyncStorage.removeItem(LOGIN_EMAIL_KEY);
        }

        if (senha?.trim()) {
          await setPassword(senha);
        } else {
          await removePassword();
        }
      } catch (error) {
        console.warn("⚠️ Erro ao salvar credenciais:", error);
      }
    };

    persistCredentials();
  }, [email, senha, rememberMe, credentialsHydrated]);

  useEffect(() => {
    if (!credentialsHydrated) return;

    const persistRememberState = async () => {
      try {
        await AsyncStorage.setItem(LOGIN_REMEMBER_KEY, String(rememberMe));

        if (!rememberMe) {
          await AsyncStorage.removeItem(LOGIN_EMAIL_KEY);
          await removePassword();
        }
      } catch (error) {
        console.warn("⚠️ Erro ao salvar preferência:", error);
      }
    };

    persistRememberState();
  }, [rememberMe, credentialsHydrated]);

  const handleLogin = async () => {
    // Proteção contra múltiplos cliques
    if (loading) {
      console.log("⚠️ Login já em progresso, ignorando novo clique");
      return;
    }

    if (!email.trim() || !senha.trim()) {
      Alert.alert("Atenção", "Preencha email e senha");
      return;
    }

    setLoading(true);
    setFormError("");
    try {
      console.log("🔐 Iniciando login para:", email);
      const response = await authService.login(email, senha);

      // authService já retorna response.data e salva o token automaticamente
      if (response && response.token) {
        console.log("✅ Login bem-sucedido");
        console.log("📊 Dados do login:", {
          user: response.user,
          token: response.token?.substring(0, 20) + "...",
        });

        // Pequeno delay para garantir que o token foi salvo
        await new Promise((resolve) => setTimeout(resolve, 800));

        console.log("🔄 Redirecionando para /(tabs)...");
        // NÃO chama setLoading(false) aqui - deixa loading=true para bloquear interações
        router.replace("/(tabs)");
        console.log("✅ Redirect executado");
      } else if (
        response?.requires_tenant_selection &&
        Array.isArray(response?.tenants) &&
        response.tenants.length > 0
      ) {
        console.log("🏷️ Seleção de tenant necessária");
        setTenantOptions(response.tenants);
        setPendingTenantLogin({
          userId: response?.user?.id,
          email,
        });
        setTenantSelectionVisible(true);
        setLoading(false);
      } else {
        console.log("⚠️ Login sem token", response);
        Alert.alert("Erro", "Não foi possível fazer login");
        setLoading(false);
      }
    } catch (error) {
      console.error("❌ ERRO AO FAZER LOGIN:", {
        erro: error,
        status: error?.status,
        statusCode: error?.response?.status,
        message: error?.message,
        code: error?.code,
        errorField: error?.error,
        fullError: error,
      });

      // Mapear mensagens de erro mais específicas
      let mensagem = "Email ou senha incorretos";

      // Tentar extrair a mensagem do erro (backend retorna { type, code, message })
      if (error?.message) {
        mensagem = error.message;
      } else if (error?.error) {
        mensagem = error.error;
      }

      // Mapear códigos de erro específicos
      if (error?.code === "INVALID_CREDENTIALS") {
        mensagem = "Email ou senha incorretos";
      } else if (error?.status === 401 || error?.response?.status === 401) {
        if (!mensagem || mensagem.includes("Email ou senha")) {
          mensagem = "Email ou senha incorretos";
        }
      } else if (error?.isNetworkError) {
        mensagem = "Erro de conexão. Verifique sua internet.";
      }

      setFormError(mensagem);
      Alert.alert("Erro ao fazer login", mensagem);
      setLoading(false);
    }
  };

  const handleSelectTenant = async (tenant) => {
    const tenantData = tenant?.tenant || tenant;
    const tenantId = tenantData?.id ?? tenant?.id;

    if (!tenantId) {
      Alert.alert("Erro", "Tenant inválido");
      return;
    }
    if (!pendingTenantLogin?.userId || !pendingTenantLogin?.email) {
      Alert.alert("Erro", "Dados de login incompletos");
      return;
    }

    setSelectingTenantId(tenantId);
    setLoading(true);
    try {
      const response = await authService.selectTenantPublic(
        pendingTenantLogin.userId,
        pendingTenantLogin.email,
        tenantId,
      );

      if (response && response.token) {
        console.log("✅ Tenant selecionado com sucesso");
        await new Promise((resolve) => setTimeout(resolve, 800));
        setTenantSelectionVisible(false);
        router.replace("/(tabs)");
      } else {
        Alert.alert("Erro", "Não foi possível selecionar a academia");
      }
    } catch (error) {
      const mensagem =
        error?.message ||
        error?.error ||
        "Não foi possível selecionar a academia";
      setFormError(mensagem);
      Alert.alert("Erro", mensagem);
    } finally {
      setLoading(false);
      setSelectingTenantId(null);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <View pointerEvents="none" style={styles.backgroundDecor}>
        <View style={styles.headerAccent} />
        <View style={styles.accentCircle} />
        <View style={styles.accentCircleSmall} />
      </View>
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : "height"}
        style={styles.keyboardView}
      >
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          {/* Logo */}
          <View style={styles.logoContainer}>
            <View style={styles.logoCircle}>
              <Feather name="check-circle" size={60} color="#fff" />
            </View>
            <Text style={styles.appName}>AppCheckin</Text>
            <Text style={styles.tagline}>Registre sua presença</Text>
          </View>

          {/* Formulário */}
          <View style={styles.formContainer}>
            <View style={styles.formCard}>
              <Text style={styles.welcomeSubtext}>
                Faça login para continuar
              </Text>

              {formError ? (
                <View style={styles.formError}>
                  <Feather name="alert-circle" size={16} color="#b91c1c" />
                  <Text style={styles.formErrorText}>{formError}</Text>
                </View>
              ) : null}

              {/* Email Input */}
              <View style={styles.inputWrapper}>
                <Feather
                  name="mail"
                  size={20}
                  color={colors.primary}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.input}
                  placeholder="Email"
                  placeholderTextColor="#999"
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoCorrect={false}
                  value={email}
                  onChangeText={setEmail}
                  editable={!loading}
                />
              </View>

              {/* Senha Input */}
              <View style={styles.inputWrapper}>
                <Feather
                  name="lock"
                  size={20}
                  color={colors.primary}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={[styles.input, { paddingRight: 50 }]}
                  placeholder="Senha"
                  placeholderTextColor="#999"
                  secureTextEntry={!showPassword}
                  value={senha}
                  onChangeText={setSenha}
                  editable={!loading}
                />
                <TouchableOpacity
                  style={styles.eyeIcon}
                  onPress={() => setShowPassword(!showPassword)}
                >
                  <Feather
                    name={showPassword ? "eye-off" : "eye"}
                    size={20}
                    color={colors.primary}
                  />
                </TouchableOpacity>
              </View>

              {/* Remember Me */}
              <TouchableOpacity
                style={styles.rememberRow}
                onPress={() => setRememberMe((prev) => !prev)}
                disabled={loading}
              >
                <View
                  style={[
                    styles.rememberBox,
                    rememberMe && styles.rememberBoxChecked,
                  ]}
                >
                  {rememberMe ? (
                    <Feather name="check" size={14} color="#fff" />
                  ) : null}
                </View>
                <Text style={styles.rememberText}>Lembrar-me</Text>
              </TouchableOpacity>

              {/* Login Button */}
              <TouchableOpacity
                style={[styles.loginButton, loading && { opacity: 0.7 }]}
                onPress={handleLogin}
                disabled={loading}
              >
                {loading ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <Text style={styles.loginButtonText}>Entrar</Text>
                )}
              </TouchableOpacity>

              {/* Forgot Password Link */}
              <TouchableOpacity
                style={styles.forgotPasswordContainer}
                onPress={() => setShowRecoveryModal(true)}
              >
                <Text style={styles.forgotPasswordText}>
                  Esqueceu sua senha?
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.registerContainer}
                onPress={() => router.push("/(auth)/register-mobile")}
              >
                <Text style={styles.registerText}>Criar uma conta</Text>
              </TouchableOpacity>
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>

      {/* Password Recovery Modal */}
      <PasswordRecoveryModal
        visible={showRecoveryModal}
        onClose={() => setShowRecoveryModal(false)}
      />

      {/* Tenant Selection Modal */}
      <Modal
        visible={tenantSelectionVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setTenantSelectionVisible(false)}
      >
        <View style={styles.tenantModalOverlay}>
          <View style={styles.tenantModalCard}>
            <Text style={styles.tenantModalTitle}>
              Selecione a academia
            </Text>
            <ScrollView
              contentContainerStyle={styles.tenantList}
              showsVerticalScrollIndicator={false}
            >
              {tenantOptions.map((item, index) => {
                const t = item?.tenant || item;
                const tenantId = t?.id ?? item?.id ?? index;
                const nome = t?.nome ?? t?.name ?? "Academia";
                const status = item?.status ?? null;
                const isSelecting = selectingTenantId === tenantId;
                return (
                  <TouchableOpacity
                    key={tenantId}
                    style={styles.tenantOptionButton}
                    onPress={() => handleSelectTenant(item)}
                    disabled={loading}
                  >
                    {isSelecting ? (
                      <ActivityIndicator color="#fff" size="small" />
                    ) : (
                      <>
                        <Text style={styles.tenantOptionText}>{nome}</Text>
                        {status ? (
                          <Text style={styles.tenantOptionSubtext}>
                            Status: {status}
                          </Text>
                        ) : null}
                      </>
                    )}
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
            <View style={styles.tenantModalActions}>
              <TouchableOpacity
                style={styles.tenantCancelButton}
                onPress={() => setTenantSelectionVisible(false)}
                disabled={loading}
              >
                <Text style={styles.tenantCancelText}>Voltar</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#fff3ea",
  },
  backgroundDecor: {
    ...StyleSheet.absoluteFillObject,
  },
  headerAccent: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    height: 220,
    backgroundColor: colors.primary,
    borderBottomLeftRadius: 32,
    borderBottomRightRadius: 32,
  },
  accentCircle: {
    position: "absolute",
    top: -60,
    right: -40,
    width: 180,
    height: 180,
    borderRadius: 90,
    backgroundColor: "rgba(255,255,255,0.2)",
  },
  accentCircleSmall: {
    position: "absolute",
    top: 120,
    left: -40,
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: "rgba(255,255,255,0.18)",
  },
  keyboardView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: "center",
    paddingHorizontal: 20,
    paddingVertical: 24,
  },
  logoContainer: {
    alignItems: "center",
    marginBottom: 28,
  },
  logoCircle: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: colors.primaryDark,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 16,
    boxShadow: "0px 12px 20px rgba(255, 107, 53, 0.35)",
    elevation: 6,
  },
  appName: {
    fontSize: 32,
    fontWeight: "bold",
    color: colors.primary,
    marginBottom: 4,
  },
  tagline: {
    fontSize: 16,
    color: "rgba(255,255,255,0.8)",
  },
  formContainer: {
    width: "100%",
  },
  formCard: {
    backgroundColor: "#fff",
    borderRadius: 20,
    paddingVertical: 24,
    paddingHorizontal: 20,
    borderWidth: 1,
    borderColor: "#fde2c2",
    boxShadow: "0px 12px 20px rgba(255, 107, 53, 0.15)",
    elevation: 6,
  },
  welcomeText: {
    fontSize: 24,
    fontWeight: "600",
    color: "#111827",
    marginBottom: 8,
  },
  welcomeSubtext: {
    fontSize: 14,
    color: "#6b7280",
    marginBottom: 24,
  },
  inputWrapper: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 14,
    borderWidth: 1,
    borderColor: "#fde2c2",
    borderRadius: 12,
    paddingHorizontal: 14,
    backgroundColor: "#fff7f2",
    height: 52,
  },
  inputIcon: {
    marginRight: 12,
  },
  input: {
    flex: 1,
    height: 52,
    fontSize: 16,
    color: "#111827",
  },
  eyeIcon: {
    padding: 10,
  },
  rememberRow: {
    flexDirection: "row",
    alignItems: "center",
    marginTop: 4,
    marginBottom: 6,
  },
  rememberBox: {
    width: 20,
    height: 20,
    borderRadius: 5,
    borderWidth: 1,
    borderColor: "#f4b07e",
    backgroundColor: "#fff7f2",
    alignItems: "center",
    justifyContent: "center",
    marginRight: 10,
  },
  rememberBoxChecked: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  rememberText: {
    fontSize: 14,
    color: "#6b7280",
    fontWeight: "500",
  },
  loginButton: {
    backgroundColor: colors.primary,
    borderRadius: 14,
    height: 52,
    justifyContent: "center",
    alignItems: "center",
    marginTop: 18,
    boxShadow: "0px 6px 14px rgba(255, 107, 53, 0.35)",
    elevation: 5,
  },
  loginButtonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
  },
  formError: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#fef2f2",
    borderWidth: 1,
    borderColor: "#fecaca",
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    marginBottom: 14,
    gap: 8,
  },
  formErrorText: {
    flex: 1,
    fontSize: 13,
    color: "#b91c1c",
  },
  forgotPasswordContainer: {
    alignItems: "center",
    marginTop: 16,
    paddingVertical: 8,
  },
  forgotPasswordText: {
    fontSize: 14,
    color: colors.primary,
    fontWeight: "500",
  },
  registerContainer: {
    alignItems: "center",
    marginTop: 4,
    paddingVertical: 6,
  },
  registerText: {
    fontSize: 14,
    color: colors.primary,
    fontWeight: "600",
  },
  tenantModalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "center",
    padding: 20,
  },
  tenantModalCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 20,
    borderWidth: 1,
    borderColor: "#f5d0c5",
    maxHeight: "80%",
  },
  tenantModalTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: "#111827",
    marginBottom: 16,
  },
  tenantList: {
    gap: 12,
    paddingBottom: 8,
  },
  tenantOptionButton: {
    backgroundColor: colors.primary,
    borderRadius: 12,
    paddingVertical: 14,
    paddingHorizontal: 16,
  },
  tenantOptionText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
  },
  tenantOptionSubtext: {
    color: "rgba(255,255,255,0.85)",
    marginTop: 4,
    fontSize: 12,
  },
  tenantModalActions: {
    marginTop: 16,
    alignItems: "center",
  },
  tenantCancelButton: {
    paddingVertical: 10,
    paddingHorizontal: 18,
  },
  tenantCancelText: {
    color: colors.primary,
    fontSize: 14,
    fontWeight: "600",
  },
});
