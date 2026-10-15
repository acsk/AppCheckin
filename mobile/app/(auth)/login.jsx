import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import * as SecureStore from "expo-secure-store";
import { useEffect, useState } from "react";
import {
    Image,
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import PasswordRecoveryModal from "../../components/PasswordRecoveryModal";
import urlsConfig from "../../src/config/urls";
import { authService } from "../../src/services/authService";
import AsyncStorage from "../../src/utils/storage";
import Button from "../../src/components/ds/Button";
import TechBackground from "../../src/components/ds/TechBackground";
import TextField from "../../src/components/ds/TextField";
import TenantSelectionModal from "../../src/components/ds/TenantSelectionModal";
import { ds as DS } from "../../src/components/ds/tokens";
import { useBrowserThemeColor } from "../../src/hooks/useBrowserThemeColor";
import {
  APP_BUILD_VERSION,
  APP_VERSION,
  ensureAppIsCurrent,
} from "../../src/config/appVersion";

const LOGO = require("../../assets/images/appcheckin-logo.png");
const tenantPreferenceKey = (userId) => `@appcheckin:preferred_tenant:${userId}`;
const lastTenantKey = (userId) => `@appcheckin:last_tenant:${userId}`;

export default function LoginScreen() {
  const router = useRouter();
  // Login é claro: a barra do navegador acompanha o fundo.
  useBrowserThemeColor(DS.surfaceSunken);
  const LOGIN_EMAIL_KEY = "@appcheckin:login_email";
  const LOGIN_PASSWORD_KEY = "@appcheckin:login_password";
  const LOGIN_PASSWORD_SECURE_KEY = "appcheckin.login_password";
  const LOGIN_REMEMBER_KEY = "@appcheckin:login_remember";
  const [email, setEmail] = useState(__DEV__ ? "andrecabrall@gmail.com" : "");
  const [senha, setSenha] = useState(__DEV__ ? "123456" : "");
  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");
  const [emailError, setEmailError] = useState("");
  const [senhaError, setSenhaError] = useState("");
  const [showRecoveryModal, setShowRecoveryModal] = useState(false);
  const [tenantSelectionVisible, setTenantSelectionVisible] = useState(false);
  const [tenantOptions, setTenantOptions] = useState([]);
  const [pendingTenantLogin, setPendingTenantLogin] = useState(null);
  const [selectedTenantId, setSelectedTenantId] = useState(null);
  const [lastAccessTenantId, setLastAccessTenantId] = useState(null);
  const [rememberTenant, setRememberTenant] = useState(true);
  const [tenantSelectionError, setTenantSelectionError] = useState("");
  const [credentialsHydrated, setCredentialsHydrated] = useState(false);
  const [rememberMe, setRememberMe] = useState(true);

  useEffect(() => {
    if (Platform.OS !== "web") return;
    if (typeof document === "undefined") return;

    document.body?.setAttribute("data-recaptcha-visible", "true");

    return () => {
      document.body?.setAttribute("data-recaptcha-visible", "false");
    };
  }, []);

  const ensureRecaptchaEnterpriseLoaded = async (siteKey) => {
    if (Platform.OS !== "web") return;
    if (typeof window === "undefined" || typeof document === "undefined") {
      throw new Error("reCAPTCHA indisponível fora do navegador");
    }

    const hasEnterprise = () =>
      Boolean(
        window.grecaptcha?.enterprise &&
        typeof window.grecaptcha.enterprise.execute === "function",
      );

    if (hasEnterprise()) return;

    const scriptSrc = `https://www.google.com/recaptcha/enterprise.js?render=${siteKey}`;
    const existingScript = document.querySelector(`script[src="${scriptSrc}"]`);

    await new Promise((resolve, reject) => {
      let attempts = 0;
      const maxAttempts = 120;

      const checkReady = () => {
        if (hasEnterprise()) {
          resolve(true);
          return;
        }

        attempts += 1;
        if (attempts >= maxAttempts) {
          reject(new Error("Timeout ao carregar reCAPTCHA"));
          return;
        }

        setTimeout(checkReady, 100);
      };

      if (!existingScript) {
        const script = document.createElement("script");
        script.src = scriptSrc;
        script.async = true;
        script.defer = true;
        script.onerror = () => {
          reject(new Error("Falha ao carregar script do reCAPTCHA"));
        };
        document.head.appendChild(script);
      }

      checkReady();
    });
  };

  const getRecaptchaToken = async () => {
    if (Platform.OS !== "web") return null;

    const siteKey = urlsConfig?.recaptcha?.siteKey;
    if (!siteKey) {
      throw new Error("Site key do reCAPTCHA não configurada");
    }

    await ensureRecaptchaEnterpriseLoaded(siteKey);

    const enterprise = window.grecaptcha?.enterprise;
    if (!enterprise || typeof enterprise.execute !== "function") {
      throw new Error("reCAPTCHA indisponível no momento. Tente novamente.");
    }

    await new Promise((resolve) => enterprise.ready(resolve));
    return enterprise.execute(siteKey, { action: "LOGIN" });
  };

  const getPassword = async () => {
    if (Platform.OS === "web") return null;

    try {
      return await SecureStore.getItemAsync(LOGIN_PASSWORD_SECURE_KEY);
    } catch (error) {
      console.warn("⚠️ Erro ao ler senha segura:", error);
      return null;
    }
  };

  const setPassword = async (value) => {
    if (Platform.OS === "web") return;

    try {
      await SecureStore.setItemAsync(LOGIN_PASSWORD_SECURE_KEY, value);
    } catch (error) {
      console.warn("⚠️ Erro ao salvar senha segura:", error);
    }
  };

  const removePassword = async () => {
    if (Platform.OS === "web") return;

    try {
      await SecureStore.deleteItemAsync(LOGIN_PASSWORD_SECURE_KEY);
    } catch (error) {
      console.warn("⚠️ Erro ao remover senha segura:", error);
    }
  };

  useEffect(() => {
    let isMounted = true;

    const loadSavedCredentials = async () => {
      try {
        // limpar legado: senha antiga no AsyncStorage
        await AsyncStorage.removeItem(LOGIN_PASSWORD_KEY);

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

    // Validação no próprio campo (Alert.alert não aparece na web).
    const emailVal = email.trim();
    let nextEmailError = "";
    if (!emailVal) {
      nextEmailError = "Informe seu e-mail ou usuário.";
    } else if (emailVal.includes("@") && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
      nextEmailError = "E-mail inválido. Confira o endereço digitado.";
    }
    const nextSenhaError = senha.trim() ? "" : "Informe sua senha.";
    setEmailError(nextEmailError);
    setSenhaError(nextSenhaError);
    setFormError("");
    if (nextEmailError || nextSenhaError) {
      return;
    }

    setLoading(true);
    try {
      console.log("🔐 Iniciando login para:", email);
      const recaptchaToken = await getRecaptchaToken();
      const response = await authService.login(email, senha, recaptchaToken);

      // authService já retorna response.data e salva o token automaticamente
      if (response && response.token) {
        console.log("✅ Login bem-sucedido");
        console.log("📊 Dados do login:", {
          user: response.user,
          token: response.token?.substring(0, 20) + "...",
        });

        // Pequeno delay para garantir que o token foi salvo
        await new Promise((resolve) => setTimeout(resolve, 800));

        const updateState = await ensureAppIsCurrent();
        if (updateState === "reloading") {
          return;
        }

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
        const [preferredId, lastId] = await Promise.all([
          AsyncStorage.getItem(tenantPreferenceKey(response.user.id)),
          AsyncStorage.getItem(lastTenantKey(response.user.id)),
        ]);
        const preferredTenant = response.tenants.find(
          (item) => String((item.tenant || item).id) === preferredId,
        );
        const initialTenant = preferredTenant || response.tenants[0];
        setSelectedTenantId((initialTenant.tenant || initialTenant).id);
        setLastAccessTenantId(lastId);
        setRememberTenant(preferredId != null || lastId == null);
        setTenantSelectionError("");
        setTenantOptions(response.tenants);
        setPendingTenantLogin({
          userId: response?.user?.id,
          email,
          nome: response?.user?.nome,
        });
        setTenantSelectionVisible(true);
        setLoading(false);
      } else {
        console.log("⚠️ Login sem token", response);
        setFormError("Não foi possível fazer login. Tente de novo.");
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

      // Credenciais erradas aparecem no campo da senha; o resto, no aviso do topo.
      if (error?.code === "INVALID_CREDENTIALS" || error?.status === 401 || error?.response?.status === 401) {
        setSenhaError("Senha incorreta. Tente de novo ou redefina.");
      } else {
        setFormError(mensagem);
      }
      setLoading(false);
    }
  };

  const handleSelectTenant = async (tenant) => {
    if (loading) return;
    const tenantData = tenant?.tenant || tenant;
    const tenantId = tenantData?.id ?? tenant?.id;

    if (!tenantId) {
      console.warn("Tenant inválido na seleção de academia");
      setTenantSelectionError("Academia inválida. Entre novamente.");
      return;
    }
    if (!pendingTenantLogin?.userId || !pendingTenantLogin?.email) {
      console.warn("Dados de login incompletos na seleção de academia");
      setTenantSelectionError("Dados de login incompletos. Entre novamente.");
      return;
    }

    setTenantSelectionError("");
    setLoading(true);
    try {
      const response = await authService.selectTenantPublic(
        pendingTenantLogin.userId,
        pendingTenantLogin.email,
        tenantId,
      );

      if (response && response.token) {
        console.log("✅ Tenant selecionado com sucesso");
        await AsyncStorage.setItem(
          lastTenantKey(pendingTenantLogin.userId),
          String(tenantId),
        );
        if (rememberTenant) {
          await AsyncStorage.setItem(
            tenantPreferenceKey(pendingTenantLogin.userId),
            String(tenantId),
          );
        } else {
          await AsyncStorage.removeItem(tenantPreferenceKey(pendingTenantLogin.userId));
        }
        await new Promise((resolve) => setTimeout(resolve, 800));
        setTenantSelectionVisible(false);
        const updateState = await ensureAppIsCurrent();
        if (updateState === "reloading") {
          return;
        }
        router.replace("/(tabs)");
      } else {
        console.warn("Seleção de academia retornou sem token");
        setTenantSelectionError("Não foi possível selecionar a academia");
      }
    } catch (error) {
      const mensagem =
        error?.message ||
        error?.error ||
        "Não foi possível selecionar a academia";
      setFormError(mensagem);
      setTenantSelectionError(mensagem);
      console.error("Erro ao selecionar academia:", error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <TechBackground />
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
            <Image
              source={LOGO}
              style={styles.logo}
              resizeMode="contain"
              accessibilityLabel="AppCheckin"
            />
            <Text style={styles.tagline}>SEU CHECK-IN MAIS SIMPLES</Text>
          </View>

          {/* Formulário */}
          <View style={styles.form}>
            {formError ? (
              <View style={styles.formError} accessibilityLiveRegion="polite">
                <Feather name="alert-circle" size={16} color={DS.danger} />
                <Text style={styles.formErrorText}>{formError}</Text>
              </View>
            ) : null}

            <TextField
              icon="mail"
              placeholder="E-mail ou usuário"
              accessibilityLabel="E-mail ou usuário"
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              autoComplete="username"
              value={email}
              onChangeText={(v) => {
                setEmail(v);
                if (emailError) setEmailError("");
              }}
              editable={!loading}
              error={emailError}
              returnKeyType="next"
            />

            <TextField
              icon="lock"
              password
              placeholder="Senha"
              accessibilityLabel="Senha"
              autoComplete="current-password"
              value={senha}
              onChangeText={(v) => {
                setSenha(v);
                if (senhaError) setSenhaError("");
              }}
              onSubmitEditing={handleLogin}
              editable={!loading}
              error={senhaError}
              returnKeyType="go"
            />

            {/* Lembrar de mim + Esqueceu a senha */}
            <View style={styles.formRow}>
              <TouchableOpacity
                style={styles.rememberRow}
                onPress={() => setRememberMe((prev) => !prev)}
                disabled={loading}
                accessibilityRole="checkbox"
                accessibilityState={{ checked: rememberMe }}
              >
                <View style={[styles.rememberBox, rememberMe && styles.rememberBoxChecked]}>
                  {rememberMe ? <Feather name="check" size={14} color={DS.onBrand} /> : null}
                </View>
                <Text style={styles.rememberText}>Lembrar de mim</Text>
              </TouchableOpacity>
              <TouchableOpacity onPress={() => setShowRecoveryModal(true)} style={styles.linkHit}>
                <Text style={styles.link}>Esqueceu sua senha?</Text>
              </TouchableOpacity>
            </View>

            <Button
              variant="glow"
              size="lg"
              block
              iconRight="arrow-right"
              loading={loading}
              onPress={handleLogin}
            >
              {loading ? "Entrando…" : "Entrar"}
            </Button>

            <View style={styles.signup}>
              <Text style={styles.signupText}>Não tem uma conta?</Text>
              <TouchableOpacity
                onPress={() => router.push("/(auth)/register-mobile")}
                style={styles.linkHit}
              >
                <Text style={styles.link}>Cadastre-se</Text>
              </TouchableOpacity>
            </View>
            <Text style={styles.versionText}>
              App {APP_VERSION} · {APP_BUILD_VERSION}
            </Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>

      {/* Password Recovery Modal */}
      <PasswordRecoveryModal
        visible={showRecoveryModal}
        onClose={() => setShowRecoveryModal(false)}
      />

      <TenantSelectionModal
        visible={tenantSelectionVisible}
        userName={pendingTenantLogin?.nome}
        options={tenantOptions}
        selectedId={selectedTenantId}
        lastAccessId={lastAccessTenantId}
        remember={rememberTenant}
        loading={loading}
        error={tenantSelectionError}
        onSelect={(tenantId) => {
          setSelectedTenantId(tenantId);
          setTenantSelectionError("");
        }}
        onRememberChange={setRememberTenant}
        onConfirm={handleSelectTenant}
        onClose={() => setTenantSelectionVisible(false)}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: DS.surfaceSunken,
  },
  keyboardView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: "center",
    paddingHorizontal: 24,
    paddingTop: 32,
    paddingBottom: 24,
    width: "100%",
    maxWidth: 440,
    alignSelf: "center",
  },
  logoContainer: {
    alignItems: "center",
    gap: 12,
    marginBottom: 32,
  },
  logo: {
    width: 135,
    height: 150,
  },
  tagline: {
    fontSize: 11,
    lineHeight: 14,
    fontWeight: "800",
    letterSpacing: 3.5,
    color: DS.inkMuted,
    textAlign: "center",
  },
  form: {
    gap: 16,
  },
  formRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginVertical: -6,
  },
  rememberRow: {
    flexDirection: "row",
    alignItems: "center",
    minHeight: 44,
  },
  rememberBox: {
    width: 20,
    height: 20,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: DS.lineStrong,
    backgroundColor: DS.surface,
    alignItems: "center",
    justifyContent: "center",
    marginRight: 8,
  },
  rememberBoxChecked: {
    backgroundColor: DS.brand,
    borderColor: DS.brand,
  },
  rememberText: {
    fontSize: 15,
    color: DS.ink,
    fontWeight: "600",
  },
  linkHit: {
    minHeight: 44,
    justifyContent: "center",
  },
  link: {
    fontSize: 15,
    color: DS.brandInk,
    fontWeight: "700",
  },
  formError: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: DS.dangerSoft,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 12,
    gap: 8,
  },
  formErrorText: {
    flex: 1,
    fontSize: 13,
    fontWeight: "600",
    color: DS.danger,
  },
  signup: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    marginTop: 8,
  },
  signupText: {
    fontSize: 15,
    fontWeight: "600",
    color: DS.inkMuted,
  },
  versionText: {
    marginTop: 12,
    textAlign: "center",
    fontSize: 12,
    color: DS.inkMuted,
    fontWeight: "500",
  },
});
