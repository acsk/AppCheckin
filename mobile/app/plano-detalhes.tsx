import { getApiUrlRuntime } from "@/src/config/urls";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useLocalSearchParams, useRouter } from "expo-router";
import React, { useEffect, useRef, useState } from "react";
import {
    ActivityIndicator,
    Image,
    Linking,
    Modal,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    useWindowDimensions,
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
  status_codigo?: string | null;
  status?: {
    codigo?: string;
    nome?: string;
    cor?: string;
  };
  matricula_ativa?: {
    status?: string;
    status_codigo?: string;
    data_inicio?: string;
    data_vencimento?: string;
    valor?: number;
  };
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
  const [pixModalVisible, setPixModalVisible] = useState(false);
  const [pixData, setPixData] = useState<{
    matricula_id: number;
    valor: number;
    qr_code?: string | null;
    qr_code_base64?: string | null;
    ticket_url?: string | null;
    expires_at?: string | null;
  } | null>(null);
  const [pixLoading, setPixLoading] = useState(false);
  const [pixCopied, setPixCopied] = useState(false);
  const [pixChecking, setPixChecking] = useState(false);
  const [pixPollingActive, setPixPollingActive] = useState(false);
  const [pixStatusMessage, setPixStatusMessage] = useState<string | null>(null);
  const [diariaCanceling, setDiariaCanceling] = useState(false);
  const pixCheckRunning = useRef(false);
  const { width: screenWidth } = useWindowDimensions();

  const handleBack = () => {
    if ("canGoBack" in router && typeof router.canGoBack === "function") {
      if (router.canGoBack()) {
        router.back();
        return;
      }
    }

    router.replace("/planos");
  };

  const showErrorModal = (
    title: string,
    message: string,
    type: "error" | "success" | "warning" = "error",
  ) => {
    setErrorModalData({ title, message, type });
    setErrorModalVisible(true);
  };

  const formatCurrency = (value: number) => {
    try {
      return new Intl.NumberFormat("pt-BR", {
        style: "currency",
        currency: "BRL",
      }).format(value || 0);
    } catch {
      return `R$ ${Number(value || 0).toFixed(2)}`;
    }
  };

  const formatDateTime = (value?: string | null) => {
    if (!value) return "";
    const d = new Date(value);
    if (isNaN(d.getTime())) return value;
    return d.toLocaleString("pt-BR");
  };

  const copyToClipboard = async (value: string) => {
    try {
      if (
        typeof navigator !== "undefined" &&
        navigator.clipboard &&
        navigator.clipboard.writeText
      ) {
        await navigator.clipboard.writeText(value);
        return true;
      }
      if (typeof document !== "undefined") {
        const input = document.createElement("textarea");
        input.value = value;
        input.setAttribute("readonly", "true");
        input.style.position = "absolute";
        input.style.left = "-9999px";
        document.body.appendChild(input);
        input.select();
        const ok = document.execCommand("copy");
        document.body.removeChild(input);
        return ok;
      }
      return false;
    } catch {
      return false;
    }
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
        const payload = data.data;
        const planoData = payload.plano || payload;
        const statusCodigoRaw =
          planoData?.status_codigo ??
          planoData?.status?.codigo ??
          planoData?.matricula?.status_codigo ??
          planoData?.matricula?.status?.codigo ??
          planoData?.matricula_ativa?.status_codigo ??
          payload?.status_codigo ??
          payload?.status?.codigo ??
          payload?.matricula?.status_codigo ??
          payload?.matricula?.status?.codigo ??
          payload?.matricula_ativa?.status_codigo ??
          payload?.assinatura?.status_codigo ??
          payload?.assinatura?.status?.codigo;
        const statusCodigo =
          typeof statusCodigoRaw === "string"
            ? statusCodigoRaw.toLowerCase()
            : null;
        const statusObj =
          planoData?.status ||
          planoData?.matricula?.status ||
          payload?.status ||
          payload?.matricula?.status ||
          payload?.assinatura?.status ||
          null;

        const matriculaAtiva =
          planoData?.matricula_ativa ?? payload?.matricula_ativa ?? null;

        const mergedPlano = {
          ...planoData,
          status_codigo: statusCodigo || planoData?.status_codigo,
          status: statusObj || planoData?.status,
          matricula_ativa: matriculaAtiva || planoData?.matricula_ativa,
        };

        setPlano(mergedPlano);

        // Selecionar primeiro ciclo por padrão
        const ciclos = (mergedPlano.ciclos || []).sort(
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

  useEffect(() => {
    if (!pixPollingActive) return;
    const interval = setInterval(() => {
      checkPixAprovacao(false);
    }, 3000);
    return () => clearInterval(interval);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pixPollingActive, pixData?.matricula_id, apiUrl]);

  const checkPixAprovacao = async (manual: boolean) => {
    if (!pixData?.matricula_id || !apiUrl) return;
    if (pixCheckRunning.current) return;

    pixCheckRunning.current = true;
    if (manual) {
      setPixChecking(true);
    }

    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Token não encontrado");

      const url = `${apiUrl}/mobile/assinaturas/aprovadas-hoje?matricula_id=${pixData.matricula_id}`;
      const response = await fetch(url, {
        method: "GET",
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

      if (!response.ok) {
        if (manual) {
          showErrorModal(
            "⚠️ Erro ao consultar pagamento",
            json?.message || text || "Não foi possível verificar o pagamento.",
            "warning",
          );
        }
        return;
      }

      const statusGateway =
        typeof json?.data?.status_gateway === "string"
          ? json.data.status_gateway.toLowerCase()
          : null;
      const statusCodigo =
        typeof json?.data?.status_codigo === "string"
          ? json.data.status_codigo.toLowerCase()
          : null;
      const aprovado =
        json?.approved === true ||
        statusGateway === "approved" ||
        statusCodigo === "paga";

      if (aprovado) {
        setPixPollingActive(false);
        setPixModalVisible(false);
        setPixStatusMessage(null);
        router.replace("/minhas-assinaturas");
        return;
      }

      if (manual) {
        const pendingMessage =
          statusGateway === "pending"
            ? "Pagamento ainda pendente. Vamos continuar verificando a cada 3 segundos."
            : "Pagamento ainda não confirmado. Vamos continuar verificando a cada 3 segundos.";
        setPixStatusMessage(pendingMessage);
        setPixPollingActive(true);
      }
    } catch (err) {
      if (manual) {
        const msg =
          err instanceof Error ? err.message : "Erro ao consultar pagamento";
        showErrorModal("⚠️ Erro ao consultar pagamento", msg, "warning");
      }
    } finally {
      pixCheckRunning.current = false;
      if (manual) {
        setPixChecking(false);
      }
    }
  };

  const handleCancelarDiaria = async () => {
    if (!pixData?.matricula_id || !apiUrl) return;
    if (diariaCanceling) return;

    try {
      setDiariaCanceling(true);
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Token não encontrado");

      const url = `${apiUrl}/mobile/diaria/${pixData.matricula_id}/cancelar`;
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
          json?.message || text || "Erro ao cancelar diária";
        showErrorModal("⚠️ Erro ao cancelar diária", msg, "warning");
        return;
      }

      setPixPollingActive(false);
      setPixStatusMessage(null);
      setPixModalVisible(false);
      showErrorModal(
        "✅ Diária cancelada",
        json?.message || "Compra da diária cancelada com sucesso.",
        "success",
      );
    } catch (err) {
      const msg =
        err instanceof Error ? err.message : "Erro ao cancelar diária";
      showErrorModal("⚠️ Erro ao cancelar diária", msg, "warning");
    } finally {
      setDiariaCanceling(false);
    }
  };

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

  const handlePagarPix = async () => {
    if (!plano || !selectedCicloId) return;
    const selectedCiclo = plano.ciclos?.find((c) => c.id === selectedCicloId);
    if (!selectedCiclo) {
      showErrorModal(
        "⚠️ Ciclo não selecionado",
        "Por favor, selecione um ciclo antes de pagar.",
        "warning",
      );
      return;
    }

    try {
      setPixLoading(true);
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Token não encontrado");

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
      if (!data.success) {
        showErrorModal(
          "❌ Não foi Possível Comprar",
          data.message || "Erro desconhecido ao processar compra",
          "error",
        );
        return;
      }

      const matriculaId = data.data?.matricula_id || data.data?.matricula?.id;
      if (!matriculaId) {
        showErrorModal(
          "⚠️ Erro",
          "Não foi possível identificar a matrícula para gerar o PIX.",
          "error",
        );
        return;
      }

      const pixResponse = await fetch(`${apiUrl}/mobile/pagamento/pix`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ matricula_id: matriculaId }),
      });

      const pixText = await pixResponse.text();
      let pixJson: any = {};
      try {
        pixJson = pixText ? JSON.parse(pixText) : {};
      } catch {
        pixJson = {};
      }

      if (!pixResponse.ok || !pixJson.success) {
        const msg =
          pixJson?.message ||
          pixJson?.error ||
          pixText ||
          "Não foi possível gerar o PIX.";
        showErrorModal("⚠️ Erro ao Gerar PIX", msg, "error");
        return;
      }

      const pix = pixJson.data?.pix || {};
      setPixData({
        matricula_id: Number(pixJson.data?.matricula_id || matriculaId),
        valor: Number(pixJson.data?.valor || selectedCiclo.valor || 0),
        qr_code: pix.qr_code || null,
        qr_code_base64: pix.qr_code_base64 || null,
        ticket_url: pix.ticket_url || null,
        expires_at: pix.expires_at || null,
      });
      setPixStatusMessage(null);
      setPixPollingActive(false);
      setPixModalVisible(true);
    } catch (err) {
      const errorMsg =
        err instanceof Error ? err.message : "Erro ao gerar PIX";
      showErrorModal("❌ Algo Deu Errado", errorMsg, "error");
    } finally {
      setPixLoading(false);
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={handleBack}
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
            onPress={handleBack}
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
          {typeof error === "string" &&
            error.toLowerCase().includes("tenant") && (
              <TouchableOpacity
                style={styles.loginButton}
                onPress={() => router.replace("/(auth)/login")}
              >
                <Feather name="log-in" size={18} color="#fff" />
                <Text style={styles.loginButtonText}>Fazer Login</Text>
              </TouchableOpacity>
            )}
        </View>
      </SafeAreaView>
    );
  }

  const ciclos = (plano.ciclos || []).sort((a, b) => a.meses - b.meses);
  const selectedCiclo = ciclos.find((c) => c.id === selectedCicloId);
  const statusCodigoRaw =
    plano.status_codigo ||
    (typeof plano.status === "string" ? plano.status : plano.status?.codigo) ||
    plano.matricula_ativa?.status_codigo ||
    (plano as { status_codigo_plano?: string }).status_codigo_plano ||
    (plano as { status_plano?: string }).status_plano ||
    (plano as { matricula_status_codigo?: string }).matricula_status_codigo ||
    (plano as { matricula?: { status_codigo?: string; status?: { codigo?: string } } })
      .matricula?.status_codigo ||
    (plano as { matricula?: { status?: { codigo?: string } } }).matricula?.status
      ?.codigo ||
    null;
  const statusNomeRaw =
    (typeof plano.status === "string" ? plano.status : plano.status?.nome) ||
    plano.matricula_ativa?.status ||
    (plano as { status_nome?: string }).status_nome ||
    plano.label ||
    "";
  const statusCodigo =
    typeof statusCodigoRaw === "string"
      ? statusCodigoRaw.toLowerCase()
      : null;
  const statusNome =
    typeof statusNomeRaw === "string" ? statusNomeRaw.toLowerCase() : "";
  const isPendente = statusCodigo === "pendente" || statusNome.includes("pendente");
  const isPlanoAtivo = !!plano.is_plano_atual && !isPendente;
  const qrSize = Math.min(320, Math.round(screenWidth * 0.72));

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={handleBack}
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
            {isPlanoAtivo && (
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

                    {ciclo.permite_recorrencia ? (
                      <View style={styles.cicloRecorrenciaBadge}>
                        <Feather
                          name="zap"
                          size={12}
                          color={styles.cicloRecorrenciaText.color}
                        />
                        <Text style={styles.cicloRecorrenciaText}>
                          {`Cobrança automática a cada ${ciclo.meses} ${
                            ciclo.meses === 1 ? "mês" : "meses"
                          }`}
                        </Text>
                      </View>
                    ) : (
                      <View style={styles.cicloAvulsaBadge}>
                        <Feather
                          name="dollar-sign"
                          size={12}
                          color={styles.cicloAvulsaText.color}
                        />
                        <Text style={styles.cicloAvulsaText}>
                          Pagamento único
                        </Text>
                      </View>
                    )}
                    <View style={styles.cicloRow}>
                      <Text
                        style={[
                          styles.cicloNome,
                          isSelected && styles.cicloNomeSelected,
                        ]}
                        numberOfLines={1}
                      >
                        {ciclo.nome}
                      </Text>
                      <Text
                        style={[
                          styles.cicloValor,
                          isSelected && styles.cicloValorSelected,
                        ]}
                        numberOfLines={1}
                      >
                        {ciclo.valor_formatado}
                      </Text>
                    </View>
                    <Text style={styles.cicloMeses} numberOfLines={1}>
                      {ciclo.meses} {ciclo.meses === 1 ? "mês" : "meses"}
                      {!!ciclo.valor_mensal_formatado && ciclo.meses > 1
                        ? ` • ${ciclo.valor_mensal_formatado}/mês`
                        : ""}
                    </Text>

                    {ciclo.economia && (
                      <View style={styles.economiaBadge}>
                        <Text style={styles.economiaText}>
                          {ciclo.economia}
                        </Text>
                      </View>
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
        {isPlanoAtivo ? (
          <View style={styles.footerButtonAtivo}>
            <Feather name="check" size={18} color="#fff" />
            <Text style={styles.footerButtonText}>Plano Ativo</Text>
          </View>
        ) : (
          <View style={styles.footerStack}>
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

            {!!selectedCiclo &&
              selectedCiclo.permite_recorrencia === false && (
                <TouchableOpacity
                  style={[
                    styles.footerPixButton,
                    pixLoading && styles.footerPixButtonLoading,
                  ]}
                  onPress={handlePagarPix}
                  disabled={pixLoading}
                  activeOpacity={0.8}
                >
                  {pixLoading ? (
                    <>
                      <ActivityIndicator color="#fff" size="small" />
                      <Text style={styles.footerButtonText}>
                        Gerando PIX...
                      </Text>
                    </>
                  ) : (
                    <>
                      <Feather name="zap" size={18} color="#fff" />
                      <Text style={styles.footerButtonText}>
                        Pagar com PIX
                      </Text>
                    </>
                  )}
                </TouchableOpacity>
              )}
          </View>
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

      {/* PIX Modal */}
      <Modal visible={pixModalVisible} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Pagamento via PIX</Text>
            <Text style={styles.modalMessage}>
              Escaneie o QR Code ou copie o código abaixo.
            </Text>

            <View style={styles.pixInfoRow}>
              <View style={styles.pixInfoCard}>
                <Text style={styles.pixInfoLabel}>Valor</Text>
                <Text style={styles.pixInfoValue}>
                  {formatCurrency(pixData?.valor || 0)}
                </Text>
              </View>
              <View style={styles.pixInfoCard}>
                <Text style={styles.pixInfoLabel}>Validade</Text>
                <Text style={styles.pixInfoValue}>
                  {formatDateTime(pixData?.expires_at)}
                </Text>
              </View>
            </View>

            {pixData?.qr_code_base64 ? (
              <View style={styles.pixQrBox}>
                <Image
                  source={{
                    uri: `data:image/png;base64,${pixData.qr_code_base64}`,
                  }}
                  style={[styles.pixQrImage, { width: qrSize, height: qrSize }]}
                />
              </View>
            ) : null}

            {!!pixData?.qr_code && (
              <>
                <Text style={styles.pixCodeLabel}>Código de pagamento</Text>
                <View style={styles.pixCodeInput}>
                  <Text
                    style={styles.pixCodeText}
                    numberOfLines={1}
                    ellipsizeMode="tail"
                  >
                    {pixData.qr_code}
                  </Text>
                  <TouchableOpacity
                    style={[
                      styles.pixCopyIconButton,
                      pixCopied && styles.pixCopyIconButtonCopied,
                    ]}
                    onPress={async () => {
                      const ok = await copyToClipboard(pixData.qr_code || "");
                      if (ok) {
                        setPixCopied(true);
                        setTimeout(() => setPixCopied(false), 1800);
                      } else {
                        showErrorModal(
                          "⚠️ Não foi possível copiar",
                          "Copie manualmente o código PIX.",
                          "warning",
                        );
                      }
                    }}
                  >
                    <Feather name="copy" size={16} color="#fff" />
                  </TouchableOpacity>
                </View>
                {pixCopied && (
                  <Text style={styles.pixCopiedText}>Código copiado!</Text>
                )}
              </>
            )}

            {!!pixData?.ticket_url && (
              <TouchableOpacity
                style={styles.pixOpenButton}
                onPress={() => {
                  if (pixData?.ticket_url) {
                    Linking.openURL(pixData.ticket_url);
                  }
                }}
              >
                <Text style={styles.pixOpenButtonText}>Abrir no banco</Text>
              </TouchableOpacity>
            )}

            <TouchableOpacity
              style={[
                styles.pixCancelDiariaButton,
                diariaCanceling && styles.pixCancelDiariaButtonLoading,
              ]}
              onPress={handleCancelarDiaria}
              disabled={diariaCanceling}
            >
              {diariaCanceling ? (
                <>
                  <ActivityIndicator color="#fff" size="small" />
                  <Text style={styles.pixCancelDiariaButtonText}>
                    Cancelando diária...
                  </Text>
                </>
              ) : (
                <>
                  <Feather name="x-circle" size={16} color="#fff" />
                  <Text style={styles.pixCancelDiariaButtonText}>
                    Cancelar diária
                  </Text>
                </>
              )}
            </TouchableOpacity>

            <TouchableOpacity
              style={[
                styles.pixCheckButton,
                pixChecking && styles.pixCheckButtonLoading,
              ]}
              onPress={() => {
                checkPixAprovacao(true);
              }}
              disabled={pixChecking}
            >
              {pixChecking ? (
                <>
                  <ActivityIndicator color="#fff" size="small" />
                  <Text style={styles.pixCheckButtonText}>
                    Consultando pagamento...
                  </Text>
                </>
              ) : (
                <>
                  <Feather name="check-circle" size={16} color="#fff" />
                  <Text style={styles.pixCheckButtonText}>
                    Já pagou? Consultar pagamento
                  </Text>
                </>
              )}
            </TouchableOpacity>

            {!!pixStatusMessage && (
              <Text style={styles.pixStatusMessage}>{pixStatusMessage}</Text>
            )}

            <TouchableOpacity
              style={[styles.modalButton, { backgroundColor: "#6b7280" }]}
              onPress={() => {
                setPixModalVisible(false);
                setPixPollingActive(false);
                setPixStatusMessage(null);
                setPixChecking(false);
              }}
            >
              <Text style={styles.modalButtonText}>Fechar</Text>
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
  loginButton: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#334155",
    paddingVertical: 14,
    paddingHorizontal: 28,
    borderRadius: 12,
    gap: 8,
    marginTop: 12,
  },
  loginButtonText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "600",
  },

  /* Content */
  content: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 96,
  },

  /* Hero Card */
  heroCard: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 16,
    marginBottom: 12,
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
    gap: 6,
  },
  heroNome: {
    fontSize: 20,
    fontWeight: "800",
    color: colors.text,
  },
  modalidadeBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: `${colors.primary}15`,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 20,
    alignSelf: "flex-start",
  },
  modalidadeText: {
    fontSize: 11,
    fontWeight: "600",
    color: colors.primary,
  },
  ativoBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    backgroundColor: "#28A745",
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
  },
  ativoBadgeText: {
    fontSize: 10,
    fontWeight: "700",
    color: "#fff",
    textTransform: "uppercase",
  },
  priceSection: {
    flexDirection: "row",
    alignItems: "baseline",
    marginTop: 12,
    gap: 4,
    flexWrap: "wrap",
  },
  priceValue: {
    fontSize: 48,
    fontWeight: "800",
    color: colors.primary,
  },
  priceCiclo: {
    fontSize: 12,
    fontWeight: "500",
    color: colors.textMuted,
  },
  priceMonthly: {
    fontSize: 11,
    fontWeight: "500",
    color: colors.textSecondary,
  },

  /* Sections */
  section: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 8,
    elevation: 2,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: "700",
    color: colors.text,
    marginBottom: 10,
  },
  descricaoText: {
    fontSize: 13,
    color: colors.textSecondary,
    lineHeight: 20,
  },

  /* Features */
  featuresList: {
    gap: 0,
  },
  featureItem: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 8,
    gap: 10,
  },
  featureIconCircle: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: `${colors.primary}10`,
    justifyContent: "center",
    alignItems: "center",
  },
  featureContent: {
    flex: 1,
  },
  featureLabel: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "600",
    marginBottom: 1,
  },
  featureValue: {
    fontSize: 13,
    fontWeight: "600",
    color: colors.text,
  },
  featureDivider: {
    height: 1,
    backgroundColor: "#f3f4f6",
  },

  /* Ciclos */
  ciclosGrid: {
    gap: 8,
  },
  cicloCard: {
    borderWidth: 1.5,
    borderColor: "#e5e7eb",
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 12,
    backgroundColor: "#fafafa",
    position: "relative",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 6,
    elevation: 2,
  },
  cicloCardSelected: {
    borderWidth: 2.5,
    borderColor: colors.primary,
    backgroundColor: `${colors.primary}08`,
  },
  cicloCheck: {
    position: "absolute",
    top: 6,
    right: 6,
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: colors.primary,
    justifyContent: "center",
    alignItems: "center",
  },
  cicloRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 6,
    marginBottom: 6,
  },
  cicloNome: {
    flex: 1,
    fontSize: 13,
    fontWeight: "700",
    color: colors.text,
  },
  cicloNomeSelected: {
    color: colors.primary,
  },
  cicloMeses: {
    fontSize: 10,
    color: colors.textMuted,
  },
  cicloValor: {
    fontSize: 26,
    fontWeight: "800",
    color: colors.text,
  },
  cicloValorSelected: {
    color: colors.primary,
  },
  cicloRecorrenciaBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    backgroundColor: "#FFFBEB",
    borderWidth: 1,
    borderColor: "#F59E0B",
    alignSelf: "flex-start",
    marginBottom: 6,
  },
  cicloRecorrenciaText: {
    fontSize: 10,
    fontWeight: "700",
    color: "#B45309",
  },
  cicloAvulsaBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    backgroundColor: `${colors.primary}14`,
    borderWidth: 1,
    borderColor: colors.primary,
    alignSelf: "flex-start",
    marginBottom: 6,
  },
  cicloAvulsaText: {
    fontSize: 10,
    fontWeight: "700",
    color: colors.primaryDark,
  },
  economiaBadge: {
    backgroundColor: "#dcfce7",
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    alignSelf: "flex-start",
  },
  economiaText: {
    fontSize: 12,
    fontWeight: "700",
    color: "#15803d",
  },

  /* Footer */
  footer: {
    position: "absolute",
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: "#fff",
    paddingHorizontal: 16,
    paddingVertical: 10,
    paddingBottom: 22,
    borderTopWidth: 1,
    borderTopColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: -3 },
    shadowOpacity: 0.08,
    shadowRadius: 8,
    elevation: 10,
  },
  footerStack: {
    gap: 10,
  },
  footerButton: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: colors.primary,
    paddingVertical: 12,
    borderRadius: 12,
    gap: 10,
  },
  footerButtonDisabled: {
    backgroundColor: "#d1d5db",
  },
  footerPixButton: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#32bcad",
    paddingVertical: 12,
    borderRadius: 12,
    gap: 10,
  },
  footerPixButtonLoading: {
    opacity: 0.7,
  },
  footerButtonAtivo: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#28A745",
    paddingVertical: 12,
    borderRadius: 12,
    gap: 10,
  },
  footerButtonText: {
    color: "#fff",
    fontSize: 14,
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
    padding: 22,
    alignItems: "center",
    width: "100%",
    maxWidth: 520,
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
  pixInfoRow: {
    width: "100%",
    flexDirection: "row",
    gap: 10,
    marginTop: 8,
    marginBottom: 4,
  },
  pixInfoCard: {
    flex: 1,
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: "#e5e7eb",
  },
  pixInfoLabel: {
    fontSize: 11,
    color: "#6b7280",
    fontWeight: "700",
    textTransform: "uppercase",
  },
  pixInfoValue: {
    fontSize: 13,
    color: "#111827",
    fontWeight: "700",
    marginTop: 4,
  },
  pixQrBox: {
    marginTop: 12,
    marginBottom: 8,
    padding: 12,
    borderRadius: 12,
    backgroundColor: "#f8fafc",
  },
  pixQrImage: {
    width: 280,
    height: 280,
  },
  pixCodeLabel: {
    alignSelf: "flex-start",
    fontSize: 12,
    color: "#374151",
    fontWeight: "700",
    marginBottom: 6,
  },
  pixCodeInput: {
    width: "100%",
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#e5e7eb",
    backgroundColor: "#fff",
    paddingHorizontal: 12,
    paddingVertical: 10,
    gap: 8,
    overflow: "hidden",
  },
  pixCodeText: {
    flex: 1,
    fontSize: 12,
    color: "#4b5563",
  },
  pixCopyIconButton: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#32bcad",
  },
  pixCopyIconButtonCopied: {
    backgroundColor: "#16a34a",
  },
  pixCopiedText: {
    marginTop: 8,
    fontSize: 12,
    color: "#16a34a",
    fontWeight: "700",
  },
  pixOpenButton: {
    width: "100%",
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: "center",
    backgroundColor: colors.primary,
    marginBottom: 10,
  },
  pixOpenButtonText: {
    color: "#fff",
    fontSize: 15,
    fontWeight: "700",
  },
  pixCancelDiariaButton: {
    width: "100%",
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#dc2626",
    flexDirection: "row",
    gap: 8,
    marginBottom: 10,
  },
  pixCancelDiariaButtonLoading: {
    backgroundColor: "#dc2626",
    opacity: 0.85,
  },
  pixCancelDiariaButtonText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "700",
  },
  pixCheckButton: {
    width: "100%",
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#0f766e",
    flexDirection: "row",
    gap: 8,
    marginBottom: 10,
  },
  pixCheckButtonLoading: {
    backgroundColor: "#0f766e",
    opacity: 0.85,
  },
  pixCheckButtonText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "700",
  },
  pixStatusMessage: {
    fontSize: 12,
    color: colors.textMuted,
    textAlign: "center",
    marginBottom: 10,
  },
});
