import { useProtectedRoute } from "@/hooks/useProtectedRoute";
import { getApiUrlRuntime } from "@/src/config/urls";
import { postComRetryGateway, solicitarPix, usuarioPodeVerSandboxPagamento } from "@/src/utils/pagamentoPix";
import DsModal from "@/src/components/ds/Modal";
import PaymentMethodOptions from "@/src/components/ds/PaymentMethodOptions";
import type { MetodoPagamentoOpcao } from "@/src/utils/metodosPagamento";
import TaxaCartaoConfirmSheet from "@/src/components/ds/TaxaCartaoConfirmSheet";
import { simularTaxaCartao, type TaxaCartaoSimulacao } from "@/src/utils/taxaCartao";
import { useGatewayPicker } from "@/src/hooks/useGatewayPicker";
import { authService } from "@/src/services/authService";
import { colors } from "@/src/theme/colors";
import { handleUnauthorizedResponse } from "@/src/utils/authHelpers";
import {
  criarCardsAssinaturas,
  isPagamentoPago,
  podePagarAssinatura,
  valorExibidoAssinatura,
  type PagamentoAssinatura,
} from "@/src/utils/assinaturaFinanceiro";
import { isSessionExpiredVisible } from "@/src/utils/sessionExpired";
import { Feather } from "@expo/vector-icons";
import { usePacotePagamento } from "@/src/hooks/usePacotePagamento";
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
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import AppHeader from "@/src/components/ds/AppHeader";
import Badge from "@/src/components/ds/Badge";
import Button from "@/src/components/ds/Button";
import { useRefreshOnFocus } from "@/src/hooks/useRefreshOnFocus";
import { useParametro } from "@/src/hooks/useConfigRemota";
import { ds } from "@/src/components/ds/tokens";
import { SubscriptionAction, SubscriptionOverviewCard, PastSubscriptions, NextSubscriptionCharge } from "@/src/components/ds/SubscriptionOverview";
import { assinaturaEncerrada, diasAteAssinatura, primeiraCobrancaAssinatura } from "@/src/utils/assinaturasLayout";
import MinhaAssinaturaFidelidade from "@/src/components/MinhaAssinaturaFidelidade";
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

interface PagamentoGatewayAssinatura {
  provedor?: string | null;
  status?: string | null;
  status_label?: string | null;
  data?: string | null;
  data_formatada?: string | null;
  aguardando_ativacao?: boolean;
  mensagem?: string | null;
}

interface PlanoAssinatura {
  nome: string;
  modalidade: string;
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
  pode_cancelar?: boolean;
  pode_renovar?: boolean;
  motivo_pode_pagar?: string | null;
  pagamentos?: PagamentoAssinatura[] | null;
  pagamento_gateway?: PagamentoGatewayAssinatura | null;
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
  pode_renovar?: boolean;
  renovacao_liberada_em?: string | null;
  valor_renovacao?: number | null;
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

type AssinaturaCardItem = {
  key: string;
  assinatura: Assinatura;
  pagamentos: PagamentoAssinatura[];
};

export default function MinhasAssinaturasScreen() {
  const router = useRouter();

  // Verificar autenticação - se não autenticado, redireciona
  useProtectedRoute();
  const { escolherGateway } = useGatewayPicker();
  // Desligado: cancelar o plano é pedido à academia (a API também recusa)
  const trocaPlanoApp = useParametro("troca_plano_app");

  const [assinaturas, setAssinaturas] = useState<Assinatura[]>([]);
  const [pacotes, setPacotes] = useState<PacoteContrato[]>([]);
  const [loading, setLoading] = useState(true);
  const [versaoFidelidade, setVersaoFidelidade] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [apiUrl, setApiUrl] = useState("");
  const [isUserAdmin, setIsUserAdmin] = useState(false);
  const [cancelando, setCancelando] = useState<number | null>(null);
  const [atualizandoPagamentoId, setAtualizandoPagamentoId] = useState<
    number | null
  >(null);
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

  // Parcela aberta: o aluno escolhe o meio antes de usar qualquer checkout anterior.
  const [escolhaPagamento, setEscolhaPagamento] = useState<Assinatura | null>(null);
  const [metodoEscolhido, setMetodoEscolhido] = useState<MetodoPagamentoOpcao | null>("pix");
  const [gerandoPagamento, setGerandoPagamento] = useState(false);
  const [simulacaoTaxaCartao, setSimulacaoTaxaCartao] = useState<TaxaCartaoSimulacao | null>(null);
  const [simulandoTaxaCartao, setSimulandoTaxaCartao] = useState(false);
  const [erroTaxaCartao, setErroTaxaCartao] = useState<string | null>(null);
  const [repetirSimulacaoTaxa, setRepetirSimulacaoTaxa] = useState(0);
  const [confirmacaoCartao, setConfirmacaoCartao] = useState<Assinatura | null>(null);

  useEffect(() => {
    if (!escolhaPagamento || metodoEscolhido !== "cartao") {
      if (!confirmacaoCartao) {
        setSimulacaoTaxaCartao(null);
        setSimulandoTaxaCartao(false);
        setErroTaxaCartao(null);
      }
      return;
    }

    let cancelada = false;
    const simular = async () => {
      setSimulacaoTaxaCartao(null);
      setErroTaxaCartao(null);
      setSimulandoTaxaCartao(true);
      try {
        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          throw new Error("Faça login novamente para calcular o valor no cartão.");
        }
        const resultado = await simularTaxaCartao({
          apiUrl: getApiUrlRuntime(),
          token,
          valor: Number(escolhaPagamento.valor),
          escolherGateway,
          podeSandbox: isUserAdmin,
        });
        if (resultado.cancelled) return;
        if (!resultado.ok) {
          throw new Error(
            resultado.json?.message ||
              resultado.json?.error ||
              "Não foi possível calcular o valor no cartão.",
          );
        }
        const simulacao = resultado.json?.data as TaxaCartaoSimulacao | undefined;
        if (!simulacao) {
          throw new Error("A simulação não retornou o valor do cartão.");
        }
        if (!cancelada) setSimulacaoTaxaCartao(simulacao);
      } catch (erro) {
        if (!cancelada) {
          setErroTaxaCartao(
            erro instanceof Error
              ? erro.message
              : "Não foi possível calcular o valor no cartão.",
          );
        }
      } finally {
        if (!cancelada) setSimulandoTaxaCartao(false);
      }
    };

    void simular();
    return () => {
      cancelada = true;
    };
  }, [escolhaPagamento, metodoEscolhido, repetirSimulacaoTaxa, escolherGateway, isUserAdmin, confirmacaoCartao]);

  const pagarComCartao = async (
    assinatura: Assinatura,
    simulacao: TaxaCartaoSimulacao,
  ) => {
    const token = await AsyncStorage.getItem("@appcheckin:token");
    if (!token) {
      showErrorModal("⚠️ Sessão expirada", "Faça login novamente para pagar.", "error");
      return;
    }
    const r = await postComRetryGateway({
      url: `${getApiUrlRuntime()}/mobile/pagamento/parcela`,
      token,
      body: {
        matricula_id: Number(assinatura.matricula_id),
        metodo_pagamento: "cartao",
        installment_count: simulacao.parcela_count ?? 1,
        ...(simulacao.provider ? { gateway: simulacao.provider } : {}),
      },
      escolherGateway,
      podeSandbox: isUserAdmin,
    });
    if (!r.ok) {
      if ((r as any).cancelled) return;
      showErrorModal("⚠️ Não foi possível gerar o pagamento", r.json?.error || r.json?.message || "Tente novamente em instantes.", "error");
      return;
    }
    const url = r.json?.data?.payment_url;
    if (url) {
      await Linking.openURL(String(url));
      return;
    }
    showErrorModal("⚠️ Pagamento indisponível", "Link de pagamento não encontrado.", "warning");
  };

  const confirmarMetodoPagamento = async () => {
    const assinatura = escolhaPagamento;
    if (!assinatura || !metodoEscolhido) return;
    if (metodoEscolhido === "cartao") {
      if (simulandoTaxaCartao) return;
      if (!simulacaoTaxaCartao) {
        setRepetirSimulacaoTaxa((tentativa) => tentativa + 1);
        return;
      }
      if (simulacaoTaxaCartao.aplica_repasse) {
        setConfirmacaoCartao(assinatura);
        setEscolhaPagamento(null);
        return;
      }
    }

    setGerandoPagamento(true);
    try {
      if (metodoEscolhido === "cartao" && simulacaoTaxaCartao) {
        await pagarComCartao(assinatura, simulacaoTaxaCartao);
      } else {
        await pagarComPix(assinatura);
      }
    } finally {
      setGerandoPagamento(false);
      setEscolhaPagamento(null);
    }
  };

  const confirmarPagamentoCartao = async (simulacao: TaxaCartaoSimulacao) => {
    const assinatura = confirmacaoCartao;
    if (!assinatura) return;
    setGerandoPagamento(true);
    try {
      await pagarComCartao(assinatura, simulacao);
    } finally {
      setGerandoPagamento(false);
      setConfirmacaoCartao(null);
    }
  };

  const handlePagarAssinatura = async (assinatura: Assinatura) => {
    const temParcelaAberta = (assinatura.pagamentos ?? []).some(
      (pagamento) => !isPagamentoPago(pagamento),
    );
    if (assinatura.pode_pagar && assinatura.matricula_id && temParcelaAberta) {
      setMetodoEscolhido("pix");
      setSimulacaoTaxaCartao(null);
      setEscolhaPagamento(assinatura);
      return;
    }
    await abrirLinkPagamento(assinatura);
  };

  const pagarComPix = async (assinatura: Assinatura) => {
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
      const pixResult = await solicitarPix({
        apiUrl,
        token,
        matriculaId: Number(assinatura.matricula_id),
        escolherGateway,
        podeSandbox: isUserAdmin,
      });
      const pixJson = pixResult.json;
      if (!pixResult.ok) {
        if (pixResult.cancelled) {
          return;
        }
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
  };

  const abrirLinkPagamento = async (assinatura: Assinatura) => {
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
    async (baseUrl: string, opts?: { silent?: boolean }) => {
      try {
        if (!opts?.silent) setLoading(true);
        setError(null);

        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          console.warn("❌ Token não encontrado no AsyncStorage");
          throw new Error("Token não encontrado");
        }

        const url = `${baseUrl}/mobile/assinaturas`;


        const response = await fetch(url, {
          method: "GET",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
        });


        if (!response.ok) {
          const responseText = await response.text();
          console.error("❌ Erro na resposta:", responseText);

          if (await handleUnauthorizedResponse(response)) {
            return;
          }
          throw new Error(`HTTP ${response.status}: ${responseText}`);
        }

        const data: ApiResponse = await response.json();


        if (data.success && data.assinaturas) {
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

  const handleAtualizarPagamento = async (assinatura: Assinatura) => {
    const matriculaId = Number(assinatura.matricula_id);
    if (!matriculaId) {
      showErrorModal(
        "⚠️ Matrícula não encontrada",
        "Não foi possível identificar a matrícula desta assinatura.",
        "warning",
      );
      return;
    }

    try {
      setAtualizandoPagamentoId(assinatura.id);
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showErrorModal(
          "⚠️ Sessão expirada",
          "Faça login novamente para atualizar o pagamento.",
          "error",
        );
        return;
      }

      const baseUrl = apiUrl || getApiUrlRuntime();
      const response = await fetch(`${baseUrl}/mobile/verificar-pagamento`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ matricula_id: matriculaId }),
      });

      const text = await response.text();
      let json: {
        success?: boolean;
        message?: string;
        error?: string;
        code?: string;
        data?: {
          pagamento_gateway?: PagamentoGatewayAssinatura | null;
        };
      } = {};
      try {
        json = text ? JSON.parse(text) : {};
      } catch {
        json = {};
      }

      if (await handleUnauthorizedResponse(response)) {
        return;
      }

      if (!response.ok) {
        showErrorModal(
          "⚠️ Não foi possível atualizar",
          json.message ||
            json.error ||
            "Tente novamente em instantes.",
          "warning",
        );
        return;
      }

      await fetchAssinaturasCallback(baseUrl, { silent: true });

      const gateway = json.data?.pagamento_gateway;
      if (json.success) {
        showErrorModal(
          "✅ Pagamento atualizado",
          json.message || "O status da matrícula foi conferido com o gateway.",
          "success",
        );
      } else if (json.code === "AGUARDANDO_ATIVACAO" || gateway?.aguardando_ativacao) {
        const titulo = gateway?.status_label
          ? `${gateway.status_label}${gateway.data_formatada ? ` em ${gateway.data_formatada}` : ""}`
          : "Pagamento reconhecido";
        showErrorModal(
          titulo,
          gateway?.mensagem ||
            json.message ||
            "O Asaas já registrou este pagamento. Aguarde a ativação da matrícula.",
          "success",
        );
      } else {
        showErrorModal(
          "Pagamento ainda pendente",
          json.message ||
            "O gateway ainda não confirmou este pagamento. Se você deu baixa no Asaas, aguarde um instante e tente de novo.",
          "warning",
        );
      }
    } catch (err) {
      const msg =
        err instanceof Error ? err.message : "Erro ao atualizar pagamento";
      showErrorModal("⚠️ Erro ao atualizar pagamento", msg, "error");
    } finally {
      setAtualizandoPagamentoId(null);
    }
  };

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

        setIsUserAdmin(usuarioPodeVerSandboxPagamento(user));
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
          const url = getApiUrlRuntime();
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

  // Atualiza sozinho: ao reabrir o app/aba e a cada 60 s (pagamento pendente → pago).
  useRefreshOnFocus(
    () => (apiUrl ? fetchAssinaturasCallback(apiUrl, { silent: true }) : undefined),
    { intervalMs: 60_000 },
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

  const assinaturaCards = useMemo<AssinaturaCardItem[]>(() => {
    // Mesmo fuso do backend (America/Sao_Paulo) — evita divergência perto da meia-noite.
    const hojeIso = new Intl.DateTimeFormat("en-CA", {
      timeZone: "America/Sao_Paulo",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    }).format(new Date());

    return criarCardsAssinaturas(assinaturas, hojeIso);
  }, [assinaturas]);

  const renderAssinaturaCard = ({ item, onlyAction = false }: { item: AssinaturaCardItem; onlyAction?: boolean }) => {
    const { assinatura, pagamentos } = item;
    const dataInicioText = formatDate(assinatura.data_inicio) || "-";
    const isAtiva = assinatura.status.codigo === "ativa";
    const isCancelada =
      assinatura.status.codigo === "cancelada" ||
      assinatura.status.codigo === "cancelled";
    const isPendente = ["pendente", "pending"].includes(
      assinatura.status.codigo.toLowerCase(),
    );
    const isAvulso =
      assinatura.tipo_cobranca === "avulso" || assinatura.recorrente === false;
    const proximaCobrancaText = formatDate(assinatura.proxima_cobranca);
    const fimAcessoText = formatDate(assinatura.data_fim);
    const ultimaCobrancaText = formatDate(assinatura.ultima_cobranca);
    const temPagamentoAberto = pagamentos.some((p) => !isPagamentoPago(p));
    const temPagamentoPago = pagamentos.some(isPagamentoPago);
    const gatewayPagamento = assinatura.pagamento_gateway;
    const aguardandoAsaas = !!gatewayPagamento?.aguardando_ativacao;
    const podePagar =
      !aguardandoAsaas && podePagarAssinatura(assinatura, pagamentos);
    const statusBadgeLabel = aguardandoAsaas
      ? gatewayPagamento?.status_label || "Aguardando"
      : assinatura.status.nome;
    const isPagamentoPendente =
      !isCancelada &&
      !aguardandoAsaas &&
      ((isPendente && !temPagamentoPago) || (!isAtiva && temPagamentoAberto));
    const valorExibido = formatCurrency(
      valorExibidoAssinatura(
        assinatura.valor,
        pagamentos,
        isPagamentoPendente && temPagamentoAberto,
      ),
    );
    const temPagamentoAguardandoComPlanoAtivo =
      isAtiva && temPagamentoAberto && !aguardandoAsaas;
    const podeAtualizarPagamento =
      !isCancelada && (isPendente || isPagamentoPendente || aguardandoAsaas);
    const podeCancelar = assinatura.pode_cancelar !== false;

    const statusCodigo = assinatura.status.codigo.toLowerCase();
    const statusTone = aguardandoAsaas
      ? "success"
      : isPagamentoPendente || temPagamentoAguardandoComPlanoAtivo
      ? "warning"
      : statusCodigo === "ativa"
        ? "success"
        : isCancelada
          ? "neutral"
          : ["vencida", "expirada", "bloqueada"].includes(statusCodigo)
            ? "danger"
            : "warning";
    const datas = [
      { label: "INÍCIO", value: dataInicioText },
      !isCancelada && proximaCobrancaText
        ? { label: "PRÓX. COBRANÇA", value: proximaCobrancaText }
        : fimAcessoText
          ? { label: isAvulso ? "VÁLIDO ATÉ" : "FIM DO ACESSO", value: fimAcessoText }
          : null,
      ultimaCobrancaText ? { label: "ÚLT. COBRANÇA", value: ultimaCobrancaText } : null,
    ].filter(Boolean) as { label: string; value: string }[];

    if (onlyAction) {
      if (aguardandoAsaas) return <SubscriptionAction key={`action-${item.key}`} tone="info" title={`${assinatura.plano.nome} · aguardando ativação`} text={gatewayPagamento?.mensagem || "Pagamento confirmado. Aguarde a ativação da matrícula."} action={podeAtualizarPagamento ? {label:"Atualizar",onPress:()=>void handleAtualizarPagamento(assinatura),loading:atualizandoPagamentoId === assinatura.id} : undefined} />;
      if (!(isPagamentoPendente || temPagamentoAguardandoComPlanoAtivo || (podePagar && assinatura.pode_renovar))) return null;
      return <SubscriptionAction key={`action-${item.key}`} title={`${assinatura.plano.nome} · ${assinatura.pode_renovar ? "renovação disponível" : "pagamento pendente"}`} text={`${valorExibido}${temPagamentoAguardandoComPlanoAtivo ? " · seu plano atual continua ativo" : ""}`}
        action={podePagar ? {label:assinatura.pode_renovar ? "Renovar" : "Pagar",onPress:()=>void handlePagarAssinatura(assinatura)} : podeAtualizarPagamento ? {label:"Atualizar",onPress:()=>void handleAtualizarPagamento(assinatura),loading:atualizandoPagamentoId === assinatura.id} : undefined} />;
    }
    const ultimoPago = pagamentos.find(isPagamentoPago);
    return (
      <SubscriptionOverviewCard title={assinatura.plano.nome} price={valorExibido} unit={!isAvulso && assinatura.ciclo?.meses === 1 ? "/mês" : undefined}
        days={diasAteAssinatura(assinatura.data_fim || assinatura.proxima_cobranca)}
        subtitle={[assinatura.ciclo?.nome, isAvulso ? (fimAcessoText ? `até ${fimAcessoText}` : "Pagamento único") : proximaCobrancaText ? `renova ${proximaCobrancaText}` : null, ultimoPago?.forma_pagamento, statusBadgeLabel].filter(Boolean).join(" · ")}>
        <Badge tone={statusTone}>{statusBadgeLabel}</Badge>
        {assinatura.external_reference ? <Text style={styles.subCode}>{assinatura.external_reference}</Text> : null}
        {datas.length > 0 ? (
          <View style={styles.subDates}>
            {datas.map((d, i) => (
              <View key={d.label} style={[styles.subDate, i > 0 && styles.subDateDivider]}>
                <Text style={styles.kvK} numberOfLines={1}>
                  {d.label}
                </Text>
                <Text style={styles.kvV}>{d.value}</Text>
              </View>
            ))}
          </View>
        ) : null}

        {pagamentos.length > 0 ? (
          <View style={styles.subHist}>
            <Text style={[styles.kvK, styles.subHistTitle]}>HISTÓRICO DE PAGAMENTOS</Text>
            {pagamentos.map((pagamento, i) => {
              const pago = isPagamentoPago(pagamento);
              const isManual =
                pagamento.origem === "manual" ||
                (!!pagamento.baixado_por_nome &&
                  pagamento.origem !== "mercadopago");
              const nomeBaixa =
                pagamento.baixado_por_nome || pagamento.criado_por_nome || "";
              const primeiroNome = nomeBaixa.trim().split(/\s+/)[0] || "";
              const labelBaixa = pago
                ? isManual && primeiroNome
                  ? `Baixa manual por ${primeiroNome}`
                  : "Baixa automática"
                : null;
              const dataTxt =
                formatDate(pago ? pagamento.data_pagamento : pagamento.data_vencimento) || "—";
              return (
                <View key={pagamento.id} style={[styles.payRow, i > 0 && styles.payRowDivider]}>
                  <View
                    style={[
                      styles.payIcon,
                      { backgroundColor: pago ? ds.successSoft : ds.warningSoft },
                    ]}
                  >
                    <Feather name="file-text" size={18} color={pago ? ds.success : ds.warning} />
                  </View>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text style={styles.payAmt}>{formatCurrency(pagamento.valor)}</Text>
                    <Text style={styles.paySub} numberOfLines={2}>
                      {[
                        pago ? `Pago em ${dataTxt}` : `Vence em ${dataTxt}`,
                        pago ? pagamento.forma_pagamento : null,
                        labelBaixa,
                      ]
                        .filter(Boolean)
                        .join(" · ")}
                    </Text>
                  </View>
                  <Badge tone={pago ? "success" : "warning"} icon={pago ? "check" : "clock"}>
                    {pago ? "Pago" : pagamento.status || "Aguardando"}
                  </Badge>
                </View>
              );
            })}
          </View>
        ) : null}

        {(podeAtualizarPagamento ||
          podePagar ||
          (!isAvulso && (isAtiva || isPendente) && (!trocaPlanoApp || podeCancelar))) && (
          <View style={styles.actionStack}>
            {podeAtualizarPagamento && (
              <Button
                variant="secondary"
                block
                icon="refresh-cw"
                loading={atualizandoPagamentoId === assinatura.id}
                onPress={() => handleAtualizarPagamento(assinatura)}
              >
                Atualizar pagamento
              </Button>
            )}
            {podePagar && (
              <Button
                block
                icon="credit-card"
                onPress={() => handlePagarAssinatura(assinatura)}
              >
                {assinatura.pode_renovar ? "Renovar agora" : "Pagar agora"}
              </Button>
            )}
            {!isAvulso && (isAtiva || isPendente) && !trocaPlanoApp && (
              <Text style={{ fontSize: 12, color: colors.textSecondary, textAlign: "center" }}>
                Para cancelar ou trocar de plano, fale com a academia.
              </Text>
            )}
            {!isAvulso && (isAtiva || isPendente) && trocaPlanoApp && podeCancelar && (
              <Button
                variant="danger"
                block
                icon="x-circle"
                loading={cancelando === assinatura.id}
                onPress={() => handleCancelarAssinatura(assinatura)}
              >
                Cancelar assinatura
              </Button>
            )}
          </View>
        )}
      </SubscriptionOverviewCard>
    );
  };

  // Pagar o pacote pendente ou renovar (PIX ou cartão, no gateway da academia)
  const pagamentoPacote = usePacotePagamento({
    onPago: () => {
      if (apiUrl) void fetchAssinaturasCallback(apiUrl, { silent: true });
    },
    onErro: (m) => showErrorModal("⚠️ Pacote", m, "warning"),
  });

  const renderPacoteItem = (item: PacoteContrato, onlyAction = false) => {
    const contratoId = item.contrato_id ?? item.id;
    const statusCodigo =
      item.status_codigo || item.status || item.status_nome || "pendente";
    const statusLower = String(statusCodigo).toLowerCase();
    const isPendente =
      statusLower.includes("pendente") || statusLower === "pending";
    const statusText =
      item.status_nome || item.status || (isPendente ? "Pendente" : "Ativo");
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
    const buttonLabel = "Pagar pacote";
    const renovacaoEm = item.renovacao_liberada_em ? formatDate(item.renovacao_liberada_em) : null;

    if (onlyAction) return isPendente && contratoId ? <SubscriptionAction key={`pacote-action-${contratoId}`} title={`${pacoteNome} pendente`} text={`${formatCurrency(total)} · ${qtd} beneficiário(s)`} action={{label:"Pagar",onPress:()=>pagamentoPacote.iniciar({titulo:pacoteNome,valor:total,contratoId})}} /> : null;
    return (
      <SubscriptionOverviewCard key={`pacote-${contratoId ?? pacoteNome}`} title={pacoteNome} price={formatCurrency(total)} days={diasAteAssinatura(item.data_fim)} subtitle={[`${qtd} beneficiário(s)`,statusText,dataFim ? `até ${dataFim}` : null].filter(Boolean).join(" · ")}>
        {pacoteDescricao ? <Text style={styles.pacoteDescricao}>{pacoteDescricao}</Text> : null}
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
            onPress={() => pagamentoPacote.iniciar({ titulo: pacoteNome, valor: total, contratoId })}
            activeOpacity={0.8}
          >
            <Feather name="credit-card" size={16} color={ds.onBrand} />
            <Text style={styles.pacotePagarButtonText}>{buttonLabel}</Text>
          </TouchableOpacity>
        )}

        {!isPendente && contratoId && item.pode_renovar ? (
          <TouchableOpacity
            style={styles.pacotePagarButton}
            onPress={() =>
              pagamentoPacote.iniciar({
                titulo: pacoteNome,
                valor: Number(item.valor_renovacao || total),
                contratoId,
                renovar: true,
              })
            }
            activeOpacity={0.8}
          >
            <Feather name="refresh-cw" size={16} color={ds.onBrand} />
            <Text style={styles.pacotePagarButtonText}>Renovar pacote</Text>
          </TouchableOpacity>
        ) : !isPendente && renovacaoEm ? (
          <Text style={styles.pacoteDateText}>Renovação pelo app a partir de {renovacaoEm}</Text>
        ) : null}
      </SubscriptionOverviewCard>
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
          <Feather name="shopping-cart" size={18} color={ds.onBrand} />
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
        <Feather name="refresh-cw" size={18} color={ds.onBrand} />
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

  const cardsAtuais = assinaturaCards.filter(item => !assinaturaEncerrada(item.assinatura.status.codigo));
  const cardsEncerrados = assinaturaCards.filter(item => assinaturaEncerrada(item.assinatura.status.codigo));
  const pacotesAtuais = pacotes.filter(item => !assinaturaEncerrada(item.status_codigo || item.status));
  const pacotesEncerrados = pacotes.filter(item => assinaturaEncerrada(item.status_codigo || item.status));

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      {/* Cabeçalho no degradê da logo */}
      <AppHeader
        title="Minhas assinaturas"
        onBack={() => router.replace("/(tabs)/checkin")}
        actions={[
          { icon: "refresh-cw", label: "Atualizar", onPress: () => { setVersaoFidelidade((v) => v + 1); fetchAssinaturasCallback(apiUrl); }, loading },
        ]}
      />

      {loading ? (
        <View style={styles.centerContent}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Carregando assinaturas...</Text>
        </View>
      ) : error ? (
        renderErrorState()
      ) : (
        <MinhaAssinaturaFidelidade versao={versaoFidelidade} renderContent={(fidelidade) => {
          const nextCharge = primeiraCobrancaAssinatura([
            ...fidelidade.charges,
            ...cardsAtuais.filter(({assinatura})=>assinatura.proxima_cobranca && assinatura.status.codigo === "ativa").map(({assinatura})=>{
              const parcela = assinatura.pagamentos?.find(pagamento => Number(pagamento.status_pagamento_id) !== 4 && !isPagamentoPago(pagamento) && String(pagamento.data_vencimento || "").slice(0,10) === String(assinatura.proxima_cobranca).slice(0,10));
              return {date:assinatura.proxima_cobranca!,amount:Number(parcela?.valor ?? assinatura.valor)};
            }),
          ]);
          const pastCount = cardsEncerrados.length + pacotesEncerrados.length + fidelidade.pastCount;
          return <FlatList data={cardsAtuais} keyExtractor={item=>item.key} renderItem={renderAssinaturaCard}
            ListHeaderComponent={<View style={styles.sectionsWrapper}>
              {assinaturaCards.map(item=>renderAssinaturaCard({item,onlyAction:true}))}
              {pacotesAtuais.map(item=>renderPacoteItem(item,true))}
              {fidelidade.alerts}
              {nextCharge ? <NextSubscriptionCharge>Próxima cobrança: <Text style={{fontWeight:"900",color:ds.ink}}>{formatCurrency(nextCharge.amount)} em {formatDate(nextCharge.date)}</Text></NextSubscriptionCharge> : null}
              {fidelidade.loading ? <Text style={styles.sectionAside}>Carregando contratos…</Text> : null}
              {cardsAtuais.length || fidelidade.count > fidelidade.pastCount ? <View style={styles.sectionHeader}><Text style={styles.sectionLabel}>SUAS ASSINATURAS</Text><Text style={styles.sectionAside}>Dias até vencer</Text></View> : null}
              {fidelidade.active}
              {pacotesAtuais.length ? <View style={styles.pacotesSection}><View style={styles.sectionHeader}><Text style={styles.sectionLabel}>PACOTES</Text><Text style={styles.sectionAside}>Vinculados ao seu pagamento</Text></View><View style={styles.pacotesList}>{pacotesAtuais.map(item=>renderPacoteItem(item))}</View></View> : null}
            </View>}
            ListFooterComponent={<View style={{gap:12,marginTop:12}}><PastSubscriptions count={pastCount}>{cardsEncerrados.map(item=><View key={item.key}>{renderAssinaturaCard({item})}</View>)}{pacotesEncerrados.map(item=>renderPacoteItem(item))}{fidelidade.past}</PastSubscriptions></View>}
            ListEmptyComponent={!assinaturas.length && !pacotes.length && !fidelidade.count && !fidelidade.loading && !fidelidade.error ? renderEmptyState() : null}
            contentContainerStyle={styles.listContent} showsVerticalScrollIndicator={false}/>
        }} />
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
      {pagamentoPacote.elemento}
      <DsModal
        visible={!!escolhaPagamento}
        variant="dialog"
        icon="credit-card"
        title={`Pagar ${escolhaPagamento?.plano?.nome ?? "plano"}`}
        description={
          !escolhaPagamento
            ? undefined
            : metodoEscolhido === "cartao"
              ? simulandoTaxaCartao
                ? "Calculando o valor para pagamento com cartão..."
                : erroTaxaCartao
                  ? erroTaxaCartao
                  : simulacaoTaxaCartao
                    ? `Valor no cartão: ${simulacaoTaxaCartao.rotulo_parcela || formatCurrency(simulacaoTaxaCartao.valor_cobrar)}.`
                    : "O valor do cartão será calculado antes de continuar."
              : `Valor no PIX: ${formatCurrency(escolhaPagamento.valor)}.`
        }
        primary={{
          label: simulandoTaxaCartao
            ? "Calculando valor"
            : metodoEscolhido === "cartao"
              ? erroTaxaCartao
                ? "Tentar novamente"
                : simulacaoTaxaCartao?.aplica_repasse
                  ? "Conferir taxas e parcelas"
                  : simulacaoTaxaCartao
                    ? "Pagar com cartão"
                    : "Calcular valor"
              : "Gerar PIX",
          icon: metodoEscolhido === "cartao" ? "credit-card" : "zap",
          onPress: () => void confirmarMetodoPagamento(),
          loading: gerandoPagamento,
          disabled: simulandoTaxaCartao,
        }}
        onClose={() => setEscolhaPagamento(null)}
      >
        <View style={{ gap: 8 }}>
          <Text style={{ fontSize: 13, fontWeight: "600", color: colors.textSecondary }}>Forma de pagamento</Text>
          <PaymentMethodOptions opcoes={["pix", "cartao"]} value={metodoEscolhido} onChange={setMetodoEscolhido} compact />
        </View>
      </DsModal>
      <TaxaCartaoConfirmSheet
        visible={!!confirmacaoCartao}
        simulacao={simulacaoTaxaCartao}
        tituloPlano={confirmacaoCartao?.plano?.nome}
        loading={gerandoPagamento}
        detalharTaxas={isUserAdmin}
        onClose={() => {
          if (!gerandoPagamento) setConfirmacaoCartao(null);
        }}
        onConfirm={(simulacao) => void confirmarPagamentoCartao(simulacao)}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  paySub: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "600",
    color: ds.inkMuted,
  },
  payAmt: {
    fontSize: 16,
    lineHeight: 20,
    fontWeight: "800",
    color: ds.ink,
    fontVariant: ["tabular-nums"],
  },
  payIcon: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: "center",
    justifyContent: "center",
  },
  payRowDivider: {
    borderTopWidth: 1,
    borderTopColor: ds.line,
  },
  payRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  subHistTitle: {
    paddingTop: 12,
    paddingHorizontal: 16,
  },
  subHist: {
    borderTopWidth: 1,
    borderTopColor: ds.line,
    backgroundColor: ds.surfaceSunken,
  },
  kvV: {
    fontSize: 15,
    lineHeight: 20,
    fontWeight: "800",
    color: ds.ink,
    marginTop: 2,
    fontVariant: ["tabular-nums"],
  },
  kvK: {
    fontSize: 11,
    lineHeight: 14,
    fontWeight: "800",
    letterSpacing: 0.66,
    color: ds.inkMuted,
  },
  subDateDivider: {
    borderLeftWidth: 1,
    borderLeftColor: ds.line,
  },
  subDate: {
    flex: 1,
    minWidth: 0,
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  subDates: {
    flexDirection: "row",
    borderTopWidth: 1,
    borderTopColor: ds.line,
  },
  subPrice: {
    fontSize: 22,
    lineHeight: 26,
    fontWeight: "900",
    color: ds.brandInk,
    fontVariant: ["tabular-nums"],
    textAlign: "right",
  },
  subCode: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: "600",
    color: ds.inkMuted,
    fontVariant: ["tabular-nums"],
    marginTop: 2,
  },
  subMeta: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "600",
    color: ds.inkMuted,
  },
  subName: {
    fontSize: 20,
    lineHeight: 24,
    fontWeight: "800",
    letterSpacing: -0.2,
    color: ds.ink,
    flexShrink: 1,
  },
  subTitleRow: {
    flexDirection: "row",
    alignItems: "center",
    flexWrap: "wrap",
    gap: 8,
    marginBottom: 4,
  },
  subHead: {
    flexDirection: "row",
    justifyContent: "space-between",
    gap: 12,
    padding: 16,
  },
  sectionAside: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "600",
    color: ds.inkMuted,
  },
  sectionLabel: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: "800",
    letterSpacing: 0.96,
    color: ds.inkMuted,
  },
  container: {
    flex: 1,
    backgroundColor: ds.ground,
  },

  /* Header */
  headerTop: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    minHeight: 64,
    paddingHorizontal: 4,
    backgroundColor: ds.surface,
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  headerTitleCentered: {
    flex: 1,
    fontSize: 22,
    lineHeight: 28,
    fontWeight: "800",
    letterSpacing: -0.2,
    color: ds.ink,
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
    padding: 16,
    paddingBottom: 32,
    gap: 12,
  },
  sectionsWrapper: {
    gap: 12,
    marginBottom: 12,
  },

  /* Pacotes */
  pacotesSection: {

  },
  sectionHeader: {
    flexDirection: "row",
    alignItems: "baseline",
    justifyContent: "space-between",
    paddingHorizontal: 4,
    marginBottom: 8,
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
    color: colors.primaryText,
  },
  pacotesSubtitle: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
  },
  pacotesList: {
    gap: 12,
  },
  assinaturasSectionHeader: {
    backgroundColor: "#fff",
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: ds.line,
    gap: 2,
  },
  assinaturasTitle: {
    fontSize: 18,
    fontWeight: "800",
    color: colors.primaryText,
  },
  assinaturasSubtitle: {
    fontSize: 11,
    color: colors.textMuted,
  },
  pacoteCard: {
    backgroundColor: ds.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: ds.line,
    padding: 16,
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
    gap: 2,
  },
  pacoteNome: {
    fontSize: 20,
    lineHeight: 24,
    fontWeight: "800",
    color: ds.ink,
  },
  pacoteDescricao: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "600",
    color: ds.inkMuted,
    marginTop: 2,
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
    borderWidth: 1,
    borderColor: ds.line,
    borderRadius: 12,
    overflow: "hidden",
  },
  pacoteMetaItem: {
    flex: 1,
    paddingVertical: 10,
    paddingHorizontal: 12,
  },
  pacoteMetaLabel: {
    fontSize: 11,
    lineHeight: 14,
    fontWeight: "800",
    letterSpacing: 0.66,
    textTransform: "uppercase",
    color: ds.inkMuted,
  },
  pacoteMetaValue: {
    fontSize: 15,
    lineHeight: 20,
    fontWeight: "800",
    color: ds.ink,
    marginTop: 2,
    fontVariant: ["tabular-nums"],
  },
  pacoteMetaDivider: {
    width: 1,
    backgroundColor: ds.line,
  },
  pacoteDatesRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    flexWrap: "wrap",
    gap: 6,
  },
  pacoteDateText: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "600",
    color: ds.inkMuted,
  },
  pacoteBeneficiarios: {
    gap: 6,
  },
  pacoteBeneficiariosTitle: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: "800",
    letterSpacing: 0.96,
    textTransform: "uppercase",
    color: ds.inkMuted,
    marginBottom: 8,
  },
  pacoteBeneficiariosList: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
  },
  pacoteBeneficiarioChip: {
    paddingHorizontal: 10,
    height: 28,
    justifyContent: "center",
    borderRadius: 999,
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
  },
  pacoteBeneficiarioText: {
    fontSize: 13,
    fontWeight: "700",
    color: ds.ink,
  },
  pacotePagarButton: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    height: 48,
    borderRadius: 12,
    backgroundColor: ds.brand,
  },
  pacotePagarButtonText: {
    fontSize: 16,
    fontWeight: "800",
    color: ds.onBrand,
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
    backgroundColor: ds.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: ds.line,
    overflow: "hidden",
  },
  cardPendente: {
    borderColor: ds.warning,
  },
  pendenteBanner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 16,
    paddingVertical: 10,
    backgroundColor: ds.warningSoft,
  },
  pendenteBannerText: {
    flex: 1,
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "700",
    color: ds.warning,
  },
  statusBadgePendente: {
    borderWidth: 1,
    borderColor: "#ea580c",
  },
  valorTextPendente: {
    fontSize: 18,
    fontWeight: "800",
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
    color: colors.primaryText,
  },

  /* Datas */
  datasContainer: {
    flexDirection: "row",
    backgroundColor: "#f8fafc",
    borderRadius: 10,
    paddingVertical: 8,
    paddingHorizontal: 8,
    borderWidth: 1,
    borderColor: ds.line,
    gap: 4,
  },

  dataItem: {
    flex: 1,
    alignItems: "flex-start",
    gap: 2,
  },

  dataLabel: {
    fontSize: 10,
    color: ds.inkMuted,
    fontWeight: "600",
  },

  dataValor: {
    fontSize: 12,
    fontWeight: "700",
    color: ds.ink,
  },

  historicoBaixa: {
    fontSize: 12,
    fontWeight: "600",
    color: "#475569",
    marginTop: 2,
  },

  historicoContainer: {
    marginTop: 14,
  },
  historicoTitulo: {
    fontSize: 13,
    fontWeight: "700",
    color: "#475569",
  },
  historicoResumo: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    gap: 8,
  },

  pagamentoDetalheBox: {
    marginTop: 10,
    backgroundColor: "#f8fafc",
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: ds.line,
    gap: 4,
  },
  pagamentoDetalheRow: {
    gap: 2,
  },
  pagamentoDetalheLabel: {
    fontSize: 10,
    fontWeight: "700",
    color: ds.inkMuted,
    textTransform: "uppercase",
    letterSpacing: 0.3,
  },
  pagamentoDetalheValue: {
    fontSize: 13,
    fontWeight: "600",
    color: colors.text,
  },

  actionStack: {
    gap: 8,
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: ds.line,
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
    color: ds.onBrand,
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
    color: ds.onBrand,
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
    color: ds.onBrand,
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
    color: ds.ink,
    marginBottom: 10,
    textAlign: "center",
  },

  modalMessage: {
    fontSize: 14,
    color: ds.inkMuted,
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
    borderColor: ds.line,
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
