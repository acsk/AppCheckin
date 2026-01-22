import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { authService } from "../../src/services/authService";
import { colors } from "../../src/theme/colors";

export default function LoginScreen() {
  const router = useRouter();
  const [email, setEmail] = useState(__DEV__ ? "andreteste@gmail.com" : "");
  const [senha, setSenha] = useState(__DEV__ ? "123456" : "");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");

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
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
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
});
