import { getApiUrlRuntime } from "@/src/config/urls";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useFocusEffect } from "@react-navigation/native";
import { useRouter } from "expo-router";
import React, { useCallback, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Linking,
  Modal,
  Platform,
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
  matricula_id?: number | null;
  status: StatusAssinatura;
  valor: number;
  tipo_cobranca?: string | null;
  recorrente?: boolean;
  data_inicio: string;
  data_fim?: string | null;
  proxima_cobranca: string | null;
  ultima_cobranca: string | null;
  mp_preapproval_id: string | null;
  preference_id?: string | null;
  external_reference?: string | null;
  ciclo: CicloAssinatura;
  gateway: GatewayAssinatura;
  plano: PlanoAssinatura;
  payment_url?: string | null;
  pode_pagar?: boolean;
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
  const [cancelandoDiaria, setCancelandoDiaria] = useState<number | null>(null);
  const [confirmModalVisible, setConfirmModalVisible] = useState(false);
  const [assinaturaParaCancelar, setAssinaturaParaCancelar] =
    useState<Assinatura | null>(null);
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

  const formatDate = (value?: string | null) => {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? null
      : date.toLocaleDateString("pt-BR");
  };

  const handlePagarAssinatura = async (assinatura: Assinatura) => {
    if (!assinatura.payment_url) {
      showErrorModal(
        "⚠️ Pagamento indisponível",
        "Link de pagamento não encontrado.",
        "warning",
      );
      return;
    }

    const supported = await Linking.canOpenURL(assinatura.payment_url);
    if (!supported) {
      showErrorModal(
        "⚠️ Não foi possível abrir o link",
        "Tente novamente em instantes.",
        "error",
      );
      return;
    }

    await Linking.openURL(assinatura.payment_url);
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

        console.log("🗑️ Cancelando assinatura ID:", assinatura.id);

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

          // Tratamento específico para erros comuns
          if (response.status === 401) {
            await AsyncStorage.removeItem("@appcheckin:token");
            await AsyncStorage.removeItem("@appcheckin:user");
            router.replace("/(auth)/login");
            return;
          }

          if (response.status === 404) {
            throw new Error("Assinatura não encontrada");
          }

          if (response.status === 403) {
            throw new Error(
              "Você não tem permissão para cancelar esta assinatura",
            );
          }

          throw new Error(`Erro ${response.status}: ${responseText}`);
        }

        const data = await response.json();

        console.log("✅ Resposta do cancelamento:", data);

        if (data.success) {
          showErrorModal(
            "✅ Cancelada com Sucesso",
            `Sua assinatura de ${assinatura.plano.nome} foi cancelada.\n\nA cobrança automática foi interrompida e você poderá usar o serviço até ${new Date(assinatura.proxima_cobranca).toLocaleDateString("pt-BR")}.`,
            "success",
          );

          // Recarregar assinaturas após alguns segundos
          setTimeout(() => {
            fetchAssinaturasCallback(apiUrl);
          }, 1500);
        } else {
          throw new Error(
            data.error || data.message || "Erro ao cancelar assinatura",
          );
        }
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao cancelar assinatura";
        console.error("❌ Erro ao cancelar:", errorMsg);
        showErrorModal("❌ Erro ao Cancelar", errorMsg, "error");
      } finally {
        setCancelando(null);
      }
    },
    [apiUrl, fetchAssinaturasCallback, router],
  );

  const handleCancelarAssinatura = useCallback(
    (assinatura: Assinatura) => {
      const dataFimCancelamento =
        formatDate(assinatura.proxima_cobranca) || "o fim do período atual";
      if (Platform.OS === "web") {
        setAssinaturaParaCancelar(assinatura);
        setConfirmModalVisible(true);
      } else {
        import("react-native").then(({ Alert }) => {
          Alert.alert(
            "⚠️ Cancelar Assinatura",
            `Tem certeza que deseja cancelar a assinatura de ${assinatura.plano.nome}?\n\n` +
              `📍 O que acontecerá:\n` +
              `• A cobrança automática será interrompida\n` +
              `• Você poderá usar o plano até ${dataFimCancelamento}\n` +
              `• Após essa data, o acesso será encerrado\n\n` +
              `Esta ação não pode ser desfeita.`,
            [
              {
                text: "Não, Manter Assinatura",
                style: "cancel",
              },
              {
                text: "Sim, Cancelar",
                onPress: () => confirmarCancelamento(assinatura),
                style: "destructive",
              },
            ],
          );
        });
      }
    },
    [confirmarCancelamento],
  );

  const handleCancelarDiaria = useCallback(
    async (assinatura: Assinatura) => {
      if (!assinatura.matricula_id) {
        showErrorModal(
          "⚠️ Matrícula não encontrada",
          "Não foi possível identificar a matrícula para cancelar a diária.",
          "warning",
        );
        return;
      }

      try {
        setCancelandoDiaria(assinatura.matricula_id);
        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          throw new Error("Token não encontrado");
        }

        const url = `${apiUrl}/mobile/diaria/${assinatura.matricula_id}/cancelar`;
        const response = await fetch(url, {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
        });

        const text = await response.text();
        let json: any = {};
        try {
          json = text ? JSON.parse(text) : {};
        } catch {
          json = {};
        }

        if (!response.ok || json?.success === false) {
          const msg = json?.message || text || "Erro ao cancelar diária";
          showErrorModal("⚠️ Erro ao cancelar diária", msg, "warning");
          return;
        }

        showErrorModal(
          "✅ Diária cancelada",
          json?.message || "Compra da diária cancelada com sucesso.",
          "success",
        );

        setTimeout(() => {
          fetchAssinaturasCallback(apiUrl);
        }, 1200);
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao cancelar diária";
        showErrorModal("❌ Erro ao cancelar diária", errorMsg, "error");
      } finally {
        setCancelandoDiaria(null);
      }
    },
    [apiUrl, fetchAssinaturasCallback],
  );

  const renderAssinatura = ({ item }: { item: Assinatura }) => {
    const dataInicioText = formatDate(item.data_inicio) || "-";
    const isAtiva = item.status.codigo === "ativa";
    const isCancelada =
      item.status.codigo === "cancelada" || item.status.codigo === "cancelled";
    const isPendente = item.status.codigo === "pendente";
    const statusCodigo =
      typeof item.status.codigo === "string"
        ? item.status.codigo.toLowerCase()
        : "";
    const isPago =
      statusCodigo === "paga" ||
      statusCodigo === "pago" ||
      statusCodigo === "paid" ||
      statusCodigo === "approved";
    const isAvulso = item.tipo_cobranca === "avulso" || item.recorrente === false;
    const proximaCobrancaRaw = isAvulso ? item.data_fim : item.proxima_cobranca;
    const proximaCobrancaText = formatDate(proximaCobrancaRaw);
    const ultimaCobrancaText = formatDate(item.ultima_cobranca);
    const podePagar =
      isPendente && !!item.payment_url && item.pode_pagar !== false;
    const podeCancelarDiaria =
      isAvulso && isPago && !!item.matricula_id;

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
          {item.external_reference && (
            <View style={styles.externalRefBadge}>
              <Feather name="hash" size={12} color="#fff" />
              <Text style={styles.externalRefText}>
                {`Ref: ${item.external_reference}`}
              </Text>
            </View>
          )}

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
                {dataInicioText}
              </Text>
            </View>
          </View>

          {!isCancelada && proximaCobrancaText && (
            <View style={styles.dataItem}>
              <Feather name="clock" size={16} color={colors.primary} />
              <View style={styles.dataContent}>
                <Text style={styles.dataLabel}>Próxima Cobrança</Text>
                <Text style={styles.dataValor}>
                  {proximaCobrancaText}
                </Text>
              </View>
            </View>
          )}

          {ultimaCobrancaText && (
            <View style={styles.dataItem}>
              <Feather name="check-circle" size={16} color={colors.primary} />
              <View style={styles.dataContent}>
                <Text style={styles.dataLabel}>Última Cobrança</Text>
                <Text style={styles.dataValor}>
                  {ultimaCobrancaText}
                </Text>
              </View>
            </View>
          )}
        </View>

        {(podePagar || podeCancelarDiaria || (!isAvulso && (isAtiva || isPendente))) && (
          <View style={styles.actionStack}>
            {podePagar && (
              <TouchableOpacity
                style={styles.botaoPagar}
                onPress={() => handlePagarAssinatura(item)}
                activeOpacity={0.8}
              >
                <Feather name="credit-card" size={16} color="#fff" />
                <Text style={styles.botaoPagarTexto}>Pagar agora</Text>
              </TouchableOpacity>
            )}

            {podeCancelarDiaria && (
              <TouchableOpacity
                style={styles.botaoCancelarDiaria}
                onPress={() => handleCancelarDiaria(item)}
                disabled={cancelandoDiaria === item.matricula_id}
                activeOpacity={0.7}
              >
                {cancelandoDiaria === item.matricula_id ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <>
                    <Feather name="x-circle" size={16} color="#fff" />
                    <Text style={styles.botaoCancelarDiariaTexto}>
                      Cancelar diária
                    </Text>
                  </>
                )}
              </TouchableOpacity>
            )}

            {/* Botão Cancelar */}
            {!isAvulso && (isAtiva || isPendente) && (
              <TouchableOpacity
                style={styles.botaoCancelar}
                onPress={() => handleCancelarAssinatura(item)}
                disabled={cancelando === item.id}
                activeOpacity={0.7}
              >
                {cancelando === item.id ? (
                  <ActivityIndicator color="#DC3545" size="small" />
                ) : (
                  <>
                    <Feather name="trash-2" size={16} color="#DC3545" />
                    <Text style={styles.botaoCancelarTexto}>
                      Cancelar Assinatura
                    </Text>
                  </>
                )}
              </TouchableOpacity>
            )}
          </View>
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
      {typeof error === "string" &&
        error.toLowerCase().includes("tenant") && (
          <TouchableOpacity
            style={styles.botaoLogin}
            onPress={() => router.replace("/(auth)/login")}
          >
            <Feather name="log-in" size={18} color="#fff" />
            <Text style={styles.botaoLoginText}>Fazer Login</Text>
          </TouchableOpacity>
        )}
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

      {/* Confirm Cancel Modal */}
      <Modal visible={confirmModalVisible} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View
              style={[
                styles.modalIcon,
                { backgroundColor: "rgba(255, 193, 7, 0.1)" },
              ]}
            >
              <Feather name="alert-triangle" size={40} color="#FFC107" />
            </View>

            <Text style={styles.modalTitle}>Cancelar Assinatura</Text>

            {assinaturaParaCancelar && (
              <Text style={styles.modalMessage}>
                {`Tem certeza que deseja cancelar a assinatura de ${assinaturaParaCancelar.plano.nome}?\n\nA cobrança automática será interrompida e você poderá usar o plano até ${new Date(assinaturaParaCancelar.proxima_cobranca).toLocaleDateString("pt-BR")}.\n\nEsta ação não pode ser desfeita.`}
              </Text>
            )}

            <View style={styles.confirmButtons}>
              <TouchableOpacity
                style={styles.confirmButtonManter}
                onPress={() => {
                  setConfirmModalVisible(false);
                  setAssinaturaParaCancelar(null);
                }}
              >
                <Text style={styles.confirmButtonManterText}>Não, Manter</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.confirmButtonCancelar}
                onPress={() => {
                  setConfirmModalVisible(false);
                  if (assinaturaParaCancelar) {
                    confirmarCancelamento(assinaturaParaCancelar);
                  }
                  setAssinaturaParaCancelar(null);
                }}
              >
                <Text style={styles.confirmButtonCancelarText}>
                  Sim, Cancelar
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

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

  externalRefBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 10,
    backgroundColor: colors.primary,
    alignSelf: "flex-start",
    marginTop: 10,
  },
  externalRefText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "700",
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

  actionStack: {
    gap: 10,
  },

  /* Botão Pagar */
  botaoPagar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: colors.primary,
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    gap: 8,
  },
  botaoPagarTexto: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "700",
  },

  botaoCancelarDiaria: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#dc2626",
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    gap: 8,
  },
  botaoCancelarDiariaTexto: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "700",
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
  botaoLogin: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#334155",
    paddingVertical: 14,
    paddingHorizontal: 28,
    borderRadius: 12,
    gap: 8,
    marginTop: 12,
  },
  botaoLoginText: {
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

  /* Confirm Modal Buttons */
  confirmButtons: {
    flexDirection: "row",
    gap: 12,
    width: "100%",
  },

  confirmButtonManter: {
    flex: 1,
    paddingVertical: 13,
    borderRadius: 12,
    alignItems: "center",
    backgroundColor: "#f3f4f6",
    borderWidth: 1,
    borderColor: "#e5e7eb",
  },

  confirmButtonManterText: {
    color: "#374151",
    fontSize: 14,
    fontWeight: "600",
  },

  confirmButtonCancelar: {
    flex: 1,
    paddingVertical: 13,
    borderRadius: 12,
    alignItems: "center",
    backgroundColor: "#DC3545",
  },

  confirmButtonCancelarText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "600",
  },
});
