import { getApiUrlRuntime } from "@/src/config/urls";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Linking,
  Modal,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

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
  economia?: string | null;
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
  label?: string | null;
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

export default function PlanosScreen() {
  const router = useRouter();

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
  const [countdown, setCountdown] = useState(3);
  const [paymentUrlToOpen, setPaymentUrlToOpen] = useState<string | null>(null);
  const [selectedCicloByPlano, setSelectedCicloByPlano] = useState<
    Record<number, number>
  >({});
  const [selectedPlanoId, setSelectedPlanoId] = useState<number | null>(null);

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
    if (!selectedPlanoId) return;
    const exists = planos.some((plano) => plano.id === selectedPlanoId);
    if (!exists) {
      setSelectedPlanoId(null);
    }
  }, [planos, selectedPlanoId]);

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

    initializeAndFetch();
  }, []);

  // Countdown para modal de redirecionamento
  useEffect(() => {
    if (redirectModalVisible && countdown > 0) {
      const timer = setTimeout(() => {
        setCountdown(countdown - 1);
      }, 1000);
      return () => clearTimeout(timer);
    }

    // Quando chegar a 0, abrir o link e fechar modal
    if (redirectModalVisible && countdown === 0 && paymentUrlToOpen) {
      (async () => {
        const supported = await Linking.canOpenURL(paymentUrlToOpen);
        if (supported) {
          console.log("🔗 Abrindo URL de pagamento:", paymentUrlToOpen);
          await Linking.openURL(paymentUrlToOpen);
        }
        setRedirectModalVisible(false);
        setCountdown(3);
        setPaymentUrlToOpen(null);
      })();
    }
  }, [redirectModalVisible, countdown, paymentUrlToOpen]);

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

  const fetchPlanos = async (baseUrl: string) => {
    try {
      setLoading(true);
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

      if (data.success && data.data?.planos) {
        console.log("✅ Planos carregados:", data.data.planos.length);
        setPlanos(data.data.planos);
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

  const handleContratar = React.useCallback(
    async (plano: Plan) => {
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

        // 3. Fazer requisição POST para comprar plano com ciclo
        const matriculaResponse = await fetch(
          `${apiUrl}/mobile/comprar-plano`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${token}`,
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              plano_id: plano.id,
              plano_ciclo_id: selectedCiclo.id,
            }),
          },
        );

        console.log("📡 Status criação matrícula:", matriculaResponse.status);

        if (!matriculaResponse.ok) {
          const errorText = await matriculaResponse.text();
          console.error("❌ Erro ao criar matrícula:", errorText);
          setComprando(false);
          setPlanoComprando(null);

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

        const matriculaData = await matriculaResponse.json();
        console.log("✅ Resposta da API:", matriculaData);

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

        console.log("💳 Payment URL:", paymentUrl);

        // 4. Salvar ID da matrícula para consultar depois
        if (matriculaId) {
          await AsyncStorage.setItem(
            "matricula_pendente_id",
            matriculaId.toString(),
          );
          console.log("💾 Matrícula ID salvo:", matriculaId);
        }

        setComprando(false);
        setPlanoComprando(null);

        // 5. Mostrar modal de redirecionamento com countdown
        setPaymentUrlToOpen(paymentUrl);
        setCountdown(3);
        setRedirectModalVisible(true);
      } catch (err) {
        const errorMsg =
          err instanceof Error ? err.message : "Erro ao processar compra";
        console.error("❌ Erro na compra:", err);
        setComprando(false);
        setPlanoComprando(null);

        showErrorModal("❌ Algo Deu Errado", errorMsg, "error");
      }
    },
    [apiUrl, selectedCicloByPlano],
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

        <TouchableOpacity
          style={[
            styles.contratarButton,
            comprando && planoComprando === plano.id
              ? styles.contratarButtonLoading
              : null,
            !selectedCiclo &&
              !plano.is_plano_atual &&
              styles.contratarButtonDisabled,
          ]}
          onPress={() => handleContratar(plano)}
          disabled={
            !selectedCiclo ||
            plano.is_plano_atual ||
            (comprando && planoComprando === plano.id)
          }
        >
          {plano.is_plano_atual ? (
            <>
              <Feather name="check" size={18} color="#fff" />
              <Text style={styles.contratarButtonText}>Plano Ativo</Text>
            </>
          ) : comprando && planoComprando === plano.id ? (
            <>
              <ActivityIndicator color="#fff" size="small" />
              <Text style={styles.contratarButtonText}>Processando...</Text>
            </>
          ) : (
            <>
              <Feather name="shopping-cart" size={18} color="#fff" />
              <Text style={styles.contratarButtonText}>
                {selectedCiclo
                  ? `Contratar por ${selectedCiclo.valor_formatado}`
                  : "Escolha um ciclo para continuar"}
              </Text>
            </>
          )}
        </TouchableOpacity>
      </View>
    );
  };

  const renderPlanListItem = ({ item: plano }: { item: Plan }) => {
    const ciclos = (plano.ciclos || []).sort((a, b) => a.meses - b.meses);
    const baseCiclo = ciclos[0];
    const priceLabel = baseCiclo?.valor_formatado || plano.valor_formatado;

    return (
      <TouchableOpacity
        style={styles.planListCard}
        activeOpacity={0.9}
        onPress={() =>
          router.push({
            pathname: "/plano-detalhes",
            params: { id: plano.id },
          })
        }
      >
        <View style={styles.planListTop}>
          <View style={styles.planListInfo}>
            <Text style={styles.planListName}>{plano.nome}</Text>
            <Text style={styles.planListModalidade}>
              {plano.modalidade.nome}
            </Text>
          </View>
          {plano.is_plano_atual && (
            <View style={styles.planListBadge}>
              <Feather name="check" size={12} color="#fff" />
              <Text style={styles.planListBadgeText}>Atual</Text>
            </View>
          )}
        </View>

        <View style={styles.planListBottom}>
          <View>
            <Text style={styles.planListPriceLabel}>A partir de</Text>
            <Text style={styles.planListPrice}>{priceLabel}</Text>
            {!!baseCiclo?.valor_mensal_formatado && (
              <Text style={styles.planListMonthly}>
                {baseCiclo.valor_mensal_formatado}/mês
              </Text>
            )}
          </View>
          <View style={styles.planListCta}>
            <Text style={styles.planListCtaText}>Ver detalhes</Text>
            <Feather name="arrow-right" size={16} color="#fff" />
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.headerTop}>
          <TouchableOpacity
            style={styles.headerBackButton}
            onPress={() => {
              if (selectedPlanoId) {
                setSelectedPlanoId(null);
                return;
              }
              router.replace("/(tabs)/checkin");
            }}
          >
            <Feather name="arrow-left" size={24} color="#fff" />
          </TouchableOpacity>
          <Text style={styles.headerTitleCentered}>Planos</Text>
          <View style={{ width: 40 }} />
        </View>
        <View style={styles.centerContent}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Carregando planos...</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.headerTop}>
        <TouchableOpacity
          style={styles.headerBackButton}
          onPress={() => {
            if (selectedPlanoId) {
              setSelectedPlanoId(null);
              return;
            }
            router.replace("/(tabs)/checkin");
          }}
        >
          <Feather name="arrow-left" size={24} color="#fff" />
        </TouchableOpacity>
        <Text style={styles.headerTitleCentered}>Planos</Text>
        <View style={{ flexDirection: "row", gap: 8 }}>
          <TouchableOpacity
            style={styles.headerBackButton}
            onPress={() => router.push("/minhas-assinaturas")}
          >
            <Feather name="list" size={20} color="#fff" />
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.headerBackButton}
            onPress={() => {
              setLoading(true);
              fetchPlanos(apiUrl);
            }}
          >
            <Feather name="refresh-cw" size={20} color="#fff" />
          </TouchableOpacity>
        </View>
      </View>

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
          <View style={styles.stepHeader}>
            <View>
              <Text style={styles.stepLabel}>Etapa 2 de 2</Text>
              <Text style={styles.stepTitle}>
                {planos.find((p) => p.id === selectedPlanoId)?.nome ||
                  "Detalhes do plano"}
              </Text>
            </View>
            <TouchableOpacity
              style={styles.stepAction}
              onPress={() => setSelectedPlanoId(null)}
            >
              <Text style={styles.stepActionText}>Ver todos</Text>
              <Feather name="grid" size={16} color={colors.primary} />
            </TouchableOpacity>
          </View>
          {(() => {
            const selectedPlano = planos.find((p) => p.id === selectedPlanoId);
            if (!selectedPlano) return null;
            return renderPlanCard({ item: selectedPlano });
          })()}
        </ScrollView>
      ) : (
        <View style={styles.listWrapper}>
          <View style={[styles.stepHeader, styles.stepHeaderPadded]}>
            <View>
              <Text style={styles.stepLabel}>Etapa 1 de 2</Text>
              <Text style={styles.stepTitle}>Escolha um plano</Text>
            </View>
          </View>
          <FlatList
            data={planos}
            renderItem={renderPlanListItem}
            keyExtractor={(item) => item.id.toString()}
            contentContainerStyle={styles.listContent}
            scrollEnabled={true}
            showsVerticalScrollIndicator={false}
          />
        </View>
      )}

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

      {/* Redirect Modal com Countdown */}
      <Modal
        visible={redirectModalVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setRedirectModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, styles.modalSuccess]}>
            {/* Icon Circle */}
            <View style={[styles.iconCircle, styles.iconCircleSuccess]}>
              <Feather name="arrow-right" size={40} color="#0a7f3c" />
            </View>

            {/* Title */}
            <Text style={styles.modalTitle}>Redirecionando para Pagamento</Text>

            {/* Message */}
            <Text style={styles.modalMessage}>
              Você será redirecionado para o Mercado Pago em{" "}
              <Text
                style={{
                  fontWeight: "800",
                  fontSize: 24,
                  color: colors.primary,
                }}
              >
                {countdown}
              </Text>
              {"\n"}segundos
            </Text>

            {/* Loading Indicator */}
            <ActivityIndicator
              size="large"
              color={colors.primary}
              style={{ marginBottom: 24 }}
            />
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
    paddingHorizontal: 16,
    paddingVertical: 16,
    paddingBottom: 32,
    gap: 16,
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
    color: colors.primary,
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
    color: colors.primary,
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
    color: "#fff",
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
    borderColor: "#eef2f7",
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
    color: colors.primary,
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
    borderColor: "#eef2f7",
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
    color: colors.primary,
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
    color: "#fff",
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
