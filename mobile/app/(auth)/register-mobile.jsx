import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useMemo, useRef, useState } from "react";
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

const INITIAL_FORM = {
  nome: "",
  email: "",
  cpf: "",
  telefone: "",
  cep: "",
  logradouro: "",
  numero: "",
  complemento: "",
  bairro: "",
  cidade: "",
  estado: "",
};

const normalizeCpf = (value) => value.replace(/\D/g, "");
const normalizeCep = (value) => value.replace(/\D/g, "");

const formatCpf = (value) => {
  const digits = normalizeCpf(value).slice(0, 11);
  if (digits.length <= 3) return digits;
  if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
  if (digits.length <= 9) {
    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
  }
  return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(
    6,
    9,
  )}-${digits.slice(9)}`;
};

const isValidCpf = (value) => {
  if (!value || value.length !== 11) {
    return false;
  }

  if (/^(\d)\1{10}$/.test(value)) {
    return false;
  }

  let sum = 0;
  for (let i = 0; i < 9; i += 1) {
    sum += Number(value[i]) * (10 - i);
  }
  let check = (sum * 10) % 11;
  if (check === 10) {
    check = 0;
  }
  if (check !== Number(value[9])) {
    return false;
  }

  sum = 0;
  for (let i = 0; i < 10; i += 1) {
    sum += Number(value[i]) * (11 - i);
  }
  check = (sum * 10) % 11;
  if (check === 10) {
    check = 0;
  }
  return check === Number(value[10]);
};

const isValidEmail = (value) => /\S+@\S+\.\S+/.test(value);

export default function RegisterMobileScreen() {
  const router = useRouter();
  const [form, setForm] = useState(INITIAL_FORM);
  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState({});
  const [cepLoading, setCepLoading] = useState(false);
  const lastCepRequestedRef = useRef("");

  const cpfDigits = useMemo(() => normalizeCpf(form.cpf), [form.cpf]);

  const handleChange = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    if (fieldErrors[field]) {
      setFieldErrors((prev) => ({ ...prev, [field]: undefined }));
    }
  };

  const handleCpfChange = (value) => {
    handleChange("cpf", formatCpf(value));
  };

  const validateFields = () => {
    const errors = {};

    if (!form.nome.trim()) {
      errors.nome = "Informe o nome";
    }

    if (!form.email.trim()) {
      errors.email = "Informe o email";
    } else if (!isValidEmail(form.email.trim())) {
      errors.email = "Email inválido";
    }

    if (!cpfDigits) {
      errors.cpf = "Informe o CPF";
    } else if (!isValidCpf(cpfDigits)) {
      errors.cpf = "CPF inválido";
    }

    setFieldErrors(errors);
    return errors;
  };

  const fetchCep = async (cepDigitsValue) => {
    if (!cepDigitsValue || cepDigitsValue.length !== 8) {
      return;
    }
    if (lastCepRequestedRef.current === cepDigitsValue) {
      return;
    }

    lastCepRequestedRef.current = cepDigitsValue;
    setCepLoading(true);

    try {
      const response = await fetch(
        `https://viacep.com.br/ws/${cepDigitsValue}/json/`,
      );
      const data = await response.json();

      if (data?.erro) {
        Alert.alert("CEP não encontrado", "Verifique o CEP informado.");
        return;
      }

      setForm((prev) => ({
        ...prev,
        cep: cepDigitsValue,
        logradouro: data.logradouro || prev.logradouro,
        bairro: data.bairro || prev.bairro,
        cidade: data.localidade || prev.cidade,
        estado: data.uf || prev.estado,
      }));
    } catch (error) {
      Alert.alert("Erro ao buscar CEP", "Não foi possível consultar o CEP.");
    } finally {
      setCepLoading(false);
    }
  };

  const handleCepChange = (value) => {
    const cepDigitsValue = normalizeCep(value);
    handleChange("cep", value);
    if (cepDigitsValue.length === 8) {
      fetchCep(cepDigitsValue);
    }
  };

  const buildPayload = () => {
    const payload = {
      nome: form.nome.trim(),
      email: form.email.trim().toLowerCase(),
      cpf: cpfDigits,
    };

    const optionalFields = [
      "telefone",
      "cep",
      "logradouro",
      "numero",
      "complemento",
      "bairro",
      "cidade",
      "estado",
    ];

    optionalFields.forEach((field) => {
      const value = form[field]?.trim();
      if (value) {
        payload[field] = value;
      }
    });

    return payload;
  };

  const validateForm = () => {
    const errors = validateFields();
    const firstError = Object.values(errors)[0];
    return firstError || "";
  };

  const handleRegister = async () => {
    if (loading) {
      return;
    }

    const validation = validateForm();
    if (validation) {
      setFormError(validation);
      Alert.alert("Atenção", validation);
      return;
    }

    setLoading(true);
    setFormError("");

    try {
      const payload = buildPayload();
      const response = await authService.registerMobile(payload);

      if (response?.token) {
        Alert.alert("Sucesso", "Cadastro realizado com sucesso");
        await new Promise((resolve) => setTimeout(resolve, 400));
        router.replace("/(tabs)");
      } else {
        Alert.alert("Erro", "Não foi possível concluir o cadastro");
      }
    } catch (error) {
      const message =
        error?.message ||
        (Array.isArray(error?.errors) ? error.errors.join("\n") : null) ||
        "Não foi possível concluir o cadastro";
      setFormError(message);
      Alert.alert("Erro no cadastro", message);
    } finally {
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
          <View style={styles.logoContainer}>
            <View style={styles.logoCircle}>
              <Feather name="user-plus" size={56} color="#fff" />
            </View>
            <Text style={styles.appName}>Cadastro</Text>
            <Text style={styles.tagline}>Crie sua conta no AppCheckin</Text>
          </View>

          <View style={styles.formContainer}>
            <View style={styles.formCard}>
              <Text style={styles.welcomeSubtext}>Informe seus dados</Text>

              {formError ? (
                <View style={styles.formError}>
                  <Feather name="alert-circle" size={16} color="#b91c1c" />
                  <Text style={styles.formErrorText}>{formError}</Text>
                </View>
              ) : null}

              <View
                style={[
                  styles.inputWrapper,
                  fieldErrors.nome && styles.inputWrapperError,
                ]}
              >
                <Feather
                  name="user"
                  size={20}
                  color={colors.primary}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.input}
                  placeholder="Nome completo"
                  placeholderTextColor="#999"
                  autoCapitalize="words"
                  value={form.nome}
                  onChangeText={(value) => handleChange("nome", value)}
                  editable={!loading}
                />
              </View>
              {fieldErrors.nome ? (
                <Text style={styles.fieldErrorText}>{fieldErrors.nome}</Text>
              ) : null}

              <View
                style={[
                  styles.inputWrapper,
                  fieldErrors.email && styles.inputWrapperError,
                ]}
              >
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
                  value={form.email}
                  onChangeText={(value) => handleChange("email", value)}
                  editable={!loading}
                />
              </View>
              {fieldErrors.email ? (
                <Text style={styles.fieldErrorText}>{fieldErrors.email}</Text>
              ) : null}

              <View
                style={[
                  styles.inputWrapper,
                  fieldErrors.cpf && styles.inputWrapperError,
                ]}
              >
                <Feather
                  name="credit-card"
                  size={20}
                  color={colors.primary}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.input}
                  placeholder="CPF"
                  placeholderTextColor="#999"
                  keyboardType="numeric"
                  value={form.cpf}
                  onChangeText={handleCpfChange}
                  editable={!loading}
                />
              </View>
              {fieldErrors.cpf ? (
                <Text style={styles.fieldErrorText}>{fieldErrors.cpf}</Text>
              ) : null}

              <View style={styles.inputWrapper}>
                <Feather
                  name="phone"
                  size={20}
                  color={colors.primary}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.input}
                  placeholder="Telefone (opcional)"
                  placeholderTextColor="#999"
                  keyboardType="phone-pad"
                  value={form.telefone}
                  onChangeText={(value) => handleChange("telefone", value)}
                  editable={!loading}
                />
              </View>

              <View style={styles.sectionTitleWrapper}>
                <Text style={styles.sectionTitle}>Endereço (opcional)</Text>
              </View>

              <View style={styles.inputWrapper}>
                <Feather
                  name="map-pin"
                  size={20}
                  color={colors.primary}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.input}
                  placeholder={cepLoading ? "Buscando CEP..." : "CEP"}
                  placeholderTextColor="#999"
                  keyboardType="numeric"
                  value={form.cep}
                  onChangeText={handleCepChange}
                  editable={!loading}
                />
              </View>

              <View style={styles.inputWrapper}>
                <Feather
                  name="map"
                  size={20}
                  color={colors.primary}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.input}
                  placeholder="Logradouro"
                  placeholderTextColor="#999"
                  value={form.logradouro}
                  onChangeText={(value) => handleChange("logradouro", value)}
                  editable={!loading}
                />
              </View>

              <View style={styles.inputRow}>
                <View style={[styles.inputWrapper, styles.inputHalf]}>
                  <Feather
                    name="home"
                    size={20}
                    color={colors.primary}
                    style={styles.inputIcon}
                  />
                  <TextInput
                    style={styles.input}
                    placeholder="Número"
                    placeholderTextColor="#999"
                    value={form.numero}
                    onChangeText={(value) => handleChange("numero", value)}
                    editable={!loading}
                  />
                </View>

                <View style={[styles.inputWrapper, styles.inputHalf]}>
                  <Feather
                    name="more-horizontal"
                    size={20}
                    color={colors.primary}
                    style={styles.inputIcon}
                  />
                  <TextInput
                    style={styles.input}
                    placeholder="Complemento"
                    placeholderTextColor="#999"
                    value={form.complemento}
                    onChangeText={(value) => handleChange("complemento", value)}
                    editable={!loading}
                  />
                </View>
              </View>

              <View style={styles.inputWrapper}>
                <Feather
                  name="map"
                  size={20}
                  color={colors.primary}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.input}
                  placeholder="Bairro"
                  placeholderTextColor="#999"
                  value={form.bairro}
                  onChangeText={(value) => handleChange("bairro", value)}
                  editable={!loading}
                />
              </View>

              <View style={styles.inputRow}>
                <View style={[styles.inputWrapper, styles.inputHalf]}>
                  <Feather
                    name="map-pin"
                    size={20}
                    color={colors.primary}
                    style={styles.inputIcon}
                  />
                  <TextInput
                    style={styles.input}
                    placeholder="Cidade"
                    placeholderTextColor="#999"
                    value={form.cidade}
                    onChangeText={(value) => handleChange("cidade", value)}
                    editable={!loading}
                  />
                </View>

                <View style={[styles.inputWrapper, styles.inputHalf]}>
                  <Feather
                    name="map"
                    size={20}
                    color={colors.primary}
                    style={styles.inputIcon}
                  />
                  <TextInput
                    style={styles.input}
                    placeholder="Estado"
                    placeholderTextColor="#999"
                    autoCapitalize="characters"
                    value={form.estado}
                    onChangeText={(value) => handleChange("estado", value)}
                    editable={!loading}
                  />
                </View>
              </View>

              <TouchableOpacity
                style={[styles.loginButton, loading && { opacity: 0.7 }]}
                onPress={handleRegister}
                disabled={loading}
              >
                {loading ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.loginButtonText}>Cadastrar</Text>
                )}
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.linkButton}
                onPress={() => router.back()}
                disabled={loading}
              >
                <Text style={styles.linkButtonText}>
                  Já possui conta? Entrar
                </Text>
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
    backgroundColor: "#fff",
  },
  keyboardView: {
    flex: 1,
  },
  backgroundDecor: {
    position: "absolute",
    width: "100%",
    height: "100%",
  },
  headerAccent: {
    height: 220,
    backgroundColor: colors.primary,
    borderBottomLeftRadius: 32,
    borderBottomRightRadius: 32,
  },
  accentCircle: {
    position: "absolute",
    width: 200,
    height: 200,
    borderRadius: 100,
    backgroundColor: "rgba(255, 255, 255, 0.15)",
    top: 40,
    right: -60,
  },
  accentCircleSmall: {
    position: "absolute",
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: "rgba(255, 255, 255, 0.15)",
    top: 140,
    left: -40,
  },
  scrollContent: {
    padding: 24,
    paddingBottom: 48,
  },
  logoContainer: {
    alignItems: "center",
    marginBottom: 24,
  },
  logoCircle: {
    width: 90,
    height: 90,
    borderRadius: 45,
    backgroundColor: colors.primary,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: "#000",
    shadowOpacity: 0.2,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 4 },
    elevation: 6,
  },
  appName: {
    fontSize: 26,
    fontWeight: "700",
    color: colors.textPrimary,
    marginTop: 12,
  },
  tagline: {
    fontSize: 14,
    color: colors.textSecondary,
    marginTop: 4,
  },
  formContainer: {
    flex: 1,
  },
  formCard: {
    backgroundColor: "#fff",
    borderRadius: 20,
    padding: 20,
    shadowColor: "#000",
    shadowOpacity: 0.08,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 6 },
    elevation: 4,
  },
  welcomeSubtext: {
    fontSize: 16,
    color: colors.textSecondary,
    marginBottom: 16,
    fontWeight: "500",
  },
  formError: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#fee2e2",
    padding: 10,
    borderRadius: 10,
    marginBottom: 16,
  },
  formErrorText: {
    color: "#b91c1c",
    marginLeft: 8,
    fontSize: 13,
  },
  inputWrapper: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 12,
  },
  inputWrapperError: {
    borderWidth: 1,
    borderColor: "#ef4444",
    backgroundColor: "#fef2f2",
  },
  fieldErrorText: {
    color: "#b91c1c",
    fontSize: 12,
    marginTop: -6,
    marginBottom: 10,
    marginLeft: 4,
  },
  inputRow: {
    flexDirection: "row",
    gap: 10,
  },
  inputHalf: {
    flex: 1,
  },
  inputIcon: {
    marginRight: 10,
  },
  input: {
    flex: 1,
    color: "#0f172a",
    fontSize: 14,
    outlineStyle: "none",
    outlineWidth: 0,
  },
  sectionTitleWrapper: {
    marginTop: 10,
    marginBottom: 6,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: "600",
    color: colors.textSecondary,
  },
  loginButton: {
    backgroundColor: colors.primary,
    paddingVertical: 14,
    borderRadius: 14,
    alignItems: "center",
    marginTop: 6,
  },
  loginButtonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
  },
  linkButton: {
    marginTop: 16,
    alignItems: "center",
  },
  linkButtonText: {
    color: colors.primary,
    fontWeight: "600",
  },
});
