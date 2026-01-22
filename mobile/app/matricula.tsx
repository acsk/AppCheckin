import { colors } from "@/src/theme/colors";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

export default function MatriculaScreen() {
  const router = useRouter();
  const { matriculaId } = useLocalSearchParams();
  const [matricula, setMatricula] = useState(null);
  const [pagamentos, setPagamentos] = useState([]);
  const [resumo, setResumo] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadMatricula();
  }, [matriculaId]);

  const loadMatricula = async () => {
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        Alert.alert("Erro", "Token não encontrado");
        return;
      }

      console.log("Buscando matrícula:", matriculaId);
      const baseUrl = getApiUrlRuntime();
      const response = await fetch(
        `${baseUrl}/mobile/matriculas/${matriculaId}`,
        {
          method: "GET",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
        },
      );

      const data = await response.json();
      console.log("Matrícula carregada:", JSON.stringify(data, null, 2));

      if (data.success && data.data) {
        setMatricula(data.data.matricula);
        setPagamentos(data.data.pagamentos || []);
        setResumo(data.data.resumo_financeiro);
      } else {
        throw new Error(data.error || "Erro ao carregar matrícula");
      }
    } catch (error) {
      console.error("Erro ao carregar matrícula:", error);
      Alert.alert("Erro", "Não foi possível carregar os detalhes da matrícula");
    } finally {
      setLoading(false);
    }
  };

  const formatarValor = (valor) => {
    return `R$ ${parseFloat(valor).toFixed(2).replace(".", ",")}`;
  };

  const formatarData = (data) => {
    if (!data) return "-";
    return new Date(data).toLocaleDateString("pt-BR");
  };

  const getStatusColor = (status) => {
    switch (status.toLowerCase()) {
      case "pago":
        return "#4CAF50";
      case "aguardando":
        return "#FFA726";
      case "vencido":
        return "#EF5350";
      case "ativa":
        return "#4CAF50";
      case "inativa":
        return "#9E9E9E";
      default:
        return colors.primary;
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      </SafeAreaView>
    );
  }

  if (!matricula) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.headerTop}>
          <TouchableOpacity onPress={() => router.back()}>
            <Feather name="arrow-left" size={24} color="#fff" />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Matrícula</Text>
          <View style={{ width: 24 }} />
        </View>
        <View style={styles.emptyContainer}>
          <Feather name="alert-circle" size={48} color="#ddd" />
          <Text style={styles.emptyText}>Matrícula não encontrada</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container} edges={["right", "bottom", "left"]}>
      <View style={styles.headerTop}>
        <TouchableOpacity onPress={() => router.back()}>
          <Feather name="arrow-left" size={24} color="#fff" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Detalhes da Matrícula</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Informações da Matrícula */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Matrícula</Text>
          <View style={styles.card}>
            <View style={styles.infoRow}>
              <Feather name="user" size={18} color={colors.primary} />
              <View style={styles.infoContent}>
                <Text style={styles.label}>Nome</Text>
                <Text style={styles.value}>{matricula.usuario}</Text>
              </View>
            </View>

            <View style={styles.divider} />

            <View style={styles.infoRow}>
              <Feather name="dumbbell" size={18} color={colors.primary} />
              <View style={styles.infoContent}>
                <Text style={styles.label}>Plano</Text>
                <Text style={styles.value}>{matricula.plano.nome}</Text>
                {matricula.plano.modalidade && (
                  <View
                    style={[
                      styles.modalidadeBadge,
                      {
                        backgroundColor: matricula.plano.modalidade.cor + "20",
                      },
                    ]}
                  >
                    <View
                      style={[
                        styles.modalidadeDot,
                        { backgroundColor: matricula.plano.modalidade.cor },
                      ]}
                    />
                    <Text
                      style={[
                        styles.modalidadeText,
                        { color: matricula.plano.modalidade.cor },
                      ]}
                    >
                      {matricula.plano.modalidade.nome}
                    </Text>
                  </View>
                )}
              </View>
            </View>

            <View style={styles.divider} />

            <View style={styles.infoRow}>
              <Feather name="calendar" size={18} color={colors.primary} />
              <View style={styles.infoContent}>
                <Text style={styles.label}>Período</Text>
                <Text style={styles.value}>
                  {formatarData(matricula.datas.inicio)} a{" "}
                  {formatarData(matricula.datas.vencimento)}
                </Text>
              </View>
            </View>

            <View style={styles.divider} />

            <View style={styles.infoRow}>
              <Feather name="activity" size={18} color={colors.primary} />
              <View style={styles.infoContent}>
                <Text style={styles.label}>Status</Text>
                <View
                  style={[
                    styles.statusBadge,
                    {
                      backgroundColor: getStatusColor(matricula.status) + "20",
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.statusText,
                      { color: getStatusColor(matricula.status) },
                    ]}
                  >
                    {matricula.status.charAt(0).toUpperCase() +
                      matricula.status.slice(1)}
                  </Text>
                </View>
              </View>
            </View>
          </View>
        </View>

        {/* Resumo Financeiro */}
        {resumo && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Resumo Financeiro</Text>
            <View style={styles.card}>
              <View style={styles.financialRow}>
                <View style={styles.financialItem}>
                  <Text style={styles.financialLabel}>Total Previsto</Text>
                  <Text style={styles.financialValue}>
                    {formatarValor(resumo.total_previsto)}
                  </Text>
                </View>
                <View style={styles.financialItem}>
                  <Text style={styles.financialLabel}>Total Pago</Text>
                  <Text style={[styles.financialValue, { color: "#4CAF50" }]}>
                    {formatarValor(resumo.total_pago)}
                  </Text>
                </View>
              </View>

              <View style={styles.divider} />

              <View style={styles.financialRow}>
                <View style={styles.financialItem}>
                  <Text style={styles.financialLabel}>Pendente</Text>
                  <Text style={[styles.financialValue, { color: "#FFA726" }]}>
                    {formatarValor(resumo.total_pendente)}
                  </Text>
                </View>
                <View style={styles.financialItem}>
                  <Text style={styles.financialLabel}>Progresso</Text>
                  <Text style={styles.financialValue}>
                    {resumo.pagamentos_realizados}/
                    {resumo.quantidade_pagamentos}
                  </Text>
                </View>
              </View>

              {resumo.quantidade_pagamentos > 0 && (
                <>
                  <View style={styles.divider} />
                  <View style={styles.progressBar}>
                    <View
                      style={[
                        styles.progressFill,
                        {
                          width: `${(resumo.pagamentos_realizados / resumo.quantidade_pagamentos) * 100}%`,
                        },
                      ]}
                    />
                  </View>
                </>
              )}
            </View>
          </View>
        )}

        {/* Histórico de Pagamentos */}
        {pagamentos.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Histórico de Pagamentos</Text>
            {pagamentos.map((pagamento) => (
              <View
                key={pagamento.id}
                style={[
                  styles.paymentCard,
                  pagamento.pendente && { borderLeftColor: "#FFA726" },
                ]}
              >
                <View style={styles.paymentHeader}>
                  <View style={styles.paymentInfo}>
                    <Text style={styles.paymentValue}>
                      {formatarValor(pagamento.valor)}
                    </Text>
                    <Text style={styles.paymentDate}>
                      Vencimento: {formatarData(pagamento.data_vencimento)}
                    </Text>
                  </View>
                  <View
                    style={[
                      styles.paymentStatusBadge,
                      {
                        backgroundColor:
                          getStatusColor(pagamento.status) + "20",
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.paymentStatusText,
                        { color: getStatusColor(pagamento.status) },
                      ]}
                    >
                      {pagamento.status}
                    </Text>
                  </View>
                </View>

                {pagamento.data_pagamento && (
                  <View style={styles.paymentDetails}>
                    <Feather
                      name="check-circle"
                      size={14}
                      color={getStatusColor(pagamento.status)}
                    />
                    <Text style={styles.paymentDetailText}>
                      Pago em {formatarData(pagamento.data_pagamento)}
                    </Text>
                    {pagamento.forma_pagamento && (
                      <Text style={styles.paymentMethod}>
                        via {pagamento.forma_pagamento}
                      </Text>
                    )}
                  </View>
                )}

                {pagamento.pendente && (
                  <View style={styles.paymentDetails}>
                    <Feather name="alert-circle" size={14} color="#FFA726" />
                    <Text
                      style={[styles.paymentDetailText, { color: "#FFA726" }]}
                    >
                      Aguardando pagamento
                    </Text>
                  </View>
                )}
              </View>
            ))}
          </View>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  headerTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 18,
    paddingVertical: 16,
    backgroundColor: colors.primary,
    borderBottomWidth: 0,
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
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 16,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  section: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#000",
    marginBottom: 12,
  },
  card: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 16,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  divider: {
    height: 1,
    backgroundColor: "#f0f0f0",
    marginVertical: 12,
  },
  infoRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 12,
  },
  infoContent: {
    flex: 1,
  },
  label: {
    fontSize: 12,
    color: "#999",
    marginBottom: 4,
  },
  value: {
    fontSize: 14,
    color: "#000",
    fontWeight: "600",
  },
  modalidadeBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 6,
    marginTop: 8,
    alignSelf: "flex-start",
  },
  modalidadeDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  modalidadeText: {
    fontSize: 12,
    fontWeight: "600",
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    alignSelf: "flex-start",
    marginTop: 8,
  },
  statusText: {
    fontSize: 12,
    fontWeight: "600",
  },
  financialRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    gap: 12,
  },
  financialItem: {
    flex: 1,
  },
  financialLabel: {
    fontSize: 12,
    color: "#999",
    marginBottom: 4,
  },
  financialValue: {
    fontSize: 18,
    fontWeight: "700",
    color: colors.primary,
  },
  progressBar: {
    height: 6,
    backgroundColor: "#f0f0f0",
    borderRadius: 3,
    overflow: "hidden",
    marginTop: 12,
  },
  progressFill: {
    height: "100%",
    backgroundColor: "#4CAF50",
  },
  paymentCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    borderLeftWidth: 4,
    borderLeftColor: "#4CAF50",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 4,
    elevation: 2,
  },
  paymentHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: 12,
  },
  paymentInfo: {
    flex: 1,
  },
  paymentValue: {
    fontSize: 16,
    fontWeight: "700",
    color: "#000",
    marginBottom: 4,
  },
  paymentDate: {
    fontSize: 12,
    color: "#999",
  },
  paymentStatusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 6,
  },
  paymentStatusText: {
    fontSize: 11,
    fontWeight: "600",
  },
  paymentDetails: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginTop: 10,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: "#f0f0f0",
  },
  paymentDetailText: {
    fontSize: 12,
    color: "#666",
  },
  paymentMethod: {
    fontSize: 12,
    color: "#999",
    fontStyle: "italic",
  },
  emptyContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  emptyText: {
    fontSize: 16,
    fontWeight: "600",
    color: "#999",
    marginTop: 12,
  },
});
