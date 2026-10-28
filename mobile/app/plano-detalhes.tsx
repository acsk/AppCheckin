import { copyToClipboard } from "@/src/utils/clipboard";
import { useProtectedRoute } from "@/hooks/useProtectedRoute";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { postComRetryGateway, solicitarPix, usuarioPodeVerSandboxPagamento } from "@/src/utils/pagamentoPix";
import { useGatewayPicker } from "@/src/hooks/useGatewayPicker";
import { authService } from "@/src/services/authService";
import Badge from "@/src/components/ds/Badge";
import Button from "@/src/components/ds/Button";
import PixPaymentSheet from "@/src/components/ds/PixPaymentSheet";
import PlanOption from "@/src/components/ds/PlanOption";
import Section from "@/src/components/ds/Section";
import Stepper from "@/src/components/ds/Stepper";
import { colors } from "@/src/theme/colors";
import { handleUnauthorizedResponse } from "@/src/utils/authHelpers";
import { isSessionExpiredVisible } from "@/src/utils/sessionExpired";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useLocalSearchParams, useRouter } from "expo-router";
import React, { useEffect, useRef, useState } from "react";
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
import CicloPaymentModes from "@/src/components/ds/CicloPaymentModes";
import { chamarRecorrencia, type Oferta } from "@/src/services/recorrencia";
import { precisaRevisaoMigracao, metodosAssinaturaDisponiveis } from "@/src/utils/cicloPagamento";
import ContratacaoCicloSheet from "@/src/components/ContratacaoCicloSheet";
import TaxaCartaoConfirmSheet from "@/src/components/ds/TaxaCartaoConfirmSheet";
import { ds } from "@/src/components/ds/tokens";
import {
  opcoesPagamentoVisiveis,
  rotuloPagarMetodo,
  type MetodoPagamentoOpcao,
} from "@/src/utils/metodosPagamento";
import { simularTaxaCartao, type TaxaCartaoSimulacao } from "@/src/utils/taxaCartao";
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
  economia: string | null;
  economia_valor: string | null;
  /** Fidelização: assinatura mensal com o preço do ciclo dividido por mês */
  cobranca_mensal?: boolean;
  fidelizacao_meses?: number | null;
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
  pode_migrar?: boolean;
  pode_renovar?: boolean;
  pode_pagar?: boolean;
  motivo_renovacao?: string | null;
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
    plano_ciclo_id?: number | null;
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

  // Verificar autenticação - se não autenticado, redireciona
  const { isLoading: isAuthChecking } = useProtectedRoute();
  const { escolherGateway } = useGatewayPicker();
  const [podeSandboxPix, setPodeSandboxPix] = useState(false);
  const onEscolherGateway = () => {
    setPixLoading(false);
    setComprando(false);
  };

  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [plano, setPlano] = useState<PlanoDetalhes | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [apiUrl, setApiUrl] = useState("");
  const [selectedCicloId, setSelectedCicloId] = useState<number | null>(null);
  // Modo e método ficam na tela; a folha de assinatura recebe somente a revisão e o aceite.
  const [contratacaoAberta, setContratacaoAberta] = useState(false);
  const [modoCobranca, setModoCobranca] = useState<'avista' | 'assinatura'>('avista');
  const [ofertas, setOfertas] = useState<Oferta[]>([]);
  const [carregandoOfertas, setCarregandoOfertas] = useState(false);
  const [tentativaOfertas, setTentativaOfertas] = useState(0);
  const [creditoPrevia, setCreditoPrevia] = useState<MigracaoSimulacao | null>(null);
  const [revisaoTipo, setRevisaoTipo] = useState<'migracao' | 'compra'>('migracao');
  const [revisaoGateway, setRevisaoGateway] = useState<string | undefined>();

  useEffect(() => { setModoCobranca('avista'); }, [selectedCicloId]);
  useEffect(() => {
    setOfertas([]);
    if (!plano?.id || !plano.ciclos?.some(c => c.cobranca_mensal)) return;
    let ativo = true;
    setCarregandoOfertas(true);
    chamarRecorrencia('GET', `/ofertas?plano_id=${plano.id}`)
      .then(({ ok, json }) => { if (ativo) setOfertas(ok ? json?.data?.ofertas || [] : []); })
      .catch(() => { if (ativo) setOfertas([]); })
      .finally(() => { if (ativo) setCarregandoOfertas(false); });
    return () => { ativo = false; };
  }, [plano?.id, tentativaOfertas]);

  useEffect(() => {
    setCreditoPrevia(null);
    if (!plano || !selectedCicloId || !precisaRevisaoMigracao(plano, selectedCicloId)) return;
    let ativo = true;
    const timer = setTimeout(async () => {
      try {
        const token = await AsyncStorage.getItem('@appcheckin:token');
        if (!token || !ativo) return;
        const response = await fetch(`${apiUrl}/mobile/simular-migracao`, { method: 'POST', headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ plano_id: plano.id, plano_ciclo_id: selectedCicloId }) });
        const json = await response.json();
        if (ativo && response.ok && json.success) setCreditoPrevia(json.data);
      } catch { /* A revisão busca novamente os valores oficiais antes de confirmar. */ }
    }, 300);
    return () => { ativo = false; clearTimeout(timer); };
  }, [plano, selectedCicloId, apiUrl]);
  const [comprando, setComprando] = useState(false);
  const [errorModalVisible, setErrorModalVisible] = useState(false);
  const [errorModalData, setErrorModalData] = useState<ErrorModalData>({
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
  const [pixChecking, setPixChecking] = useState(false);
  const [pixPollingActive, setPixPollingActive] = useState(false);
  const [pixStatusMessage, setPixStatusMessage] = useState<string | null>(null);
  const [pixWaitSeconds, setPixWaitSeconds] = useState(0);
  const [migracaoModalVisible, setMigracaoModalVisible] = useState(false);
  const [migracaoSimulacao, setMigracaoSimulacao] =
    useState<MigracaoSimulacao | null>(null);
  const [metodoSelecionado, setMetodoSelecionado] =
    useState<MetodoPagamentoOpcao | null>(null);
  const [migracaoMetodoPendente, setMigracaoMetodoPendente] = useState<
    MetodoPagamentoOpcao | null
  >(null);
  const [confirmandoMigracao, setConfirmandoMigracao] = useState(false);
  const [taxaCartaoVisible, setTaxaCartaoVisible] = useState(false);
  const [taxaCartaoSimulacao, setTaxaCartaoSimulacao] =
    useState<TaxaCartaoSimulacao | null>(null);
  const [taxaCartaoGateway, setTaxaCartaoGateway] = useState<string | undefined>();
  const [taxaCartaoConfirmando, setTaxaCartaoConfirmando] = useState(false);
  const [paymentTarifa, setPaymentTarifa] = useState<TaxaCartaoSimulacao | null>(null);
  const pixCheckRunning = useRef(false);
  const confirmacaoPagamentoRef = useRef(false);

  useEffect(() => {
    const ciclo = plano?.ciclos?.find((item) => item.id === selectedCicloId);
    const opcoes = modoCobranca === 'assinatura'
      ? metodosAssinaturaDisponiveis(ofertas.find(o => Number(o.plano_ciclo_id) === Number(selectedCicloId))?.metodos)
      : opcoesPagamentoVisiveis(ciclo?.metodos_pagamento, ciclo?.pix_disponivel === true);
    setMetodoSelecionado((atual) => {
      if (atual && opcoes.includes(atual)) {
        return atual;
      }
      return opcoes[0] ?? null;
    });
  }, [plano, selectedCicloId, modoCobranca, ofertas]);

  const handleBack = () => {
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

        if (await handleUnauthorizedResponse(response)) {
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

        // Preferir ciclo da matrícula atual; senão o primeiro
        const ciclos = (mergedPlano.ciclos || []).sort(
          (a: Ciclo, b: Ciclo) => a.meses - b.meses,
        );
        if (ciclos.length > 0) {
          const cicloAtualId = mergedPlano.matricula_ativa?.plano_ciclo_id;
          const cicloAtual = cicloAtualId
            ? ciclos.find((c: Ciclo) => c.id === cicloAtualId)
            : null;
          setSelectedCicloId(cicloAtual?.id ?? ciclos[0].id);
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

        console.log("✅ Usuário autenticado com permissão para acessar plano");
        setPodeSandboxPix(usuarioPodeVerSandboxPagamento(user));
        setHasPermission(true);
      } catch (err) {
        console.error("❌ Erro ao verificar permissão:", err);
        setHasPermission(false);
        router.replace("/planos");
      }
    };

    checkPermission();
  }, [router]);

  useEffect(() => {
    const init = async () => {
      const url = getApiUrlRuntime();
      setApiUrl(url);
      if (id && hasPermission) {
        await fetchPlanoDetalhes(url, id);
      }
    };
    init();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, hasPermission]);

  const abrirCheckout = (
    url: string,
    provider?: string | null,
    metodo?: string | null,
    tarifa?: TaxaCartaoSimulacao | null,
    revisado = false,
  ) => {
    setPaymentUrlToOpen(url);
    setPaymentProvider(provider ?? null);
    setPaymentMetodo(metodo ?? null);
    setPaymentTarifa(tarifa ?? null);
    if (revisado) {
      setRedirectModalVisible(false);
      void Linking.openURL(url).catch(() => setRedirectModalVisible(true));
      return;
    }
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

  useEffect(() => {
    if (!pixPollingActive) return;
    const interval = setInterval(() => {
      checkPixAprovacao(false);
    }, 3000);
    return () => clearInterval(interval);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pixPollingActive, pixData?.matricula_id, apiUrl]);

  useEffect(() => {
    if (!pixPollingActive) return;
    const timer = setInterval(() => {
      setPixWaitSeconds((prev) => prev + 1);
    }, 1000);
    return () => clearInterval(timer);
  }, [pixPollingActive]);

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
      const gateway = json?.data?.pagamento_gateway;
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

      if (gateway?.aguardando_ativacao) {
        setPixStatusMessage(
          gateway.mensagem ||
            `PIX ${gateway.status_label || "recebido"} no Asaas. Aguarde a ativação da matrícula.`,
        );
        setPixPollingActive(true);
        if (manual) {
          setPixWaitSeconds(0);
        }
        return;
      }

      if (manual) {
        const pendingMessage =
          statusGateway === "pending"
            ? "Pagamento ainda pendente. Vamos continuar verificando a cada 3 segundos."
            : "Pagamento ainda não confirmado. Vamos continuar verificando a cada 3 segundos.";
        setPixStatusMessage(pendingMessage);
        setPixPollingActive(true);
        setPixWaitSeconds(0);
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

  const executarMigracao = async (
    metodo: MetodoPagamentoOpcao,
    installmentCount?: number,
    tarifaConfirmada?: TaxaCartaoSimulacao | null,
  ) => {
    if (!plano || !selectedCicloId) return;
    const token = await AsyncStorage.getItem("@appcheckin:token");
    if (!token) throw new Error("Token não encontrado");

    console.log("🔄 [Migrar] Enviando...", {
      plano_id: plano.id,
      plano_ciclo_id: selectedCicloId,
      metodo_pagamento: metodo,
      apiUrl,
    });

    const migrarResult = await postComRetryGateway({
      url: `${apiUrl}/mobile/migrar-plano`,
      token,
      body: {
        plano_id: plano.id,
        plano_ciclo_id: selectedCicloId,
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
      throw new Error(
        data.message ||
          data.error ||
          `Não foi possível migrar o plano (${data.code || "erro de integração"})`,
      );
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
      // Recarrega detalhes com o novo ciclo
      if (id && apiUrl) {
        void fetchPlanoDetalhes(apiUrl, String(id));
      }
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
      throw new Error("Link de pagamento não foi gerado");
    }

    abrirCheckout(
      paymentUrl,
      data.data?.gateway || data.data?.provider || migrarResult.gateway,
      metodo,
      tarifaConfirmada || data.data?.tarifa_cartao || null,
      true,
    );
  };

  const abrirConfirmacaoMigracao = async (metodo: MetodoPagamentoOpcao) => {
    if (!plano || !selectedCicloId) {
      throw new Error("Selecione um ciclo antes de migrar");
    }

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
        plano_ciclo_id: selectedCicloId,
      }),
    });

    const simText = await simResponse.text();
    let simData: {
      success?: boolean;
      message?: string;
      data?: MigracaoSimulacao;
    } = {};
    try {
      simData = simText ? JSON.parse(simText) : {};
    } catch {
      throw new Error("Resposta inválida ao simular migração");
    }

    if (!simResponse.ok || !simData.success) {
      throw new Error(simData.message || "Não foi possível simular a migração");
    }

    const simulacao = simData.data || null;
    if (!simulacao) throw new Error("A API não retornou os valores da revisão.");
    if (
      metodo === "cartao" &&
      simulacao &&
      Number(simulacao.valor_parcela ?? 0) > 0
    ) {
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
      if (!taxaRes.ok || !taxaRes.json?.data) throw new Error(taxaRes.json?.message || taxaRes.json?.error || "Não foi possível calcular as taxas do cartão.");
      if (taxaRes.json?.data) {
        simulacao.taxa_cartao = taxaRes.json.data;
      }
    }

    setRevisaoTipo("migracao");
    setMigracaoSimulacao(simulacao);
    setMigracaoMetodoPendente(metodo);
    setMigracaoModalVisible(true);
  };

  const executarMigracaoConfirmada = async () => {
    const metodo = migracaoMetodoPendente;
    const installmentCount = migracaoSimulacao?.taxa_cartao?.parcela_count;
    const tarifaConfirmada = migracaoSimulacao?.taxa_cartao ?? null;
    if (!metodo) {
      showErrorModal(
        "⚠️ Sessão expirada",
        "Abra a migração novamente e confirme o pagamento.",
        "warning",
      );
      return;
    }
    if (confirmandoMigracao) return;

    try {
      setConfirmandoMigracao(true);
      // Fecha o modal de confirmação antes da API para o feedback (sucesso/erro) ficar visível
      setMigracaoModalVisible(false);
      setMigracaoSimulacao(null);
      setMigracaoMetodoPendente(null);
      await executarMigracao(metodo, installmentCount, tarifaConfirmada);
    } catch (err) {
      const errorMsg =
        err instanceof Error ? err.message : "Erro ao migrar plano";
      console.error("❌ [Migrar] Falha:", errorMsg);
      showErrorModal("❌ Algo Deu Errado", errorMsg, "error");
    } finally {
      setConfirmandoMigracao(false);
    }
  };

  const handleContratar = async (
    metodo: MetodoPagamentoOpcao = "cartao",
    opcoes?: {
      jaSimulouTaxa?: boolean;
      revisado?: boolean;
      gateway?: string;
      installment_count?: number;
      tarifaConfirmada?: TaxaCartaoSimulacao | null;
    },
  ) => {
    if (!plano || !selectedCicloId) return;

    const selectedCiclo = plano.ciclos?.find((c) => c.id === selectedCicloId);
    console.log("🧪 [Contratar] selectedCiclo:", {
      id: selectedCiclo?.id,
      nome: selectedCiclo?.nome,
      permite_recorrencia: selectedCiclo?.permite_recorrencia,
      pix_disponivel: selectedCiclo?.pix_disponivel,
      metodos_pagamento: selectedCiclo?.metodos_pagamento,
    });
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

      if (plano.pode_migrar) {
        await abrirConfirmacaoMigracao(metodo);
        return;
      }

      // Mesmo plano, outro ciclo → migração com crédito/diferença
      const statusMatriculaCheckout = String(
        plano.matricula_ativa?.status_codigo || "",
      ).toLowerCase();
      const cicloAtualMatricula = plano.matricula_ativa?.plano_ciclo_id ?? null;
      if (
        statusMatriculaCheckout !== "pendente" &&
        plano.is_plano_atual &&
        (cicloAtualMatricula == null ||
          Number(cicloAtualMatricula) !== Number(selectedCiclo.id))
      ) {
        await abrirConfirmacaoMigracao(metodo);
        return;
      }

      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Token não encontrado");

      const userJson = await AsyncStorage.getItem("@appcheckin:user");
      if (!userJson) throw new Error("Dados do usuário não encontrados");

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
          setTaxaCartaoVisible(true);
          return;
        }
      }

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
        return;
      }
      const data = compraResult.json;

      console.log("📡 Status:", compraResult.status);

      if (!compraResult.ok) {
        if (data?.code === "MIGRACAO_NECESSARIA") {
          await abrirConfirmacaoMigracao(metodo);
          return;
        }
        showErrorModal(
          "⚠️ Problema na Compra",
          data?.message || "Não foi possível processar sua compra",
          "error",
        );
        return;
      }
      console.log("✅ [Contratar] Resposta:", data);
      console.log("✅ [Contratar] payment_url:", data?.data?.payment_url);

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
      const metodoPagamentoResposta = String(
        data.data?.metodo_pagamento || "",
      ).toLowerCase();

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

      if (metodoPagamentoResposta === "pix" && metodo !== "pix") {
        showErrorModal(
          "⚠️ Método de pagamento diferente",
          "O pagamento pendente é PIX. Use a opção PIX para concluir.",
          "warning",
        );
      }

      abrirCheckout(
        paymentUrl,
        data.data?.gateway || data.data?.provider || compraResult.gateway,
        metodo,
        opcoes?.tarifaConfirmada || data.data?.tarifa_cartao || null,
        opcoes?.revisado === true,
      );
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
    console.log("🧪 [PIX] selectedCiclo:", {
      id: selectedCiclo?.id,
      nome: selectedCiclo?.nome,
      permite_recorrencia: selectedCiclo?.permite_recorrencia,
      pix_disponivel: selectedCiclo?.pix_disponivel,
      metodos_pagamento: selectedCiclo?.metodos_pagamento,
    });
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

      if (plano.pode_migrar) {
        await abrirConfirmacaoMigracao("pix");
        return;
      }

      const statusMatricula = String(
        plano.matricula_ativa?.status_codigo || "",
      ).toLowerCase();
      // Pendente: não migrar (evita crédito indevido). Segue compra/PIX da pendência.
      const cicloAtualMatricula = plano.matricula_ativa?.plano_ciclo_id ?? null;
      if (
        statusMatricula !== "pendente" &&
        plano.is_plano_atual &&
        (cicloAtualMatricula == null ||
          Number(cicloAtualMatricula) !== Number(selectedCiclo.id))
      ) {
        await abrirConfirmacaoMigracao("pix");
        return;
      }

      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Token não encontrado");

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
        return;
      }
      const data = compraResult.json;

      if (!compraResult.ok) {
        if (data?.code === "MIGRACAO_NECESSARIA") {
          await abrirConfirmacaoMigracao("pix");
          return;
        }
        showErrorModal(
          "⚠️ Problema na Compra",
          data?.message || "Não foi possível processar sua compra",
          "error",
        );
        return;
      }
      console.log("✅ [PIX] Resposta:", data);
      if (!data.success) {
        showErrorModal(
          "❌ Não foi Possível Comprar",
          data.message || "Erro desconhecido ao processar compra",
          "error",
        );
        return;
      }

      const metodoPagamento = String(
        data.data?.metodo_pagamento || "",
      ).toLowerCase();
      if (
        metodoPagamento &&
        metodoPagamento !== "pix" &&
        data.data?.payment_url
      ) {
        showErrorModal(
          "⚠️ Pagamento pendente no checkout",
          "Já existe um pagamento pendente no checkout. Vamos abrir o link para você concluir.",
          "warning",
        );
        const supported = await Linking.canOpenURL(data.data.payment_url);
        if (supported) {
          await Linking.openURL(data.data.payment_url);
        }
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
          return;
        }
        const msg =
          pixJson?.message ||
          pixJson?.error ||
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
      setPixWaitSeconds(0);
      setPixModalVisible(true);
    } catch (err) {
      const errorMsg = err instanceof Error ? err.message : "Erro ao gerar PIX";
      showErrorModal("❌ Algo Deu Errado", errorMsg, "error");
    } finally {
      setPixLoading(false);
    }
  };

  const revisarPagamento = async () => {
    const ciclo = plano?.ciclos?.find(c => c.id === selectedCicloId);
    if (!plano || !ciclo || !metodoSelecionado || comprando || pixLoading) return;
    if (modoCobranca === 'assinatura') { setContratacaoAberta(true); return; }
    setComprando(true);
    try {
      if (precisaRevisaoMigracao(plano, ciclo.id)) { await abrirConfirmacaoMigracao(metodoSelecionado); return; }
      let taxa: TaxaCartaoSimulacao | null = null;
      let gateway: string | undefined;
      if (metodoSelecionado === 'cartao') {
        const token = await AsyncStorage.getItem('@appcheckin:token');
        if (!token) throw new Error('Sessão expirada. Entre novamente.');
        const cotacao = await simularTaxaCartao({ apiUrl, token, valor: Number(ciclo.valor), escolherGateway, onEscolherGateway, podeSandbox: podeSandboxPix });
        if ('cancelled' in cotacao && cotacao.cancelled) return;
        if (!cotacao.ok || !cotacao.json?.data) throw new Error(cotacao.json?.message || cotacao.json?.error || 'Não foi possível revisar o pagamento.');
        taxa = cotacao.json.data;
        gateway = cotacao.gateway || taxa?.provider;
      }
      setRevisaoTipo('compra'); setRevisaoGateway(gateway);
      setMigracaoSimulacao({ plano_novo: { nome: plano.nome, ciclo_nome: ciclo.nome, valor_formatado: ciclo.valor_formatado }, valor_parcela: Number(ciclo.valor), valor_parcela_formatado: ciclo.valor_formatado, taxa_cartao: taxa });
      setMigracaoMetodoPendente(metodoSelecionado); setMigracaoModalVisible(true);
    } catch (e) { showErrorModal('Não foi possível revisar', e instanceof Error ? e.message : 'Tente novamente.', 'warning'); }
    finally { setComprando(false); }
  };

  const confirmarRevisao = async () => {
    if (confirmacaoPagamentoRef.current) return;
    confirmacaoPagamentoRef.current = true;
    if (revisaoTipo === 'migracao') {
      try { await executarMigracaoConfirmada(); } finally { confirmacaoPagamentoRef.current = false; }
      return;
    }
    const metodo = migracaoMetodoPendente, taxa = migracaoSimulacao?.taxa_cartao;
    if (!metodo || confirmandoMigracao) { confirmacaoPagamentoRef.current = false; return; }
    setConfirmandoMigracao(true); setMigracaoModalVisible(false);
    try {
      if (metodo === 'pix') await handlePagarPix();
      else await handleContratar(metodo, { jaSimulouTaxa: true, revisado: true, gateway: revisaoGateway, installment_count: taxa?.parcela_count ?? 1, tarifaConfirmada: taxa });
    } finally { confirmacaoPagamentoRef.current = false; setConfirmandoMigracao(false); setMigracaoSimulacao(null); setMigracaoMetodoPendente(null); }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container} edges={["left", "right"]}>
        <AppHeader
          title="Detalhes do Plano"
          onBack={handleBack}
          bottom={
            <Stepper tone="brand" steps={["Plano", "Ciclo e pagamento"]} current={1} />
          }
        />
        <View style={styles.centerContent}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Carregando detalhes...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (error || !plano) {
    return (
      <SafeAreaView style={styles.container} edges={["left", "right"]}>
        <AppHeader
          title="Detalhes do Plano"
          onBack={handleBack}
          bottom={
            <Stepper tone="brand" steps={["Plano", "Ciclo e pagamento"]} current={1} />
          }
        />
        <View style={styles.errorContainer}>
          <Feather name="alert-circle" size={48} color="#dc2626" />
          <Text style={styles.errorTitle}>Erro ao carregar plano</Text>
          <Text style={styles.errorMessage}>
            {error || "Plano não encontrado"}
          </Text>
          <TouchableOpacity style={styles.retryButton} onPress={handleBack}>
            <Feather name="arrow-left" size={18} color={ds.ink} />
            <Text style={styles.retryButtonText}>Voltar aos Planos</Text>
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
      </SafeAreaView>
    );
  }

  const ciclos = (plano.ciclos || []).sort((a, b) => a.meses - b.meses);
  const selectedCiclo = ciclos.find((c) => c.id === selectedCicloId);
  const ofertaSelecionada = ofertas.find(o => Number(o.plano_ciclo_id) === Number(selectedCicloId)) || null;
  const opcoesMetodo = modoCobranca === 'assinatura'
    ? metodosAssinaturaDisponiveis(ofertaSelecionada?.metodos)
    : opcoesPagamentoVisiveis(selectedCiclo?.metodos_pagamento, selectedCiclo?.pix_disponivel === true);
  const canPix = opcoesMetodo.includes("pix");
  const canCheckout =
    opcoesMetodo.includes("cartao") || opcoesMetodo.includes("boleto");
  const canBuy =
    !!selectedCiclo &&
    !comprando &&
    !pixLoading &&
    opcoesMetodo.length > 0 &&
    !!metodoSelecionado;
  const statusCodigoRaw =
    plano.status_codigo ||
    (typeof plano.status === "string" ? plano.status : plano.status?.codigo) ||
    plano.matricula_ativa?.status_codigo ||
    (plano as { status_codigo_plano?: string }).status_codigo_plano ||
    (plano as { status_plano?: string }).status_plano ||
    (plano as { matricula_status_codigo?: string }).matricula_status_codigo ||
    (
      plano as {
        matricula?: { status_codigo?: string; status?: { codigo?: string } };
      }
    ).matricula?.status_codigo ||
    (plano as { matricula?: { status?: { codigo?: string } } }).matricula
      ?.status?.codigo ||
    null;
  const statusNomeRaw =
    (typeof plano.status === "string" ? plano.status : plano.status?.nome) ||
    plano.matricula_ativa?.status ||
    (plano as { status_nome?: string }).status_nome ||
    plano.label ||
    "";
  const statusCodigo =
    typeof statusCodigoRaw === "string" ? statusCodigoRaw.toLowerCase() : null;
  const statusNome =
    typeof statusNomeRaw === "string" ? statusNomeRaw.toLowerCase() : "";
  const isPendente =
    statusCodigo === "pendente" || statusNome.includes("pendente");
  const podeRenovar = !!(plano.pode_renovar || plano.pode_pagar);
  const isPlanoAtivo = !!plano.is_plano_atual && !isPendente && !podeRenovar;
  const cicloAtualId = plano.matricula_ativa?.plano_ciclo_id ?? null;
  // Plano atual ainda vigente: a assinatura com fidelidade só pode começar depois que ele vencer
  const acessoAtual = plano.is_plano_atual && !isPendente ? (plano.matricula_ativa?.data_vencimento || "").slice(0, 10) : "";
  const assinaturaDisponivelEm = acessoAtual && acessoAtual >= new Date().toISOString().slice(0, 10)
    ? acessoAtual.split("-").reverse().join("/")
    : null;
  const cicloDiferenteDoAtual =
    isPlanoAtivo &&
    !!selectedCiclo &&
    (cicloAtualId == null || selectedCiclo.id !== cicloAtualId);
  const podeMigrarAgora = !!plano.pode_migrar || cicloDiferenteDoAtual;
  // Plano ativo sem renovação e sem troca de ciclo: botão estático.
  const bloquearCompraPorAtivo =
    isPlanoAtivo && !podeRenovar && !cicloDiferenteDoAtual;
  // Preço cheio do ciclo mensal riscado quando o ciclo escolhido sai mais barato/mês.
  const mensais = ciclos.map((c) => ({
    id: c.id,
    mensal: Number(c.valor_mensal) || (c.meses ? Number(c.valor) / c.meses : Number(c.valor)),
    desconto: Number(c.desconto_percentual) || 0,
  }));
  const maiorMensal = mensais.length ? Math.max(...mensais.map((m) => m.mensal)) : 0;
  const cicloMelhorPrecoId =
    mensais.filter((m) => m.desconto > 0).sort((x, y) => x.mensal - y.mensal)[0]?.id ?? null;

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      {/* Cabeçalho no degradê da logo: modalidade e nome do plano */}
      <AppHeader
        eyebrow={plano.modalidade?.nome || "Detalhes do plano"}
        title={plano.nome}
        onBack={handleBack}
        bottom={
          <Stepper tone="brand" steps={["Plano", "Ciclo e pagamento"]} current={1} />
        }
        right={
          isPlanoAtivo || podeRenovar ? (
            <Badge tone="solid" icon={podeRenovar ? "refresh-cw" : "check"}>
              {podeRenovar ? "Renovar" : "Seu plano"}
            </Badge>
          ) : null
        }
      />

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
      >
        {/* O que está incluso: três fatos */}
        <View style={styles.factsCard}>
          <View style={styles.fact}>
            <Text style={styles.factK}>AULAS</Text>
            <Text style={styles.factV}>
              {plano.checkins_semanais ? `${plano.checkins_semanais}/semana` : "Livre"}
            </Text>
          </View>
          <View style={[styles.fact, styles.factDivider]}>
            <Text style={styles.factK}>{assinaturaDisponivelEm ? "SEU PLANO" : "DURAÇÃO"}</Text>
            <Text style={styles.factV} numberOfLines={1}>
              {assinaturaDisponivelEm ? `Até ${assinaturaDisponivelEm.slice(0, 5)}` : plano.duracao_texto ||
                `${plano.duracao_dias} ${plano.duracao_dias === 1 ? "dia" : "dias"}`}
            </Text>
          </View>
          <View style={[styles.fact, styles.factDivider]}>
            <Text style={styles.factK}>{creditoPrevia?.credito?.valor_formatado ? "SEU CRÉDITO" : "MENSAL"}</Text>
            <Text style={styles.factV} numberOfLines={1}>
              {creditoPrevia?.credito?.valor_formatado || plano.valor_formatado || `${formatCurrency(Number(plano.valor) || 0)}/mês`}
            </Text>
          </View>
        </View>

        {podeRenovar &&
        (plano.motivo_renovacao === "limite_checkins_ciclo" || isPendente) ? (
          <View style={styles.pendingBanner}>
            <Feather name="refresh-cw" size={18} color={ds.warning} />
            <View style={styles.pendingBannerContent}>
              <Text style={styles.pendingBannerTitle}>Renovação disponível</Text>
              <Text style={styles.pendingBannerText}>
                Você usou todos os check-ins deste ciclo. Renove o plano para
                continuar treinando.
              </Text>
            </View>
          </View>
        ) : isPendente ? (
          <View style={styles.pendingBanner}>
            <Feather name="clock" size={18} color={ds.warning} />
            <View style={styles.pendingBannerContent}>
              <Text style={styles.pendingBannerTitle}>Pagamento pendente</Text>
              <Text style={styles.pendingBannerText}>
                Você já iniciou a contratação deste plano. Conclua o pagamento
                para ativar a matrícula.
              </Text>
            </View>
          </View>
        ) : null}

        {/* Ciclos: preço por mês em destaque */}
        {ciclos.length > 0 && (
          <Section title="Escolha o ciclo" aside="Preço por mês" bare>
            <View style={styles.ciclosList} accessibilityRole="radiogroup" accessibilityLabel="Ciclo de cobrança">
              {ciclos.map((ciclo) => {
                const mensal = Number(ciclo.valor_mensal) || (ciclo.meses ? Number(ciclo.valor) / ciclo.meses : Number(ciclo.valor));
                const desconto = Number(ciclo.desconto_percentual) || 0;
                // A API manda "R$ 40,00 de economia": prefixar "Economize" duplicava a palavra
                const economiaValor = ciclo.economia_valor
                  ? /econom/i.test(ciclo.economia_valor)
                    ? ciclo.economia_valor.replace(/^r\$/i, "R$").replace(/\s+de economia\.?$/i, "").replace(/^(?!econom)/i, "economize ")
                    : `Economize ${ciclo.economia_valor}`
                  : null;
                return (
                  <View key={ciclo.id}>
                  <PlanOption
                    label={ciclo.nome}
                    detail={`${ciclo.meses} ${ciclo.meses === 1 ? "mês" : "meses"}`}
                    monthly={ciclo.valor_mensal_formatado || formatCurrency(mensal)}
                    fullMonthly={maiorMensal && mensal < maiorMensal - 0.009 ? formatCurrency(maiorMensal) : null}
                    total={ciclo.valor_formatado}
                    saving={desconto > 0 ? `−${Math.round(desconto)}%` : null}
                    savingAmount={economiaValor}
                    tag={ciclos.length > 1 && cicloMelhorPrecoId === ciclo.id ? "Melhor preço" : null}
                    selected={selectedCicloId === ciclo.id}
                    onSelect={() => setSelectedCicloId(ciclo.id)}
                  >
                  {selectedCicloId === ciclo.id && ciclo.cobranca_mensal ? <>
                    <CicloPaymentModes value={modoCobranca} onChange={setModoCobranca} total={ciclo.valor_formatado} mensal={ciclo.valor_mensal_formatado} meses={ciclo.meses}
                      fidelidadeMeses={ciclo.meses <= 1 ? 0 : ofertaSelecionada?.fidelizacao_meses ?? ciclo.fidelizacao_meses ?? ciclo.meses}
                      bloqueio={assinaturaDisponivelEm ? `Libera em ${assinaturaDisponivelEm}, quando seu plano atual vencer` : carregandoOfertas ? 'Consultando disponibilidade…' : !ofertaSelecionada ? 'Assinatura indisponível no momento.' : null} />
                    {!carregandoOfertas && !ofertaSelecionada ? <Button size="sm" variant="ghost" onPress={() => setTentativaOfertas(v => v + 1)}>Consultar assinatura novamente</Button> : null}
                  </> : null}
                  </PlanOption>
                  </View>
                );
              })}
            </View>
          </Section>
        )}

        {/* Sem ciclo escolhido o painel de pagamento não aparece */}
        {!bloquearCompraPorAtivo && ciclos.length > 0 && !selectedCiclo ? (
          <View style={styles.cicloNote}>
            <Feather name="alert-circle" size={18} color={ds.inkMuted} />
            <Text style={styles.cicloNoteText}>Escolha um ciclo para ver as formas de pagamento.</Text>
          </View>
        ) : null}

        {/* Descrição */}
        {!!plano.descricao && (
          <Section title="Sobre o plano" padded>
            <Text style={styles.descricaoText}>{plano.descricao}</Text>
          </Section>
        )}
      </ScrollView>

      {/* Rodapé: plano ativo, ou o painel de pagamento (CheckoutDock) depois de escolher o ciclo */}
      {bloquearCompraPorAtivo ? (
        <View style={styles.footer}>
          <View style={styles.footerAtivo}>
            <Feather name="check-circle" size={20} color={ds.success} />
            <Text style={styles.footerAtivoText}>Este já é o seu plano ativo</Text>
          </View>
        </View>
      ) : selectedCiclo || ciclos.length === 0 ? (
        <View style={styles.dock} accessibilityLabel="Pagamento">
          {opcoesMetodo.length > 0 ? (
            <>
              <View style={styles.dockHead}>
                <Text style={styles.dockTitle}>Como você quer pagar?</Text>
                <View style={styles.dockSecure}>
                  <Feather name="lock" size={12} color={ds.success} />
                  <Text style={styles.dockSecureText}>Pagamento seguro</Text>
                </View>
              </View>
              <PaymentMethodOptions
                compact
                opcoes={opcoesMetodo}
                value={metodoSelecionado}
                onChange={setMetodoSelecionado}
              />
            </>
          ) : null}
          <View style={[styles.dockFoot, opcoesMetodo.length > 0 && styles.dockFootDivider]}>
            <View style={styles.summaryText}>
              <Text style={styles.summaryLabel} numberOfLines={1}>
                {selectedCiclo
                  ? [
                      `${selectedCiclo.nome} · ${modoCobranca === "assinatura" ? "mensal" : "à vista"}`,
                      metodoSelecionado === "pix"
                        ? "PIX"
                        : metodoSelecionado === "boleto"
                          ? "Boleto"
                          : metodoSelecionado === "cartao"
                            ? "Cartão"
                            : `${selectedCiclo.meses} ${selectedCiclo.meses === 1 ? "mês" : "meses"}`,
                    ].join(" · ")
                  : "Escolha um ciclo"}
              </Text>
              <Text style={styles.summaryTotal} numberOfLines={1}>
                {modoCobranca === 'assinatura' ? `${ofertaSelecionada ? formatCurrency(Number(ofertaSelecionada.valor_mensal)) : selectedCiclo?.valor_mensal_formatado}/mês`
                  : creditoPrevia?.valor_parcela_formatado || (selectedCiclo && precisaRevisaoMigracao(plano, selectedCiclo.id) ? "A calcular" : selectedCiclo ? selectedCiclo.valor_formatado : "—")}
              </Text>
            </View>
          <Button
            size="lg"
            icon={
              podeMigrarAgora
                ? "repeat"
                : podeRenovar
                  ? "refresh-cw"
                  : metodoSelecionado === "pix"
                    ? "zap"
                    : metodoSelecionado === "boleto"
                      ? "file-text"
                      : "credit-card"
            }
            loading={comprando || pixLoading}
            disabled={!canBuy || (modoCobranca === "assinatura" && (!ofertaSelecionada || !!assinaturaDisponivelEm))}
            onPress={() => void revisarPagamento()}
          >
            {comprando || pixLoading
              ? "Processando…"
              : !selectedCiclo
                ? "Escolha"
                : !metodoSelecionado
                  ? "Indisponível"
                  : modoCobranca === 'assinatura' ? 'Revisar e assinar' : 'Revisar e pagar'}
          </Button>
          </View>
        </View>
      ) : null}

      {plano && selectedCiclo?.cobranca_mensal ? (
        <ContratacaoCicloSheet
          visible={contratacaoAberta}
          revisaoDireta ofertaInicial={ofertaSelecionada} metodoInicial={metodoSelecionado}
          planoId={plano.id}
          ciclo={selectedCiclo}
          assinaturaDisponivelEm={assinaturaDisponivelEm}
          metodosAvista={opcoesMetodo}
          rotuloAvista={(m) => rotuloPagarMetodo(m, podeMigrarAgora ? "migrar" : podeRenovar ? "renovar" : "pagar")}
          onPagarAvista={(m) => {
            setMetodoSelecionado(m);
            if (m === "pix") void handlePagarPix();
            else void handleContratar(m);
          }}
          onClose={() => setContratacaoAberta(false)}
          onAssinado={() => {
            setContratacaoAberta(false);
            router.replace("/minhas-assinaturas");
          }}
        />
      ) : null}

      {/* Confirmação da troca de plano (design system · TelasPagamento) */}
      <MigracaoPlanoSheet
        visible={migracaoModalVisible}
        tipo={revisaoTipo}
        simulacao={migracaoSimulacao}
        metodo={migracaoMetodoPendente}
        planoNovoNome={plano?.nome}
        cicloNovoNome={selectedCiclo?.nome}
        loading={confirmandoMigracao}
        detalharTaxas={podeSandboxPix}
        onChangeTaxa={(next) =>
          setMigracaoSimulacao((prev) => (prev ? { ...prev, taxa_cartao: next } : prev))
        }
        onConfirm={() => void confirmarRevisao()}
        onClose={() => {
          if (confirmandoMigracao) return;
          setMigracaoModalVisible(false);
          setMigracaoSimulacao(null);
          setMigracaoMetodoPendente(null);
        }}
      />

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

      <CheckoutRedirectModal
        visible={redirectModalVisible}
        url={paymentUrlToOpen}
        provider={paymentProvider}
        metodo={paymentMetodo}
        tarifaCartao={paymentTarifa}
        detalharTaxas={podeSandboxPix}
        resumo={(() => {
          const c = plano?.ciclos?.find((item) => item.id === selectedCicloId);
          return {
            titulo: [plano?.nome, c?.nome].filter(Boolean).join(" · ") || null,
            subtitulo: plano?.modalidade?.nome || null,
            valor: c ? c.valor_formatado || formatCurrency(c.valor) : null,
          };
        })()}
        onRedirect={() => {
          void irParaCheckout();
        }}
        onClose={fecharCheckout}
      />

      <TaxaCartaoConfirmSheet
        visible={taxaCartaoVisible}
        simulacao={taxaCartaoSimulacao}
        tituloPlano={[plano?.nome, selectedCiclo?.nome].filter(Boolean).join(" · ") || null}
        loading={taxaCartaoConfirmando}
        detalharTaxas={podeSandboxPix}
        onClose={() => {
          if (!taxaCartaoConfirmando) {
            setTaxaCartaoVisible(false);
            setTaxaCartaoSimulacao(null);
          }
        }}
        onConfirm={(sim) => {
          void (async () => {
            setTaxaCartaoConfirmando(true);
            setTaxaCartaoVisible(false);
            try {
              await handleContratar("cartao", {
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
        description={plano?.nome || null}
        code={pixData?.qr_code}
        qrBase64={pixData?.qr_code_base64}
        expiresAt={pixData?.expires_at}
        ticketUrl={pixData?.ticket_url}
        status={pixChecking || pixPollingActive ? "checking" : "waiting"}
        notice={
          pixPollingActive
            ? `${pixStatusMessage || "Aguardando a confirmação do pagamento."} Tempo aguardando: ${pixWaitSeconds}s.`
            : pixStatusMessage
        }
        onCopy={copyToClipboard}
        onCheck={() => checkPixAprovacao(true)}
        onOpenBank={() => {
          if (pixData?.ticket_url) {
            Linking.openURL(pixData.ticket_url);
          }
        }}
        onClose={() => {
          setPixModalVisible(false);
          setPixPollingActive(false);
          setPixStatusMessage(null);
          setPixChecking(false);
          setPixWaitSeconds(0);
        }}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  summaryTotal: {
    fontSize: 22,
    lineHeight: 26,
    fontWeight: "900",
    letterSpacing: -0.4,
    color: ds.ink,
    fontVariant: ["tabular-nums"],
  },
  summaryLabel: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: "700",
    color: ds.inkMuted,
  },
  summaryText: {
    flex: 1,
    minWidth: 0,
  },
  footerAtivoText: {
    fontSize: 16,
    fontWeight: "800",
    color: ds.success,
  },
  footerAtivo: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    height: 56,
    borderRadius: 12,
    backgroundColor: ds.successSoft,
  },
  ciclosList: {
    gap: 12,
  },
  factV: {
    fontSize: 14,
    lineHeight: 20,
    fontWeight: "800",
    color: ds.ink,
    marginTop: 2,
    fontVariant: ["tabular-nums"],
  },
  factK: {
    fontSize: 11,
    lineHeight: 14,
    fontWeight: "800",
    letterSpacing: 0.66,
    color: ds.inkMuted,
  },
  factDivider: {
    borderLeftWidth: 1,
    borderLeftColor: ds.line,
  },
  fact: {
    flex: 1,
    minWidth: 0,
    paddingVertical: 12,
    paddingHorizontal: 12,
  },
  factsCard: {
    flexDirection: "row",
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
    borderRadius: 16,
    overflow: "hidden",
  },
  headerEyebrow: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "600",
    color: ds.inkMuted,
  },
  headerTitles: {
    flex: 1,
    minWidth: 0,
  },
  container: {
    flex: 1,
    backgroundColor: ds.ground,
  },

  /* Header */
  header: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    minHeight: 64,
    paddingLeft: 4,
    paddingRight: 16,
    paddingVertical: 8,
    backgroundColor: ds.surface,
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  headerTitle: {
    fontSize: 22,
    lineHeight: 28,
    fontWeight: "800",
    letterSpacing: -0.2,
    color: ds.ink,
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
    color: ds.onBrand,
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
    padding: 16,
    paddingBottom: 32,
    gap: 16,
  },

  /* Hero Card */
  heroCard: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: ds.line,
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
    color: colors.primaryText,
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
    color: colors.primaryText,
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
    borderColor: ds.line,
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
    fontSize: 15,
    lineHeight: 22,
    fontWeight: "500",
    color: ds.ink,
  },
  pendingBanner: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 12,
    padding: 16,
    borderRadius: 16,
    backgroundColor: ds.warningSoft,
  },
  pendingBannerContent: {
    flex: 1,
    gap: 2,
  },
  pendingBannerTitle: {
    fontSize: 15,
    lineHeight: 20,
    fontWeight: "800",
    color: ds.warning,
  },
  pendingBannerText: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "600",
    color: ds.ink,
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
    borderColor: ds.line,
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
    color: colors.primaryText,
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
    color: colors.primaryText,
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
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: ds.surface,
    borderTopWidth: 1,
    borderTopColor: ds.line,
    shadowColor: "#171a21",
    shadowOffset: { width: 0, height: -8 },
    shadowOpacity: 0.07,
    shadowRadius: 24,
    elevation: 12,
  },
  dock: {
    gap: 12,
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 12,
    backgroundColor: ds.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    shadowColor: "#171a21",
    shadowOffset: { width: 0, height: -10 },
    shadowOpacity: 0.12,
    shadowRadius: 28,
    elevation: 16,
    zIndex: 2,
  },
  dockHead: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  dockTitle: {
    fontSize: 11,
    lineHeight: 14,
    fontWeight: "800",
    letterSpacing: 0.88,
    textTransform: "uppercase",
    color: ds.inkMuted,
  },
  dockSecure: { flexDirection: "row", alignItems: "center", gap: 4 },
  dockSecureText: { fontSize: 11, lineHeight: 14, fontWeight: "700", color: ds.success },
  dockFoot: { flexDirection: "row", alignItems: "center", gap: 12 },
  dockFootDivider: { paddingTop: 12, borderTopWidth: 1, borderTopColor: ds.line },
  cicloNote: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 14,
    borderRadius: 16,
    backgroundColor: ds.surfaceSunken,
  },
  cicloNoteText: { flex: 1, fontSize: 14, lineHeight: 19, fontWeight: "600", color: ds.inkMuted },
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
    color: ds.onBrand,
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
    fontSize: 20,
    fontWeight: "700",
    color: ds.ink,
    marginBottom: 14,
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
    color: ds.inkMuted,
    fontWeight: "700",
    textTransform: "uppercase",
  },
  pixInfoValue: {
    fontSize: 13,
    color: ds.ink,
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
    borderColor: ds.line,
    backgroundColor: "#fff",
    paddingHorizontal: 12,
    paddingVertical: 10,
    gap: 8,
    overflow: "hidden",
  },
  pixCodeText: {
    flex: 1,
    fontSize: 12,
    color: ds.inkMuted,
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
  pixOpenLink: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    marginBottom: 12,
  },
  pixOpenLinkText: {
    color: colors.primaryText,
    fontSize: 13,
    fontWeight: "700",
    textDecorationLine: "underline",
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
  pixWaitCounter: {
    fontSize: 12,
    color: colors.textSecondary,
    textAlign: "center",
    marginTop: 6,
  },
  pixCancelWaitButton: {
    marginTop: 16,
    width: "100%",
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: "center",
    backgroundColor: "#6b7280",
  },
  pixCancelWaitButtonText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "700",
  },
});
