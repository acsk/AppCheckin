import { getApiUrlRuntime } from "@/src/config/urls";
import { authService } from "@/src/services/authService";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useFocusEffect } from "@react-navigation/native";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect, useState } from "react";
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

interface PacoteBeneficiario {
  aluno_id: number;
  nome: string;
  status?: string | null;
}

interface PacoteInfo {
  nome?: string | null;
  descricao?: string | null;
  valor_total?: number | null;
  qtd_beneficiarios?: number | null;
}

interface PacoteContrato {
  id?: number;
  contrato_id?: number;
  status?: string | null;
  status_codigo?: string | null;
  status_nome?: string | null;
  status_cor?: string | null;
  valor_total?: number | null;
  data_inicio?: string | null;
  data_fim?: string | null;
  payment_url?: string | null;
  pacote?: PacoteInfo | null;
  pacote_nome?: string | null;
  pacote_descricao?: string | null;
  beneficiarios?: PacoteBeneficiario[];
}

interface ApiResponse {
  success: boolean;
  assinaturas?: Assinatura[];
  pacotes?: PacoteContrato[];
  data?: {
    pacotes?: PacoteContrato[];
  };
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
  const [pacotes, setPacotes] = useState<PacoteContrato[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [apiUrl, setApiUrl] = useState("");
  const [isUserAdmin, setIsUserAdmin] = useState(false);
  const [cancelando, setCancelando] = useState<number | null>(null);
  const [cancelandoDiaria, setCancelandoDiaria] = useState<number | null>(null);
  const [pagandoPacoteId, setPagandoPacoteId] = useState<number | null>(null);
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

  const formatCurrency = (value?: number | null) => {
    const numeric =
      typeof value === "number" && !Number.isNaN(value) ? value : 0;
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(numeric);
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

        const pacotesData = data.pacotes ?? data.data?.pacotes ?? [];
        if (Array.isArray(pacotesData)) {
          setPacotes(pacotesData);
        } else {
          setPacotes([]);
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

  // Verificar se o usuário é admin
  useEffect(() => {
    const checkAdminStatus = async () => {
      try {
        // Verificar se existe token
        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          console.warn("❌ Token não encontrado - redirecionando para login");
          router.replace("/(auth)/login");
          setIsUserAdmin(false);
          return;
        }

        const user = await authService.getCurrentUser();
        if (!user) {
          console.warn("⚠️ Usuário não autenticado - redirecionando para login");
          await AsyncStorage.removeItem("@appcheckin:token");
          router.replace("/(auth)/login");
          setIsUserAdmin(false);
          return;
        }

        // Verificar se o usuário é admin (papel_id 3) ou super admin (papel_id 4)
        const isAdmin = user.papel_id === 3 || user.papel_id === 4;
        const hasAdminRole =
          Array.isArray(user.papeis) &&
          user.papeis.some((r: any) => r.id === 3 || r.id === 4);

        setIsUserAdmin(isAdmin || hasAdminRole);
      } catch (err) {
        console.warn("⚠️ Erro ao verificar status de admin:", err);
        setIsUserAdmin(false);
      }
    };

    checkAdminStatus();
  }, [router]);

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

  const handlePagarPacote = useCallback(
    async (pacote: PacoteContrato) => {
      try {
        const contratoId = pacote.contrato_id ?? pacote.id;
        if (!contratoId) {
          showErrorModal(
            "⚠️ Contrato inválido",
            "Não foi possível identificar o contrato do pacote.",
            "warning",
          );
          return;
        }

        setPagandoPacoteId(contratoId);

        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) throw new Error("Token não encontrado");

        const url = `${apiUrl}/mobile/pacotes/contratos/${contratoId}/pagar`;
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
          const msg =
            json?.message || json?.error || text || "Erro ao gerar pagamento";
          showErrorModal("⚠️ Erro ao gerar pagamento", msg, "warning");
          return;
        }

        const paymentUrl =
          json?.payment_url || json?.data?.payment_url || pacote.payment_url;
        if (!paymentUrl) {
          showErrorModal(
            "⚠️ Pagamento indisponível",
            "Não foi possível gerar o link de pagamento.",
            "warning",
          );
          return;
        }

        setPacotes((prev) =>
          prev.map((item) => {
            const itemId = item.contrato_id ?? item.id;
            if (itemId !== contratoId) return item;
            return {
              ...item,
              payment_url: paymentUrl,
            };
          }),
        );

        const supported = await Linking.canOpenURL(paymentUrl);
        if (!supported) {
          showErrorModal(
            "⚠️ Não foi possível abrir o link",
            "Tente novamente em instantes.",
            "error",
          );
          return;
        }

        await Linking.openURL(paymentUrl);
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao gerar pagamento";
        showErrorModal("❌ Erro", errorMsg, "error");
      } finally {
        setPagandoPacoteId(null);
      }
    },
    [apiUrl],
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
    const isAvulso =
      item.tipo_cobranca === "avulso" || item.recorrente === false;
    const proximaCobrancaRaw = isAvulso ? item.data_fim : item.proxima_cobranca;
    const proximaCobrancaText = formatDate(proximaCobrancaRaw);
    const ultimaCobrancaText = formatDate(item.ultima_cobranca);
    const podePagar =
      isPendente && !!item.payment_url && item.pode_pagar !== false;
    const podeCancelarDiaria = isAvulso && isPago && !!item.matricula_id;

    const valorFormatado = formatCurrency(item.valor);

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
              <Text style={styles.dataValor}>{dataInicioText}</Text>
            </View>
          </View>

          {!isCancelada && proximaCobrancaText && (
            <View style={styles.dataItem}>
              <Feather name="clock" size={16} color={colors.primary} />
              <View style={styles.dataContent}>
                <Text style={styles.dataLabel}>Próxima Cobrança</Text>
                <Text style={styles.dataValor}>{proximaCobrancaText}</Text>
              </View>
            </View>
          )}

          {ultimaCobrancaText && (
            <View style={styles.dataItem}>
              <Feather name="check-circle" size={16} color={colors.primary} />
              <View style={styles.dataContent}>
                <Text style={styles.dataLabel}>Última Cobrança</Text>
                <Text style={styles.dataValor}>{ultimaCobrancaText}</Text>
              </View>
            </View>
          )}
        </View>

        {(podePagar ||
          podeCancelarDiaria ||
          (!isAvulso && (isAtiva || isPendente))) && (
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

  const renderPacoteItem = (item: PacoteContrato) => {
    const contratoId = item.contrato_id ?? item.id;
    const statusCodigo =
      item.status_codigo || item.status || item.status_nome || "pendente";
    const statusLower = String(statusCodigo).toLowerCase();
    const isPendente =
      statusLower.includes("pendente") || statusLower === "pending";
    const statusText =
      item.status_nome || item.status || (isPendente ? "Pendente" : "Ativo");
    const statusColor = item.status_cor || (isPendente ? "#f59e0b" : "#22c55e");
    const pacoteNome = item.pacote_nome || item.pacote?.nome || "Pacote";
    const pacoteDescricao =
      item.pacote_descricao || item.pacote?.descricao || "";
    const total =
      typeof item.valor_total === "number"
        ? item.valor_total
        : item.pacote?.valor_total || 0;
    const qtd =
      item.pacote?.qtd_beneficiarios || item.beneficiarios?.length || 0;
    const rateio = qtd > 0 ? total / qtd : null;
    const dataInicio = formatDate(item.data_inicio);
    const dataFim = formatDate(item.data_fim);
    const buttonLabel = item.payment_url ? "Pagar pacote" : "Gerar pagamento";

    return (
      <View
        key={`pacote-${contratoId ?? Math.random().toString(36)}`}
        style={styles.pacoteCard}
      >
        <View style={styles.pacoteHeader}>
          <View style={styles.pacoteInfo}>
            <Text style={styles.pacoteNome}>{pacoteNome}</Text>
            {!!pacoteDescricao && (
              <Text style={styles.pacoteDescricao}>{pacoteDescricao}</Text>
            )}
          </View>
          <View
            style={[styles.pacoteStatusBadge, { backgroundColor: statusColor }]}
          >
            <Text style={styles.pacoteStatusText}>{statusText}</Text>
          </View>
        </View>

        <View style={styles.pacoteMetaRow}>
          <View style={styles.pacoteMetaItem}>
            <Text style={styles.pacoteMetaLabel}>Total</Text>
            <Text style={styles.pacoteMetaValue}>{formatCurrency(total)}</Text>
          </View>
          <View style={styles.pacoteMetaDivider} />
          <View style={styles.pacoteMetaItem}>
            <Text style={styles.pacoteMetaLabel}>Beneficiários</Text>
            <Text style={styles.pacoteMetaValue}>{qtd || "-"}</Text>
          </View>
          <View style={styles.pacoteMetaDivider} />
          <View style={styles.pacoteMetaItem}>
            <Text style={styles.pacoteMetaLabel}>Rateio</Text>
            <Text style={styles.pacoteMetaValue}>
              {rateio ? formatCurrency(rateio) : "-"}
            </Text>
          </View>
        </View>

        {(dataInicio || dataFim) && (
          <View style={styles.pacoteDatesRow}>
            {!!dataInicio && (
              <Text style={styles.pacoteDateText}>Início: {dataInicio}</Text>
            )}
            {!!dataFim && (
              <Text style={styles.pacoteDateText}>Fim: {dataFim}</Text>
            )}
          </View>
        )}

        {!!item.beneficiarios?.length && (
          <View style={styles.pacoteBeneficiarios}>
            <Text style={styles.pacoteBeneficiariosTitle}>Beneficiários</Text>
            <View style={styles.pacoteBeneficiariosList}>
              {item.beneficiarios.map((beneficiario) => (
                <View
                  key={`benef-${item.id}-${beneficiario.aluno_id}`}
                  style={styles.pacoteBeneficiarioChip}
                >
                  <Text style={styles.pacoteBeneficiarioText}>
                    {beneficiario.nome}
                  </Text>
                </View>
              ))}
            </View>
          </View>
        )}

        {isPendente && contratoId && (
          <TouchableOpacity
            style={styles.pacotePagarButton}
            onPress={() => handlePagarPacote(item)}
            disabled={pagandoPacoteId === contratoId}
            activeOpacity={0.8}
          >
            {pagandoPacoteId === contratoId ? (
              <ActivityIndicator size="small" color="#fff" />
            ) : (
              <>
                <Feather name="credit-card" size={16} color="#fff" />
                <Text style={styles.pacotePagarButtonText}>{buttonLabel}</Text>
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
      {isUserAdmin && (
        <TouchableOpacity
          style={styles.botaoVerPlanos}
          onPress={async () => {
            try {
              // Verificar se existe token antes de navegar
              const token = await AsyncStorage.getItem("@appcheckin:token");
              if (!token) {
                console.warn("⚠️ Token não encontrado - redirecionando para login");
                router.replace("/(auth)/login");
                return;
              }
              router.push("/planos");
            } catch (err) {
              console.error("❌ Erro ao navegar para planos:", err);
            }
          }}
        >
          <Feather name="shopping-cart" size={18} color="#fff" />
          <Text style={styles.botaoVerPlanosText}>Ver Planos Disponíveis</Text>
        </TouchableOpacity>
      )}
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
      {typeof error === "string" && error.toLowerCase().includes("tenant") && (
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
      ) : assinaturas.length === 0 && pacotes.length === 0 ? (
        renderEmptyState()
      ) : (
        <FlatList
          data={assinaturas}
          keyExtractor={(item) => item.id.toString()}
          renderItem={renderAssinatura}
          ListHeaderComponent={
            <View style={styles.sectionsWrapper}>
              {pacotes.length > 0 && (
                <View style={styles.pacotesSection}>
                  <View style={styles.sectionHeader}>
                    <View style={styles.sectionHeaderIcon}>
                      <Feather name="users" size={18} color={colors.primary} />
                    </View>
                    <Text style={styles.pacotesTitle}>Pacotes</Text>
                    <View style={styles.sectionHeaderLine} />
                  </View>
                  <Text style={styles.pacotesSubtitle}>
                    Pacotes vinculados ao seu pagamento
                  </Text>
                  <View style={styles.pacotesList}>
                    {pacotes.map(renderPacoteItem)}
                  </View>
                </View>
              )}
              <View style={styles.assinaturasSectionHeader}>
                <View style={styles.sectionHeader}>
                  <View style={styles.sectionHeaderIcon}>
                    <Feather
                      name="file-text"
                      size={18}
                      color={colors.primary}
                    />
                  </View>
                  <Text style={styles.assinaturasTitle}>Assinaturas</Text>
                  <View style={styles.sectionHeaderLine} />
                </View>
                <Text style={styles.assinaturasSubtitle}>
                  Suas assinaturas individuais
                </Text>
              </View>
            </View>
          }
          ListEmptyComponent={
            pacotes.length > 0 ? (
              <View style={styles.assinaturasEmptyNote}>
                <Feather name="info" size={16} color={colors.primary} />
                <Text style={styles.assinaturasEmptyNoteText}>
                  Você não possui assinaturas individuais no momento.
                </Text>
              </View>
            ) : null
          }
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
  sectionsWrapper: {
    gap: 16,
  },

  /* Pacotes */
  pacotesSection: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 14,
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.04,
    shadowRadius: 10,
    elevation: 2,
    gap: 10,
  },
  sectionHeader: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  sectionHeaderIcon: {
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: `${colors.primary}14`,
  },
  sectionHeaderLine: {
    flex: 1,
    height: 1,
    backgroundColor: "#e5e7eb",
  },
  pacotesTitle: {
    fontSize: 22,
    fontWeight: "800",
    color: colors.primary,
  },
  pacotesSubtitle: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
  },
  pacotesList: {
    gap: 12,
    marginBottom: 6,
  },
  assinaturasSectionHeader: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 14,
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.04,
    shadowRadius: 10,
    elevation: 2,
    gap: 4,
  },
  assinaturasTitle: {
    fontSize: 22,
    fontWeight: "800",
    color: colors.primary,
  },
  assinaturasSubtitle: {
    fontSize: 12,
    color: colors.textMuted,
  },
  pacoteCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: "#eff2f6",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.04,
    shadowRadius: 12,
    elevation: 2,
    gap: 12,
  },
  pacoteHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: 12,
  },
  pacoteInfo: {
    flex: 1,
    gap: 4,
  },
  pacoteNome: {
    fontSize: 16,
    fontWeight: "800",
    color: colors.text,
  },
  pacoteDescricao: {
    fontSize: 12,
    color: colors.textMuted,
  },
  pacoteStatusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
  },
  pacoteStatusText: {
    color: "#fff",
    fontSize: 11,
    fontWeight: "700",
    textTransform: "uppercase",
  },
  pacoteMetaRow: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: "#eef2f7",
  },
  pacoteMetaItem: {
    flex: 1,
    gap: 4,
  },
  pacoteMetaLabel: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "600",
  },
  pacoteMetaValue: {
    fontSize: 14,
    fontWeight: "700",
    color: colors.text,
  },
  pacoteMetaDivider: {
    width: 1,
    height: 36,
    backgroundColor: "#e5e7eb",
    marginHorizontal: 8,
  },
  pacoteDatesRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    flexWrap: "wrap",
    gap: 8,
  },
  pacoteDateText: {
    fontSize: 12,
    color: colors.textSecondary,
    fontWeight: "600",
  },
  pacoteBeneficiarios: {
    gap: 8,
  },
  pacoteBeneficiariosTitle: {
    fontSize: 12,
    fontWeight: "700",
    color: colors.text,
  },
  pacoteBeneficiariosList: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
  },
  pacoteBeneficiarioChip: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
    backgroundColor: "#eef2ff",
  },
  pacoteBeneficiarioText: {
    fontSize: 11,
    color: colors.primary,
    fontWeight: "700",
  },
  pacotePagarButton: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: colors.primary,
    borderRadius: 12,
    paddingVertical: 12,
    gap: 8,
  },
  pacotePagarButtonText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "700",
  },
  assinaturasEmptyNote: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    backgroundColor: "#eef2f7",
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  assinaturasEmptyNoteText: {
    fontSize: 12,
    color: colors.textSecondary,
    fontWeight: "600",
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
