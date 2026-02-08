import { getApiUrlRuntime } from "@/src/config/urls";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useLocalSearchParams, useRouter } from "expo-router";
import React, { useEffect, useState } from "react";
import {
    ActivityIndicator,
    Linking,
    Modal,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";

interface Ciclo {
  id: number;
  nome: string;
  codigo: string;
  meses: number;
  valor: number;
  valor_formatado: string;
  valor_mensal: number;
  valor_mensal_formatado: string;
  desconto_percentual: number;
  permite_recorrencia: boolean;
  economia: string | null;
  economia_valor: string | null;
}

interface PlanoDetalhes {
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
  ciclos?: Ciclo[];
}

interface ErrorModalData {
  title: string;
  message: string;
  type: "error" | "success" | "warning";
}

export default function PlanoDetalhesScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();

  const [plano, setPlano] = useState<PlanoDetalhes | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [apiUrl, setApiUrl] = useState("");
  const [selectedCicloId, setSelectedCicloId] = useState<number | null>(null);
  const [comprando, setComprando] = useState(false);
  const [errorModalVisible, setErrorModalVisible] = useState(false);
  const [errorModalData, setErrorModalData] = useState<ErrorModalData>({
    title: "",
    message: "",
    type: "error",
  });
  const [redirectModalVisible, setRedirectModalVisible] = useState(false);
  const [countdown, setCountdown] = useState(3);
  const [paymentUrlToOpen, setPaymentUrlToOpen] = useState<string | null>(null);

  const showErrorModal = (
    title: string,
    message: string,
    type: "error" | "success" | "warning" = "error",
  ) => {
    setErrorModalData({ title, message, type });
    setErrorModalVisible(true);
  };

  const fetchPlanoDetalhes = async (baseUrl: string, planoId: string) => {
    try {
      setLoading(true);
      setError(null);

      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        throw new Error("Token não encontrado");
      }

      const url = `${baseUrl}/mobile/planos/${planoId}`;
      console.log("📍 Buscando detalhes do plano:", url);

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
          await AsyncStorage.removeItem("@appcheckin:token");
          await AsyncStorage.removeItem("@appcheckin:user");
          router.replace("/(auth)/login");
          return;
        }
        if (response.status === 404) {
          throw new Error("Plano não encontrado");
        }
        throw new Error(`HTTP ${response.status}: ${responseText}`);
      }

      const data = await response.json();
      console.log("✅ Detalhes do plano:", JSON.stringify(data, null, 2));

      if (data.success && data.data) {
        const planoData = data.data.plano || data.data;
        setPlano(planoData);

        // Selecionar primeiro ciclo por padrão
        const ciclos = (planoData.ciclos || []).sort(
          (a: Ciclo, b: Ciclo) => a.meses - b.meses,
        );
        if (ciclos.length > 0) {
          setSelectedCicloId(ciclos[0].id);
        }
      } else {
        throw new Error(data.error || "Falha ao carregar detalhes do plano");
      }
    } catch (err) {
      const errorMsg =
        err instanceof Error ? err.message : "Erro ao carregar plano";
      console.error("❌ Erro:", errorMsg);
      setError(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const init = async () => {
      const url = getApiUrlRuntime();
      setApiUrl(url);
      if (id) {
        await fetchPlanoDetalhes(url, id);
      }
    };
    init();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  // Countdown para modal de redirecionamento
  useEffect(() => {
    if (redirectModalVisible && countdown > 0) {
      const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
      return () => clearTimeout(timer);
    }
    if (redirectModalVisible && countdown === 0 && paymentUrlToOpen) {
      (async () => {
        const supported = await Linking.canOpenURL(paymentUrlToOpen);
        if (supported) {
          await Linking.openURL(paymentUrlToOpen);
        }
        setRedirectModalVisible(false);
        setCountdown(3);
        setPaymentUrlToOpen(null);
      })();
    }
  }, [redirectModalVisible, countdown, paymentUrlToOpen]);

  const handleContratar = async () => {
    if (!plano || !selectedCicloId) return;

    const selectedCiclo = plano.ciclos?.find((c) => c.id === selectedCicloId);
    if (!selectedCiclo) {
      showErrorModal(
        "⚠️ Ciclo não selecionado",
        "Por favor, selecione um ciclo antes de contratar.",
        "warning",
      );
      return;
    }

    try {
      setComprando(true);
      console.log(
        "🛒 Contratando plano:",
        plano.nome,
        "Ciclo:",
        selectedCiclo.nome,
      );

      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Token não encontrado");

      const userJson = await AsyncStorage.getItem("@appcheckin:user");
      if (!userJson) throw new Error("Dados do usuário não encontrados");

      const response = await fetch(`${apiUrl}/mobile/comprar-plano`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          plano_id: plano.id,
          plano_ciclo_id: selectedCiclo.id,
        }),
      });

      console.log("📡 Status:", response.status);

      if (!response.ok) {
        const errorText = await response.text();
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

      const data = await response.json();
      console.log("✅ Resposta:", data);

      if (!data.success) {
        showErrorModal(
          "❌ Não foi Possível Comprar",
          data.message || "Erro desconhecido ao processar compra",
          "error",
        );
        return;
      }

      let paymentUrl = data.data?.payment_url;
      const matriculaId = data.data?.matricula_id || data.data?.matricula?.id;

      if (!paymentUrl && data.data?.pagamento) {
        paymentUrl = data.data.pagamento.url;
      }

      if (!paymentUrl) {
        showErrorModal(
          "⚠️ Erro",
          "Link de pagamento não foi gerado. Tente novamente.",
          "error",
        );
        return;
      }

      if (matriculaId) {
        await AsyncStorage.setItem(
          "matricula_pendente_id",
          matriculaId.toString(),
        );
      }

      setPaymentUrlToOpen(paymentUrl);
      setCountdown(3);
      setRedirectModalVisible(true);
    } catch (err) {
      const errorMsg =
        err instanceof Error ? err.message : "Erro ao processar compra";
      showErrorModal("❌ Algo Deu Errado", errorMsg, "error");
    } finally {
      setComprando(false);
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
          >
            <Feather name="arrow-left" size={24} color="#fff" />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Detalhes do Plano</Text>
          <View style={{ width: 40 }} />
        </View>
        <View style={styles.centerContent}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Carregando detalhes...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (error || !plano) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
          >
            <Feather name="arrow-left" size={24} color="#fff" />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Detalhes do Plano</Text>
          <View style={{ width: 40 }} />
        </View>
        <View style={styles.errorContainer}>
          <Feather name="alert-circle" size={48} color="#dc2626" />
          <Text style={styles.errorTitle}>Erro ao carregar plano</Text>
          <Text style={styles.errorMessage}>
            {error || "Plano não encontrado"}
          </Text>
          <TouchableOpacity
            style={styles.retryButton}
            onPress={() => router.back()}
          >
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.retryButtonText}>Voltar aos Planos</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const ciclos = (plano.ciclos || []).sort((a, b) => a.meses - b.meses);
  const selectedCiclo = ciclos.find((c) => c.id === selectedCicloId);

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => router.back()}
        >
          <Feather name="arrow-left" size={24} color="#fff" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Detalhes do Plano</Text>
        <View style={{ width: 40 }} />
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
      >
        {/* Hero Card */}
        <View style={styles.heroCard}>
          <View style={styles.heroTop}>
            <View style={styles.heroInfo}>
              <Text style={styles.heroNome}>{plano.nome}</Text>
              <View style={styles.modalidadeBadge}>
                <Feather name="activity" size={12} color={colors.primary} />
                <Text style={styles.modalidadeText}>
                  {plano.modalidade.nome}
                </Text>
              </View>
            </View>
            {plano.is_plano_atual && (
              <View style={styles.ativoBadge}>
                <Feather name="check-circle" size={14} color="#fff" />
                <Text style={styles.ativoBadgeText}>
                  {plano.label || "Seu plano"}
                </Text>
              </View>
            )}
          </View>

          {selectedCiclo && (
            <View style={styles.priceSection}>
              <Text style={styles.priceValue}>
                {selectedCiclo.valor_formatado}
              </Text>
              <Text style={styles.priceCiclo}>/{selectedCiclo.nome}</Text>
              {!!selectedCiclo.valor_mensal_formatado &&
                selectedCiclo.meses > 1 && (
                  <Text style={styles.priceMonthly}>
                    ({selectedCiclo.valor_mensal_formatado}/mês)
                  </Text>
                )}
            </View>
          )}
        </View>

        {/* Descrição */}
        {!!plano.descricao && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Sobre o Plano</Text>
            <Text style={styles.descricaoText}>{plano.descricao}</Text>
          </View>
        )}

        {/* Detalhes / Features */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>O que está incluso</Text>
          <View style={styles.featuresList}>
            <View style={styles.featureItem}>
              <View style={styles.featureIconCircle}>
                <Feather name="check-circle" size={18} color={colors.primary} />
              </View>
              <View style={styles.featureContent}>
                <Text style={styles.featureLabel}>Check-ins por semana</Text>
                <Text style={styles.featureValue}>
                  {plano.checkins_semanais === 999
                    ? "Ilimitados"
                    : `${plano.checkins_semanais}x por semana`}
                </Text>
              </View>
            </View>

            <View style={styles.featureDivider} />

            <View style={styles.featureItem}>
              <View style={styles.featureIconCircle}>
                <Feather name="calendar" size={18} color={colors.primary} />
              </View>
              <View style={styles.featureContent}>
                <Text style={styles.featureLabel}>Duração do acesso</Text>
                <Text style={styles.featureValue}>
                  {plano.duracao_texto || `${plano.duracao_dias} dias`}
                </Text>
              </View>
            </View>

            <View style={styles.featureDivider} />

            <View style={styles.featureItem}>
              <View style={styles.featureIconCircle}>
                <Feather name="tag" size={18} color={colors.primary} />
              </View>
              <View style={styles.featureContent}>
                <Text style={styles.featureLabel}>Modalidade</Text>
                <Text style={styles.featureValue}>{plano.modalidade.nome}</Text>
              </View>
            </View>
          </View>
        </View>

        {/* Seleção de Ciclo */}
        {ciclos.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Escolha o ciclo de cobrança</Text>
            <View style={styles.ciclosGrid}>
              {ciclos.map((ciclo) => {
                const isSelected = selectedCicloId === ciclo.id;
                return (
                  <TouchableOpacity
                    key={ciclo.id}
                    style={[
                      styles.cicloCard,
                      isSelected && styles.cicloCardSelected,
                    ]}
                    onPress={() => setSelectedCicloId(ciclo.id)}
                    activeOpacity={0.7}
                  >
                    {isSelected && (
                      <View style={styles.cicloCheck}>
                        <Feather name="check" size={12} color="#fff" />
                      </View>
                    )}

                    <Text
                      style={[
                        styles.cicloNome,
                        isSelected && styles.cicloNomeSelected,
                      ]}
                    >
                      {ciclo.nome}
                    </Text>
                    <Text style={styles.cicloMeses}>
                      {ciclo.meses} {ciclo.meses === 1 ? "mês" : "meses"}
                    </Text>

                    <View style={styles.cicloPriceRow}>
                      <Text
                        style={[
                          styles.cicloValor,
                          isSelected && styles.cicloValorSelected,
                        ]}
                      >
                        {ciclo.valor_formatado}
                      </Text>
                    </View>

                    {!!ciclo.valor_mensal_formatado && ciclo.meses > 1 && (
                      <Text style={styles.cicloMensal}>
                        {ciclo.valor_mensal_formatado}/mês
                      </Text>
                    )}

                    {ciclo.economia && (
                      <View style={styles.economiaBadge}>
                        <Text style={styles.economiaText}>
                          {ciclo.economia}
                        </Text>
                      </View>
                    )}

                    {ciclo.economia_valor && (
                      <Text style={styles.economiaValorText}>
                        {ciclo.economia_valor}
                      </Text>
                    )}
                  </TouchableOpacity>
                );
              })}
            </View>
          </View>
        )}
      </ScrollView>

      {/* Footer fixo com botão */}
      <View style={styles.footer}>
        {plano.is_plano_atual ? (
          <View style={styles.footerButtonAtivo}>
            <Feather name="check" size={18} color="#fff" />
            <Text style={styles.footerButtonText}>Plano Ativo</Text>
          </View>
        ) : (
          <TouchableOpacity
            style={[
              styles.footerButton,
              (!selectedCiclo || comprando) && styles.footerButtonDisabled,
            ]}
            onPress={handleContratar}
            disabled={!selectedCiclo || comprando}
            activeOpacity={0.8}
          >
            {comprando ? (
              <>
                <ActivityIndicator color="#fff" size="small" />
                <Text style={styles.footerButtonText}>Processando...</Text>
              </>
            ) : (
              <>
                <Feather name="shopping-cart" size={18} color="#fff" />
                <Text style={styles.footerButtonText}>
                  {selectedCiclo
                    ? `Contratar por ${selectedCiclo.valor_formatado}`
                    : "Escolha um ciclo"}
                </Text>
              </>
            )}
          </TouchableOpacity>
        )}
      </View>

      {/* Error Modal */}
      <Modal visible={errorModalVisible} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
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
            <Text style={styles.modalTitle}>{errorModalData.title}</Text>
            <Text style={styles.modalMessage}>{errorModalData.message}</Text>
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

      {/* Redirect Modal */}
      <Modal visible={redirectModalVisible} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View
              style={[
                styles.modalIcon,
                { backgroundColor: "rgba(40, 167, 69, 0.1)" },
              ]}
            >
              <Feather name="external-link" size={40} color="#28A745" />
            </View>
            <Text style={styles.modalTitle}>Redirecionando para Pagamento</Text>
            <Text style={styles.modalMessage}>
              Você será redirecionado para o Mercado Pago em{" "}
              <Text style={{ fontWeight: "800", color: colors.primary }}>
                {countdown}
              </Text>{" "}
              {countdown === 1 ? "segundo" : "segundos"}...
            </Text>
            <TouchableOpacity
              style={[styles.modalButton, { backgroundColor: "#6b7280" }]}
              onPress={() => {
                setRedirectModalVisible(false);
                setCountdown(3);
                setPaymentUrlToOpen(null);
              }}
            >
              <Text style={styles.modalButtonText}>Cancelar</Text>
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
  header: {
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
  headerTitle: {
    fontSize: 22,
    fontWeight: "800",
    color: "#fff",
    flex: 1,
    textAlign: "center",
  },
  backButton: {
    padding: 8,
  },

  /* Loading */
  centerContent: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: colors.text,
    fontWeight: "500",
  },

  /* Error */
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
  retryButton: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: colors.primary,
    paddingVertical: 14,
    paddingHorizontal: 28,
    borderRadius: 12,
    gap: 8,
  },
  retryButtonText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "600",
  },

  /* Content */
  content: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 120,
  },

  /* Hero Card */
  heroCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 20,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.04,
    shadowRadius: 12,
    elevation: 3,
  },
  heroTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
  },
  heroInfo: {
    flex: 1,
    gap: 8,
  },
  heroNome: {
    fontSize: 24,
    fontWeight: "800",
    color: colors.text,
  },
  modalidadeBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: `${colors.primary}15`,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 20,
    alignSelf: "flex-start",
  },
  modalidadeText: {
    fontSize: 12,
    fontWeight: "600",
    color: colors.primary,
  },
  ativoBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    backgroundColor: "#28A745",
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
  },
  ativoBadgeText: {
    fontSize: 11,
    fontWeight: "700",
    color: "#fff",
    textTransform: "uppercase",
  },
  priceSection: {
    flexDirection: "row",
    alignItems: "baseline",
    marginTop: 16,
    gap: 4,
    flexWrap: "wrap",
  },
  priceValue: {
    fontSize: 28,
    fontWeight: "800",
    color: colors.primary,
  },
  priceCiclo: {
    fontSize: 14,
    fontWeight: "500",
    color: colors.textMuted,
  },
  priceMonthly: {
    fontSize: 13,
    fontWeight: "500",
    color: colors.textSecondary,
  },

  /* Sections */
  section: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 18,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 8,
    elevation: 2,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "700",
    color: colors.text,
    marginBottom: 14,
  },
  descricaoText: {
    fontSize: 14,
    color: colors.textSecondary,
    lineHeight: 22,
  },

  /* Features */
  featuresList: {
    gap: 0,
  },
  featureItem: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 12,
    gap: 14,
  },
  featureIconCircle: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: `${colors.primary}10`,
    justifyContent: "center",
    alignItems: "center",
  },
  featureContent: {
    flex: 1,
  },
  featureLabel: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: "600",
    marginBottom: 2,
  },
  featureValue: {
    fontSize: 15,
    fontWeight: "600",
    color: colors.text,
  },
  featureDivider: {
    height: 1,
    backgroundColor: "#f3f4f6",
  },

  /* Ciclos */
  ciclosGrid: {
    gap: 10,
  },
  cicloCard: {
    borderWidth: 1.5,
    borderColor: "#e5e7eb",
    borderRadius: 14,
    padding: 16,
    backgroundColor: "#fafafa",
    position: "relative",
  },
  cicloCardSelected: {
    borderColor: colors.primary,
    backgroundColor: `${colors.primary}08`,
  },
  cicloCheck: {
    position: "absolute",
    top: 10,
    right: 10,
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: colors.primary,
    justifyContent: "center",
    alignItems: "center",
  },
  cicloNome: {
    fontSize: 16,
    fontWeight: "700",
    color: colors.text,
    marginBottom: 2,
  },
  cicloNomeSelected: {
    color: colors.primary,
  },
  cicloMeses: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 8,
  },
  cicloPriceRow: {
    flexDirection: "row",
    alignItems: "baseline",
    gap: 6,
  },
  cicloValor: {
    fontSize: 20,
    fontWeight: "800",
    color: colors.text,
  },
  cicloValorSelected: {
    color: colors.primary,
  },
  cicloMensal: {
    fontSize: 12,
    fontWeight: "500",
    color: colors.textMuted,
    marginTop: 2,
  },
  economiaBadge: {
    backgroundColor: "#dcfce7",
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    alignSelf: "flex-start",
    marginTop: 8,
  },
  economiaText: {
    fontSize: 11,
    fontWeight: "700",
    color: "#15803d",
  },
  economiaValorText: {
    fontSize: 11,
    fontWeight: "500",
    color: "#15803d",
    marginTop: 4,
  },

  /* Footer */
  footer: {
    position: "absolute",
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: "#fff",
    paddingHorizontal: 16,
    paddingVertical: 14,
    paddingBottom: 30,
    borderTopWidth: 1,
    borderTopColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: -3 },
    shadowOpacity: 0.08,
    shadowRadius: 8,
    elevation: 10,
  },
  footerButton: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: colors.primary,
    paddingVertical: 16,
    borderRadius: 14,
    gap: 10,
  },
  footerButtonDisabled: {
    backgroundColor: "#d1d5db",
  },
  footerButtonAtivo: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#28A745",
    paddingVertical: 16,
    borderRadius: 14,
    gap: 10,
  },
  footerButtonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "700",
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
