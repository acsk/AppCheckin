import { useProtectedRoute } from "@/hooks/useProtectedRoute";
import { getApiUrlRuntime } from "@/src/config/urls";
import { authService } from "@/src/services/authService";
import { colors } from "@/src/theme/colors";
import { handleUnauthorizedResponse } from "@/src/utils/authHelpers";
import { isSessionExpiredVisible } from "@/src/utils/sessionExpired";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useFocusEffect } from "@react-navigation/native";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect, useMemo, useState } from "react";
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

interface PagamentoAssinatura {
  id: number;
  valor: number;
  data_vencimento?: string | null;
  data_pagamento?: string | null;
  status?: string | null;
  forma_pagamento?: string | null;
  baixado_por_nome?: string | null;
  criado_por_nome?: string | null;
  tipo_baixa_nome?: string | null;
  origem?: string | null;
}

interface Assinatura {
  id: number;
  matricula_id?: number | null;
  mercadopago_payment_ids?: (string | number)[] | null;
  mercadopago_last_payment_id?: string | number | null;
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
  pode_renovar?: boolean;
  motivo_pode_pagar?: string | null;
  pagamentos?: PagamentoAssinatura[] | null;
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

/** Um card da lista = um pagamento (ou a assinatura se ainda sem parcelas). */
type AssinaturaCardItem = {
  key: string;
  assinatura: Assinatura;
  pagamento: PagamentoAssinatura | null;
  showActions: boolean;
};

export default function MinhasAssinaturasScreen() {
  const router = useRouter();

  // Verificar autenticação - se não autenticado, redireciona
  useProtectedRoute();

  const [assinaturas, setAssinaturas] = useState<Assinatura[]>([]);
  const [pacotes, setPacotes] = useState<PacoteContrato[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [apiUrl, setApiUrl] = useState("");
  const [isUserAdmin, setIsUserAdmin] = useState(false);
  const [cancelando, setCancelando] = useState<number | null>(null);
  const [pagandoPacoteId, setPagandoPacoteId] = useState<number | null>(null);
  const [confirmModalVisible, setConfirmModalVisible] = useState(false);
  const [assinaturaParaCancelar, setAssinaturaParaCancelar] =
    useState<Assinatura | null>(null);
  const [reprocessModalVisible, setReprocessModalVisible] = useState(false);
  const [assinaturaParaReprocessar, setAssinaturaParaReprocessar] =
    useState<Assinatura | null>(null);
  const [reprocessando, setReprocessando] = useState<number | null>(null);
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
    // Preferir parse local de YYYY-MM-DD para evitar shift de timezone (UTC).
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
    if (match) {
      const year = Number(match[1]);
      const month = Number(match[2]);
      const day = Number(match[3]);
      return `${String(day).padStart(2, "0")}/${String(month).padStart(2, "0")}/${year}`;
    }
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
    // Renovação de matrícula ativa: gera PIX sob demanda
    if (
      assinatura.pode_pagar &&
      !assinatura.payment_url &&
      assinatura.matricula_id
    ) {
      try {
        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          showErrorModal(
            "⚠️ Sessão expirada",
            "Faça login novamente para pagar.",
            "error",
          );
          return;
        }
        const apiUrl = getApiUrlRuntime();
        const pixResponse = await fetch(`${apiUrl}/mobile/pagamento/pix`, {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ matricula_id: assinatura.matricula_id }),
        });
        const pixText = await pixResponse.text();
        let pixJson: any = {};
        try {
          pixJson = pixText ? JSON.parse(pixText) : {};
        } catch {
          pixJson = {};
        }
        if (!pixResponse.ok || !pixJson.success) {
          showErrorModal(
            "⚠️ Não foi possível gerar o pagamento",
            pixJson.error ||
              pixJson.message ||
              "Tente novamente em instantes.",
            "error",
          );
          return;
        }
        const ticketUrl =
          pixJson.data?.pix?.ticket_url || pixJson.data?.payment_url;
        if (ticketUrl) {
          const supported = await Linking.canOpenURL(ticketUrl);
          if (supported) {
            await Linking.openURL(ticketUrl);
            return;
          }
        }
        showErrorModal(
          "✅ PIX gerado",
          "Abra Minhas Assinaturas novamente ou use o QR Code no comprovante.",
          "success",
        );
        return;
      } catch (e) {
        showErrorModal(
          "⚠️ Erro ao gerar pagamento",
          "Tente novamente em instantes.",
          "error",
        );
        return;
      }
    }

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

          if (await handleUnauthorizedResponse(response)) {
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
          if (!isSessionExpiredVisible()) {
            console.warn("❌ Token não encontrado - redirecionando para login");
            router.replace("/(auth)/login");
          }
          setIsUserAdmin(false);
          return;
        }

        const user = await authService.getCurrentUser();
        if (!user) {
          if (!isSessionExpiredVisible()) {
            console.warn(
              "⚠️ Usuário não autenticado - redirecionando para login",
            );
            await AsyncStorage.removeItem("@appcheckin:token");
            router.replace("/(auth)/login");
          }
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
          if (await handleUnauthorizedResponse(response)) {
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
          const dataLimite =
            formatDate(assinatura.proxima_cobranca) || "o fim do período atual";
          showErrorModal(
            "✅ Cancelada com Sucesso",
            `Sua assinatura de ${assinatura.plano.nome} foi cancelada.\n\nA cobrança automática foi interrompida e você poderá usar o serviço até ${dataLimite}.`,
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

  const confirmarReprocessamento = useCallback(
    async (assinatura: Assinatura) => {
      const paymentIds = Array.isArray(assinatura.mercadopago_payment_ids)
        ? assinatura.mercadopago_payment_ids
        : [];
      const paymentId =
        assinatura.mercadopago_last_payment_id ?? paymentIds[0] ?? null;

      if (!paymentId) {
        showErrorModal(
          "⚠️ Payment ID não encontrado",
          "Não foi possível identificar um pagamento para reprocessar.",
          "warning",
        );
        return;
      }

      try {
        setReprocessando(assinatura.id);

        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          throw new Error("Token não encontrado");
        }

        const url = `${apiUrl}/api/webhooks/mercadopago/payment/${paymentId}/reprocess`;
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
          if (await handleUnauthorizedResponse(response)) {
            return;
          }

          const msg =
            json?.message ||
            json?.error ||
            text ||
            "Não foi possível reprocessar o pagamento.";
          showErrorModal("⚠️ Erro ao reprocessar", msg, "warning");
          return;
        }

        showErrorModal(
          "✅ Reprocessamento iniciado",
          json?.message ||
            "Solicitação de reprocessamento enviada com sucesso.",
          "success",
        );

        setTimeout(() => {
          fetchAssinaturasCallback(apiUrl);
        }, 1200);
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao reprocessar pagamento";
        showErrorModal("❌ Erro ao reprocessar", errorMsg, "error");
      } finally {
        setReprocessando(null);
      }
    },
    [apiUrl, fetchAssinaturasCallback, router],
  );

  const assinaturaCards = useMemo<AssinaturaCardItem[]>(() => {
    const cards: AssinaturaCardItem[] = [];
    for (const assinatura of assinaturas) {
      const pagamentos = Array.isArray(assinatura.pagamentos)
        ? assinatura.pagamentos
        : [];
      if (pagamentos.length === 0) {
        cards.push({
          key: `assinatura-${assinatura.id}`,
          assinatura,
          pagamento: null,
          showActions: true,
        });
        continue;
      }
      pagamentos.forEach((pagamento, index) => {
        cards.push({
          key: `pagamento-${assinatura.id}-${pagamento.id}`,
          assinatura,
          pagamento,
          showActions: index === 0,
        });
      });
    }
    return cards;
  }, [assinaturas]);

  const renderAssinaturaCard = ({ item }: { item: AssinaturaCardItem }) => {
    const { assinatura, pagamento, showActions } = item;
    const dataInicioText = formatDate(assinatura.data_inicio) || "-";
    const isAtiva = assinatura.status.codigo === "ativa";
    const isCancelada =
      assinatura.status.codigo === "cancelada" ||
      assinatura.status.codigo === "cancelled";
    const isPendente = assinatura.status.codigo === "pendente";
    const statusCodigo =
      typeof assinatura.status.codigo === "string"
        ? assinatura.status.codigo.toLowerCase()
        : "";
    const isAssinaturaPaga =
      statusCodigo === "paga" ||
      statusCodigo === "pago" ||
      statusCodigo === "paid" ||
      statusCodigo === "approved";
    const isAvulso =
      assinatura.tipo_cobranca === "avulso" || assinatura.recorrente === false;
    const proximaCobrancaText = formatDate(assinatura.proxima_cobranca);
    const fimAcessoText = formatDate(assinatura.data_fim);
    const ultimaCobrancaText = formatDate(assinatura.ultima_cobranca);
    const podePagar =
      !!assinatura.pode_pagar && (isPendente ? !!assinatura.payment_url : true);
    const paymentIds = Array.isArray(assinatura.mercadopago_payment_ids)
      ? assinatura.mercadopago_payment_ids
      : [];
    const podeReprocessar =
      paymentIds.length > 0 || !!assinatura.mercadopago_last_payment_id;

    const pagamentoPago = pagamento
      ? String(pagamento.status || "")
          .toLowerCase()
          .includes("pago") || !!pagamento.data_pagamento
      : isAssinaturaPaga;
    const isManual =
      !!pagamento &&
      (pagamento.origem === "manual" ||
        (!!pagamento.baixado_por_nome &&
          pagamento.origem !== "mercadopago"));
    const nomeBaixa = pagamento
      ? pagamento.baixado_por_nome || pagamento.criado_por_nome || ""
      : "";
    const primeiroNome = nomeBaixa.trim().split(/\s+/)[0] || "";
    const labelBaixa =
      pagamento && pagamentoPago
        ? isManual && primeiroNome
          ? `Baixa manual por ${primeiroNome}`
          : "Baixa Automática por Integração"
        : null;

    const statusBadgeLabel = pagamento?.status || assinatura.status.nome;
    const statusBadgeColor = pagamentoPago
      ? "#28A745"
      : pagamento
        ? "#FFA500"
        : assinatura.status.cor;
    const valorExibido = formatCurrency(
      pagamento ? pagamento.valor : assinatura.valor,
    );
    const dataPagamentoText = pagamento
      ? formatDate(pagamento.data_pagamento) ||
        formatDate(pagamento.data_vencimento) ||
        "—"
      : ultimaCobrancaText || "—";

    return (
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <View style={styles.planoInfo}>
            <View style={styles.planoTitleRow}>
              <Text style={styles.planoNome} numberOfLines={1}>
                {assinatura.plano.nome}
              </Text>
              <View
                style={[
                  styles.statusBadge,
                  { backgroundColor: statusBadgeColor },
                ]}
              >
                <Text style={styles.statusText}>{statusBadgeLabel}</Text>
              </View>
            </View>
            <Text style={styles.modalidadeNome} numberOfLines={1}>
              {assinatura.plano.modalidade}
              {assinatura.external_reference
                ? `  ·  ${assinatura.external_reference}`
                : ""}
            </Text>
          </View>
        </View>

        <View style={styles.cicloValor}>
          <Text style={styles.cicloValor_text} numberOfLines={1}>
            {assinatura.ciclo.nome}
            {assinatura.ciclo.meses > 0
              ? ` · ${assinatura.ciclo.meses} ${assinatura.ciclo.meses === 1 ? "mês" : "meses"}`
              : ""}
          </Text>
          <Text style={styles.valorText}>{valorExibido}</Text>
        </View>

        <View style={styles.datasContainer}>
          <View style={styles.dataItem}>
            <Text style={styles.dataLabel}>Início</Text>
            <Text style={styles.dataValor}>{dataInicioText}</Text>
          </View>
          {!isCancelada && proximaCobrancaText ? (
            <View style={styles.dataItem}>
              <Text style={styles.dataLabel}>Próxima</Text>
              <Text style={styles.dataValor}>{proximaCobrancaText}</Text>
            </View>
          ) : fimAcessoText ? (
            <View style={styles.dataItem}>
              <Text style={styles.dataLabel}>
                {isAvulso ? "Válido até" : "Fim"}
              </Text>
              <Text style={styles.dataValor}>{fimAcessoText}</Text>
            </View>
          ) : null}
          {ultimaCobrancaText ? (
            <View style={styles.dataItem}>
              <Text style={styles.dataLabel}>Última</Text>
              <Text style={styles.dataValor}>{ultimaCobrancaText}</Text>
            </View>
          ) : null}
        </View>

        {pagamento ? (
          <View style={styles.pagamentoDetalheBox}>
            <View style={styles.pagamentoDetalheRow}>
              <Text style={styles.pagamentoDetalheLabel}>Pagamento</Text>
              <Text style={styles.pagamentoDetalheValue}>
                {dataPagamentoText}
                {pagamento.forma_pagamento
                  ? ` · ${pagamento.forma_pagamento}`
                  : ""}
              </Text>
            </View>
            {labelBaixa ? (
              <Text style={styles.historicoBaixa} numberOfLines={2}>
                {labelBaixa}
              </Text>
            ) : null}
          </View>
        ) : null}

        {showActions &&
          (podePagar ||
            podeReprocessar ||
            (!isAvulso && (isAtiva || isPendente))) && (
            <View style={styles.actionStack}>
              {podePagar && (
                <TouchableOpacity
                  style={styles.botaoPagar}
                  onPress={() => handlePagarAssinatura(assinatura)}
                  activeOpacity={0.8}
                >
                  <Feather name="credit-card" size={15} color="#fff" />
                  <Text style={styles.botaoPagarTexto}>
                    {assinatura.pode_renovar ? "Renovar agora" : "Pagar agora"}
                  </Text>
                </TouchableOpacity>
              )}

              {podeReprocessar && (
                <TouchableOpacity
                  style={styles.botaoReprocessar}
                  onPress={() => {
                    setAssinaturaParaReprocessar(assinatura);
                    setReprocessModalVisible(true);
                  }}
                  disabled={reprocessando === assinatura.id}
                  activeOpacity={0.8}
                >
                  {reprocessando === assinatura.id ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <>
                      <Feather name="rotate-cw" size={15} color="#fff" />
                      <Text style={styles.botaoReprocessarTexto}>
                        Reprocessar
                      </Text>
                    </>
                  )}
                </TouchableOpacity>
              )}

              {!isAvulso && (isAtiva || isPendente) && (
                <TouchableOpacity
                  style={styles.botaoCancelar}
                  onPress={() => handleCancelarAssinatura(assinatura)}
                  disabled={cancelando === assinatura.id}
                  activeOpacity={0.7}
                >
                  {cancelando === assinatura.id ? (
                    <ActivityIndicator color="#DC3545" size="small" />
                  ) : (
                    <>
                      <Feather name="trash-2" size={15} color="#DC3545" />
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
                console.warn(
                  "⚠️ Token não encontrado - redirecionando para login",
                );
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
          data={assinaturaCards}
          keyExtractor={(item) => item.key}
          renderItem={renderAssinaturaCard}
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

            {assinaturaParaCancelar &&
              (() => {
                const dataCancelamento =
                  formatDate(assinaturaParaCancelar.proxima_cobranca) ||
                  "o fim do período atual";
                return (
                  <Text style={styles.modalMessage}>
                    {`Tem certeza que deseja cancelar a assinatura de ${assinaturaParaCancelar.plano.nome}?\n\nA cobrança automática será interrompida e você poderá usar o plano até ${dataCancelamento}.\n\nEsta ação não pode ser desfeita.`}
                  </Text>
                );
              })()}

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
      <Modal visible={reprocessModalVisible} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View
              style={[
                styles.modalIcon,
                { backgroundColor: "rgba(59, 130, 246, 0.1)" },
              ]}
            >
              <Feather name="rotate-cw" size={40} color="#2563eb" />
            </View>

            <Text style={styles.modalTitle}>Reprocessar Pagamento</Text>

            <Text style={styles.modalMessage}>
              {`Deseja reprocessar os pagamentos da assinatura${assinaturaParaReprocessar?.plano?.nome ? ` ${assinaturaParaReprocessar.plano.nome}` : ""}?\n\nEssa ação tentará sincronizar novamente os pagamentos do Mercado Pago.`}
            </Text>

            <View style={styles.confirmButtons}>
              <TouchableOpacity
                style={styles.confirmButtonManter}
                onPress={() => {
                  setReprocessModalVisible(false);
                  setAssinaturaParaReprocessar(null);
                }}
              >
                <Text style={styles.confirmButtonManterText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.confirmButtonReprocessar}
                onPress={() => {
                  setReprocessModalVisible(false);
                  if (assinaturaParaReprocessar) {
                    confirmarReprocessamento(assinaturaParaReprocessar);
                  }
                  setAssinaturaParaReprocessar(null);
                }}
              >
                <Text style={styles.confirmButtonCancelarText}>
                  Sim, Reprocessar
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
    paddingHorizontal: 14,
    paddingVertical: 12,
    paddingBottom: 28,
    gap: 10,
  },
  sectionsWrapper: {
    gap: 12,
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
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: "#eef2f7",
    gap: 2,
  },
  assinaturasTitle: {
    fontSize: 18,
    fontWeight: "800",
    color: colors.primary,
  },
  assinaturasSubtitle: {
    fontSize: 11,
    color: colors.textMuted,
  },
  pacoteCard: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 12,
    borderWidth: 1,
    borderColor: "#eff2f6",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 8,
    elevation: 2,
    gap: 10,
  },
  pacoteHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: 10,
  },
  pacoteInfo: {
    flex: 1,
    gap: 2,
  },
  pacoteNome: {
    fontSize: 15,
    fontWeight: "800",
    color: colors.text,
  },
  pacoteDescricao: {
    fontSize: 11,
    color: colors.textMuted,
  },
  pacoteStatusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
  },
  pacoteStatusText: {
    color: "#fff",
    fontSize: 10,
    fontWeight: "700",
    textTransform: "uppercase",
  },
  pacoteMetaRow: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#f8fafc",
    borderRadius: 10,
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderWidth: 1,
    borderColor: "#eef2f7",
  },
  pacoteMetaItem: {
    flex: 1,
    gap: 2,
  },
  pacoteMetaLabel: {
    fontSize: 10,
    color: colors.textMuted,
    fontWeight: "600",
  },
  pacoteMetaValue: {
    fontSize: 13,
    fontWeight: "700",
    color: colors.text,
  },
  pacoteMetaDivider: {
    width: 1,
    height: 28,
    backgroundColor: "#e5e7eb",
    marginHorizontal: 6,
  },
  pacoteDatesRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    flexWrap: "wrap",
    gap: 6,
  },
  pacoteDateText: {
    fontSize: 11,
    color: colors.textSecondary,
    fontWeight: "600",
  },
  pacoteBeneficiarios: {
    gap: 6,
  },
  pacoteBeneficiariosTitle: {
    fontSize: 11,
    fontWeight: "700",
    color: colors.text,
  },
  pacoteBeneficiariosList: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
  },
  pacoteBeneficiarioChip: {
    paddingHorizontal: 8,
    paddingVertical: 4,
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
    borderRadius: 10,
    paddingVertical: 10,
    gap: 6,
  },
  pacotePagarButtonText: {
    color: "#fff",
    fontSize: 13,
    fontWeight: "700",
  },
  assinaturasEmptyNote: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    backgroundColor: "#eef2f7",
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  assinaturasEmptyNoteText: {
    fontSize: 12,
    color: colors.textSecondary,
    fontWeight: "600",
  },

  card: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 12,
    borderWidth: 1,
    borderColor: "#eff2f6",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 8,
    elevation: 2,
    gap: 8,
  },

  cardHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
  },

  planoInfo: {
    flex: 1,
    gap: 2,
  },

  planoTitleRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
  },

  planoNome: {
    flex: 1,
    fontSize: 15,
    fontWeight: "800",
    color: colors.text,
  },

  modalidadeNome: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "500",
  },

  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
  },

  statusText: {
    color: "#fff",
    fontSize: 10,
    fontWeight: "700",
    textTransform: "uppercase",
  },

  /* Ciclo e Valor */
  cicloValor: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
  },

  cicloValor_text: {
    flex: 1,
    fontSize: 13,
    fontWeight: "600",
    color: "#374151",
  },

  valorText: {
    fontSize: 16,
    fontWeight: "700",
    color: colors.primary,
  },

  /* Datas */
  datasContainer: {
    flexDirection: "row",
    backgroundColor: "#f8fafc",
    borderRadius: 10,
    paddingVertical: 8,
    paddingHorizontal: 8,
    borderWidth: 1,
    borderColor: "#eef2f7",
    gap: 4,
  },

  dataItem: {
    flex: 1,
    alignItems: "flex-start",
    gap: 2,
  },

  dataLabel: {
    fontSize: 10,
    color: "#9ca3af",
    fontWeight: "600",
  },

  dataValor: {
    fontSize: 12,
    fontWeight: "700",
    color: "#111827",
  },

  historicoBaixa: {
    fontSize: 12,
    fontWeight: "600",
    color: "#475569",
    marginTop: 2,
  },

  pagamentoDetalheBox: {
    marginTop: 10,
    backgroundColor: "#f8fafc",
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: "#eef2f7",
    gap: 4,
  },
  pagamentoDetalheRow: {
    gap: 2,
  },
  pagamentoDetalheLabel: {
    fontSize: 10,
    fontWeight: "700",
    color: "#9ca3af",
    textTransform: "uppercase",
    letterSpacing: 0.3,
  },
  pagamentoDetalheValue: {
    fontSize: 13,
    fontWeight: "600",
    color: colors.text,
  },

  actionStack: {
    gap: 6,
  },

  /* Botão Pagar */
  botaoPagar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: colors.primary,
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    gap: 6,
  },
  botaoPagarTexto: {
    color: "#fff",
    fontSize: 13,
    fontWeight: "700",
  },

  botaoReprocessar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#2563eb",
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    gap: 6,
  },
  botaoReprocessarTexto: {
    color: "#fff",
    fontSize: 13,
    fontWeight: "700",
  },

  /* Botão Cancelar */
  botaoCancelar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1.5,
    borderColor: "#dc2626",
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    gap: 6,
  },

  botaoCancelarTexto: {
    color: "#dc2626",
    fontSize: 13,
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

  confirmButtonReprocessar: {
    flex: 1,
    paddingVertical: 13,
    borderRadius: 12,
    alignItems: "center",
    backgroundColor: "#2563eb",
  },

  confirmButtonCancelarText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "600",
  },
});
