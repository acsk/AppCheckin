import { getApiUrlRuntime } from "@/src/config/urls";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Linking,
  Modal,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

// NOVO ARQUIVO - SEM CÓDIGO ANTIGO

interface Plan {
  id: number;
  nome: string;
  descricao: string;
  valor: number;
  valor_formatado: string;
  duracao_dias: number;
  duracao_texto: string;
  checkins_semanais: number;
  modalidade: {
    id: number;
    nome: string;
  };
  is_plano_atual?: boolean;
  label?: string | null;
}

interface ApiResponse {
  success: boolean;
  data?: {
    planos: Plan[];
    total: number;
  };
  error?: string;
}

interface Toast {
  message: string;
  type: "info" | "success" | "error" | "warning";
}

export default function PlanosScreen() {
  const router = useRouter();

  const [planos, setPlanos] = useState<Plan[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [apiUrl, setApiUrl] = useState("");
  const [comprando, setComprando] = useState(false);
  const [planoComprando, setPlanoComprando] = useState<number | null>(null);
  const [errorModalVisible, setErrorModalVisible] = useState(false);
  const [errorModalData, setErrorModalData] = useState<{
    title: string;
    message: string;
    type: "error" | "success" | "warning";
  }>({
    title: "",
    message: "",
    type: "error",
  });
  const [redirectModalVisible, setRedirectModalVisible] = useState(false);
  const [countdown, setCountdown] = useState(3);
  const [paymentUrlToOpen, setPaymentUrlToOpen] = useState<string | null>(null);

  useEffect(() => {
    const initializeAndFetch = async () => {
      try {
        console.log("🚀 Inicializando página de planos...");
        const url = getApiUrlRuntime();
        console.log("🌐 URL da API:", url);
        setApiUrl(url);
        await fetchPlanos(url);
      } catch (err) {
        console.error("❌ Erro na inicialização:", err);
        setError("Erro ao inicializar página");
      }
    };

    initializeAndFetch();
  }, []);

  // Countdown para modal de redirecionamento
  useEffect(() => {
    if (redirectModalVisible && countdown > 0) {
      const timer = setTimeout(() => {
        setCountdown(countdown - 1);
      }, 1000);
      return () => clearTimeout(timer);
    }

    // Quando chegar a 0, abrir o link e fechar modal
    if (redirectModalVisible && countdown === 0 && paymentUrlToOpen) {
      (async () => {
        const supported = await Linking.canOpenURL(paymentUrlToOpen);
        if (supported) {
          console.log("🔗 Abrindo URL de pagamento:", paymentUrlToOpen);
          await Linking.openURL(paymentUrlToOpen);
        }
        setRedirectModalVisible(false);
        setCountdown(3);
        setPaymentUrlToOpen(null);
      })();
    }
  }, [redirectModalVisible, countdown, paymentUrlToOpen]);

  // Monitorar retorno do Mercado Pago
  useEffect(() => {
    const handleDeepLink = async (event: { url: string }) => {
      console.log("🔗 Deep link recebido:", event.url);

      if (
        event.url.includes("mobile.appcheckin.com.br/pagamento") ||
        event.url.includes("pagamento/pendente") ||
        event.url.includes("pagamento/aprovado")
      ) {
        // Extrair parâmetros da URL
        const url = new URL(event.url);
        const params = Object.fromEntries(url.searchParams);

        console.log("📋 Parâmetros recebidos:", params);

        const collectionStatus = params.collection_status;
        const paymentId = params.payment_id;
        const externalReference = params.external_reference;

        // Processar resultado do pagamento
        if (collectionStatus === "approved") {
          console.log("✅ Pagamento APROVADO!");
          showErrorModal(
            "✅ Pagamento Realizado",
            "Seu pagamento foi aprovado com sucesso! Sua matrícula está ativa.",
            "success",
          );
          // Recarregar planos após alguns segundos
          setTimeout(() => {
            fetchPlanos(apiUrl);
          }, 2000);
        } else if (collectionStatus === "pending") {
          console.log("⏳ Pagamento PENDENTE");
          showErrorModal(
            "⏳ Pagamento em Análise",
            "Seu pagamento está em análise. Você receberá uma confirmação em breve.",
            "warning",
          );
        } else if (collectionStatus === "rejected") {
          console.log("❌ Pagamento REJEITADO");
          showErrorModal(
            "❌ Pagamento Recusado",
            "Seu pagamento foi recusado. Tente novamente com outro método de pagamento.",
            "error",
          );
        } else {
          console.log("❓ Status desconhecido:", collectionStatus);
          showErrorModal(
            "ℹ️ Retorno do Pagamento",
            `Seu pagamento retornou com status: ${collectionStatus}. Entre em contato se tiver dúvidas.`,
            "warning",
          );
        }
      }
    };

    // Verificar se abriu com deep link
    Linking.getInitialURL().then((url) => {
      if (url != null) {
        handleDeepLink({ url });
      }
    });

    // Ouvir novos deep links
    const subscription = Linking.addEventListener("url", handleDeepLink);

    return () => {
      subscription.remove();
    };
  }, [apiUrl]);

  const showErrorModal = (
    title: string,
    message: string,
    type: "error" | "success" | "warning" = "error",
  ) => {
    setErrorModalData({ title, message, type });
    setErrorModalVisible(true);
  };

  const fetchPlanos = async (baseUrl: string) => {
    try {
      setLoading(true);
      setError(null);

      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        console.warn("❌ Token não encontrado no AsyncStorage");
        throw new Error("Token não encontrado");
      }

      // Usar modalidade padrão (1) ou deixar sem filtro
      const url = `${baseUrl}/mobile/planos-disponiveis`;

      console.log("📍 URL da requisição:", url);
      console.log("🔑 Token encontrado:", token.substring(0, 20) + "...");

      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      console.log("📡 Status da resposta:", response.status);

      if (!response.ok) {
        const responseText = await response.text();
        console.error("❌ Erro na resposta:", responseText);

        if (response.status === 401) {
          console.warn("🔑 Token inválido ou expirado");
          await AsyncStorage.removeItem("@appcheckin:token");
          await AsyncStorage.removeItem("@appcheckin:user");
          router.replace("/(auth)/login");
          return;
        }
        throw new Error(`HTTP ${response.status}: ${responseText}`);
      }

      const data: ApiResponse = await response.json();

      console.log("✅ Resposta da API:", JSON.stringify(data, null, 2));

      if (data.success && data.data?.planos) {
        console.log("✅ Planos carregados:", data.data.planos.length);
        setPlanos(data.data.planos);
      } else {
        console.warn("⚠️ Resposta sem planos:", data);
        throw new Error(data.error || "Falha ao carregar planos");
      }
    } catch (err) {
      const errorMsg =
        err instanceof Error ? err.message : "Erro ao carregar planos";
      console.error("❌ Erro completo:", err);
      console.error("📝 Mensagem:", errorMsg);
      setError(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleContratar = React.useCallback(
    async (plano: Plan) => {
      try {
        setComprando(true);
        setPlanoComprando(plano.id);

        console.log("🛒 Iniciando compra do plano:", plano.nome);

        // 1. Obter token e dados do usuário
        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          console.error("❌ Token não encontrado");
          throw new Error("Token não encontrado");
        }

        // 2. Obter dados do usuário (para aluno_id)
        const userJson = await AsyncStorage.getItem("@appcheckin:user");
        if (!userJson) {
          console.error("❌ Dados do usuário não encontrados");
          throw new Error("Dados do usuário não encontrados");
        }

        const user = JSON.parse(userJson);
        console.log("👤 Usuário ID:", user.id);

        // 3. Fazer requisição POST para comprar plano
        const matriculaResponse = await fetch(
          `${apiUrl}/mobile/comprar-plano`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${token}`,
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              plano_id: plano.id,
            }),
          },
        );

        console.log("📡 Status criação matrícula:", matriculaResponse.status);

        if (!matriculaResponse.ok) {
          const errorText = await matriculaResponse.text();
          console.error("❌ Erro ao criar matrícula:", errorText);
          setComprando(false);
          setPlanoComprando(null);

          try {
            const errorData = JSON.parse(errorText);
            showErrorModal(
              "⚠️ Problema na Compra",
              errorData.message || "Não foi possível processar sua compra",
              "error",
            );
          } catch {
            showErrorModal(
              "⚠️ Problema na Compra",
              "Não foi possível processar sua compra. Tente novamente.",
              "error",
            );
          }
          return;
        }

        const matriculaData = await matriculaResponse.json();
        console.log("✅ Resposta da API:", matriculaData);
        console.log(
          "📋 Estrutura completa de data:",
          JSON.stringify(matriculaData.data, null, 2),
        );

        // Verificar se a API retornou sucesso
        if (!matriculaData.success) {
          const errorMessage =
            matriculaData.message || "Erro desconhecido ao processar compra";
          console.error("❌ Erro da API:", errorMessage);
          setComprando(false);
          setPlanoComprando(null);
          showErrorModal("❌ Não foi Possível Comprar", errorMessage, "error");
          return;
        }

        // Buscar payment_url em diferentes localizações
        let paymentUrl = matriculaData.data?.payment_url;
        let matriculaId =
          matriculaData.data?.matricula?.id || matriculaData.data?.matricula_id;

        // Se não encontrou em payment_url, procura em outras estruturas
        if (!paymentUrl && matriculaData.data?.pagamento) {
          paymentUrl = matriculaData.data.pagamento.url;
        }

        console.log("🔍 Debug payment_url:", {
          "data keys": Object.keys(matriculaData.data || {}),
          payment_url: paymentUrl,
          matricula_id: matriculaId,
          data: matriculaData.data,
        });

        if (!paymentUrl) {
          console.error(
            "❌ Link de pagamento não encontrado em nenhuma localização",
          );
          console.error("📊 Data recebida:", matriculaData.data);
          setComprando(false);
          setPlanoComprando(null);
          showErrorModal(
            "⚠️ Erro",
            "Link de pagamento não foi gerado. Tente novamente.",
            "error",
          );
          return;
        }

        console.log("💳 Payment URL:", paymentUrl);

        // 4. Salvar ID da matrícula para consultar depois
        if (matriculaId) {
          await AsyncStorage.setItem(
            "matricula_pendente_id",
            matriculaId.toString(),
          );
          console.log("💾 Matrícula ID salvo:", matriculaId);
        }

        setComprando(false);
        setPlanoComprando(null);

        // 5. Mostrar modal de redirecionamento com countdown
        setPaymentUrlToOpen(paymentUrl);
        setCountdown(3);
        setRedirectModalVisible(true);
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao processar compra";
        console.error("❌ Erro na compra:", err);
        setComprando(false);
        setPlanoComprando(null);

        showErrorModal("❌ Algo Deu Errado", errorMsg, "error");
      }
    },
    [apiUrl, fetchPlanos],
  );

  const renderPlanCard = ({ item: plano }: { item: Plan }) => (
    <View style={styles.planCard}>
      {/* Badge de Plano Atual */}
      {plano.is_plano_atual && (
        <View style={styles.planCurrentBadge}>
          <Feather name="check-circle" size={14} color="#fff" />
          <Text style={styles.planCurrentBadgeText}>
            {plano.label || "Seu plano atual"}
          </Text>
        </View>
      )}

      <View style={styles.planHeader}>
        <View style={styles.planInfo}>
          <Text style={styles.planNome}>{plano.nome}</Text>
          <Text style={styles.planModalidade}>{plano.modalidade.nome}</Text>
        </View>
        <View style={styles.planPriceContainer}>
          <Text style={styles.planPrice}>{plano.valor_formatado}</Text>
          <Text style={styles.planDuracao}>{plano.duracao_texto}</Text>
        </View>
      </View>

      <Text style={styles.planDescricao}>{plano.descricao}</Text>

      <View style={styles.planFeatures}>
        <View style={styles.featureItem}>
          <Feather name="check-circle" size={16} color={colors.primary} />
          <Text style={styles.featureText}>
            {plano.checkins_semanais === 999
              ? "Ilimitado"
              : `${plano.checkins_semanais} check-ins por semana`}
          </Text>
        </View>
        <View style={styles.featureItem}>
          <Feather name="calendar" size={16} color={colors.primary} />
          <Text style={styles.featureText}>
            {plano.duracao_dias} dias de acesso
          </Text>
        </View>
      </View>

      <TouchableOpacity
        style={[
          styles.contratarButton,
          plano.is_plano_atual && styles.contratarButtonCurrent,
          comprando && planoComprando === plano.id
            ? styles.contratarButtonLoading
            : null,
        ]}
        onPress={() => handleContratar(plano)}
        disabled={
          plano.is_plano_atual || (comprando && planoComprando === plano.id)
        }
      >
        {plano.is_plano_atual ? (
          <>
            <Feather name="check" size={18} color="#fff" />
            <Text style={styles.contratarButtonText}>Plano Ativo</Text>
          </>
        ) : comprando && planoComprando === plano.id ? (
          <>
            <ActivityIndicator color="#fff" size="small" />
            <Text style={styles.contratarButtonText}>Processando...</Text>
          </>
        ) : (
          <>
            <Feather name="shopping-cart" size={18} color="#fff" />
            <Text style={styles.contratarButtonText}>Contratar Plano</Text>
          </>
        )}
      </TouchableOpacity>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.container} edges={["top"]}>
        <View style={styles.headerTop}>
          <TouchableOpacity
            style={styles.headerBackButton}
            onPress={() => router.replace("/(tabs)/checkin")}
          >
            <Feather name="arrow-left" size={24} color="#fff" />
          </TouchableOpacity>
          <Text style={styles.headerTitleCentered}>Planos</Text>
          <View style={{ width: 40 }} />
        </View>
        <View style={styles.centerContent}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Carregando planos...</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container} edges={["top"]}>
      <View style={styles.headerTop}>
        <TouchableOpacity
          style={styles.headerBackButton}
          onPress={() => router.replace("/(tabs)/checkin")}
        >
          <Feather name="arrow-left" size={24} color="#fff" />
        </TouchableOpacity>
        <Text style={styles.headerTitleCentered}>Planos</Text>
        <TouchableOpacity
          style={styles.headerBackButton}
          onPress={() => {
            setLoading(true);
            fetchPlanos(apiUrl);
          }}
        >
          <Feather name="refresh-cw" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      {error ? (
        <View style={styles.errorContainer}>
          <Feather name="alert-circle" size={48} color="#dc2626" />
          <Text style={styles.errorTitle}>Erro ao carregar planos</Text>
          <Text style={styles.errorMessage}>{error}</Text>
          <TouchableOpacity
            style={styles.retryButton}
            onPress={() => {
              setLoading(true);
              fetchPlanos(apiUrl);
            }}
          >
            <Text style={styles.retryButtonText}>Tentar novamente</Text>
          </TouchableOpacity>
        </View>
      ) : planos.length === 0 ? (
        <View style={styles.emptyContainer}>
          <Feather name="inbox" size={48} color="#d1d5db" />
          <Text style={styles.emptyText}>Nenhum plano disponível</Text>
          <Text style={styles.emptySubtext}>
            Não há planos disponíveis no momento
          </Text>
        </View>
      ) : (
        <FlatList
          data={planos}
          renderItem={renderPlanCard}
          keyExtractor={(item) => item.id.toString()}
          contentContainerStyle={styles.listContent}
          scrollEnabled={true}
          showsVerticalScrollIndicator={false}
        />
      )}

      {/* Error Modal */}
      <Modal
        visible={errorModalVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setErrorModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View
            style={[
              styles.modalContent,
              errorModalData.type === "error" && styles.modalError,
              errorModalData.type === "success" && styles.modalSuccess,
              errorModalData.type === "warning" && styles.modalWarning,
            ]}
          >
            {/* Icon Circle */}
            <View
              style={[
                styles.iconCircle,
                errorModalData.type === "error" && styles.iconCircleError,
                errorModalData.type === "success" && styles.iconCircleSuccess,
                errorModalData.type === "warning" && styles.iconCircleWarning,
              ]}
            >
              <Feather
                name={
                  errorModalData.type === "success"
                    ? "check"
                    : errorModalData.type === "warning"
                      ? "alert-triangle"
                      : "x"
                }
                size={40}
                color={
                  errorModalData.type === "success"
                    ? "#0a7f3c"
                    : errorModalData.type === "warning"
                      ? "#b26a00"
                      : "#b3261e"
                }
              />
            </View>

            {/* Title */}
            <Text style={styles.modalTitle}>{errorModalData.title}</Text>

            {/* Message */}
            <Text style={styles.modalMessage}>{errorModalData.message}</Text>

            {/* Button */}
            <TouchableOpacity
              style={[
                styles.modalButton,
                errorModalData.type === "error" && styles.buttonError,
                errorModalData.type === "success" && styles.buttonSuccess,
                errorModalData.type === "warning" && styles.buttonWarning,
              ]}
              onPress={() => setErrorModalVisible(false)}
            >
              <Text style={styles.modalButtonText}>OK, Entendi</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* Redirect Modal com Countdown */}
      <Modal
        visible={redirectModalVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setRedirectModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, styles.modalSuccess]}>
            {/* Icon Circle */}
            <View style={[styles.iconCircle, styles.iconCircleSuccess]}>
              <Feather name="arrow-right" size={40} color="#0a7f3c" />
            </View>

            {/* Title */}
            <Text style={styles.modalTitle}>Redirecionando para Pagamento</Text>

            {/* Message */}
            <Text style={styles.modalMessage}>
              Você será redirecionado para o Mercado Pago em{" "}
              <Text
                style={{
                  fontWeight: "800",
                  fontSize: 24,
                  color: colors.primary,
                }}
              >
                {countdown}
              </Text>
              {"\n"}segundos
            </Text>

            {/* Loading Indicator */}
            <ActivityIndicator
              size="large"
              color={colors.primary}
              style={{ marginBottom: 24 }}
            />
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}
const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#F6F7F9",
  },
  headerTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 18,
    paddingVertical: 16,
    backgroundColor: colors.primary,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.2,
    shadowRadius: 6,
    elevation: 8,
  },
  headerTitleCentered: {
    fontSize: 22,
    fontWeight: "800",
    color: "#fff",
    flex: 1,
    textAlign: "center",
  },
  headerBackButton: {
    padding: 8,
  },
  centerContent: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingVertical: 50,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: colors.text,
    fontWeight: "500",
  },
  listContent: {
    paddingHorizontal: 16,
    paddingVertical: 16,
    paddingBottom: 32,
    gap: 16,
  },
  planCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 8,
    elevation: 2,
  },
  planCurrentBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: "#0a7f3c",
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    marginBottom: 12,
    alignSelf: "flex-start",
  },
  planCurrentBadgeText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "700",
  },
  planHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    marginBottom: 12,
  },
  planInfo: {
    flex: 1,
    gap: 4,
  },
  planNome: {
    fontSize: 16,
    fontWeight: "700",
    color: colors.text,
  },
  planModalidade: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: "500",
  },
  planPriceContainer: {
    alignItems: "flex-end",
  },
  planPrice: {
    fontSize: 20,
    fontWeight: "700",
    color: colors.primary,
  },
  planDuracao: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: "500",
  },
  planDescricao: {
    fontSize: 13,
    color: colors.textMuted,
    marginBottom: 12,
    lineHeight: 18,
  },
  planFeatures: {
    gap: 8,
    marginBottom: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: "#f0f1f4",
  },
  featureItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  featureText: {
    fontSize: 13,
    color: colors.text,
    fontWeight: "500",
  },
  contratarButton: {
    backgroundColor: colors.primary,
    borderRadius: 12,
    paddingVertical: 12,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    shadowColor: colors.primary,
    shadowOpacity: 0.25,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
  },
  contratarButtonLoading: {
    opacity: 0.7,
  },
  contratarButtonCurrent: {
    backgroundColor: "#10b981",
    opacity: 0.8,
  },
  contratarButtonText: {
    fontSize: 14,
    fontWeight: "700",
    color: "#fff",
  },
  errorContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 24,
  },
  errorTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: colors.text,
    marginTop: 16,
  },
  errorMessage: {
    fontSize: 14,
    color: colors.textMuted,
    textAlign: "center",
    marginTop: 8,
  },
  retryButton: {
    marginTop: 20,
    backgroundColor: colors.primary,
    paddingHorizontal: 32,
    paddingVertical: 12,
    borderRadius: 8,
  },
  retryButtonText: {
    color: "#fff",
    fontWeight: "600",
    fontSize: 14,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 24,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: "700",
    color: colors.text,
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 8,
    textAlign: "center",
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0, 0, 0, 0.5)",
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 24,
  },
  modalContent: {
    borderRadius: 24,
    padding: 32,
    alignItems: "center",
    minWidth: "80%",
    maxWidth: "90%",
  },
  modalError: {
    backgroundColor: "#fff",
  },
  modalSuccess: {
    backgroundColor: "#fff",
  },
  modalWarning: {
    backgroundColor: "#fff",
  },
  iconCircle: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 24,
  },
  iconCircleError: {
    backgroundColor: "#ffebee",
  },
  iconCircleSuccess: {
    backgroundColor: "#e6f4ec",
  },
  iconCircleWarning: {
    backgroundColor: "#fff3e0",
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: "700",
    color: colors.text,
    marginBottom: 12,
    textAlign: "center",
  },
  modalMessage: {
    fontSize: 16,
    color: colors.textMuted,
    textAlign: "center",
    marginBottom: 28,
    lineHeight: 24,
  },
  modalButton: {
    width: "100%",
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
  buttonError: {
    backgroundColor: "#b3261e",
  },
  buttonSuccess: {
    backgroundColor: "#0a7f3c",
  },
  buttonWarning: {
    backgroundColor: "#b26a00",
  },
  modalButtonText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#fff",
  },
});
