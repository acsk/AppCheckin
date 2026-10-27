import { copyToClipboard } from "@/src/utils/clipboard";
import { useProtectedRoute } from "@/hooks/useProtectedRoute";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { postComRetryGateway, solicitarPix, usuarioPodeVerSandboxPagamento } from "@/src/utils/pagamentoPix";
import { useGatewayPicker } from "@/src/hooks/useGatewayPicker";
import { authService } from "@/src/services/authService";
import PixPaymentSheet from "@/src/components/ds/PixPaymentSheet";
import { colors } from "@/src/theme/colors";
import { handleUnauthorizedResponse } from "@/src/utils/authHelpers";
import { isSessionExpiredVisible } from "@/src/utils/sessionExpired";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Modal,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import AppHeader from "@/src/components/ds/AppHeader";
import CheckoutRedirectModal from "@/src/components/ds/CheckoutRedirectModal";
import MigracaoPlanoSheet, { type MigracaoSimulacao } from "@/src/components/ds/MigracaoPlanoSheet";
import PaymentMethodOptions from "@/src/components/ds/PaymentMethodOptions";
import TaxaCartaoConfirmSheet from "@/src/components/ds/TaxaCartaoConfirmSheet";
import PlanCard from "@/src/components/ds/PlanCard";
import PacotesVitrine from "@/src/components/PacotesVitrine";
import Section from "@/src/components/ds/Section";
import Stepper from "@/src/components/ds/Stepper";
import { useRefreshOnFocus } from "@/src/hooks/useRefreshOnFocus";
import { ds } from "@/src/components/ds/tokens";
import {
  opcoesPagamentoVisiveis,
  rotuloPagarMetodo,
  type MetodoPagamentoOpcao,
} from "@/src/utils/metodosPagamento";
import { simularTaxaCartao, type TaxaCartaoSimulacao } from "@/src/utils/taxaCartao";
// NOVO ARQUIVO - SEM CÓDIGO ANTIGO

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
  pix_disponivel?: boolean;
  metodos_pagamento?: string[];
  economia?: string | null;
  /** Fidelização: assinatura mensal (aceite dos termos nos detalhes do plano) */
  cobranca_mensal?: boolean;
}

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
  pode_migrar?: boolean;
  pode_renovar?: boolean;
  pode_pagar?: boolean;
  motivo_renovacao?: string | null;
  label?: string | null;
  matricula_ativa?: {
    id?: number;
    plano_id?: number;
    plano_ciclo_id?: number | null;
    valor?: number;
    status_codigo?: string;
  } | null;
  ciclos?: Ciclo[];
}


interface ApiResponse {
  success: boolean;
  data?: {
    planos: Plan[];
    total: number;
  };
  error?: string;
}

const isAdminUser = (user: any) => usuarioPodeVerSandboxPagamento(user);

const isDevPlanName = (nome?: string | null) =>
  String(nome || "")
    .trim()
    .toLowerCase()
    .includes("dev");

export default function PlanosScreen() {
  const router = useRouter();

  // Verificar autenticação - se não autenticado, redireciona
  const { isLoading: isAuthChecking } = useProtectedRoute();
  const { escolherGateway } = useGatewayPicker();
  const [podeSandboxPix, setPodeSandboxPix] = useState(false);
  const onEscolherGateway = () => {
    setPixLoading(false);
    setComprando(false);
  };

  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
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
  const [paymentUrlToOpen, setPaymentUrlToOpen] = useState<string | null>(null);
  const [paymentProvider, setPaymentProvider] = useState<string | null>(null);
  const [paymentMetodo, setPaymentMetodo] = useState<string | null>(null);
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
  const [selectedCicloByPlano, setSelectedCicloByPlano] = useState<
    Record<number, number>
  >({});
  const [selectedPlanoId, setSelectedPlanoId] = useState<number | null>(null);
  const [migracaoModalVisible, setMigracaoModalVisible] = useState(false);
  const [migracaoSimulacao, setMigracaoSimulacao] =
    useState<MigracaoSimulacao | null>(null);
  const [migracaoPendente, setMigracaoPendente] = useState<{
    plano: Plan;
    cicloId: number;
    metodo: MetodoPagamentoOpcao;
  } | null>(null);
  const [metodoByPlano, setMetodoByPlano] = useState<
    Record<number, MetodoPagamentoOpcao>
  >({});
  const [confirmandoMigracao, setConfirmandoMigracao] = useState(false);
  const [taxaCartaoVisible, setTaxaCartaoVisible] = useState(false);
  const [taxaCartaoSimulacao, setTaxaCartaoSimulacao] =
    useState<TaxaCartaoSimulacao | null>(null);
  const [taxaCartaoGateway, setTaxaCartaoGateway] = useState<string | undefined>();
  const [taxaCartaoPlano, setTaxaCartaoPlano] = useState<Plan | null>(null);
  const [taxaCartaoConfirmando, setTaxaCartaoConfirmando] = useState(false);
  const [paymentTarifa, setPaymentTarifa] = useState<TaxaCartaoSimulacao | null>(null);

  useEffect(() => {
    if (planos.length === 0) return;
    setSelectedCicloByPlano((prev) => {
      const next = { ...prev };
      let hasChange = false;
      planos.forEach((plano) => {
        if (next[plano.id]) return;
        const ciclosOrdenados = (plano.ciclos || []).sort(
          (a, b) => a.meses - b.meses,
        );
        if (ciclosOrdenados[0]) {
          next[plano.id] = ciclosOrdenados[0].id;
          hasChange = true;
        }
      });
      return hasChange ? next : prev;
    });
  }, [planos]);

  useEffect(() => {
    if (planos.length === 0) return;
    setMetodoByPlano((prev) => {
      const next = { ...prev };
      let hasChange = false;
      planos.forEach((plano) => {
        const cicloId = selectedCicloByPlano[plano.id];
        const ciclo =
          plano.ciclos?.find((item) => item.id === cicloId) ??
          plano.ciclos?.[0];
        const opcoes = opcoesPagamentoVisiveis(
          ciclo?.metodos_pagamento,
          ciclo?.pix_disponivel === true,
        );
        const atual = next[plano.id];
        if (opcoes.length === 0) {
          if (atual) {
            delete next[plano.id];
            hasChange = true;
          }
          return;
        }
        if (!atual || !opcoes.includes(atual)) {
          next[plano.id] = opcoes[0];
          hasChange = true;
        }
      });
      return hasChange ? next : prev;
    });
  }, [planos, selectedCicloByPlano]);

  useEffect(() => {
    if (!selectedPlanoId) return;
    const exists = planos.some((plano) => plano.id === selectedPlanoId);
    if (!exists) {
      setSelectedPlanoId(null);
    }
  }, [planos, selectedPlanoId]);

  // Verificar permissão de acesso
  useEffect(() => {
    const checkPermission = async () => {
      try {
        // Verificar se existe token
        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          if (!isSessionExpiredVisible()) {
            console.warn("❌ Token não encontrado - redirecionando para login");
            router.replace("/(auth)/login");
          }
          setHasPermission(false);
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
          setHasPermission(false);
          return;
        }

        console.log("✅ Usuário autenticado com permissão para acessar planos");
        setHasPermission(true);
      } catch (err) {
        console.error("❌ Erro ao verificar permissão:", err);
        setHasPermission(false);
      }
    };

    checkPermission();
  }, [router]);

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

    // Só inicializa se o usuário tem permissão
    if (hasPermission === true) {
      initializeAndFetch();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [hasPermission]);

  const abrirCheckout = (
    url: string,
    provider?: string | null,
    metodo?: string | null,
    tarifa?: TaxaCartaoSimulacao | null,
  ) => {
    setPaymentUrlToOpen(url);
    setPaymentProvider(provider ?? null);
    setPaymentMetodo(metodo ?? null);
    setPaymentTarifa(tarifa ?? null);
    setRedirectModalVisible(true);
  };

  const fecharCheckout = () => {
    setRedirectModalVisible(false);
    setPaymentUrlToOpen(null);
    setPaymentProvider(null);
    setPaymentMetodo(null);
    setPaymentTarifa(null);
  };

  const irParaCheckout = async () => {
    if (!paymentUrlToOpen) return;
    const supported = await Linking.canOpenURL(paymentUrlToOpen);
    if (supported) {
      await Linking.openURL(paymentUrlToOpen);
    }
    fecharCheckout();
  };

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




  const fetchPlanos = async (baseUrl: string, opts?: { silent?: boolean }) => {
    try {
      if (!opts?.silent) setLoading(true);
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

        if (await handleUnauthorizedResponse(response)) {
          return;
        }
        throw new Error(`HTTP ${response.status}: ${responseText}`);
      }

      const data: ApiResponse = await response.json();

      console.log("✅ Resposta da API:", JSON.stringify(data, null, 2));

      if (data.success && data.data?.planos) {
        const user = await authService.getCurrentUser();
        const admin = isAdminUser(user);
        setPodeSandboxPix(admin);
        const planosFiltrados = admin
          ? data.data.planos
          : data.data.planos.filter((plano) => !isDevPlanName(plano?.nome));

        console.log("✅ Planos carregados:", planosFiltrados.length);
        setPlanos(planosFiltrados);
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

  const abrirConfirmacaoMigracao = React.useCallback(
    async (plano: Plan, cicloId: number, metodo: MetodoPagamentoOpcao) => {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Token não encontrado");

      const simResponse = await fetch(`${apiUrl}/mobile/simular-migracao`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          plano_id: plano.id,
          plano_ciclo_id: cicloId,
        }),
      });

      const simText = await simResponse.text();
      let simData: { success?: boolean; message?: string; data?: MigracaoSimulacao } =
        {};
      try {
        simData = simText ? JSON.parse(simText) : {};
      } catch {
        throw new Error("Resposta inválida ao simular migração");
      }

      if (!simResponse.ok || !simData.success) {
        throw new Error(simData.message || "Não foi possível simular a migração");
      }

      const simulacao = simData.data || null;
      if (metodo === "cartao" && simulacao && Number(simulacao.valor_parcela ?? 0) > 0) {
        const taxaRes = await simularTaxaCartao({
          apiUrl,
          token,
          valor: Number(simulacao.valor_parcela),
          escolherGateway,
          onEscolherGateway,
          podeSandbox: podeSandboxPix,
        });
        if (taxaRes.cancelled) {
          return;
        }
        if (taxaRes.ok && taxaRes.json?.data) {
          simulacao.taxa_cartao = taxaRes.json.data;
        }
      }

      setMigracaoSimulacao(simulacao);
      setMigracaoPendente({ plano, cicloId, metodo });
      setMigracaoModalVisible(true);
    },
    [apiUrl, escolherGateway, podeSandboxPix],
  );

  const executarMigracao = React.useCallback(async () => {
    if (!migracaoPendente) return;

    const pendente = migracaoPendente;
    const installmentCount = migracaoSimulacao?.taxa_cartao?.parcela_count;
    const tarifaConfirmada = migracaoSimulacao?.taxa_cartao ?? null;
    try {
      setConfirmandoMigracao(true);
      // Fecha confirmação antes da API para o feedback ficar visível
      setMigracaoModalVisible(false);
      setMigracaoPendente(null);
      setMigracaoSimulacao(null);

      const { plano, cicloId, metodo } = pendente;
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Token não encontrado");

      console.log("🔄 [Migrar] Enviando...", {
        plano_id: plano.id,
        plano_ciclo_id: cicloId,
        metodo_pagamento: metodo,
        apiUrl,
      });

      const migrarResult = await postComRetryGateway({
        url: `${apiUrl}/mobile/migrar-plano`,
        token,
        body: {
          plano_id: plano.id,
          plano_ciclo_id: cicloId,
          metodo_pagamento: metodo,
          ...(metodo === "cartao" && installmentCount
            ? { installment_count: installmentCount }
            : {}),
        },
        escolherGateway,
        onEscolherGateway,
        podeSandbox: podeSandboxPix,
      });
      const data = migrarResult.json;
      if (migrarResult.cancelled) {
        return;
      }

      console.log("🔄 [Migrar] Resposta:", migrarResult.status, data);

      if (!migrarResult.ok) {
        if (data.code === "ERRO_PAGAMENTO" && metodo === "pix") {
          const matriculaId = data.matricula_id ?? data.data?.matricula_id;
          if (matriculaId) {
            const pixResult = await solicitarPix({
              apiUrl,
              token,
              matriculaId: Number(matriculaId),
              escolherGateway,
              onEscolherGateway,
              podeSandbox: podeSandboxPix,
            });
            const pixJson = pixResult.json;
            if (pixResult.ok) {
              const pix = pixJson.data?.pix || {};
              setPixData({
                matricula_id: Number(pixJson.data?.matricula_id || matriculaId),
                valor: Number(
                  pixJson.data?.valor || data.data?.valor_parcela || 0,
                ),
                qr_code: pix.qr_code || null,
                qr_code_base64: pix.qr_code_base64 || null,
                ticket_url: pix.ticket_url || null,
                expires_at: pix.expires_at || null,
              });
              setPixModalVisible(true);
              return;
            }
            if (pixResult.cancelled) {
              return;
            }
          }
        }

        showErrorModal(
          "⚠️ Migração não concluída",
          data.message ||
            data.error ||
            `Não foi possível migrar o plano (${data.code || response.status})`,
          "error",
        );
        return;
      }

      const valorParcela = Number(data.data?.valor_parcela ?? 0);
      const paymentUrl = data.data?.payment_url;
      const aguardandoPagamento = !!data.data?.aguardando_pagamento || !!data.data?.plano_atual_mantido;

      if (!aguardandoPagamento && valorParcela <= 0 && !paymentUrl && !data.data?.pix) {
        showErrorModal(
          "✅ Plano migrado",
          data.message ||
            "Migração concluída. O crédito cobriu o valor — nada a pagar.",
          "success",
        );
        await fetchPlanos(apiUrl);
        return;
      }

      if (metodo === "pix" && data.data?.pix) {
        setPixData({
          matricula_id: data.data.matricula_id,
          valor: valorParcela,
          qr_code: data.data.pix.qr_code,
          qr_code_base64: data.data.pix.qr_code_base64,
          ticket_url: data.data.pix.ticket_url,
          expires_at: data.data.pix.expires_at,
        });
        setPixModalVisible(true);
        return;
      }

      if (!paymentUrl) {
        showErrorModal(
          "⚠️ Erro",
          "Link de pagamento não foi gerado. Tente novamente.",
          "error",
        );
        return;
      }

      abrirCheckout(
        paymentUrl,
        data.data?.gateway || data.data?.provider || migrarResult.gateway,
        metodo,
        tarifaConfirmada || data.data?.tarifa_cartao || null,
      );
    } catch (err) {
      const errorMsg =
        err instanceof Error ? err.message : "Erro ao migrar plano";
      console.error("❌ [Migrar] Falha:", errorMsg);
      showErrorModal("❌ Algo Deu Errado", errorMsg, "error");
    } finally {
      setConfirmandoMigracao(false);
      setComprando(false);
      setPixLoading(false);
      setPlanoComprando(null);
    }
  }, [apiUrl, migracaoPendente, migracaoSimulacao, fetchPlanos]);

  const handleContratar = React.useCallback(
    async (
      plano: Plan,
      metodo: MetodoPagamentoOpcao = "cartao",
      opcoes?: {
        jaSimulouTaxa?: boolean;
        gateway?: string;
        installment_count?: number;
        tarifaConfirmada?: TaxaCartaoSimulacao | null;
      },
    ) => {
      try {
        setComprando(true);
        setPlanoComprando(plano.id);

        console.log("🛒 Iniciando compra do plano:", plano.nome);

        // Obter o ciclo selecionado para este plano
        const selectedCicloId = selectedCicloByPlano[plano.id];
        const selectedCiclo = plano.ciclos?.find(
          (c) => c.id === selectedCicloId,
        );

        if (!selectedCiclo) {
          console.error("❌ Ciclo não selecionado");
          showErrorModal(
            "⚠️ Ciclo não selecionado",
            "Por favor, selecione um ciclo antes de contratar.",
            "warning",
          );
          setComprando(false);
          setPlanoComprando(null);
          return;
        }

        // Fidelidade não é compra à vista: termos, aceite e assinatura ficam nos detalhes do plano
        if (selectedCiclo.cobranca_mensal) {
          setComprando(false);
          setPlanoComprando(null);
          router.push({ pathname: "/plano-detalhes", params: { id: String(plano.id) } });
          return;
        }

        if (plano.pode_migrar) {
          await abrirConfirmacaoMigracao(plano, selectedCiclo.id, metodo);
          return;
        }

        const cicloAtualMatricula = plano.matricula_ativa?.plano_ciclo_id ?? null;
        const statusMatricula = String(
          plano.matricula_ativa?.status_codigo || "",
        ).toLowerCase();
        if (
          statusMatricula !== "pendente" &&
          plano.is_plano_atual &&
          (cicloAtualMatricula == null ||
            Number(cicloAtualMatricula) !== Number(selectedCiclo.id))
        ) {
          await abrirConfirmacaoMigracao(plano, selectedCiclo.id, metodo);
          return;
        }

        console.log(
          "📅 Ciclo selecionado:",
          selectedCiclo.nome,
          selectedCiclo.valor_formatado,
        );

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

        let gateway = opcoes?.gateway;
        if (metodo === "cartao" && !opcoes?.jaSimulouTaxa) {
          const taxaRes = await simularTaxaCartao({
            apiUrl,
            token,
            valor: Number(selectedCiclo.valor),
            escolherGateway,
            onEscolherGateway,
            podeSandbox: podeSandboxPix,
          });
          if (taxaRes.cancelled) {
            setComprando(false);
            setPlanoComprando(null);
            return;
          }
          if (!taxaRes.ok) {
            showErrorModal(
              "⚠️ Taxa do cartão",
              taxaRes.json?.message ||
                taxaRes.json?.error ||
                "Não foi possível simular a taxa do cartão.",
              "warning",
            );
            return;
          }
          const taxa = taxaRes.json?.data as TaxaCartaoSimulacao | undefined;
          gateway = taxaRes.gateway || taxa?.provider;
          if (taxa?.aplica_repasse) {
            setTaxaCartaoSimulacao(taxa);
            setTaxaCartaoGateway(gateway);
            setTaxaCartaoPlano(plano);
            setTaxaCartaoVisible(true);
            return;
          }
        }

        // 3. Fazer requisição POST para comprar plano com ciclo
        const compraResult = await postComRetryGateway({
          url: `${apiUrl}/mobile/comprar-plano`,
          token,
          body: {
            plano_id: plano.id,
            plano_ciclo_id: selectedCiclo.id,
            metodo_pagamento: metodo,
            ...(gateway ? { gateway } : {}),
            ...(metodo === "cartao" && opcoes?.installment_count
              ? { installment_count: opcoes.installment_count }
              : {}),
          },
          escolherGateway,
          onEscolherGateway,
          podeSandbox: podeSandboxPix,
        });
        if (compraResult.cancelled) {
          setComprando(false);
          setPlanoComprando(null);
          return;
        }
        const matriculaData = compraResult.json;

        console.log("📡 Status criação matrícula:", compraResult.status);

        if (!compraResult.ok) {
          console.error("❌ Erro ao criar matrícula:", matriculaData);
          if (matriculaData?.code === "MIGRACAO_NECESSARIA") {
            await abrirConfirmacaoMigracao(
              plano,
              selectedCiclo.id,
              metodo,
            );
            return;
          }
          setComprando(false);
          setPlanoComprando(null);
          showErrorModal(
            "⚠️ Problema na Compra",
            matriculaData?.message || "Não foi possível processar sua compra",
            "error",
          );
          return;
        }
        console.log("✅ [Contratar] Resposta da API:", matriculaData);

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
          matriculaData.data?.matricula_id || matriculaData.data?.matricula?.id;
        const metodoPagamento = String(
          matriculaData.data?.metodo_pagamento || "",
        ).toLowerCase();

        // Se não encontrou em payment_url, procura em outras estruturas
        if (!paymentUrl && matriculaData.data?.pagamento) {
          paymentUrl = matriculaData.data.pagamento.url;
        }

        console.log("🔍 Debug payment_url:", {
          "data keys": Object.keys(matriculaData.data || {}),
          payment_url: paymentUrl,
          matricula_id: matriculaId,
        });

        if (!paymentUrl) {
          console.error(
            "❌ Link de pagamento não encontrado em nenhuma localização",
          );
          setComprando(false);
          setPlanoComprando(null);
          showErrorModal(
            "⚠️ Erro",
            "Link de pagamento não foi gerado. Tente novamente.",
            "error",
          );
          return;
        }

        if (metodoPagamento === "pix") {
          showErrorModal(
            "⚠️ Método de pagamento diferente",
            "O pagamento pendente é PIX. Use a opção PIX para concluir.",
            "warning",
          );
        }

        console.log("💳 Payment URL:", paymentUrl);

        setComprando(false);
        setPlanoComprando(null);

        abrirCheckout(
          paymentUrl,
          matriculaData.data?.gateway ||
            matriculaData.data?.provider ||
            compraResult.gateway,
          metodo,
          opcoes?.tarifaConfirmada || matriculaData.data?.tarifa_cartao || null,
        );
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao processar compra";
        console.error("❌ Erro na compra:", err);
        showErrorModal("❌ Algo Deu Errado", errorMsg, "error");
      } finally {
        setComprando(false);
        setPlanoComprando(null);
      }
    },
    [apiUrl, selectedCicloByPlano, abrirConfirmacaoMigracao, escolherGateway, podeSandboxPix],
  );

  const handlePagarPix = React.useCallback(
    async (plano: Plan) => {
      try {
        setPixLoading(true);
        setPlanoComprando(plano.id);

        const selectedCicloId = selectedCicloByPlano[plano.id];
        const selectedCiclo = plano.ciclos?.find(
          (c) => c.id === selectedCicloId,
        );

        if (!selectedCiclo) {
          showErrorModal(
            "⚠️ Ciclo não selecionado",
            "Por favor, selecione um ciclo antes de pagar.",
            "warning",
          );
          setPixLoading(false);
          setPlanoComprando(null);
          return;
        }

        if (plano.pode_migrar) {
          await abrirConfirmacaoMigracao(plano, selectedCiclo.id, "pix");
          return;
        }

        const cicloAtualMatriculaPix =
          plano.matricula_ativa?.plano_ciclo_id ?? null;
        const statusMatriculaPix = String(
          plano.matricula_ativa?.status_codigo || "",
        ).toLowerCase();
        if (
          statusMatriculaPix !== "pendente" &&
          plano.is_plano_atual &&
          (cicloAtualMatriculaPix == null ||
            Number(cicloAtualMatriculaPix) !== Number(selectedCiclo.id))
        ) {
          await abrirConfirmacaoMigracao(plano, selectedCiclo.id, "pix");
          return;
        }

        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          throw new Error("Token não encontrado");
        }

        const compraResult = await postComRetryGateway({
          url: `${apiUrl}/mobile/comprar-plano`,
          token,
          body: {
            plano_id: plano.id,
            plano_ciclo_id: selectedCiclo.id,
            metodo_pagamento: "pix",
          },
          escolherGateway,
          onEscolherGateway,
          podeSandbox: podeSandboxPix,
        });
        if (compraResult.cancelled) {
          setPixLoading(false);
          setPlanoComprando(null);
          return;
        }
        const matriculaData = compraResult.json;

        if (!compraResult.ok) {
          if (matriculaData?.code === "MIGRACAO_NECESSARIA") {
            await abrirConfirmacaoMigracao(plano, selectedCiclo.id, "pix");
            return;
          }
          if (
            matriculaData?.code === "RENOVACAO_PIX" &&
            matriculaData.data?.matricula_id
          ) {
            const matriculaIdRenov = Number(matriculaData.data.matricula_id);
            const pixResultRenov = await solicitarPix({
              apiUrl,
              token,
              matriculaId: matriculaIdRenov,
              gateway: compraResult.gateway,
              escolherGateway,
              onEscolherGateway,
              podeSandbox: podeSandboxPix,
            });
            const pixJsonRenov = pixResultRenov.json;
            if (pixResultRenov.ok) {
              const pix = pixJsonRenov.data?.pix || {};
              setPixData({
                matricula_id: Number(
                  pixJsonRenov.data?.matricula_id || matriculaIdRenov,
                ),
                valor: Number(
                  pixJsonRenov.data?.valor || selectedCiclo.valor || 0,
                ),
                qr_code: pix.qr_code || null,
                qr_code_base64: pix.qr_code_base64 || null,
                ticket_url: pix.ticket_url || null,
                expires_at: pix.expires_at || null,
              });
              setPixModalVisible(true);
              setPixLoading(false);
              setPlanoComprando(null);
              return;
            }
            if (pixResultRenov.cancelled) {
              setPixLoading(false);
              setPlanoComprando(null);
              return;
            }
          }
          showErrorModal(
            "⚠️ Problema na Compra",
            matriculaData?.message || "Não foi possível processar sua compra",
            "error",
          );
          setPixLoading(false);
          setPlanoComprando(null);
          return;
        }
        console.log("✅ [PIX] Resposta da API:", matriculaData);
        if (!matriculaData.success) {
          const errorMessage =
            matriculaData.message || "Erro desconhecido ao processar compra";
          showErrorModal("❌ Não foi Possível Comprar", errorMessage, "error");
          setPixLoading(false);
          setPlanoComprando(null);
          return;
        }

        // Renovação liberada: segue direto para gerar PIX da matrícula ativa
        if (
          matriculaData.code === "RENOVACAO_PIX" ||
          matriculaData.data?.renovacao
        ) {
          const matriculaIdRenov = Number(matriculaData.data?.matricula_id);
          if (!matriculaIdRenov) {
            showErrorModal(
              "⚠️ Erro",
              "Não foi possível identificar a matrícula para renovar.",
              "error",
            );
            setPixLoading(false);
            setPlanoComprando(null);
            return;
          }
          const pixResultRenov = await solicitarPix({
            apiUrl,
            token,
            matriculaId: matriculaIdRenov,
            escolherGateway,
            onEscolherGateway,
            podeSandbox: podeSandboxPix,
          });
          const pixJsonRenov = pixResultRenov.json;
          if (!pixResultRenov.ok) {
            if (pixResultRenov.cancelled) {
              setPixLoading(false);
              setPlanoComprando(null);
              return;
            }
            showErrorModal(
              "⚠️ Erro ao Gerar PIX",
              pixJsonRenov?.error ||
                pixJsonRenov?.message ||
                "Não foi possível gerar o PIX da renovação.",
              "error",
            );
            setPixLoading(false);
            setPlanoComprando(null);
            return;
          }
          const pixRenov = pixJsonRenov.data?.pix || {};
          setPixData({
            matricula_id: Number(
              pixJsonRenov.data?.matricula_id || matriculaIdRenov,
            ),
            valor: Number(
              pixJsonRenov.data?.valor ||
                matriculaData.data?.valor ||
                selectedCiclo.valor ||
                0,
            ),
            qr_code: pixRenov.qr_code || null,
            qr_code_base64: pixRenov.qr_code_base64 || null,
            ticket_url: pixRenov.ticket_url || null,
            expires_at: pixRenov.expires_at || null,
          });
          setPixModalVisible(true);
          setPixLoading(false);
          setPlanoComprando(null);
          return;
        }

        const metodoPagamento = String(
          matriculaData.data?.metodo_pagamento || "",
        ).toLowerCase();
        if (
          metodoPagamento &&
          metodoPagamento !== "pix" &&
          matriculaData.data?.payment_url
        ) {
          showErrorModal(
            "⚠️ Pagamento pendente no checkout",
            "Já existe um pagamento pendente no checkout. Vamos abrir o link para você concluir.",
            "warning",
          );
          const supported = await Linking.canOpenURL(
            matriculaData.data.payment_url,
          );
          if (supported) {
            await Linking.openURL(matriculaData.data.payment_url);
          }
          setPixLoading(false);
          setPlanoComprando(null);
          return;
        }

        const matriculaId =
          matriculaData.data?.matricula_id || matriculaData.data?.matricula?.id;

        if (!matriculaId) {
          showErrorModal(
            "⚠️ Erro",
            "Não foi possível identificar a matrícula para gerar o PIX.",
            "error",
          );
          setPixLoading(false);
          setPlanoComprando(null);
          return;
        }

        const pixResult = await solicitarPix({
          apiUrl,
          token,
          matriculaId: Number(matriculaId),
          gateway: compraResult.gateway,
          escolherGateway,
          onEscolherGateway,
          podeSandbox: podeSandboxPix,
        });
        const pixJson = pixResult.json;

        if (!pixResult.ok) {
          if (pixResult.cancelled) {
            setPixLoading(false);
            setPlanoComprando(null);
            return;
          }
          const msg =
            pixJson?.message ||
            pixJson?.error ||
            "Não foi possível gerar o PIX.";
          showErrorModal("⚠️ Erro ao Gerar PIX", msg, "error");
          setPixLoading(false);
          setPlanoComprando(null);
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
        setPixModalVisible(true);
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao gerar PIX";
        showErrorModal("❌ Algo Deu Errado", errorMsg, "error");
      } finally {
        setPixLoading(false);
        setPlanoComprando(null);
      }
    },
    [apiUrl, selectedCicloByPlano, abrirConfirmacaoMigracao],
  );

  const renderPlanCard = ({ item: plano }: { item: Plan }) => {
    const ciclos = (plano.ciclos || []).sort((a, b) => a.meses - b.meses);
    const selectedCicloId = selectedCicloByPlano[plano.id];
    const selectedCiclo = ciclos.find((c) => c.id === selectedCicloId);
    const economiaText = selectedCiclo?.economia || null;

    return (
      <View style={styles.planCard}>
        <View style={styles.planHero}>
          <View style={styles.planHeroTop}>
            <View style={styles.planHeroInfo}>
              <Text style={styles.planNome}>{plano.nome}</Text>
              <Text style={styles.planModalidade}>{plano.modalidade.nome}</Text>
            </View>
            {selectedCiclo ? (
              <View style={styles.planPricePill}>
                <Text style={styles.planPricePillValue}>
                  {selectedCiclo.valor_formatado}
                </Text>
                <Text style={styles.planPricePillLabel}>
                  {selectedCiclo.nome}
                </Text>
              </View>
            ) : (
              <View style={styles.planPricePillMuted}>
                <Text style={styles.planPriceHint}>Selecione um ciclo</Text>
              </View>
            )}
          </View>

          {!!selectedCiclo?.valor_mensal_formatado && (
            <Text style={styles.planMonthly}>
              {selectedCiclo.valor_mensal_formatado}/mês
            </Text>
          )}

          {plano.is_plano_atual && (
            <View style={styles.planCurrentBadge}>
              <Feather name="check-circle" size={14} color="#fff" />
              <Text style={styles.planCurrentBadgeText}>
                {plano.label || "Seu plano atual"}
              </Text>
            </View>
          )}
        </View>

        {!!plano.descricao && (
          <Text style={styles.planDescricao}>{plano.descricao}</Text>
        )}

        <View style={styles.planFeaturesGrid}>
          <View style={styles.featurePill}>
            <Feather name="check-circle" size={14} color={colors.primary} />
            <Text style={styles.featureText}>
              {plano.checkins_semanais === 999
                ? "Check-ins ilimitados"
                : `${plano.checkins_semanais} check-ins/semana`}
            </Text>
          </View>
          <View style={styles.featurePill}>
            <Feather name="calendar" size={14} color={colors.primary} />
            <Text style={styles.featureText}>
              {plano.duracao_dias} dias de acesso
            </Text>
          </View>
        </View>

        {economiaText && (
          <Text style={styles.economiaText}>{economiaText}</Text>
        )}

        {ciclos.length > 0 && (
          <View style={styles.ciclosContainer}>
            <Text style={styles.ciclosTitle}>Ciclo</Text>
            <View style={styles.ciclosGrid}>
              {ciclos.map((ciclo) => (
                <TouchableOpacity
                  key={ciclo.id}
                  style={[
                    styles.cicloChip,
                    selectedCicloId === ciclo.id && styles.cicloChipSelected,
                  ]}
                  onPress={() =>
                    setSelectedCicloByPlano({
                      ...selectedCicloByPlano,
                      [plano.id]: ciclo.id,
                    })
                  }
                >
                  <Text
                    style={[
                      styles.cicloChipTitle,
                      selectedCicloId === ciclo.id &&
                        styles.cicloChipTitleSelected,
                    ]}
                  >
                    {ciclo.nome}
                  </Text>
                  <Text
                    style={[
                      styles.cicloChipPrice,
                      selectedCicloId === ciclo.id &&
                        styles.cicloChipPriceSelected,
                    ]}
                  >
                    {ciclo.valor_formatado}
                  </Text>
                  {!!ciclo.valor_mensal_formatado && (
                    <Text style={styles.cicloChipMonthly}>
                      {ciclo.valor_mensal_formatado}/mês
                    </Text>
                  )}
                  {ciclo.economia && (
                    <View style={styles.cicloChipEconomia}>
                      <Text style={styles.cicloChipEconomiaText}>
                        {ciclo.economia}
                      </Text>
                    </View>
                  )}
                </TouchableOpacity>
              ))}
            </View>
          </View>
        )}

        {(() => {
          const opcoesMetodo = opcoesPagamentoVisiveis(
            selectedCiclo?.metodos_pagamento,
            selectedCiclo?.pix_disponivel === true,
          );
          const metodo =
            (metodoByPlano[plano.id] &&
            opcoesMetodo.includes(metodoByPlano[plano.id])
              ? metodoByPlano[plano.id]
              : opcoesMetodo[0]) ?? null;
          const podeRenovar = !!(plano.pode_renovar || plano.pode_pagar);
          const bloquearPorAtivo = !!plano.is_plano_atual && !podeRenovar;
          const canBuy =
            !!selectedCiclo &&
            !bloquearPorAtivo &&
            opcoesMetodo.length > 0 &&
            !!metodo;
          const contexto = plano.pode_migrar
            ? "migrar"
            : podeRenovar
              ? "renovar"
              : "pagar";
          const iconName =
            bloquearPorAtivo
              ? "check"
              : podeRenovar
                ? "refresh-cw"
                : plano.pode_migrar
                  ? "repeat"
                  : metodo === "pix"
                    ? "zap"
                    : metodo === "boleto"
                      ? "file-text"
                      : "credit-card";

          return (
            <>
              {!bloquearPorAtivo && selectedCiclo && opcoesMetodo.length > 0 ? (
                <View style={styles.metodosWrap}>
                  <PaymentMethodOptions
                    opcoes={opcoesMetodo}
                    value={metodo}
                    onChange={(id) =>
                      setMetodoByPlano((prev) => ({ ...prev, [plano.id]: id }))
                    }
                  />
                </View>
              ) : null}
              <TouchableOpacity
                style={[
                  styles.contratarButton,
                  comprando && planoComprando === plano.id
                    ? styles.contratarButtonLoading
                    : null,
                  !selectedCiclo &&
                    bloquearPorAtivo &&
                    styles.contratarButtonDisabled,
                  !selectedCiclo &&
                    !plano.is_plano_atual &&
                    styles.contratarButtonDisabled,
                  selectedCiclo && !canBuy && styles.contratarButtonDisabled,
                ]}
                onPress={() => {
                  if (!metodo) return;
                  if (metodo === "pix") {
                    void handlePagarPix(plano);
                    return;
                  }
                  void handleContratar(plano, metodo);
                }}
                disabled={
                  !selectedCiclo ||
                  bloquearPorAtivo ||
                  (comprando && planoComprando === plano.id) ||
                  (pixLoading && planoComprando === plano.id) ||
                  !canBuy
                }
              >
                {bloquearPorAtivo ? (
                  <>
                    <Feather name="check" size={18} color={ds.onBrand} />
                    <Text style={styles.contratarButtonText}>Plano Ativo</Text>
                  </>
                ) : (comprando || pixLoading) &&
                  planoComprando === plano.id ? (
                  <>
                    <ActivityIndicator color={ds.onBrand} size="small" />
                    <Text style={styles.contratarButtonText}>
                      Processando...
                    </Text>
                  </>
                ) : (
                  <>
                    <Feather name={iconName} size={18} color={ds.onBrand} />
                    <Text style={styles.contratarButtonText}>
                      {selectedCiclo
                        ? metodo
                          ? `${rotuloPagarMetodo(metodo, contexto)} • ${selectedCiclo.valor_formatado}`
                          : "Pagamento indisponível"
                        : "Escolha um ciclo para continuar"}
                    </Text>
                  </>
                )}
              </TouchableOpacity>
            </>
          );
        })()}
      </View>
    );
  };

  // Atualiza sozinho ao voltar para a tela e ao reabrir o app/aba.
  const [vitrineVersao, setVitrineVersao] = useState(0);
  useRefreshOnFocus(() => {
    setVitrineVersao((v) => v + 1);
    return apiUrl ? fetchPlanos(apiUrl, { silent: true }) : undefined;
  }, { onScreenFocus: true });

  // ---- Lista de planos no modelo "TelasPlanos" (PlanCard do design system) ----
  const fmtPreco = (v: number) => {
    const inteiro = Math.abs(v - Math.round(v)) < 0.005;
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
      minimumFractionDigits: inteiro ? 0 : 2,
      maximumFractionDigits: 2,
    }).format(v || 0);
  };
  const valorNormal = (plano: Plan) => Number(plano.valor) || 0;
  const isAvulso = (plano: Plan) => !plano.checkins_semanais && Number(plano.duracao_dias) <= 1;
  const precoPorAula = (plano: Plan) =>
    plano.checkins_semanais > 0 ? valorNormal(plano) / (plano.checkins_semanais * 4) : null;

  const planosAtuais = planos.filter((p) => p.is_plano_atual);
  const planosSemanais = planos
    .filter((p) => !p.is_plano_atual && !isAvulso(p))
    .sort((a, b) => (a.checkins_semanais || 0) - (b.checkins_semanais || 0) || valorNormal(a) - valorNormal(b));
  const planosAvulsos = planos.filter((p) => !p.is_plano_atual && isAvulso(p));
  // Selo só para um fato verificável: o menor preço por aula entre os planos semanais.
  const idMenorPorAula =
    planosSemanais.length > 1
      ? planosSemanais
          .filter((p) => precoPorAula(p) != null)
          .sort((a, b) => (precoPorAula(a) as number) - (precoPorAula(b) as number))[0]?.id
      : undefined;

  const renderPlanCardDs = (plano: Plan) => {
    const avulso = isAvulso(plano);
    const n = Number(plano.checkins_semanais) || 0;
    const porAula = precoPorAula(plano);
    return (
      <PlanCard
        key={plano.id}
        current={!!plano.is_plano_atual}
        name={plano.nome}
        modality={plano.modalidade?.nome}
        detail={plano.is_plano_atual ? plano.label || "Ativo" : avulso ? "Sem mensalidade" : null}
        freq={avulso ? "1" : n > 0 ? `${n}x` : "∞"}
        freqUnit={avulso ? "aula" : n > 0 ? "semana" : "livre"}
        price={fmtPreco(valorNormal(plano))}
        unit={avulso ? "/aula" : "/mês"}
        perClass={
          porAula
            ? `≈ ${new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(porAula)}`
            : null
        }
        tag={!plano.is_plano_atual && plano.id === idMenorPorAula ? "Menor preço por aula" : null}
        actionLabel={
          plano.is_plano_atual
            ? plano.pode_renovar
              ? "Renovar"
              : "Ver meu plano"
            : plano.pode_migrar
              ? "Migrar"
              : "Escolher"
        }
        onSelect={() =>
          router.push({
            pathname: "/plano-detalhes",
            params: { id: plano.id },
          })
        }
      />
    );
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container} edges={["left", "right"]}>
        <AppHeader
          title="Planos"
          onBack={() => {
              if (selectedPlanoId) {
                setSelectedPlanoId(null);
                return;
              }
              router.replace("/(tabs)/checkin");
            }}
          bottom={
            <Stepper tone="brand" steps={["Plano", "Ciclo e pagamento"]} current={selectedPlanoId ? 1 : 0} />
          }
        />
        <View style={styles.centerContent}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Carregando planos...</Text>
        </View>
      </SafeAreaView>
    );
  }

  // Verificar se a permissão está sendo carregada
  if (hasPermission === null) {
    return (
      <SafeAreaView style={styles.container} edges={["left", "right"]}>
        <View style={styles.centerContent}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Verificando permissões...</Text>
        </View>
      </SafeAreaView>
    );
  }

  // Bloquear acesso se o usuário não tem permissão
  if (hasPermission === false) {
    return (
      <SafeAreaView style={styles.container} edges={["left", "right"]}>
        <AppHeader
          title="Planos"
          onBack={() => router.replace("/(tabs)/checkin")}
        />
        <View style={styles.centerContent}>
          <Feather name="lock" size={64} color="#dc2626" />
          <Text
            style={[
              styles.loadingText,
              { fontSize: 18, fontWeight: "600", marginTop: 16 },
            ]}
          >
            Acesso Restrito
          </Text>
          <Text
            style={[
              styles.loadingText,
              {
                fontSize: 14,
                color: "#6b7280",
                marginTop: 8,
                textAlign: "center",
                paddingHorizontal: 20,
              },
            ]}
          >
            Não foi possível validar sua sessão. Faça login novamente.
          </Text>
          <TouchableOpacity
            style={[
              styles.headerBackButton,
              {
                marginTop: 24,
                paddingHorizontal: 16,
                paddingVertical: 10,
                backgroundColor: colors.primary,
              },
            ]}
            onPress={() => router.replace("/(tabs)/checkin")}
          >
            <Text style={{ color: ds.ink, fontWeight: "600" }}>Voltar</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  // Modalidades da lista, para o subtítulo do cabeçalho.
  const modalidadesNomes = Array.from(
    new Set(planos.map((p) => p.modalidade?.nome).filter(Boolean) as string[]),
  );

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      <AppHeader
        title="Planos"
        subtitle={modalidadesNomes.join(" · ") || undefined}
        onBack={() => {
          if (selectedPlanoId) {
            setSelectedPlanoId(null);
            return;
          }
          router.replace("/(tabs)/checkin");
        }}
        actions={[
          { icon: "file-text", label: "Minhas assinaturas", onPress: () => router.push("/minhas-assinaturas") },
          {
            icon: "refresh-cw",
            label: "Atualizar",
            onPress: () => {
              setLoading(true);
              fetchPlanos(apiUrl);
            },
          },
        ]}
        bottom={
          <Stepper tone="brand" steps={["Plano", "Ciclo e pagamento"]} current={selectedPlanoId ? 1 : 0} />
        }
      />

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
          {typeof error === "string" &&
            error.toLowerCase().includes("tenant") && (
              <TouchableOpacity
                style={styles.loginButton}
                onPress={() => router.replace("/(auth)/login")}
              >
                <Feather name="log-in" size={18} color={ds.ink} />
                <Text style={styles.loginButtonText}>Fazer Login</Text>
              </TouchableOpacity>
            )}
        </View>
      ) : planos.length === 0 ? (
        <View style={styles.emptyContainer}>
          <Feather name="inbox" size={48} color="#d1d5db" />
          <Text style={styles.emptyText}>Nenhum plano disponível</Text>
          <Text style={styles.emptySubtext}>
            Não há planos disponíveis no momento
          </Text>
        </View>
      ) : selectedPlanoId ? (
        <ScrollView
          contentContainerStyle={styles.detailContainer}
          showsVerticalScrollIndicator={false}
        >
          {(() => {
            const selectedPlano = planos.find((p) => p.id === selectedPlanoId);
            if (!selectedPlano) return null;
            return renderPlanCard({ item: selectedPlano });
          })()}
        </ScrollView>
      ) : (
        <ScrollView
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
        >
          {planosAtuais.length > 0 ? (
            <Section title="Seu plano atual" bare>
              <View style={styles.planStack}>{planosAtuais.map(renderPlanCardDs)}</View>
            </Section>
          ) : null}
          {planosSemanais.length > 0 ? (
            <Section title="Planos por semana" aside="Por mês" bare>
              <View style={styles.planStack}>{planosSemanais.map(renderPlanCardDs)}</View>
            </Section>
          ) : null}
          {planosAvulsos.length > 0 ? (
            <Section title="Avulso" bare>
              <View style={styles.planStack}>{planosAvulsos.map(renderPlanCardDs)}</View>
            </Section>
          ) : null}
          {/* Pacotes vendidos pelo app (para todos ou liberados para o aluno) */}
          <PacotesVitrine
            recarregar={vitrineVersao}
            onPago={() => fetchPlanos(apiUrl, { silent: true })}
            onErro={(m) => showErrorModal("⚠️ Pacote", m, "warning")}
          />
          <View style={styles.note}>
            <Feather name="info" size={18} color={ds.info} />
            <Text style={styles.noteText}>
              Na próxima etapa você escolhe o ciclo de cobrança e paga com PIX.
            </Text>
          </View>
        </ScrollView>
      )}

      {/* Confirmação da troca de plano (design system · TelasPagamento) */}
      <MigracaoPlanoSheet
        visible={migracaoModalVisible}
        simulacao={migracaoSimulacao}
        metodo={migracaoPendente?.metodo ?? null}
        planoNovoNome={migracaoPendente?.plano?.nome}
        cicloNovoNome={migracaoPendente?.plano?.ciclos?.find((c) => c.id === migracaoPendente?.cicloId)?.nome}
        loading={confirmandoMigracao}
        detalharTaxas={podeSandboxPix}
        onChangeTaxa={(next) =>
          setMigracaoSimulacao((prev) => (prev ? { ...prev, taxa_cartao: next } : prev))
        }
        onConfirm={() => void executarMigracao()}
        onClose={() => {
          if (confirmandoMigracao) return;
          setMigracaoModalVisible(false);
          setMigracaoPendente(null);
          setMigracaoSimulacao(null);
        }}
      />

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

      <CheckoutRedirectModal
        visible={redirectModalVisible}
        url={paymentUrlToOpen}
        provider={paymentProvider}
        metodo={paymentMetodo}
        tarifaCartao={paymentTarifa}
        detalharTaxas={podeSandboxPix}
        resumo={
          taxaCartaoPlano
            ? {
                titulo: (() => {
                  const cicloId = selectedCicloByPlano[taxaCartaoPlano.id];
                  const ciclo = taxaCartaoPlano.ciclos?.find((c) => c.id === cicloId);
                  return [taxaCartaoPlano.nome, ciclo?.nome].filter(Boolean).join(" · ") || null;
                })(),
                subtitulo: taxaCartaoPlano.modalidade?.nome || null,
              }
            : undefined
        }
        onRedirect={() => {
          void irParaCheckout();
        }}
        onClose={fecharCheckout}
      />

      <TaxaCartaoConfirmSheet
        visible={taxaCartaoVisible}
        simulacao={taxaCartaoSimulacao}
        tituloPlano={taxaCartaoPlano?.nome || null}
        loading={taxaCartaoConfirmando}
        detalharTaxas={podeSandboxPix}
        onClose={() => {
          if (!taxaCartaoConfirmando) {
            setTaxaCartaoVisible(false);
            setTaxaCartaoSimulacao(null);
            setTaxaCartaoPlano(null);
          }
        }}
        onConfirm={(sim) => {
          const plano = taxaCartaoPlano;
          if (!plano) return;
          void (async () => {
            setTaxaCartaoConfirmando(true);
            setTaxaCartaoVisible(false);
            try {
              await handleContratar(plano, "cartao", {
                jaSimulouTaxa: true,
                gateway: taxaCartaoGateway,
                installment_count: sim.parcela_count ?? 1,
                tarifaConfirmada: sim,
              });
            } finally {
              setTaxaCartaoConfirmando(false);
            }
          })();
        }}
      />

      {/* PIX: folha inferior do design system */}
      <PixPaymentSheet
        visible={pixModalVisible}
        amount={formatCurrency(pixData?.valor || 0)}
        code={pixData?.qr_code}
        qrBase64={pixData?.qr_code_base64}
        expiresAt={pixData?.expires_at}
        ticketUrl={pixData?.ticket_url}
        onCopy={copyToClipboard}
        onOpenBank={() => {
          if (pixData?.ticket_url) {
            Linking.openURL(pixData.ticket_url);
          }
        }}
        onClose={() => setPixModalVisible(false)}
      />
    </SafeAreaView>
  );
}
const styles = StyleSheet.create({
  noteText: {
    flex: 1,
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "600",
    color: ds.ink,
  },
  note: {
    flexDirection: "row",
    gap: 12,
    alignItems: "flex-start",
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 16,
    backgroundColor: ds.infoSoft,
  },
  planStack: {
    gap: 12,
  },
  container: {
    flex: 1,
    backgroundColor: ds.ground,
  },
  headerTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 18,
    paddingVertical: 16,
    backgroundColor: ds.surface,
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  headerTitleCentered: {
    fontSize: 22,
    fontWeight: "800",
    color: ds.ink,
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
    padding: 16,
    paddingBottom: 32,
    gap: 24,
  },
  listWrapper: {
    flex: 1,
  },
  detailContainer: {
    flexGrow: 1,
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 32,
  },
  stepHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 12,
  },
  stepHeaderPadded: {
    paddingHorizontal: 16,
    paddingTop: 16,
  },
  stepLabel: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "700",
    textTransform: "uppercase",
    letterSpacing: 0.6,
  },
  stepTitle: {
    fontSize: 18,
    fontWeight: "800",
    color: colors.text,
    marginTop: 4,
  },
  stepAction: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 999,
    backgroundColor: "#fff",
    borderWidth: 1,
    borderColor: "#e7ecf3",
  },
  stepActionText: {
    fontSize: 12,
    fontWeight: "700",
    color: colors.primaryText,
  },
  planListCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: "#eff2f6",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.04,
    shadowRadius: 12,
    elevation: 3,
  },
  planListTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: 12,
    marginBottom: 12,
  },
  planListInfo: {
    flex: 1,
    gap: 4,
  },
  planListName: {
    fontSize: 16,
    fontWeight: "800",
    color: colors.text,
  },
  planListModalidade: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: "500",
  },
  planListBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    backgroundColor: "#111827",
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
  },
  planListBadgeText: {
    fontSize: 10,
    color: "#fff",
    fontWeight: "700",
    textTransform: "uppercase",
  },
  planListBottom: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-end",
  },
  planListPriceLabel: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "600",
    marginBottom: 4,
  },
  planListPrice: {
    fontSize: 18,
    fontWeight: "800",
    color: colors.primaryText,
  },
  planListMonthly: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "600",
    marginTop: 2,
  },
  planListCta: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: colors.primary,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 999,
    shadowColor: colors.primary,
    shadowOpacity: 0.18,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 2 },
    elevation: 2,
  },
  planListCtaText: {
    fontSize: 12,
    color: ds.onBrand,
    fontWeight: "700",
  },
  planCard: {
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
  planHero: {
    backgroundColor: "#f8fafc",
    borderRadius: 14,
    padding: 14,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: ds.line,
  },
  planHeroTop: {
    flexDirection: "row",
    alignItems: "flex-start",
    justifyContent: "space-between",
    gap: 12,
  },
  planCurrentBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: "#111827",
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
    marginTop: 10,
    alignSelf: "flex-start",
  },
  planCurrentBadgeText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "700",
  },
  planHeroInfo: {
    flex: 1,
    gap: 4,
  },
  planNome: {
    fontSize: 18,
    fontWeight: "800",
    color: colors.text,
  },
  planModalidade: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: "500",
  },
  planPricePill: {
    backgroundColor: "#fff",
    borderRadius: 12,
    paddingVertical: 8,
    paddingHorizontal: 12,
    alignItems: "flex-end",
    borderWidth: 1,
    borderColor: "#e7ecf3",
  },
  planPricePillMuted: {
    backgroundColor: "#fff",
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 12,
    alignItems: "center",
    borderWidth: 1,
    borderColor: "#e7ecf3",
  },
  planPricePillValue: {
    fontSize: 18,
    fontWeight: "800",
    color: colors.primaryText,
  },
  planPricePillLabel: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "600",
  },
  planMonthly: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "600",
    marginTop: 8,
  },
  planPriceHint: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: "600",
  },
  planDescricao: {
    fontSize: 13,
    color: colors.textMuted,
    lineHeight: 18,
    fontWeight: "500",
    marginBottom: 12,
  },
  planFeaturesGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
    marginBottom: 14,
  },
  featurePill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: "#f8fafc",
    borderRadius: 10,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderWidth: 1,
    borderColor: ds.line,
  },
  economiaText: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: "700",
    marginBottom: 12,
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
  metodosWrap: {
    marginBottom: 12,
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
  contratarButtonDisabled: {
    opacity: 0.5,
    backgroundColor: "#999",
  },
  ciclosContainer: {
    marginVertical: 12,
  },
  ciclosTitle: {
    fontSize: 12,
    fontWeight: "700",
    color: colors.text,
    marginBottom: 8,
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  ciclosGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
  },
  cicloChip: {
    flex: 1,
    minWidth: "47%",
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 12,
    alignItems: "flex-start",
    borderWidth: 1,
    borderColor: "#e7ecf3",
  },
  cicloChipSelected: {
    backgroundColor: "#ecfdf3",
    borderColor: "#16a34a",
    shadowColor: "#16a34a",
    shadowOpacity: 0.18,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 3 },
    elevation: 3,
  },
  cicloChipTitle: {
    fontSize: 12,
    fontWeight: "700",
    color: colors.text,
  },
  cicloChipTitleSelected: {
    color: "#166534",
  },
  cicloChipPrice: {
    fontSize: 15,
    fontWeight: "700",
    color: colors.primaryText,
    marginTop: 6,
  },
  cicloChipPriceSelected: {
    color: "#16a34a",
  },
  cicloChipMonthly: {
    fontSize: 10,
    color: colors.textMuted,
    fontWeight: "600",
    marginTop: 2,
  },
  cicloChipEconomia: {
    marginTop: 6,
    backgroundColor: "#14532d",
    paddingVertical: 3,
    paddingHorizontal: 6,
    borderRadius: 999,
    alignSelf: "flex-start",
  },
  cicloChipEconomiaText: {
    fontSize: 10,
    color: "#fff",
    fontWeight: "700",
  },
  contratarButtonText: {
    fontSize: 14,
    fontWeight: "700",
    color: ds.onBrand,
  },
  pixButton: {
    marginTop: 10,
    backgroundColor: "#32bcad",
    paddingVertical: 14,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
  },
  pixButtonLoading: {
    opacity: 0.7,
  },
  pixButtonText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "700",
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
    borderColor: ds.line,
  },
  pixInfoLabel: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "700",
    textTransform: "uppercase",
  },
  pixInfoValue: {
    fontSize: 13,
    color: colors.text,
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
    width: 220,
    height: 220,
  },
  pixCodeBox: {
    width: "100%",
    maxHeight: 120,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: ds.line,
    backgroundColor: "#fff",
    padding: 10,
    marginBottom: 12,
  },
  pixCodeScroll: {
    width: "100%",
  },
  pixCodeText: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  pixCopyButton: {
    width: "100%",
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
    backgroundColor: "#32bcad",
    marginBottom: 10,
  },
  pixCopyButtonText: {
    color: "#fff",
    fontSize: 14,
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
    color: ds.onBrand,
    fontSize: 15,
    fontWeight: "700",
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
    color: ds.onBrand,
    fontWeight: "600",
    fontSize: 14,
  },
  loginButton: {
    marginTop: 12,
    backgroundColor: "#334155",
    paddingHorizontal: 32,
    paddingVertical: 12,
    borderRadius: 8,
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  loginButtonText: {
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
