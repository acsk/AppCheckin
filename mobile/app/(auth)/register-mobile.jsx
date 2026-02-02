import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useEffect, useMemo, useRef, useState } from "react";
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
  whatsapp: "",
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

const formatPhone = (value) => {
  const digits = value.replace(/\D/g, "").slice(0, 11);
  if (digits.length <= 2) return digits;
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  if (digits.length <= 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
  }
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
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
  const cepDigits = useMemo(() => normalizeCep(form.cep), [form.cep]);

  const handleChange = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    if (fieldErrors[field]) {
      setFieldErrors((prev) => ({ ...prev, [field]: undefined }));
    }
  };

  const handleCpfChange = (value) => {
    handleChange("cpf", formatCpf(value));
  };

  const handlePhoneChange = (field, value) => {
    handleChange(field, formatPhone(value));
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

      if (data?.erro === true || data?.erro === "true") {
        setFieldErrors((prev) => ({
          ...prev,
          cep: "CEP não encontrado",
        }));
        return;
      }

      setForm((prev) => ({
        ...prev,
        cep: data.cep || cepDigitsValue,
        logradouro: data.logradouro || prev.logradouro,
        complemento: data.complemento || prev.complemento,
        bairro: data.bairro || prev.bairro,
        cidade: data.localidade || prev.cidade,
        estado: data.estado || data.uf || prev.estado,
      }));
    } catch (error) {
      Alert.alert("Erro ao buscar CEP", "Não foi possível consultar o CEP.");
    } finally {
      setCepLoading(false);
    }
  };

  const handleCepChange = (value) => {
    handleChange("cep", value);
  };

  useEffect(() => {
    if (cepDigits.length === 8) {
      fetchCep(cepDigits);
      return;
    }

    if (cepDigits.length < 8) {
      lastCepRequestedRef.current = "";
    }
  }, [cepDigits]);

  const buildPayload = () => {
    const payload = {
      nome: form.nome.trim(),
      email: form.email.trim().toLowerCase(),
      cpf: cpfDigits,
    };

    const optionalFields = [
      "telefone",
      "whatsapp",
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
      return;
    }

    setLoading(true);

    try {
      const payload = buildPayload();
      const response = await authService.registerMobile(payload);

      if (response?.token) {
        Alert.alert("Sucesso", "Cadastro realizado com sucesso");
        await new Promise((resolve) => setTimeout(resolve, 400));
        router.replace("/(auth)/register-success");
      } else {
        Alert.alert("Erro", "Não foi possível concluir o cadastro");
      }
    } catch (error) {
      const errorMessage = error?.message || "";
      const errorCode = error?.code || "";

      // Detectar erro de email já cadastrado
      if (
        errorCode === "EMAIL_ALREADY_EXISTS" ||
        errorMessage.toLowerCase().includes("email já cadastrado") ||
        errorMessage.toLowerCase().includes("email already exists")
      ) {
        setFieldErrors((prev) => ({
          ...prev,
          email: "Email já cadastrado",
        }));
        return;
      }

      // Detectar erro de CPF já cadastrado
      if (
        errorCode === "CPF_ALREADY_EXISTS" ||
        errorMessage.toLowerCase().includes("cpf já cadastrado") ||
        errorMessage.toLowerCase().includes("cpf already exists")
      ) {
        setFieldErrors((prev) => ({
          ...prev,
          cpf: "CPF já cadastrado",
        }));
        return;
      }

      // Outros erros
      const message =
        errorMessage ||
        (Array.isArray(error?.errors) ? error.errors.join("\n") : null) ||
        "Não foi possível concluir o cadastro";
      Alert.alert("Erro no cadastro", message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
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
                  onChangeText={(value) => handlePhoneChange("telefone", value)}
                  editable={!loading}
                />
              </View>

              <View style={styles.inputWrapper}>
                <Feather
                  name="message-circle"
                  size={20}
                  color={colors.primary}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.input}
                  placeholder="WhatsApp (opcional)"
                  placeholderTextColor="#999"
                  keyboardType="phone-pad"
                  value={form.whatsapp}
                  onChangeText={(value) => handlePhoneChange("whatsapp", value)}
                  editable={!loading}
                />
              </View>

              <View style={styles.sectionTitleWrapper}>
                <Text style={styles.sectionTitle}>Endereço (opcional)</Text>
              </View>

              <View
                style={[
                  styles.inputWrapper,
                  fieldErrors.cep && styles.inputWrapperError,
                ]}
              >
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
              {fieldErrors.cep ? (
                <Text style={styles.fieldErrorText}>{fieldErrors.cep}</Text>
              ) : null}

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
    backgroundColor: "#f7f4f1",
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
    height: 260,
    backgroundColor: colors.primary,
    borderBottomLeftRadius: 36,
    borderBottomRightRadius: 36,
  },
  headerGlow: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    height: 180,
    backgroundColor: "rgba(255,255,255,0.12)",
  },
  accentCircle: {
    position: "absolute",
    width: 220,
    height: 220,
    borderRadius: 110,
    backgroundColor: "rgba(255, 255, 255, 0.12)",
    top: 30,
    right: -70,
  },
  accentCircleSmall: {
    position: "absolute",
    width: 140,
    height: 140,
    borderRadius: 70,
    backgroundColor: "rgba(255, 255, 255, 0.12)",
    top: 150,
    left: -50,
  },
  scrollContent: {
    padding: 20,
    paddingTop: 14,
    paddingBottom: 44,
  },
  logoContainer: {
    alignItems: "center",
    marginBottom: 18,
  },
  logoCircle: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: colors.primary,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: "#000",
    shadowOpacity: 0.18,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 4 },
    elevation: 6,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.4)",
  },
  appName: {
    fontSize: 22,
    fontWeight: "700",
    color: "#1f2937",
    marginTop: 10,
  },
  tagline: {
    fontSize: 13,
    color: "#6b7280",
    marginTop: 4,
  },
  formContainer: {
    flex: 1,
  },
  formCard: {
    backgroundColor: "#fff",
    borderRadius: 24,
    padding: 22,
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 24,
    shadowOffset: { width: 0, height: 6 },
    elevation: 4,
    borderWidth: 1,
    borderColor: "#f4e1d1",
  },
  welcomeSubtext: {
    fontSize: 15,
    color: "#7c6a5d",
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
    backgroundColor: "#fff",
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: "#efe7df",
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
    fontSize: 15,
    outlineStyle: "none",
    outlineWidth: 0,
  },
  sectionTitleWrapper: {
    marginTop: 10,
    marginBottom: 6,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: "700",
    color: "#8b7b70",
    textTransform: "uppercase",
    letterSpacing: 0.6,
  },
  loginButton: {
    backgroundColor: colors.primary,
    paddingVertical: 16,
    borderRadius: 16,
    alignItems: "center",
    marginTop: 6,
    shadowColor: colors.primary,
    shadowOpacity: 0.25,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 6 },
    elevation: 4,
  },
  loginButtonText: {
    color: "#fff",
    fontSize: 17,
    fontWeight: "700",
  },
  linkButton: {
    marginTop: 18,
    alignItems: "center",
  },
  linkButtonText: {
    color: colors.primary,
    fontWeight: "700",
  },
});
