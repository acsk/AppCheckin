import { authService } from "@/src/services/authService";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import React, { useState } from "react";
import {
    ActivityIndicator,
    Alert,
    Dimensions,
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

interface PasswordRecoveryModalProps {
  visible: boolean;
  onClose: () => void;
}

type RecoveryStep = "email" | "validate" | "reset" | "success";

export default function PasswordRecoveryModal({
  visible,
  onClose,
}: PasswordRecoveryModalProps) {
  const [step, setStep] = useState<RecoveryStep>("email");
  const [email, setEmail] = useState("");
  const [token, setToken] = useState("");
  const [novaSenha, setNovaSenha] = useState("");
  const [confirmacao, setConfirmacao] = useState("");
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const handleRequestReset = async () => {
    if (!email.trim()) {
      Alert.alert("Atenção", "Por favor, insira seu email");
      return;
    }

    setLoading(true);
    setMessage("");

    try {
      const response = await authService.requestPasswordRecovery(email);
      setMessage(response.message);
      Alert.alert("Sucesso", "Verifique seu email para o link de recuperação");
      // Mostrar campo para inserir token ou esperar link
      setTimeout(() => {
        setStep("validate");
      }, 500);
    } catch (error: any) {
      const errorMsg =
        error?.message || "Erro ao solicitar recuperação de senha";
      setMessage(errorMsg);
      Alert.alert("Erro", errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleValidateToken = async () => {
    if (!token.trim()) {
      Alert.alert("Atenção", "Por favor, insira o token de recuperação");
      return;
    }

    setLoading(true);
    setMessage("");

    try {
      const response = await authService.validatePasswordToken(token);
      setMessage(response.message);
      setStep("reset");
    } catch (error: any) {
      const errorMsg = error?.message || "Token inválido ou expirado";
      setMessage(errorMsg);
      Alert.alert("Erro", errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleResetPassword = async () => {
    if (!novaSenha.trim() || !confirmacao.trim()) {
      Alert.alert("Atenção", "Por favor, preencha todas os campos");
      return;
    }

    if (novaSenha !== confirmacao) {
      Alert.alert("Atenção", "As senhas não coincidem");
      return;
    }

    if (novaSenha.length < 6) {
      Alert.alert("Atenção", "Senha deve ter no mínimo 6 caracteres");
      return;
    }

    setLoading(true);
    setMessage("");

    try {
      const response = await authService.resetPassword(
        token,
        novaSenha,
        confirmacao,
      );
      setMessage(response.message);
      setStep("success");

      // Fechar modal após sucesso
      setTimeout(() => {
        handleClose();
      }, 2000);
    } catch (error: any) {
      const errorMsg = error?.message || "Erro ao resetar senha";
      setMessage(errorMsg);
      Alert.alert("Erro", errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    // Resetar estado
    setStep("email");
    setEmail("");
    setToken("");
    setNovaSenha("");
    setConfirmacao("");
    setMessage("");
    setShowPassword(false);
    setShowConfirmPassword(false);
    onClose();
  };

  const handleBack = () => {
    if (step === "validate") {
      setStep("email");
      setToken("");
    } else if (step === "reset") {
      setStep("validate");
      setNovaSenha("");
      setConfirmacao("");
    }
    setMessage("");
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={handleClose}
    >
      <View style={styles.overlay}>
        <KeyboardAvoidingView
          behavior={Platform.OS === "ios" ? "padding" : "height"}
          style={styles.container}
        >
          <ScrollView
            contentContainerStyle={styles.scrollContent}
            keyboardShouldPersistTaps="handled"
          >
            <View style={styles.modal}>
              {/* Header */}
              <View style={styles.header}>
                <Text style={styles.title}>Recuperar Senha</Text>
                <TouchableOpacity
                  onPress={handleClose}
                  style={styles.closeButton}
                >
                  <Feather name="x" size={24} color={colors.primary} />
                </TouchableOpacity>
              </View>

              {/* Step 1: Email */}
              {step === "email" && (
                <View style={styles.content}>
                  <Text style={styles.stepDescription}>
                    Digite seu email para receber um link de recuperação
                  </Text>

                  <View style={styles.inputGroup}>
                    <Text style={styles.label}>Email</Text>
                    <TextInput
                      style={styles.input}
                      placeholder="seu@email.com"
                      placeholderTextColor="#ccc"
                      keyboardType="email-address"
                      value={email}
                      onChangeText={setEmail}
                      editable={!loading}
                      autoCapitalize="none"
                    />
                  </View>

                  {message && (
                    <View style={styles.messageContainer}>
                      <Text style={styles.messageText}>{message}</Text>
                    </View>
                  )}

                  <TouchableOpacity
                    style={[
                      styles.button,
                      styles.primaryButton,
                      loading && styles.buttonDisabled,
                    ]}
                    onPress={handleRequestReset}
                    disabled={loading}
                  >
                    {loading ? (
                      <ActivityIndicator size="small" color="#fff" />
                    ) : (
                      <Text style={styles.buttonText}>Enviar Link</Text>
                    )}
                  </TouchableOpacity>
                </View>
              )}

              {/* Step 2: Validate Token */}
              {step === "validate" && (
                <View style={styles.content}>
                  <Text style={styles.stepDescription}>
                    Digite o token que você recebeu por email
                  </Text>

                  <View style={styles.inputGroup}>
                    <Text style={styles.label}>Token de Recuperação</Text>
                    <TextInput
                      style={styles.input}
                      placeholder="Cole o token aqui"
                      placeholderTextColor="#ccc"
                      value={token}
                      onChangeText={setToken}
                      editable={!loading}
                      autoCapitalize="none"
                      multiline
                    />
                  </View>

                  {message && (
                    <View style={styles.messageContainer}>
                      <Text style={styles.messageText}>{message}</Text>
                    </View>
                  )}

                  <TouchableOpacity
                    style={[
                      styles.button,
                      styles.primaryButton,
                      loading && styles.buttonDisabled,
                    ]}
                    onPress={handleValidateToken}
                    disabled={loading}
                  >
                    {loading ? (
                      <ActivityIndicator size="small" color="#fff" />
                    ) : (
                      <Text style={styles.buttonText}>Validar Token</Text>
                    )}
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[styles.button, styles.secondaryButton]}
                    onPress={handleBack}
                    disabled={loading}
                  >
                    <Text style={styles.secondaryButtonText}>Voltar</Text>
                  </TouchableOpacity>
                </View>
              )}

              {/* Step 3: Reset Password */}
              {step === "reset" && (
                <View style={styles.content}>
                  <Text style={styles.stepDescription}>
                    Digite sua nova senha (mínimo 6 caracteres)
                  </Text>

                  <View style={styles.inputGroup}>
                    <Text style={styles.label}>Nova Senha</Text>
                    <View style={styles.passwordInputContainer}>
                      <TextInput
                        style={styles.passwordInput}
                        placeholder="Nova senha"
                        placeholderTextColor="#ccc"
                        secureTextEntry={!showPassword}
                        value={novaSenha}
                        onChangeText={setNovaSenha}
                        editable={!loading}
                      />
                      <TouchableOpacity
                        onPress={() => setShowPassword(!showPassword)}
                        style={styles.eyeButton}
                      >
                        <Feather
                          name={showPassword ? "eye-off" : "eye"}
                          size={20}
                          color="#999"
                        />
                      </TouchableOpacity>
                    </View>
                  </View>

                  <View style={styles.inputGroup}>
                    <Text style={styles.label}>Confirmar Senha</Text>
                    <View style={styles.passwordInputContainer}>
                      <TextInput
                        style={styles.passwordInput}
                        placeholder="Confirme a senha"
                        placeholderTextColor="#ccc"
                        secureTextEntry={!showConfirmPassword}
                        value={confirmacao}
                        onChangeText={setConfirmacao}
                        editable={!loading}
                      />
                      <TouchableOpacity
                        onPress={() =>
                          setShowConfirmPassword(!showConfirmPassword)
                        }
                        style={styles.eyeButton}
                      >
                        <Feather
                          name={showConfirmPassword ? "eye-off" : "eye"}
                          size={20}
                          color="#999"
                        />
                      </TouchableOpacity>
                    </View>
                  </View>

                  {message && (
                    <View style={styles.messageContainer}>
                      <Text style={styles.messageText}>{message}</Text>
                    </View>
                  )}

                  <TouchableOpacity
                    style={[
                      styles.button,
                      styles.primaryButton,
                      loading && styles.buttonDisabled,
                    ]}
                    onPress={handleResetPassword}
                    disabled={loading}
                  >
                    {loading ? (
                      <ActivityIndicator size="small" color="#fff" />
                    ) : (
                      <Text style={styles.buttonText}>Atualizar Senha</Text>
                    )}
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[styles.button, styles.secondaryButton]}
                    onPress={handleBack}
                    disabled={loading}
                  >
                    <Text style={styles.secondaryButtonText}>Voltar</Text>
                  </TouchableOpacity>
                </View>
              )}

              {/* Step 4: Success */}
              {step === "success" && (
                <View style={styles.content}>
                  <View style={styles.successContainer}>
                    <Feather
                      name="check-circle"
                      size={64}
                      color={colors.primary}
                    />
                    <Text style={styles.successTitle}>Senha Alterada!</Text>
                    <Text style={styles.successMessage}>
                      Sua senha foi alterada com sucesso. Você será
                      redirecionado para o login.
                    </Text>
                  </View>
                </View>
              )}
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: "rgba(0, 0, 0, 0.6)",
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 20,
  },
  container: {
    flex: 1,
    justifyContent: "center",
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: "center",
  },
  modal: {
    backgroundColor: "#fff",
    borderRadius: 16,
    overflow: "hidden",
    maxHeight: Dimensions.get("window").height * 0.85,
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 20,
    paddingTop: 20,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: "#f0f0f0",
  },
  title: {
    fontSize: 20,
    fontWeight: "700",
    color: "#000",
  },
  closeButton: {
    padding: 8,
    marginRight: -8,
  },
  content: {
    paddingHorizontal: 20,
    paddingVertical: 24,
  },
  stepDescription: {
    fontSize: 14,
    color: "#666",
    marginBottom: 20,
    lineHeight: 20,
  },
  inputGroup: {
    marginBottom: 16,
  },
  label: {
    fontSize: 13,
    fontWeight: "600",
    color: "#000",
    marginBottom: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: "#e0e0e0",
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 14,
    color: "#000",
    backgroundColor: "#f9f9f9",
  },
  passwordInputContainer: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderColor: "#e0e0e0",
    borderRadius: 8,
    backgroundColor: "#f9f9f9",
  },
  passwordInput: {
    flex: 1,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 14,
    color: "#000",
  },
  eyeButton: {
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  messageContainer: {
    backgroundColor: "#f0f0f0",
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 16,
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
  },
  messageText: {
    fontSize: 13,
    color: "#333",
  },
  button: {
    borderRadius: 8,
    paddingVertical: 12,
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    marginTop: 12,
  },
  primaryButton: {
    backgroundColor: colors.primary,
  },
  secondaryButton: {
    borderWidth: 1,
    borderColor: colors.primary,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  buttonText: {
    color: "#fff",
    fontSize: 15,
    fontWeight: "600",
  },
  secondaryButtonText: {
    color: colors.primary,
    fontSize: 15,
    fontWeight: "600",
  },
  successContainer: {
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 20,
  },
  successTitle: {
    fontSize: 20,
    fontWeight: "700",
    color: "#000",
    marginTop: 16,
  },
  successMessage: {
    fontSize: 14,
    color: "#666",
    marginTop: 8,
    textAlign: "center",
    lineHeight: 20,
  },
});
