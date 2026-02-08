import { getApiUrlRuntime } from "@/src/config/urls";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useFocusEffect } from "@react-navigation/native";
import { useRouter } from "expo-router";
import React, { useCallback, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

interface StatusAssinatura {
  id: number;
  codigo: string;
  nome: string;
  cor: string;
}

interface CicloAssinatura {
  nome: string;
  meses: number;
}

interface GatewayAssinatura {
  nome: string;
}

interface PlanoAssinatura {
  nome: string;
  modalidade: string;
}

interface Assinatura {
  id: number;
  status: StatusAssinatura;
  valor: number;
  data_inicio: string;
  proxima_cobranca: string;
  ultima_cobranca: string | null;
  mp_preapproval_id: string;
  ciclo: CicloAssinatura;
  gateway: GatewayAssinatura;
  plano: PlanoAssinatura;
}

interface ApiResponse {
  success: boolean;
  assinaturas?: Assinatura[];
  error?: string;
}

interface ErrorModalData {
  title: string;
  message: string;
  type: "error" | "success" | "warning";
}

export default function MinhasAssinaturasScreen() {
  const router = useRouter();

  const [assinaturas, setAssinaturas] = useState<Assinatura[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [apiUrl, setApiUrl] = useState("");
  const [cancelando, setCancelando] = useState<number | null>(null);
  const [errorModalVisible, setErrorModalVisible] = useState(false);
  const [errorModalData, setErrorModalData] = useState<ErrorModalData>({
    title: "",
    message: "",
    type: "error",
  });

  const showErrorModal = (
    title: string,
    message: string,
    type: "error" | "success" | "warning" = "error",
  ) => {
    setErrorModalData({ title, message, type });
    setErrorModalVisible(true);
  };

  const fetchAssinaturasCallback = useCallback(
    async (baseUrl: string) => {
      try {
        setLoading(true);
        setError(null);

        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          console.warn("❌ Token não encontrado no AsyncStorage");
          throw new Error("Token não encontrado");
        }

        const url = `${baseUrl}/mobile/assinaturas`;

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

        if (data.success && data.assinaturas) {
          console.log("✅ Assinaturas carregadas:", data.assinaturas.length);
          setAssinaturas(data.assinaturas);
        } else {
          console.warn("⚠️ Resposta sem assinaturas:", data);
          setAssinaturas([]);
        }
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao carregar assinaturas";
        console.error("❌ Erro completo:", err);
        console.error("📝 Mensagem:", errorMsg);
        setError(errorMsg);
        showErrorModal("❌ Erro ao Carregar", errorMsg, "error");
      } finally {
        setLoading(false);
      }
    },
    [router],
  );

  useFocusEffect(
    useCallback(() => {
      const initializeAndFetch = async () => {
        try {
          console.log("🚀 Inicializando página de assinaturas...");
          const url = getApiUrlRuntime();
          console.log("🌐 URL da API:", url);
          setApiUrl(url);
          await fetchAssinaturasCallback(url);
        } catch (err) {
          console.error("❌ Erro na inicialização:", err);
          setError("Erro ao inicializar página");
        }
      };

      initializeAndFetch();
      return () => {};
    }, [fetchAssinaturasCallback]),
  );

  const confirmarCancelamento = useCallback(
    async (assinatura: Assinatura) => {
      try {
        setCancelando(assinatura.id);

        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          throw new Error("Token não encontrado");
        }

        const url = `${apiUrl}/mobile/assinatura/${assinatura.id}/cancelar`;

        console.log("🗑️ Cancelando assinatura:", assinatura.id);
        console.log("📍 URL:", url);

        const response = await fetch(url, {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            motivo: "Cancelado pelo usuário via app",
          }),
        });

        console.log("📡 Status da resposta:", response.status);

        if (!response.ok) {
          const responseText = await response.text();
          console.error("❌ Erro na resposta:", responseText);
          throw new Error(`HTTP ${response.status}: ${responseText}`);
        }

        const data = await response.json();

        console.log("✅ Resposta:", data);

        if (data.success) {
          showErrorModal(
            "✅ Cancelada com Sucesso",
            `Sua assinatura de ${assinatura.plano.nome} foi cancelada. Você poderá usar o serviço até o fim do período.`,
            "success",
          );

          // Recarregar assinaturas após alguns segundos
          setTimeout(() => {
            fetchAssinaturasCallback(apiUrl);
          }, 1500);
        } else {
          throw new Error(data.error || "Erro ao cancelar assinatura");
        }
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao cancelar assinatura";
        console.error("❌ Erro:", errorMsg);
        showErrorModal("❌ Erro ao Cancelar", errorMsg, "error");
      } finally {
        setCancelando(null);
      }
    },
    [apiUrl, fetchAssinaturasCallback],
  );

  const handleCancelarAssinatura = useCallback(
    (assinatura: Assinatura) => {
      Alert.alert(
        "❓ Cancelar Assinatura",
        `Deseja cancelar a assinatura de ${assinatura.plano.nome}?\n\nA cobrança automática será interrompida, mas você poderá usar o plano até o fim do período atual (${new Date(assinatura.proxima_cobranca).toLocaleDateString("pt-BR")}).`,
        [
          {
            text: "Não, Manter",
            onPress: () => console.log("Cancelamento abortado"),
            style: "cancel",
          },
          {
            text: "Sim, Cancelar",
            onPress: () => confirmarCancelamento(assinatura),
            style: "destructive",
          },
        ],
      );
    },
    [confirmarCancelamento],
  );

  const renderAssinatura = ({ item }: { item: Assinatura }) => {
    const dataInicio = new Date(item.data_inicio);
    const proximaCobranca = new Date(item.proxima_cobranca);
    const ultimaCobranca = item.ultima_cobranca
      ? new Date(item.ultima_cobranca)
      : null;

    const isAtiva = item.status.codigo === "ativa";
    const isCancelada = item.status.codigo === "cancelada";

    // Formatar valor
    const valorFormatado = new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(item.valor);

    return (
      <View style={styles.card}>
        <View style={styles.cardHero}>
          {/* Header com nome do plano e status */}
          <View style={styles.cardHeader}>
            <View style={styles.planoInfo}>
              <Text style={styles.planoNome}>{item.plano.nome}</Text>
              <Text style={styles.modalidadeNome}>{item.plano.modalidade}</Text>
            </View>
            <View
              style={[styles.statusBadge, { backgroundColor: item.status.cor }]}
            >
              <Text style={styles.statusText}>{item.status.nome}</Text>
            </View>
          </View>

          <View style={styles.heroDivider} />

          {/* Ciclo e Valor */}
          <View style={styles.cicloValor}>
            <View>
              <Text style={styles.cicloLabel}>Período</Text>
              <Text style={styles.cicloValor_text}>
                {item.ciclo.nome} ({item.ciclo.meses}{" "}
                {item.ciclo.meses === 1 ? "mês" : "meses"})
              </Text>
            </View>
            <View style={styles.divider} />
            <View>
              <Text style={styles.valorLabel}>Valor</Text>
              <Text style={styles.valorText}>{valorFormatado}</Text>
            </View>
          </View>
        </View>

        {/* Datas */}
        <View style={styles.datasContainer}>
          <View style={styles.dataItem}>
            <Feather name="calendar" size={16} color={colors.primary} />
            <View style={styles.dataContent}>
              <Text style={styles.dataLabel}>Início</Text>
              <Text style={styles.dataValor}>
                {dataInicio.toLocaleDateString("pt-BR")}
              </Text>
            </View>
          </View>

          {!isCancelada && (
            <View style={styles.dataItem}>
              <Feather name="clock" size={16} color={colors.primary} />
              <View style={styles.dataContent}>
                <Text style={styles.dataLabel}>Próxima Cobrança</Text>
                <Text style={styles.dataValor}>
                  {proximaCobranca.toLocaleDateString("pt-BR")}
                </Text>
              </View>
            </View>
          )}

          {ultimaCobranca && (
            <View style={styles.dataItem}>
              <Feather name="check-circle" size={16} color={colors.primary} />
              <View style={styles.dataContent}>
                <Text style={styles.dataLabel}>Última Cobrança</Text>
                <Text style={styles.dataValor}>
                  {ultimaCobranca.toLocaleDateString("pt-BR")}
                </Text>
              </View>
            </View>
          )}
        </View>

        {/* Botão Cancelar */}
        {isAtiva && (
          <TouchableOpacity
            style={styles.botaoCancelar}
            onPress={() => handleCancelarAssinatura(item)}
            disabled={cancelando === item.id}
          >
            {cancelando === item.id ? (
              <ActivityIndicator color="#DC3545" size="small" />
            ) : (
              <>
                <Feather name="trash-2" size={16} color="#DC3545" />
                <Text style={styles.botaoCancelarTexto}>Cancelar</Text>
              </>
            )}
          </TouchableOpacity>
        )}
      </View>
    );
  };

  const renderEmptyState = () => (
    <View style={styles.emptyContainer}>
      <Feather name="inbox" size={48} color={colors.primary} />
      <Text style={styles.emptyTitle}>Nenhuma Assinatura</Text>
      <Text style={styles.emptyMessage}>
        Você não possui assinaturas ativas no momento.
      </Text>
      <TouchableOpacity
        style={styles.botaoVerPlanos}
        onPress={() => router.push("/planos")}
      >
        <Feather name="shopping-cart" size={18} color="#fff" />
        <Text style={styles.botaoVerPlanosText}>Ver Planos Disponíveis</Text>
      </TouchableOpacity>
    </View>
  );

  const renderErrorState = () => (
    <View style={styles.errorContainer}>
      <Feather name="alert-circle" size={48} color={colors.primary} />
      <Text style={styles.errorTitle}>Erro ao Carregar</Text>
      <Text style={styles.errorMessage}>{error}</Text>
      <TouchableOpacity
        style={styles.botaoTentarNovamente}
        onPress={() => fetchAssinaturasCallback(apiUrl)}
      >
        <Feather name="refresh-cw" size={18} color="#fff" />
        <Text style={styles.botaoTentarNovamenteText}>Tentar Novamente</Text>
      </TouchableOpacity>
    </View>
  );

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.headerTop}>
        <TouchableOpacity
          style={styles.headerBackButton}
          onPress={() => router.replace("/(tabs)/checkin")}
        >
          <Feather name="arrow-left" size={24} color="#fff" />
        </TouchableOpacity>
        <Text style={styles.headerTitleCentered}>Minhas Assinaturas</Text>
        <View style={{ flexDirection: "row", gap: 8 }}>
          <TouchableOpacity
            style={styles.headerBackButton}
            onPress={() => fetchAssinaturasCallback(apiUrl)}
          >
            <Feather name="refresh-cw" size={20} color="#fff" />
          </TouchableOpacity>
        </View>
      </View>

      {loading ? (
        <View style={styles.centerContent}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Carregando assinaturas...</Text>
        </View>
      ) : error ? (
        renderErrorState()
      ) : assinaturas.length === 0 ? (
        renderEmptyState()
      ) : (
        <FlatList
          data={assinaturas}
          keyExtractor={(item) => item.id.toString()}
          renderItem={renderAssinatura}
          contentContainerStyle={styles.listContent}
          scrollEnabled={true}
          showsVerticalScrollIndicator={false}
        />
      )}

      {/* Error Modal */}
      <Modal visible={errorModalVisible} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            {/* Icon */}
            <View
              style={[
                styles.modalIcon,
                {
                  backgroundColor:
                    errorModalData.type === "error"
                      ? "rgba(220, 53, 69, 0.1)"
                      : errorModalData.type === "success"
                        ? "rgba(40, 167, 69, 0.1)"
                        : "rgba(255, 193, 7, 0.1)",
                },
              ]}
            >
              <Feather
                name={
                  errorModalData.type === "error"
                    ? "x-circle"
                    : errorModalData.type === "success"
                      ? "check-circle"
                      : "alert-circle"
                }
                size={40}
                color={
                  errorModalData.type === "error"
                    ? "#DC3545"
                    : errorModalData.type === "success"
                      ? "#28A745"
                      : "#FFC107"
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
                {
                  backgroundColor:
                    errorModalData.type === "error"
                      ? "#DC3545"
                      : errorModalData.type === "success"
                        ? "#28A745"
                        : "#FFC107",
                },
              ]}
              onPress={() => setErrorModalVisible(false)}
            >
              <Text style={styles.modalButtonText}>OK</Text>
            </TouchableOpacity>
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

  /* Header */
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

  /* Loading */
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

  /* List */
  listContent: {
    paddingHorizontal: 16,
    paddingVertical: 16,
    paddingBottom: 32,
    gap: 16,
  },

  /* Card */
  card: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 18,
    borderWidth: 1,
    borderColor: "#eff2f6",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.04,
    shadowRadius: 12,
    elevation: 3,
  },
  cardHero: {
    backgroundColor: "#f8fafc",
    borderRadius: 14,
    padding: 14,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: "#eef2f7",
  },

  cardHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: 12,
  },

  planoInfo: {
    flex: 1,
    gap: 4,
  },

  planoNome: {
    fontSize: 18,
    fontWeight: "800",
    color: colors.text,
  },

  modalidadeNome: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: "500",
  },

  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
  },

  statusText: {
    color: "#fff",
    fontSize: 11,
    fontWeight: "700",
    textTransform: "uppercase",
  },

  heroDivider: {
    height: 1,
    backgroundColor: "#eef2f7",
    marginVertical: 12,
  },

  /* Ciclo e Valor */
  cicloValor: {
    flexDirection: "row",
    alignItems: "center",
  },

  cicloLabel: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
    fontWeight: "600",
  },

  cicloValor_text: {
    fontSize: 15,
    fontWeight: "600",
    color: "#111827",
  },

  divider: {
    flex: 1,
  },

  valorLabel: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
    textAlign: "right",
    fontWeight: "600",
  },

  valorText: {
    fontSize: 18,
    fontWeight: "700",
    color: colors.primary,
    textAlign: "right",
  },

  /* Datas */
  datasContainer: {
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 14,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: "#eef2f7",
  },

  dataItem: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 12,
  },

  dataContent: {
    marginLeft: 12,
    flex: 1,
  },

  dataLabel: {
    fontSize: 12,
    color: "#9ca3af",
    marginBottom: 3,
    fontWeight: "600",
  },

  dataValor: {
    fontSize: 14,
    fontWeight: "600",
    color: "#111827",
  },

  /* Botão Cancelar */
  botaoCancelar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1.5,
    borderColor: "#dc2626",
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    gap: 8,
  },

  botaoCancelarTexto: {
    color: "#dc2626",
    fontSize: 14,
    fontWeight: "600",
  },

  /* Empty State */
  emptyContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 24,
  },

  emptyTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: colors.text,
    marginTop: 18,
    marginBottom: 8,
  },

  emptyMessage: {
    fontSize: 14,
    color: colors.textMuted,
    textAlign: "center",
    marginBottom: 28,
    lineHeight: 22,
  },

  botaoVerPlanos: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: colors.primary,
    paddingVertical: 14,
    paddingHorizontal: 28,
    borderRadius: 12,
    gap: 8,
  },

  botaoVerPlanosText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "600",
  },

  /* Error State */
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
    marginTop: 18,
    marginBottom: 8,
  },

  errorMessage: {
    fontSize: 14,
    color: colors.textMuted,
    textAlign: "center",
    marginBottom: 28,
    lineHeight: 22,
  },

  botaoTentarNovamente: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: colors.primary,
    paddingVertical: 14,
    paddingHorizontal: 28,
    borderRadius: 12,
    gap: 8,
  },

  botaoTentarNovamenteText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "600",
  },

  /* Modal */
  modalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0, 0, 0, 0.5)",
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 20,
  },

  modalContent: {
    backgroundColor: "#fff",
    borderRadius: 20,
    padding: 28,
    alignItems: "center",
    minWidth: "80%",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.15,
    shadowRadius: 12,
    elevation: 5,
  },

  modalIcon: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 20,
  },

  modalTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: "#111827",
    marginBottom: 10,
    textAlign: "center",
  },

  modalMessage: {
    fontSize: 14,
    color: "#6b7280",
    textAlign: "center",
    marginBottom: 28,
    lineHeight: 20,
  },

  modalButton: {
    width: "100%",
    paddingVertical: 13,
    borderRadius: 12,
    alignItems: "center",
  },

  modalButtonText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "600",
  },
});
