import { DebugLogsViewer } from "@/components/DebugLogsViewer";
import PasswordRecoveryModal from "@/components/PasswordRecoveryModal";
import AuthService from "@/src/services/authService";
import MobileService from "@/src/services/mobileService";
import { colors } from "@/src/theme/colors";
import {
    DiaCheckin,
    RankingItem,
    RankingModalidade,
    UserProfile,
} from "@/src/types";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { getTokenTenantId, handleAuthError } from "@/src/utils/authHelpers";
import {
    compressImage,
    logCompressionInfo,
} from "@/src/utils/imageCompression";
import AsyncStorage from "@/src/utils/storage";
import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import * as ImagePicker from "expo-image-picker";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect, useRef, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    Animated,
    Dimensions,
    Image,
    Modal,
    Platform,
    Pressable,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

const ROLE_NAME_FALLBACK: Record<number, string> = {
  1: "Aluno",
  2: "Professor",
  3: "Admin",
  4: "Super Admin",
};

const withShadow = (
  webBoxShadow: string,
  nativeShadow: Record<string, unknown>,
) => (Platform.OS === "web" ? { boxShadow: webBoxShadow } : nativeShadow);

export default function AccountScreen() {
  const router = useRouter();
  const [userProfile, setUserProfile] = useState<UserProfile | null>(null);
  const [currentTenant, setCurrentTenant] = useState<any | null>(null);
  const [tenants, setTenants] = useState<any[]>([]);
  const [showTenantModal, setShowTenantModal] = useState(false);
  const [loading, setLoading] = useState(true);
  const [rankingLoading, setRankingLoading] = useState(false);
  const [rankingError, setRankingError] = useState<string | null>(null);
  const [ranking, setRanking] = useState<RankingItem[]>([]);
  const [rankingPeriodo, setRankingPeriodo] = useState<string>("");
  const [rankingModalidades, setRankingModalidades] = useState<
    RankingModalidade[]
  >([]);
  const [selectedModalidadeId, setSelectedModalidadeId] = useState<
    number | null
  >(null);
  // Removido: carregamento de modalidades a partir de turmas (não utilizado)
  const [updatingPhoto, setUpdatingPhoto] = useState(false);
  const [photoUrl, setPhotoUrl] = useState<string | null>(null);
  const [photoError, setPhotoError] = useState<string | null>(null);
  const [hasPhotoLoadError, setHasPhotoLoadError] = useState(false);
  const [apiUrl, setApiUrl] = useState<string>("");
  // const [assetsUrl, setAssetsUrl] = useState<string>("");
  const [showRecoveryModal, setShowRecoveryModal] = useState(false);
  const [showSidebar, setShowSidebar] = useState(false);
  const [userRoles, setUserRoles] = useState<any[]>([]);
  const sidebarTranslateX = useRef(
    new Animated.Value(-Dimensions.get("window").width),
  ).current;
  // Flags para evitar chamadas duplicadas e controlar ciclo de fetch
  const hasLoadedWeekRef = useRef(false);
  const lastRankingCalledIdRef = useRef<number | null>(null);
  const hasVerifiedTokenTenantRef = useRef(false);
  const latestProfileReqRef = useRef<number>(0);
  const latestRankingReqRef = useRef<number>(0);
  const latestWeekReqRef = useRef<number>(0);
  const profileLoadTriggeredRef = useRef(false);
  const lastLoadedTenantIdRef = useRef<number | null>(null);

  // Estados do calendário semanal de check-ins
  const [weekDias, setWeekDias] = useState<DiaCheckin[]>([]);
  const [weekSemanaInicio, setWeekSemanaInicio] = useState<string | null>(null);
  const [weekSemanaFim, setWeekSemanaFim] = useState<string | null>(null);
  const [weekLoading, setWeekLoading] = useState(false);

  const normalizeProfileModalidades = (
    items: UserProfile["ranking_modalidades"] = [],
  ): RankingModalidade[] =>
    items
      .filter((item) => item?.modalidade_id && item?.modalidade_nome)
      .map((item) => ({
        id: item.modalidade_id,
        nome: item.modalidade_nome,
        icone: item.modalidade_icone,
        cor: item.modalidade_cor,
      }));

  const normalizeApiModalidades = (
    items: RankingModalidade[] = [],
  ): RankingModalidade[] =>
    items
      .filter((item) => item?.id && item?.nome)
      .map((item) => ({
        id: item.id,
        nome: item.nome,
        icone: item.icone,
        cor: item.cor,
      }));

  const mergeModalidades = (...lists: RankingModalidade[][]) => {
    const map = new Map<number, RankingModalidade>();
    lists.forEach((list) => {
      list.forEach((item) => {
        if (!item?.id) {
          return;
        }
        if (!map.has(item.id)) {
          map.set(item.id, item);
        }
      });
    });
    return Array.from(map.values());
  };

  const getInitials = (nome: string = "") => {
    const parts = nome.split(" ").filter(Boolean);
    if (parts.length === 0) return "?";
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (
      parts[0].charAt(0) + parts[parts.length - 1].charAt(0)
    ).toUpperCase();
  };

  const getUserPhotoUri = () => {
    const buildAbsoluteUrl = (base: string, path: string) => {
      const normalizedBase = (base || "").trim().replace(/\/+$/, "");
      const normalizedPath = (path || "").trim().replace(/^\/+/, "");
      if (!normalizedBase || !normalizedPath) {
        return null;
      }
      return `${normalizedBase}/${normalizedPath}`;
    };

    if (photoUrl) return photoUrl;
    if (userProfile?.foto_caminho) {
      const raw = userProfile.foto_caminho.trim();
      if (/^https?:\/\//i.test(raw)) return raw;
      if (apiUrl) {
        const fullUrl = buildAbsoluteUrl(apiUrl, raw);
        if (fullUrl) return fullUrl;
      }
    }
    if (userProfile?.foto_base64) {
      return `data:image/jpeg;base64,${userProfile.foto_base64}`;
    }
    return null;
  };

  // Exibir exatamente o nome fornecido pelo backend, sem normalizações

  const getTenantName = () => {
    // Preferir o nome vindo de /auth/tenants (lista) que reflete o display desejado
    const currentId = currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
    console.log(
      "🔎 [getTenantName] currentId:",
      currentId,
      "tenants.length:",
      Array.isArray(tenants) ? tenants.length : 0,
    );
    if (currentId && Array.isArray(tenants) && tenants.length > 0) {
      const match = tenants.find(
        (t: any) => t?.tenant?.id === currentId || t?.id === currentId,
      );
      const nomeLista = match?.tenant?.nome ?? match?.nome;
      if (nomeLista) {
        console.log("✅ [getTenantName] Nome encontrado na lista:", nomeLista);
        return nomeLista;
      }
    }
    // Fallback para nome do objeto currentTenant
    const fallbackName =
      currentTenant?.tenant?.nome ?? currentTenant?.nome ?? "Academia";
    console.log("⚠️ [getTenantName] Usando fallback:", fallbackName);
    return fallbackName;
  };

  const getTenantDisplayName = () => getTenantName();

  const userRoleLabels = React.useMemo(() => {
    if (!Array.isArray(userRoles) || userRoles.length === 0) {
      return [];
    }
    const labels = userRoles
      .map((role) => {
        const roleId = Number(role?.id ?? role?.papel_id);
        return role?.nome || ROLE_NAME_FALLBACK[roleId] || null;
      })
      .filter(Boolean) as string[];
    return Array.from(new Set(labels));
  }, [userRoles]);

  const loadUserRoles = useCallback(async () => {
    try {
      const user = await AuthService.getCurrentUser();
      if (
        user?.papeis &&
        Array.isArray(user.papeis) &&
        user.papeis.length > 0
      ) {
        setUserRoles(user.papeis);
        return;
      }
      if (user?.papel_id) {
        const roleId = Number(user.papel_id);
        if (Number.isFinite(roleId)) {
          setUserRoles([{ id: roleId, nome: ROLE_NAME_FALLBACK[roleId] }]);
          return;
        }
      }
      setUserRoles([]);
    } catch (error) {
      console.warn("⚠️ Falha ao carregar papéis do usuário:", error);
      setUserRoles([]);
    }
  }, []);

  // Verificar se o usuário é admin (papel_id 3 ou 4)
  const isUserAdmin = React.useMemo(() => {
    if (!Array.isArray(userRoles) || userRoles.length === 0) {
      return false;
    }
    return userRoles.some((role) => {
      const roleId = Number(role?.id ?? role?.papel_id);
      return roleId === 3 || roleId === 4;
    });
  }, [userRoles]);

  const getTenantImageUrl = () => {
    const raw =
      currentTenant?.tenant?.logo_caminho ??
      currentTenant?.tenant?.logo ??
      currentTenant?.tenant?.imagem ??
      currentTenant?.tenant?.foto_caminho ??
      currentTenant?.tenant?.foto ??
      currentTenant?.logo_caminho ??
      currentTenant?.logo ??
      currentTenant?.imagem ??
      currentTenant?.foto_caminho ??
      currentTenant?.foto ??
      null;
    if (!raw) return null;
    if (/^https?:\/\//i.test(raw)) return raw;
    if (!apiUrl) return raw;
    return `${apiUrl}${raw}`;
  };

  // Memoized loaders to satisfy hook dependencies
  const loadUserProfileMemo = useCallback(async () => {
    try {
      console.log("\n🔄 INICIANDO CARREGAMENTO DE PERFIL");
      console.log("📊 [loadUserProfileMemo] currentTenant:", currentTenant);
      console.log("📊 [loadUserProfileMemo] apiUrl:", apiUrl);
      const expectedTenantId =
        currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
      const reqId = Date.now();
      latestProfileReqRef.current = reqId;
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        console.error("❌ Token não encontrado");
        router.replace("/(auth)/login");
        return;
      }
      const profileData = await MobileService.getPerfil();
      if (profileData?.success) {
        // Garantir que aplicamos apenas a resposta mais recente e do tenant esperado
        const tokenTid = await getTokenTenantId();
        if (latestProfileReqRef.current !== reqId) {
          console.warn("⏭️ Ignorando resposta de perfil (requisição obsoleta)");
          return;
        }
        if (expectedTenantId && tokenTid && tokenTid !== expectedTenantId) {
          console.warn("⏭️ Ignorando resposta de perfil (tenant divergente)");
          return;
        }
        setUserProfile(profileData.data);
        setHasPhotoLoadError(false);
        console.log(
          "✅ [loadUserProfileMemo] Perfil carregado com sucesso:",
          profileData.data?.nome,
        );
        console.log(
          "📋 [loadUserProfileMemo] Plano recebido:",
          JSON.stringify(profileData.data?.plano, null, 2),
        );
        if (profileData.data?.foto_caminho) {
          const fullPhotoUrl = apiUrl + profileData.data.foto_caminho;
          console.log("🖼️ URL COMPLETA DA FOTO:", fullPhotoUrl);
        }
      } else {
        const errorMsg =
          profileData?.error || "Não foi possível carregar o perfil";
        Alert.alert("Erro", errorMsg);
      }
    } catch (error: any) {
      console.error(
        "❌ [loadUserProfileMemo] Erro ao carregar perfil:",
        error?.message || error,
      );
      const status = error?.response?.status;
      if (status === 401) {
        console.log("🔑 Detectado 401 - Token inválido/expirado");
        await handleAuthError();
        router.replace("/(auth)/login");
        return;
      }
      if (status === 403) {
        const code = error?.code || error?.response?.data?.code;
        if (code === "NO_ACTIVE_CONTRACT") {
          Alert.alert(
            "Contrato Inativo",
            "Seu acesso a esta academia está bloqueado. Verifique seu plano/contrato.",
          );
          return;
        }
      }
      Alert.alert("Erro", error?.message || "Erro ao conectar com o servidor");
    } finally {
      setLoading(false);
    }
  }, [apiUrl, router, currentTenant]);

  const loadWeekCheckinsMemo = useCallback(async () => {
    try {
      setWeekLoading(true);
      const expectedTenantId =
        currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
      const reqId = Date.now();
      latestWeekReqRef.current = reqId;
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) return;
      const url = `${getApiUrlRuntime()}/mobile/checkins/por-modalidade`;
      console.log("📅 [Semana] Request iniciada", {
        tenantId: expectedTenantId,
        url,
      });
      console.log("📅 [loadWeekCheckinsMemo] Iniciando carregamento...");
      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      const data = await response.json();
      const tokenTid = await getTokenTenantId();
      if (latestWeekReqRef.current !== reqId) {
        console.warn("⏭️ [Semana] Ignorando resposta (requisição obsoleta)");
        return;
      }
      if (expectedTenantId && tokenTid && tokenTid !== expectedTenantId) {
        console.warn("⏭️ [Semana] Ignorando resposta (tenant divergente)");
        return;
      }
      if (response.ok && data?.success) {
        console.log("✅ [Semana] Sucesso", {
          semana_inicio: data.data?.semana_inicio,
          semana_fim: data.data?.semana_fim,
          diasCount: Array.isArray(data.data?.dias) ? data.data.dias.length : 0,
        });
        setWeekSemanaInicio(data.data?.semana_inicio || null);
        setWeekSemanaFim(data.data?.semana_fim || null);
        setWeekDias(data.data?.dias || []);
      } else {
        console.warn("⚠️ Erro ao carregar check-ins da semana:", data?.error);
        setWeekDias([]);
      }
    } catch (error) {
      console.error("❌ Erro ao carregar check-ins da semana:", error);
      setWeekDias([]);
    } finally {
      setWeekLoading(false);
    }
  }, [currentTenant]);

  // Carrega modalidades a partir das turmas disponíveis
  // Removido: função loadModalidadesFromTurmas (não utilizada)

  const loadRankingMemo = useCallback(
    async (modalidadeId?: number | null) => {
      try {
        setRankingError(null);
        setRankingLoading(true);
        const expectedTenantId =
          currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
        const reqId = Date.now();
        latestRankingReqRef.current = reqId;
        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          setRankingError("Token não encontrado");
          return;
        }
        const params = modalidadeId ? `?modalidade_id=${modalidadeId}` : "";
        const url = `${getApiUrlRuntime()}/mobile/ranking/mensal${params}`;
        console.log("🏆 [Ranking] Request iniciada", {
          tenantId: expectedTenantId,
          modalidadeId: modalidadeId ?? null,
          url,
        });
        console.log("🏆 [loadRankingMemo] Iniciando carregamento...");
        const response = await fetch(url, {
          method: "GET",
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
        const data = await response.json();
        const tokenTid = await getTokenTenantId();
        if (latestRankingReqRef.current !== reqId) {
          console.warn("⏭️ [Ranking] Ignorando resposta (requisição obsoleta)");
          return;
        }
        if (expectedTenantId && tokenTid && tokenTid !== expectedTenantId) {
          console.warn("⏭️ [Ranking] Ignorando resposta (tenant divergente)");
          return;
        }
        if (!response.ok || !data?.success) {
          throw new Error(data?.error || "Erro ao carregar ranking");
        }
        const rankingData = data?.data;
        console.log("✅ [Ranking] Sucesso", {
          periodo: rankingData?.periodo,
          rankingTop3Count: Array.isArray(rankingData?.ranking)
            ? Math.min(3, rankingData.ranking.length)
            : 0,
          modalidadesCount: Array.isArray(rankingData?.modalidades)
            ? rankingData.modalidades.length
            : 0,
        });
        setRanking(rankingData?.ranking || []);
        setRankingPeriodo(rankingData?.periodo || "");
        const apiModalidades = normalizeApiModalidades(
          Array.isArray(rankingData?.modalidades)
            ? rankingData.modalidades
            : [],
        );
        const profileModalidades = normalizeProfileModalidades(
          userProfile?.ranking_modalidades || [],
        );
        const mergedModalidades = mergeModalidades(
          apiModalidades,
          profileModalidades,
        );
        setRankingModalidades(mergedModalidades);
      } catch (error: any) {
        setRankingError(error?.message || "Erro ao carregar ranking");
      } finally {
        setRankingLoading(false);
      }
    },
    [currentTenant, userProfile],
  );

  useEffect(() => {
    loadUserRoles();
  }, [loadUserRoles]);

  // Inicializa API URL e carrega tenants no mount

  useEffect(() => {
    console.log("🎬 [Account Mount] Iniciando...");
    setApiUrl(getApiUrlRuntime());
    console.log("📍 API URL (Account):", getApiUrlRuntime());
    (async () => {
      try {
        console.log("🔄 [Account Mount] Carregando tenants...");
        const ts = await AuthService.getTenants();
        console.log("✅ [Account Mount] Tenants carregados:", ts);
        setTenants(Array.isArray(ts) ? ts : []);
        const ct = await AuthService.getCurrentTenant();
        console.log("✅ [Account Mount] currentTenant:", ct);
        setCurrentTenant(ct);
      } catch (e) {
        console.warn("⚠️ Falha ao carregar tenants atuais", e);
      }
    })();
  }, []);

  // Carrega perfil apenas quando tenant estiver definido e evita duplicidade
  useEffect(() => {
    const tenantId = currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
    console.log(
      "🔹 [Effect 1] Carregamento de perfil - tenantId:",
      tenantId,
      "apiUrl:",
      apiUrl ? "set" : "vazio",
    );
    if (!apiUrl || !tenantId) {
      console.log("⏭️ [Effect 1] Aguardando apiUrl ou tenantId...");
      return;
    }
    if (
      profileLoadTriggeredRef.current &&
      lastLoadedTenantIdRef.current === tenantId
    ) {
      console.log("⏭️ [Effect 1] Já carregado para tenantId:", tenantId);
      return;
    }
    console.log(
      "🚀 [Effect 1] Disparando loadUserProfileMemo para tenantId:",
      tenantId,
    );
    profileLoadTriggeredRef.current = true;
    lastLoadedTenantIdRef.current = tenantId;
    loadUserProfileMemo();
  }, [apiUrl, currentTenant, loadUserProfileMemo]);

  // Recarrega perfil quando o tenant mudar (após troca de academia)
  // Recarrega perfil quando o tenant mudar de fato (id diferente)
  useEffect(() => {
    const tenantId = currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
    if (!apiUrl || !tenantId) return;
    if (lastLoadedTenantIdRef.current === tenantId) return;
    lastLoadedTenantIdRef.current = tenantId;
    loadUserProfileMemo();
  }, [currentTenant, apiUrl, loadUserProfileMemo]);

  // Garante que o token esteja alinhado com o tenant atual
  useEffect(() => {
    const ensureTenantTokenAlignment = async () => {
      const tenantId = currentTenant?.tenant?.id ?? currentTenant?.id;
      if (!tenantId) return; // Aguarda tenant
      if (hasVerifiedTokenTenantRef.current) return; // Evita duplicidade
      const tokenTenantId = await getTokenTenantId();
      if (tokenTenantId && tokenTenantId !== tenantId) {
        console.log(
          `🔁 Realinhando token: token.tenant_id=${tokenTenantId} → atual=${tenantId}`,
        );
        try {
          await AuthService.selectTenant(tenantId);
          const ct = await AuthService.getCurrentTenant();
          setCurrentTenant(ct);
        } catch (e) {
          console.warn("⚠️ Falha ao realinhar token com tenant", e);
        }
      }
      hasVerifiedTokenTenantRef.current = true;
    };
    ensureTenantTokenAlignment();
  }, [currentTenant, loadUserProfileMemo]);

  // Logs de auditoria: token vs tenant corrente
  useEffect(() => {
    const logTenantAlignment = async () => {
      const tenantId = currentTenant?.tenant?.id ?? currentTenant?.id;
      const tenantNome = currentTenant?.tenant?.nome ?? currentTenant?.nome;
      if (!tenantId) return;
      const tokenTenantId = await getTokenTenantId();
      console.log(
        `🏷️ Tenant atual: id=${tenantId} nome=${tenantNome ?? "(sem nome)"}`,
      );
      console.log(`🔎 JWT tenant_id: ${tokenTenantId ?? "null"}`);
      if (tokenTenantId && tokenTenantId !== tenantId) {
        console.warn("⚠️ Divergência: token aponta para outro tenant");
      } else {
        console.log("✅ Token alinhado com tenant atual");
      }
    };
    logTenantAlignment();
  }, [currentTenant]);

  const openSidebar = () => {
    const screenWidth = Dimensions.get("window").width;
    sidebarTranslateX.setValue(-screenWidth);
    setShowSidebar(true);
    requestAnimationFrame(() => {
      Animated.timing(sidebarTranslateX, {
        toValue: 0,
        duration: 260,
        useNativeDriver: true,
      }).start();
    });
  };

  const closeSidebar = () => {
    const screenWidth = Dimensions.get("window").width;
    Animated.timing(sidebarTranslateX, {
      toValue: -screenWidth,
      duration: 220,
      useNativeDriver: true,
    }).start(({ finished }) => {
      if (finished) {
        setShowSidebar(false);
      }
    });
  };

  useEffect(() => {
    if (!userProfile) return;

    // Carrega ranking apenas uma vez quando ainda não temos modalidades
    if (rankingModalidades.length === 0) {
      loadRankingMemo();

      // Semeia modalidades a partir do perfil apenas na primeira carga
      if (userProfile.ranking_modalidades?.length) {
        const modalidades = normalizeProfileModalidades(
          userProfile.ranking_modalidades,
        );
        setRankingModalidades(modalidades);
        if (!selectedModalidadeId && modalidades[0]) {
          setSelectedModalidadeId(modalidades[0].id);
        }
      }
    }
  }, [
    userProfile,
    loadRankingMemo,
    selectedModalidadeId,
    rankingModalidades.length,
  ]);

  // Debug: quando apiUrl ou userProfile mudam

  useEffect(() => {
    if (userProfile && apiUrl) {
      console.log("\n🔍 DEBUG: Render Photo");
      console.log("   apiUrl:", apiUrl);
      console.log("   photoUrl:", photoUrl || "vazio");
      console.log("   foto_caminho:", userProfile.foto_caminho || "vazio");
      if (userProfile.foto_caminho) {
        console.log("   URL completa:", `${apiUrl}${userProfile.foto_caminho}`);
      }
    }
  }, [userProfile, apiUrl, photoUrl]);

  // Carregar ranking quando modalidade selecionada muda

  useEffect(() => {
    const tenantId = currentTenant?.tenant?.id ?? currentTenant?.id;
    if (!tenantId) return; // Aguarda tenant carregado
    if (selectedModalidadeId && userProfile) {
      console.log("🔸 [Ranking] Trigger por mudança de modalidade", {
        tenantId,
        selectedModalidadeId,
      });
      if (lastRankingCalledIdRef.current === selectedModalidadeId) {
        return;
      }
      lastRankingCalledIdRef.current = selectedModalidadeId;
      loadRankingMemo(selectedModalidadeId);
    }
  }, [selectedModalidadeId, userProfile, loadRankingMemo, currentTenant]);

  // Carregar check-ins da semana quando userProfile xcarrega
  useEffect(() => {
    const tenantId = currentTenant?.tenant?.id ?? currentTenant?.id;
    if (!tenantId) return; // Aguarda tenant carregado
    if (userProfile && !hasLoadedWeekRef.current) {
      console.log("🔹 [Semana] Trigger por carregamento de perfil", {
        tenantId,
      });
      hasLoadedWeekRef.current = true;
      loadWeekCheckinsMemo();
    }
  }, [userProfile, loadWeekCheckinsMemo, currentTenant]);

  // Reset flags ao trocar de tenant
  useEffect(() => {
    hasLoadedWeekRef.current = false;
    lastRankingCalledIdRef.current = null;
    hasVerifiedTokenTenantRef.current = false;
    // Limpa dados dependentes de tenant para evitar exibição de informações antigas
    setPhotoUrl(null);
    setPhotoError(null);
    setHasPhotoLoadError(false);
    setRanking([]);
    setRankingPeriodo("");
    setRankingModalidades([]);
    setSelectedModalidadeId(null);
    setWeekDias([]);
    setWeekSemanaInicio(null);
    setWeekSemanaFim(null);
  }, [currentTenant]);

  // loadWeekCheckins replaced by memoized version above

  const handleSelectTenant = async (tenantId: number) => {
    try {
      await AuthService.selectTenant(tenantId);
      setShowTenantModal(false);
      const ct = await AuthService.getCurrentTenant();
      setCurrentTenant(ct);
      // Perfil será recarregado pelo efeito que depende de currentTenant
    } catch (e: any) {
      console.warn("⚠️ Falha ao selecionar tenant", e);
      Alert.alert("Erro", "Não foi possível trocar de academia.");
    }
  };

  // Gera os dias da semana (domingo a sábado) baseado na semana info
  const getWeekDays = (): {
    date: Date;
    dayName: string;
    dayNumber: number;
    dateStr: string;
  }[] => {
    const days: {
      date: Date;
      dayName: string;
      dayNumber: number;
      dateStr: string;
    }[] = [];
    const dayNames = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];

    if (weekSemanaInicio) {
      // Usar data de início da semana da API
      const startDate = new Date(weekSemanaInicio + "T00:00:00");
      for (let i = 0; i < 7; i++) {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + i);
        const dateStr = date.toISOString().split("T")[0];
        days.push({
          date,
          dayName: dayNames[date.getDay()],
          dayNumber: date.getDate(),
          dateStr,
        });
      }
    } else {
      // Fallback: calcular semana atual
      const today = new Date();
      const dayOfWeek = today.getDay(); // 0 = domingo
      const startOfWeek = new Date(today);
      startOfWeek.setDate(today.getDate() - dayOfWeek);

      for (let i = 0; i < 7; i++) {
        const date = new Date(startOfWeek);
        date.setDate(startOfWeek.getDate() + i);
        const dateStr = date.toISOString().split("T")[0];
        days.push({
          date,
          dayName: dayNames[date.getDay()],
          dayNumber: date.getDate(),
          dateStr,
        });
      }
    }

    return days;
  };

  // Verifica se um dia tem check-in
  const getDayCheckins = (dateStr: string): DiaCheckin[] => {
    return weekDias.filter((c) => c.data === dateStr);
  };

  // Formata o período da semana para exibição
  const formatWeekPeriod = (): string => {
    if (weekSemanaInicio && weekSemanaFim) {
      const inicio = new Date(weekSemanaInicio + "T00:00:00");
      const fim = new Date(weekSemanaFim + "T00:00:00");
      const formatDate = (d: Date) =>
        d.toLocaleDateString("pt-BR", { day: "2-digit", month: "short" });
      return `${formatDate(inicio)} - ${formatDate(fim)}`;
    }
    return "";
  };

  const formatCPF = (cpf: string) => {
    if (!cpf) return "";
    const cleaned = cpf.replace(/\D/g, "");
    return cleaned.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
  };

  const formatPhone = (phone: string) => {
    if (!phone) return "";
    const cleaned = phone.replace(/\D/g, "");
    if (cleaned.length === 11) {
      return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
    }
    return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
  };

  // loadUserProfile replaced by memoized version above

  const handleChangePhoto = async () => {
    try {
      // Pedir permissão para acessar galeria
      const { status } =
        await ImagePicker.requestMediaLibraryPermissionsAsync();

      if (status !== "granted") {
        Alert.alert(
          "Permissão Negada",
          "Você precisa permitir acesso à galeria para trocar sua foto",
        );
        return;
      }

      // Abrir seletor de imagem
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: "images",
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.8,
      });

      if (!result.canceled && result.assets && result.assets.length > 0) {
        const asset = result.assets[0];

        console.log("\n\n");
        console.log("═══════════════════════════════════════════════════════");
        console.log("🖼️ NOVO FLUXO DE UPLOAD - handleChangePhoto");
        console.log("═══════════════════════════════════════════════════════");
        console.log("📸 Asset selecionado:", {
          uri: asset.uri,
          width: asset.width,
          height: asset.height,
          type: asset.type,
        });

        setUpdatingPhoto(true);

        try {
          // 🎨 Comprimir imagem antes de enviar
          console.log("🎨 Iniciando compressão de imagem...");
          console.log("📸 Asset URI:", asset.uri);
          console.log("📱 Platform.OS:", Platform.OS);

          let uploadUri = asset.uri;
          let uploadType = "image/jpeg";

          // ✅ COMPRESSÃO ATIVADA
          try {
            console.log("⏳ Chamando compressImage()...");
            console.log(
              "📦 compressImage function type:",
              typeof compressImage,
            );
            console.log("📦 compressImage function exists:", !!compressImage);

            const compressResult = await compressImage(asset.uri, {
              maxWidth: 1080,
              maxHeight: 1080,
              quality: 0.8,
              outputFormat: "jpeg",
            });
            console.log("✅ compressImage retornou:", compressResult);

            // Log das informações de compressão
            logCompressionInfo(compressResult);
            uploadUri = compressResult.uri;
          } catch (compressionError: any) {
            console.warn(
              "⚠️ Compressão falhou, usando imagem original:",
              compressionError,
            );
            console.error(
              "❌ Erro de compressão (stack):",
              compressionError.stack,
            );
            console.log(
              "📸 Usando imagem original:",
              asset.uri,
              "Tamanho desconhecido",
            );
          }

          // Criar FormData para upload
          const formData = new FormData();
          const filename = uploadUri.split("/").pop() || "photo.jpg";

          // No web, converter blob; no mobile, usar uri
          if (Platform.OS === "web") {
            // Para web, fazer fetch da imagem e converter para blob
            const response = await fetch(uploadUri);
            const blob = await response.blob();
            formData.append("foto", blob, filename);
          } else {
            // Para mobile, usar uri diretamente
            formData.append("foto", {
              uri: uploadUri,
              type: uploadType,
              name: filename,
            } as any);
          }

          // Enviar para servidor
          console.log("📸 Enviando foto para servidor...");
          const response: any = await MobileService.atualizarFoto(formData);
          console.log("📸 Resposta do servidor:", response);

          if (response?.success) {
            console.log("✅ Foto enviada com sucesso, recarregando perfil...");
            // Armazenar URL da foto se disponível
            if (response.data?.caminho_url) {
              const apiUrl = getApiUrlRuntime();
              setHasPhotoLoadError(false);
              setPhotoUrl(`${apiUrl}${response.data.caminho_url}`);
              console.log(
                "📸 URL da foto armazenada:",
                `${apiUrl}${response.data.caminho_url}`,
              );
            }
            Alert.alert("Sucesso", "Foto atualizada com sucesso!");
            // Recarregar perfil para pegar a nova foto
            setUpdatingPhoto(false); // Desabilita loading ANTES de recarregar
            await loadUserProfileMemo();
          } else {
            console.error("❌ Erro na resposta:", response);
            // Tratar erro específico de aluno não encontrado
            if (response?.error === "Aluno não encontrado para este usuário") {
              Alert.alert("Erro", "Aluno não encontrado para este usuário");
            } else {
              Alert.alert(
                "Erro",
                response?.error ||
                  response?.message ||
                  "Erro ao atualizar foto",
              );
            }
          }
        } catch (error: any) {
          console.error("Erro ao processar foto:", error);
          console.error("Erro details - type:", typeof error);
          console.error("Erro details - error.error:", error?.error);
          console.error("Erro details - error.message:", error?.message);
          console.error(
            "Erro details - full object:",
            JSON.stringify(error, null, 2),
          );

          // Tratar erro específico de aluno não encontrado
          const errorMessage =
            error?.error ||
            error?.message ||
            error?.toString() ||
            "Erro ao processar foto. Tente novamente.";

          setPhotoError(errorMessage);
          setUpdatingPhoto(false);
        } finally {
          setUpdatingPhoto(false);
        }
      }
    } catch (error: any) {
      console.error("Erro ao trocar foto:", error);
      console.error("Erro details (outer) - type:", typeof error);
      console.error("Erro details (outer) - error.error:", error?.error);
      console.error("Erro details (outer) - error.message:", error?.message);
      console.error(
        "Erro details (outer) - full object:",
        JSON.stringify(error, null, 2),
      );

      const errorMessage =
        error?.error ||
        error?.message ||
        error?.toString() ||
        "Erro ao trocar foto. Tente novamente.";

      setPhotoError(errorMessage);
      setUpdatingPhoto(false);
    } finally {
      setUpdatingPhoto(false);
    }
  };

  // loadRanking replaced by memoized version above

  // (removidas: definições duplicadas tardias de loadModalidadesFromTurmas e loadRankingMemo)

  const userPhotoUri = getUserPhotoUri();
  const shouldRenderUserPhoto = Boolean(userPhotoUri && !hasPhotoLoadError);
  const userPhotoUriSafe = userPhotoUri || "";

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      </SafeAreaView>
    );
  }

  if (!userProfile) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <Text style={styles.errorText}>Erro ao carregar perfil</Text>
          <TouchableOpacity
            style={styles.retryButton}
            onPress={loadUserProfileMemo}
          >
            <Text style={styles.retryButtonText}>Tentar Novamente</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* Mostrar erro de foto se houver */}
      {photoError ? (
        <View style={styles.errorBanner}>
          <Feather name="alert-circle" size={18} color="#dc2626" />
          <Text style={styles.errorBannerText}>{photoError}</Text>
          <TouchableOpacity onPress={() => setPhotoError(null)}>
            <Feather name="x" size={20} color="#dc2626" />
          </TouchableOpacity>
        </View>
      ) : null}

      {/* Header com dados do usuário */}
      <View style={styles.headerTop}>
        <View style={styles.headerUserRow}>
          <View style={styles.headerUserLeft}>
            <View style={styles.headerPhotoWrapper}>
              <View style={styles.headerPhotoContainer}>
                {shouldRenderUserPhoto ? (
                  <Image
                    source={{ uri: userPhotoUriSafe }}
                    style={styles.headerPhotoImage}
                    onError={(event) => {
                      const reason =
                        event?.nativeEvent?.error || "Erro desconhecido";
                      console.warn("⚠️ Falha ao carregar foto do usuário:", {
                        uri: userPhotoUriSafe,
                        reason,
                      });
                      setHasPhotoLoadError(true);
                    }}
                  />
                ) : (
                  <Text style={styles.headerPhotoInitials}>
                    {getInitials(userProfile?.nome || "User")}
                  </Text>
                )}
              </View>
              {userProfile?.plano?.matricula_status?.nome ? (
                <View
                  style={[
                    styles.matriculaStatusBadge,
                    {
                      backgroundColor:
                        userProfile.plano.matricula_status.cor || "#6C757D",
                    },
                  ]}
                >
                  <Text style={styles.matriculaStatusBadgeText}>
                    {userProfile.plano.matricula_status.nome}
                  </Text>
                </View>
              ) : null}
            </View>
            <View style={styles.headerUserInfo}>
              <Text style={styles.headerUserName}>
                {userProfile?.nome || "Usuário"}
              </Text>
              {userRoleLabels.length > 0 ? (
                <Text style={styles.headerUserRoles}>
                  {userRoleLabels.join(" • ")}
                </Text>
              ) : null}
              {/* {getTenantDisplayName() && (
                  <TouchableOpacity
                    style={styles.tenantSwitchButton}
                    onPress={() => setShowTenantModal(true)}
                    accessibilityLabel="Trocar de academia"
                    activeOpacity={0.8}
                  >
                    <Feather name="building" size={12} color={colors.primary} />
                    <Text style={styles.tenantSwitchButtonText}>
                      {getTenantDisplayName()}
                    </Text>
                  </TouchableOpacity>
                )} */}
            </View>
          </View>
          <TouchableOpacity
            style={styles.headerMenuButton}
            onPress={openSidebar}
            accessibilityLabel="Abrir menu"
          >
            <Feather name="menu" size={22} color="#fff" />
          </TouchableOpacity>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Calendário Semanal de Check-ins */}
        <View style={styles.weekCalendarSection} className="notranslate">
          <View style={styles.weekCalendarHeader}>
            <Text style={styles.sectionTitle}>Minha Semana</Text>
            <Text style={styles.weekPeriodText}>{formatWeekPeriod()}</Text>
          </View>

          {weekLoading ? (
            <View style={styles.weekCalendarLoading}>
              <ActivityIndicator size="small" color={colors.primary} />
            </View>
          ) : (
            <View style={styles.weekCalendarGrid}>
              {getWeekDays().map((day) => {
                const checkins = getDayCheckins(day.dateStr);
                const hasCheckin = checkins.length > 0;
                const today = new Date();
                const isToday =
                  day.date.toDateString() === today.toDateString();
                const isPast = day.date < today && !isToday;

                return (
                  <View
                    key={day.dateStr}
                    style={[
                      styles.weekDayItem,
                      isPast && !hasCheckin && styles.weekDayItemMissed,
                      isToday && styles.weekDayItemToday,
                      hasCheckin && styles.weekDayItemChecked,
                    ]}
                  >
                    <Text
                      style={[
                        styles.weekDayName,
                        isToday && styles.weekDayNameToday,
                        hasCheckin && styles.weekDayNameChecked,
                      ]}
                      className="notranslate"
                    >
                      {day.dayName}
                    </Text>
                    <Text
                      style={[
                        styles.weekDayNumber,
                        isToday && styles.weekDayNumberToday,
                        hasCheckin && styles.weekDayNumberChecked,
                      ]}
                    >
                      {day.dayNumber}
                    </Text>
                    <View style={styles.weekDayCheckContainer}>
                      {hasCheckin ? (
                        <View style={styles.weekDayCheckIcon}>
                          <Feather name="check" size={16} color="#fff" />
                        </View>
                      ) : isPast ? (
                        <View style={styles.weekDayMissedIcon}>
                          <Feather name="x" size={14} color={colors.gray400} />
                        </View>
                      ) : isToday ? (
                        <View style={styles.weekDayIconContainer}>
                          <Feather
                            name="calendar"
                            size={12}
                            color={colors.gray500}
                          />
                        </View>
                      ) : (
                        <View style={styles.weekDayIconContainer}>
                          <Feather
                            name="clock"
                            size={12}
                            color={colors.gray400}
                          />
                        </View>
                      )}
                    </View>
                    {/* Indicador de modalidade */}
                    {hasCheckin && checkins[0]?.modalidade && (
                      <View
                        style={[
                          styles.weekDayModalidadeDot,
                          {
                            backgroundColor:
                              checkins[0].modalidade.cor || colors.primary,
                          },
                        ]}
                      />
                    )}
                  </View>
                );
              })}
            </View>
          )}

          {/* Legenda */}
          <View style={styles.weekCalendarLegend}>
            <View style={styles.weekLegendItem}>
              <View
                style={[styles.weekLegendDot, { backgroundColor: "#10b981" }]}
              />
              <Text style={styles.weekLegendText}>Check-in feito</Text>
            </View>
            <View style={styles.weekLegendItem}>
              <View
                style={[
                  styles.weekLegendDot,
                  { backgroundColor: colors.primary, opacity: 0.3 },
                ]}
              />
              <Text style={styles.weekLegendText}>Hoje</Text>
            </View>
          </View>
        </View>

        {/* Ranking de Check-ins */}
        <View style={styles.rankingSection}>
          <Text style={styles.sectionTitle}>
            Ranking de Check-ins{rankingPeriodo ? ` • ${rankingPeriodo}` : ""}
          </Text>
          {rankingModalidades.length > 0 && (
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.modalidadeTabs}
            >
              {rankingModalidades.map((modalidade) => {
                const active = selectedModalidadeId === modalidade.id;
                const chipColor = modalidade.cor || colors.primary;
                return (
                  <TouchableOpacity
                    key={modalidade.id}
                    style={[
                      styles.modalidadeTab,
                      active && styles.modalidadeTabActive,
                      {
                        borderColor: active ? chipColor : "#f3e6d9",
                        backgroundColor: active ? chipColor : "#fff",
                      },
                    ]}
                    onPress={() => {
                      setSelectedModalidadeId(modalidade.id);
                    }}
                  >
                    <View style={styles.modalidadeChipContent}>
                      {modalidade.icone ? (
                        <MaterialCommunityIcons
                          name={modalidade.icone as any}
                          size={14}
                          color={active ? "#fff" : chipColor}
                        />
                      ) : null}
                      <Text
                        style={[
                          styles.modalidadeChipText,
                          active && styles.modalidadeChipTextActive,
                          { color: active ? "#fff" : "#8b6b3b" },
                        ]}
                      >
                        {modalidade.nome}
                      </Text>
                    </View>
                    {active && (
                      <View
                        style={[
                          styles.modalidadeTabIndicator,
                          { backgroundColor: chipColor },
                        ]}
                      />
                    )}
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          )}
          <View style={styles.rankingCard}>
            {rankingLoading ? (
              <View style={styles.rankingLoading}>
                <ActivityIndicator size="small" color={colors.primary} />
                <Text style={styles.rankingLoadingText}>
                  Carregando ranking...
                </Text>
              </View>
            ) : rankingError ? (
              <Text style={styles.rankingErrorText}>{rankingError}</Text>
            ) : (
              <>
                <View style={styles.rankingList}>
                  {ranking.slice(0, 3).map((item) => {
                    const pos = Number(item.posicao) || 0;
                    const isGold = pos === 1;
                    const isSilver = pos === 2;
                    const isBronze = pos === 3;
                    const badgeColor = isGold
                      ? "#f59e0b"
                      : isSilver
                        ? "#94a3b8"
                        : "#d97706";
                    const badgeSoft = isGold
                      ? "#fff7ed"
                      : isSilver
                        ? "#f8fafc"
                        : "#fff7ed";
                    return (
                      <View
                        key={item.aluno?.id ?? item.posicao}
                        style={[
                          styles.rankingListItem,
                          isGold && styles.rankingListItemGold,
                          isSilver && styles.rankingListItemSilver,
                          isBronze && styles.rankingListItemBronze,
                        ]}
                      >
                        <View
                          style={[
                            styles.rankingPosition,
                            { backgroundColor: badgeColor },
                          ]}
                        >
                          {isGold && (
                            <MaterialCommunityIcons
                              name="crown"
                              size={14}
                              color="#fff"
                            />
                          )}
                          <Text style={styles.rankingPositionNumber}>
                            {item.posicao}
                          </Text>
                        </View>
                        <View style={styles.rankingAvatar}>
                          {item.aluno?.foto_caminho && apiUrl ? (
                            <Image
                              source={{
                                uri: `${apiUrl}${item.aluno.foto_caminho}`,
                              }}
                              style={styles.rankingAvatarImage}
                            />
                          ) : (
                            <View style={styles.rankingAvatarPlaceholder}>
                              <MaterialCommunityIcons
                                name="account"
                                size={20}
                                color="#9ca3af"
                              />
                            </View>
                          )}
                        </View>
                        <View style={styles.rankingListContent}>
                          <View style={styles.rankingNameRow}>
                            <Text style={styles.rankingName}>
                              {item.aluno?.nome ?? "Usuário"}
                            </Text>
                            <View
                              style={[
                                styles.rankingTopBadge,
                                { backgroundColor: badgeSoft },
                              ]}
                            >
                              <Text
                                style={[
                                  styles.rankingTopBadgeText,
                                  { color: badgeColor },
                                ]}
                              >
                                TOP {item.posicao}
                              </Text>
                            </View>
                          </View>
                          <Text style={styles.rankingCheckins}>
                            {item.total_checkins} check-ins
                          </Text>
                        </View>
                      </View>
                    );
                  })}
                </View>
                <View style={styles.rankingDivider} />
                <View style={styles.rankingUserRow}>
                  <View>
                    <Text style={styles.rankingUserLabel}>Sua posicao</Text>
                    <Text style={styles.rankingUserName}>
                      {userProfile.nome}
                    </Text>
                  </View>
                  <View style={styles.rankingUserPosition}>
                    <Text style={styles.rankingUserPositionText}>
                      {userProfile.ranking_modalidades?.find(
                        (item) => item.modalidade_id === selectedModalidadeId,
                      )?.posicao ||
                        ranking.find(
                          (item) => item.aluno?.id === userProfile.id,
                        )?.posicao ||
                        "--"}
                    </Text>
                  </View>
                </View>
              </>
            )}
          </View>
        </View>

        {/* Member Since */}
        {userProfile.membro_desde && (
          <View style={styles.memberSinceSection}>
            <Feather name="heart" size={16} color={colors.primary} />
            <Text style={styles.memberSinceText}>
              Membro desde{" "}
              {new Date(userProfile.membro_desde).toLocaleDateString("pt-BR")}
            </Text>
          </View>
        )}

        {/* Change Password Button */}
        <TouchableOpacity
          style={styles.changePasswordButton}
          onPress={() => setShowRecoveryModal(true)}
        >
          <Feather name="key" size={20} color="#fff" />
          <Text style={styles.changePasswordText}>Alterar Senha</Text>
        </TouchableOpacity>
      </ScrollView>

      <Modal transparent visible={showSidebar} animationType="none">
        <Pressable style={styles.sidebarOverlay} onPress={closeSidebar} />
        <Animated.View
          style={[
            styles.sidebarContainer,
            { transform: [{ translateX: sidebarTranslateX }] },
          ]}
        >
          <View style={styles.sidebarHeader}>
            <View style={styles.sidebarPhotoWrapper}>
              <TouchableOpacity
                style={styles.sidebarPhotoContainer}
                onPress={handleChangePhoto}
                disabled={updatingPhoto}
              >
                {userPhotoUri ? (
                  <Image
                    source={{ uri: userPhotoUri }}
                    style={styles.sidebarPhotoImage}
                  />
                ) : (
                  <Text style={styles.sidebarPhotoInitials}>
                    {getInitials(userProfile.nome)}
                  </Text>
                )}
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.sidebarPhotoCameraBadge}
                onPress={handleChangePhoto}
                disabled={updatingPhoto}
                accessibilityLabel="Alterar foto"
              >
                <Feather name="camera" size={28} color="#fff" />
              </TouchableOpacity>
              {userProfile?.plano?.matricula_status?.nome ? (
                <View
                  style={[
                    styles.sidebarMatriculaBadge,
                    {
                      backgroundColor:
                        userProfile.plano.matricula_status.cor || "#6C757D",
                    },
                  ]}
                >
                  <Text style={styles.sidebarMatriculaBadgeText}>
                    {userProfile.plano.matricula_status.nome}
                  </Text>
                </View>
              ) : null}
            </View>
            <View style={styles.sidebarHeaderInfo}>
              <Text style={styles.sidebarUserName}>{userProfile.nome}</Text>
              {userProfile?.plano?.vencimento?.texto ? (
                <View
                  style={[
                    styles.sidebarPlanStatusRow,
                    {
                      backgroundColor:
                        (userProfile.plano.vencimento.dias_restantes ?? 0) < 0
                          ? "rgba(220, 53, 69, 0.15)"
                          : "rgba(40, 167, 69, 0.15)",
                    },
                  ]}
                >
                  <Feather
                    name={
                      (userProfile.plano.vencimento.dias_restantes ?? 0) < 0
                        ? "alert-circle"
                        : "clock"
                    }
                    size={13}
                    color={
                      (userProfile.plano.vencimento.dias_restantes ?? 0) < 0
                        ? "#DC3545"
                        : "#28A745"
                    }
                  />
                  <Text
                    style={[
                      styles.sidebarPlanStatusText,
                      {
                        color:
                          (userProfile.plano.vencimento.dias_restantes ?? 0) < 0
                            ? "#DC3545"
                            : "#28A745",
                      },
                    ]}
                  >
                    {(userProfile.plano.vencimento.dias_restantes ?? 0) < 0
                      ? "BLOQUEADO"
                      : userProfile.plano.vencimento.texto}
                  </Text>
                </View>
              ) : null}
            </View>
            <TouchableOpacity
              style={styles.sidebarCloseButton}
              onPress={closeSidebar}
            >
              <Feather name="chevron-right" size={20} color="#fff" />
            </TouchableOpacity>
          </View>

          <ScrollView
            contentContainerStyle={styles.sidebarContent}
            showsVerticalScrollIndicator={false}
          >
            <View style={styles.infoSectionSidebar}>
              <Text style={styles.sidebarSectionTitle}>
                Informações Pessoais
              </Text>

              <View style={styles.sidebarInfoItem}>
                <View style={styles.sidebarIconBubble}>
                  <Feather name="mail" size={14} color="#fff" />
                </View>
                <View style={styles.infoContent}>
                  <Text style={styles.infoLabelSidebar}>Email</Text>
                  <Text style={styles.infoValueSidebar}>
                    {userProfile.email}
                  </Text>
                </View>
              </View>

              {userProfile.cpf && (
                <View style={styles.sidebarInfoItem}>
                  <View style={styles.sidebarIconBubble}>
                    <Feather name="credit-card" size={14} color="#fff" />
                  </View>
                  <View style={styles.infoContent}>
                    <Text style={styles.infoLabelSidebar}>CPF</Text>
                    <Text style={styles.infoValueSidebar}>
                      {formatCPF(userProfile.cpf)}
                    </Text>
                  </View>
                </View>
              )}

              {userProfile.telefone && (
                <View style={styles.sidebarInfoItem}>
                  <View style={styles.sidebarIconBubble}>
                    <Feather name="phone" size={14} color="#fff" />
                  </View>
                  <View style={styles.infoContent}>
                    <Text style={styles.infoLabelSidebar}>Telefone</Text>
                    <Text style={styles.infoValueSidebar}>
                      {formatPhone(userProfile.telefone)}
                    </Text>
                  </View>
                </View>
              )}

              {userProfile.data_nascimento && (
                <View style={styles.sidebarInfoItem}>
                  <View style={styles.sidebarIconBubble}>
                    <Feather name="calendar" size={14} color="#fff" />
                  </View>
                  <View style={styles.infoContent}>
                    <Text style={styles.infoLabelSidebar}>
                      Data de Nascimento
                    </Text>
                    <Text style={styles.infoValueSidebar}>
                      {new Date(userProfile.data_nascimento).toLocaleDateString(
                        "pt-BR",
                      )}
                    </Text>
                  </View>
                </View>
              )}
            </View>

            {userProfile.tenants && userProfile.tenants.length > 0 && (
              <View style={styles.academiasSectionSidebar}>
                <Text style={styles.sidebarSectionTitle}>Minhas Academias</Text>
                {userProfile.tenants.map((tenant) => (
                  <TouchableOpacity
                    key={tenant.id}
                    style={styles.academiaCardSidebar}
                    onPress={() => {
                      closeSidebar();
                      router.push(`/planos?tenantId=${tenant.id}`);
                    }}
                    activeOpacity={0.7}
                  >
                    <View style={styles.academiaCardContent}>
                      <Text style={styles.academiaNameSidebar}>
                        {tenant.nome}
                      </Text>
                      {tenant.email && (
                        <View style={styles.academiaInfoSidebar}>
                          <Feather name="mail" size={12} color="#fff" />
                          <Text style={styles.academiaInfoTextSidebar}>
                            {tenant.email}
                          </Text>
                        </View>
                      )}
                      {tenant.telefone && (
                        <View style={styles.academiaInfoSidebar}>
                          <Feather name="phone" size={12} color="#fff" />
                          <Text style={styles.academiaInfoTextSidebar}>
                            {tenant.telefone}
                          </Text>
                        </View>
                      )}
                    </View>
                    <View style={styles.sidebarChevronButton}>
                      <Feather name="chevron-right" size={18} color="#fff" />
                    </View>
                  </TouchableOpacity>
                ))}
              </View>
            )}

            <TouchableOpacity
              style={styles.sidebarMenuItem}
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
                  closeSidebar();
                  router.push("/planos");
                } catch (err) {
                  console.error("❌ Erro ao navegar para planos:", err);
                }
              }}
            >
              <View style={styles.sidebarMenuItemIcon}>
                <Feather name="shopping-cart" size={16} color="#fff" />
              </View>
              <Text style={styles.sidebarMenuItemText}>Planos</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.sidebarMenuItem}
              onPress={() => {
                closeSidebar();
                router.push("/minhas-assinaturas");
              }}
            >
              <View style={styles.sidebarMenuItemIcon}>
                <Feather name="list" size={16} color="#fff" />
              </View>
              <Text style={styles.sidebarMenuItemText}>Minhas Assinaturas</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.sidebarLogoutButton}
              onPress={() => {
                closeSidebar();
                router.push("/logout");
              }}
            >
              <Feather name="log-out" size={16} color="#fff" />
              <Text style={styles.sidebarLogoutText}>Sair</Text>
            </TouchableOpacity>
          </ScrollView>
        </Animated.View>
      </Modal>

      {/* Tenant Switch Modal */}
      <Modal transparent visible={showTenantModal} animationType="fade">
        <Pressable
          style={styles.tenantModalOverlay}
          onPress={() => setShowTenantModal(false)}
        />
        <View style={styles.tenantModalContainer}>
          <View style={styles.tenantModalCard}>
            <Text style={styles.tenantModalTitle}>Trocar de academia</Text>
            <View style={{ gap: 10 }}>
              {tenants.map((t) => {
                const id = t?.tenant?.id ?? t?.id;
                const nome = t?.tenant?.nome ?? t?.nome ?? "Academia";
                return (
                  <TouchableOpacity
                    key={String(id)}
                    style={styles.tenantOptionButton}
                    onPress={() => handleSelectTenant(id)}
                    activeOpacity={0.8}
                  >
                    <Feather name="home" size={16} color="#fff" />
                    <Text style={styles.tenantOptionText}>{nome}</Text>
                  </TouchableOpacity>
                );
              })}
            </View>
            <TouchableOpacity
              style={[
                styles.tenantOptionButton,
                { marginTop: 14, backgroundColor: "#6b7280" },
              ]}
              onPress={() => setShowTenantModal(false)}
            >
              <Feather name="x" size={16} color="#fff" />
              <Text style={styles.tenantOptionText}>Cancelar</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* Password Recovery Modal */}
      <PasswordRecoveryModal
        visible={showRecoveryModal}
        onClose={() => setShowRecoveryModal(false)}
      />

      {/* Debug Logs Viewer - Apenas em Desenvolvimento */}
      {__DEV__ && <DebugLogsViewer />}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
  },
  errorBanner: {
    backgroundColor: "#fee2e2",
    borderBottomWidth: 1,
    borderBottomColor: "#fecaca",
    paddingHorizontal: 16,
    paddingVertical: 12,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  errorBannerText: {
    flex: 1,
    color: "#dc2626",
    fontSize: 13,
    fontWeight: "500",
  },
  headerTop: {
    paddingHorizontal: 20,
    paddingTop: 18,
    paddingBottom: 20,
    backgroundColor: colors.primary,
    borderBottomWidth: 0,
    borderBottomLeftRadius: 22,
    borderBottomRightRadius: 22,
    ...withShadow("0px 3px 8px rgba(0, 0, 0, 0.18)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 3 },
      shadowOpacity: 0.18,
      shadowRadius: 8,
      elevation: 6,
    }),
  },
  headerUserRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
  },
  headerUserLeft: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    flex: 1,
  },
  headerUserInfo: {
    flex: 1,
    gap: 4,
    justifyContent: "center",
  },
  headerUserName: {
    fontSize: 17,
    fontWeight: "800",
    color: "#fff",
    flexShrink: 1,
    lineHeight: 22,
    includeFontPadding: false,
  },
  headerUserRoles: {
    fontSize: 12,
    fontWeight: "700",
    color: "rgba(255,255,255,0.9)",
    lineHeight: 16,
    includeFontPadding: false,
    marginTop: 0,
  },
  headerMenuButton: {
    marginLeft: 6,
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: "rgba(255,255,255,0.2)",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.25)",
    justifyContent: "center",
    alignItems: "center",
  },
  headerPhotoWrapper: {
    position: "relative",
    width: 84,
    height: 84,
    borderRadius: 42,
    marginBottom: 12,
    overflow: "visible",
  },
  headerPhotoContainer: {
    width: 84,
    height: 84,
    borderRadius: 42,
    backgroundColor: "rgba(255,255,255,0.2)",
    justifyContent: "center",
    alignItems: "center",
    overflow: "hidden",
    borderWidth: 2,
    borderColor: "rgba(255,255,255,0.6)",
  },
  headerPhotoImage: {
    width: "100%",
    height: "100%",
    borderRadius: 999,
  },
  headerPhotoInitials: {
    fontSize: 20,
    fontWeight: "800",
    color: "#fff",
    lineHeight: 20,
    includeFontPadding: false,
    textAlignVertical: "center",
  },
  headerPhotoLoading: {
    position: "absolute",
    width: "100%",
    height: "100%",
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "center",
    alignItems: "center",
  },
  matriculaStatusBadge: {
    position: "absolute",
    bottom: -10,
    alignSelf: "center",
    left: -6,
    right: -6,
    paddingHorizontal: 6,
    paddingVertical: 3,
    borderRadius: 10,
    zIndex: 10,
    alignItems: "center",
    justifyContent: "center",
    overflow: "visible",
    ...Platform.select({
      ios: {
        shadowColor: "#000",
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.25,
        shadowRadius: 4,
      },
      android: {
        elevation: 4,
      },
      web: {
        boxShadow: "0 2px 8px rgba(0,0,0,0.3)",
      },
    }),
  },
  matriculaStatusBadgeText: {
    color: "#fff",
    fontSize: 11,
    fontWeight: "700",
    letterSpacing: 0.5,
    textAlign: "center",
  },
  sidebarOverlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
  },
  sidebarContainer: {
    position: "absolute",
    top: 0,
    left: 0,
    bottom: 0,
    width: "82%",
    backgroundColor: colors.primary,
    paddingTop: 24,
    paddingHorizontal: 18,
  },
  sidebarHeader: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255,255,255,0.22)",
  },
  sidebarHeaderInfo: {
    flex: 1,
  },
  sidebarUserName: {
    fontSize: 18,
    fontWeight: "800",
    color: "#fff",
  },
  sidebarPlanStatusRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    marginTop: 6,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    alignSelf: "flex-start",
  },
  sidebarPlanStatusText: {
    fontSize: 11,
    fontWeight: "700",
    letterSpacing: 0.3,
  },
  sidebarCloseButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: "rgba(255,255,255,0.2)",
    justifyContent: "center",
    alignItems: "center",
  },
  sidebarPhotoContainer: {
    width: 128,
    height: 128,
    borderRadius: 64,
    backgroundColor: "rgba(255,255,255,0.18)",
    justifyContent: "center",
    alignItems: "center",
    overflow: "hidden",
    borderWidth: 2,
    borderColor: "rgba(255,255,255,0.7)",
  },
  sidebarPhotoWrapper: {
    position: "relative",
    width: 128,
    height: 128,
    borderRadius: 64,
    marginBottom: 16,
    overflow: "visible",
  },
  sidebarPhotoImage: {
    width: "100%",
    height: "100%",
    borderRadius: 999,
  },
  sidebarPhotoCameraBadge: {
    position: "absolute",
    right: -8,
    bottom: -8,
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "center",
    alignItems: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.7)",
  },
  sidebarMatriculaBadge: {
    position: "absolute",
    bottom: -14,
    left: -10,
    right: -10,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    zIndex: 20,
    alignItems: "center",
    justifyContent: "center",
    overflow: "visible",
    ...Platform.select({
      ios: {
        shadowColor: "#000",
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.3,
        shadowRadius: 4,
      },
      android: {
        elevation: 5,
      },
      web: {
        boxShadow: "0 2px 8px rgba(0,0,0,0.3)",
      },
    }),
  },
  sidebarMatriculaBadgeText: {
    color: "#fff",
    fontSize: 13,
    fontWeight: "700",
    letterSpacing: 0.5,
    textAlign: "center",
  },
  sidebarPhotoInitials: {
    fontSize: 44,
    fontWeight: "800",
    color: "#fff",
  },
  sidebarContent: {
    paddingTop: 18,
    paddingBottom: 32,
  },
  sidebarSectionTitle: {
    fontSize: 13,
    fontWeight: "700",
    color: "rgba(255,255,255,0.7)",
    marginBottom: 12,
  },
  infoSectionSidebar: {
    marginBottom: 20,
    padding: 14,
    borderRadius: 14,
    backgroundColor: "rgba(255,255,255,0.16)",
  },
  academiasSectionSidebar: {
    marginBottom: 20,
    padding: 14,
    borderRadius: 14,
    backgroundColor: "rgba(255,255,255,0.16)",
  },
  academiaCardSidebar: {
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255,255,255,0.18)",
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
  },
  academiaNameSidebar: {
    fontSize: 14,
    fontWeight: "700",
    color: "#fff",
    marginBottom: 6,
  },
  sidebarInfoItem: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 10,
    gap: 10,
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255,255,255,0.12)",
  },
  sidebarIconBubble: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: "rgba(255,255,255,0.22)",
    justifyContent: "center",
    alignItems: "center",
  },
  infoLabelSidebar: {
    fontSize: 11,
    color: "rgba(255,255,255,0.7)",
    marginBottom: 2,
  },
  infoValueSidebar: {
    fontSize: 13,
    color: "#fff",
    fontWeight: "600",
  },
  academiaInfoSidebar: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginBottom: 4,
  },
  academiaInfoTextSidebar: {
    fontSize: 12,
    color: "rgba(255,255,255,0.85)",
  },
  sidebarChevronButton: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: "rgba(255,255,255,0.25)",
    justifyContent: "center",
    alignItems: "center",
  },
  sidebarLogoutButton: {
    marginTop: 6,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 10,
    paddingVertical: 12,
    borderRadius: 12,
    backgroundColor: "rgba(255,255,255,0.18)",
  },
  sidebarLogoutText: {
    fontSize: 13,
    fontWeight: "700",
    color: "#fff",
  },
  sidebarMenuItem: {
    marginVertical: 8,
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 12,
    backgroundColor: "rgba(255,255,255,0.12)",
  },
  sidebarMenuItemIcon: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: "rgba(255,255,255,0.2)",
    justifyContent: "center",
    alignItems: "center",
  },
  sidebarMenuItemText: {
    fontSize: 14,
    fontWeight: "600",
    color: "#fff",
  },
  scrollContent: {
    padding: 18,
    paddingBottom: 48,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingVertical: 50,
  },
  errorText: {
    fontSize: 16,
    color: "#666",
    marginBottom: 16,
  },
  retryButton: {
    backgroundColor: colors.primary,
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 8,
  },
  retryButtonText: {
    color: "#fff",
    fontWeight: "600",
  },
  statisticsSection: {
    marginBottom: 20,
  },
  // Calendário Semanal
  weekCalendarSection: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 18,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    ...withShadow("0px 6px 10px rgba(0, 0, 0, 0.06)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.06,
      shadowRadius: 10,
      elevation: 2,
    }),
  },
  weekCalendarHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 16,
  },
  weekPeriodText: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: "500",
  },
  weekCalendarLoading: {
    paddingVertical: 40,
    alignItems: "center",
  },
  weekCalendarGrid: {
    flexDirection: "row",
    justifyContent: "space-between",
    gap: 6,
  },
  weekDayItem: {
    flex: 1,
    alignItems: "center",
    paddingVertical: 10,
    paddingHorizontal: 2,
    borderRadius: 12,
    backgroundColor: "#F1F3F5",
    minWidth: 38,
  },
  weekDayItemToday: {
    backgroundColor: colors.primary + "15",
    borderWidth: 2,
    borderColor: colors.primary,
  },
  weekDayItemChecked: {
    backgroundColor: "#dcfce7",
    borderWidth: 2,
    borderColor: "#10b981",
  },
  weekDayItemMissed: {
    backgroundColor: colors.gray100,
    borderWidth: 1,
    borderColor: colors.gray200,
  },
  weekDayName: {
    fontSize: 11,
    fontWeight: "600",
    color: colors.textMuted,
    marginBottom: 2,
  },
  weekDayNameToday: {
    color: colors.primary,
  },
  weekDayNameChecked: {
    color: "#059669",
    fontWeight: "700",
  },
  weekDayNumber: {
    fontSize: 18,
    fontWeight: "700",
    color: colors.text,
    marginBottom: 6,
  },
  weekDayNumberToday: {
    color: colors.primary,
  },
  weekDayNumberChecked: {
    color: "#059669",
  },
  weekDayCheckContainer: {
    height: 26,
    justifyContent: "center",
    alignItems: "center",
  },
  weekDayCheckIcon: {
    width: 26,
    height: 26,
    borderRadius: 13,
    backgroundColor: "#10b981",
    justifyContent: "center",
    alignItems: "center",
    ...withShadow("0px 2px 3px rgba(16, 185, 129, 0.25)", {
      shadowColor: "#10b981",
      shadowOffset: { width: 0, height: 2 },
      shadowOpacity: 0.25,
      shadowRadius: 3,
      elevation: 2,
    }),
  },
  weekDayMissedIcon: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: colors.gray100,
    justifyContent: "center",
    alignItems: "center",
    borderWidth: 1,
    borderColor: colors.gray200,
  },
  weekDayIconContainer: {
    width: 24,
    height: 24,
    justifyContent: "center",
    alignItems: "center",
  },
  weekDayEmptyIcon: {
    width: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: "transparent",
  },
  weekDayModalidadeDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginTop: 6,
  },
  weekCalendarLegend: {
    flexDirection: "row",
    justifyContent: "center",
    gap: 20,
    marginTop: 14,
    paddingTop: 14,
    borderTopWidth: 1,
    borderTopColor: "#f0f1f4",
  },
  weekLegendItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  weekLegendDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  weekLegendText: {
    fontSize: 11,
    color: colors.textMuted,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: "800",
    color: colors.text,
    marginBottom: 16,
  },
  statsGrid: {
    flexDirection: "row",
    gap: 12,
    marginBottom: 12,
  },
  statCard: {
    flex: 1,
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 14,
    alignItems: "center",
    borderWidth: 1,
    borderColor: "#f0f1f4",
    ...withShadow("0px 6px 10px rgba(0, 0, 0, 0.06)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.06,
      shadowRadius: 10,
      elevation: 2,
    }),
  },
  statLabel: {
    fontSize: 13,
    color: colors.textMuted,
    marginTop: 8,
    marginBottom: 4,
    textAlign: "center",
  },
  statValue: {
    fontSize: 21,
    fontWeight: "800",
    color: colors.primaryDark,
  },
  lastCheckinCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 14,
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    ...withShadow("0px 6px 10px rgba(0, 0, 0, 0.06)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.06,
      shadowRadius: 10,
      elevation: 2,
    }),
  },
  lastCheckinContent: {
    flex: 1,
  },
  lastCheckinLabel: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 2,
  },
  lastCheckinValue: {
    fontSize: 16,
    fontWeight: "700",
    color: colors.text,
  },
  infoSection: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 18,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    ...withShadow("0px 6px 10px rgba(0, 0, 0, 0.06)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.06,
      shadowRadius: 10,
      elevation: 2,
    }),
  },
  infoItem: {
    flexDirection: "row",
    alignItems: "flex-start",
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#eef2f7",
    gap: 12,
  },
  infoContent: {
    flex: 1,
  },
  infoLabel: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 4,
  },
  infoValue: {
    fontSize: 16,
    fontWeight: "600",
    color: colors.text,
  },
  membershipSection: {
    marginBottom: 20,
  },
  membershipCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    ...withShadow("0px 6px 10px rgba(0, 0, 0, 0.06)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.06,
      shadowRadius: 10,
      elevation: 2,
    }),
  },
  membershipHeader: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 12,
    gap: 12,
  },
  membershipInfo: {
    flex: 1,
  },
  membershipName: {
    fontSize: 16,
    fontWeight: "600",
    color: "#000",
  },
  membershipStatus: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: "500",
    marginTop: 2,
    textTransform: "capitalize",
  },
  membershipDates: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 8,
    gap: 8,
  },
  membershipLabel: {
    fontSize: 12,
    color: "#999",
    fontWeight: "500",
  },
  membershipValue: {
    fontSize: 13,
    color: "#000",
    fontWeight: "500",
  },
  membershipValue2: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 8,
    gap: 8,
  },
  membershipPriceValue: {
    fontSize: 14,
    color: colors.primary,
    fontWeight: "600",
  },
  viewDetailsButton: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    marginTop: 16,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: "#eef2f7",
  },
  viewDetailsText: {
    fontSize: 14,
    color: colors.primary,
    fontWeight: "600",
  },
  memberSinceSection: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 12,
    marginBottom: 20,
    gap: 6,
  },
  memberSinceText: {
    fontSize: 13,
    color: colors.textMuted,
  },
  contratosSection: {
    marginBottom: 20,
  },
  academiasSection: {
    marginBottom: 20,
  },
  rankingSection: {
    marginBottom: 20,
  },
  modalidadeTabs: {
    gap: 12,
    paddingVertical: 8,
    paddingHorizontal: 4,
    marginBottom: 16,
  },
  modalidadeTab: {
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 16,
    backgroundColor: "#fff",
    borderWidth: 1,
    borderColor: "#fde3c8",
  },
  modalidadeTabActive: {
    backgroundColor: "transparent",
    borderColor: "transparent",
  },
  modalidadeTabIndicator: {
    marginTop: 6,
    height: 3,
    borderRadius: 999,
    alignSelf: "stretch",
  },
  modalidadeChipContent: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  modalidadeChipText: {
    fontSize: 15,
    color: "#9a5b1e",
    fontWeight: "800",
  },
  modalidadeChipTextActive: {
    color: "#fff",
  },
  rankingCard: {
    backgroundColor: "#fff7ed",
    borderRadius: 18,
    padding: 18,
    borderWidth: 1,
    borderColor: colors.primary + "35",
    ...withShadow("0px 6px 12px rgba(0, 0, 0, 0.1)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.1,
      shadowRadius: 12,
      elevation: 4,
    }),
  },
  rankingList: {
    gap: 10,
  },
  rankingListItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "#fff",
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: colors.primary + "25",
  },
  rankingListItemGold: {
    borderColor: "#f59e0b55",
    backgroundColor: "#fffbeb",
  },
  rankingListItemSilver: {
    borderColor: "#94a3b855",
    backgroundColor: "#f8fafc",
  },
  rankingListItemBronze: {
    borderColor: "#d9770655",
    backgroundColor: "#fff7ed",
  },
  rankingPosition: {
    minWidth: 46,
    height: 40,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.6)",
    alignItems: "center",
    justifyContent: "center",
    gap: 2,
  },
  rankingPositionNumber: {
    fontSize: 14,
    fontWeight: "800",
    color: "#fff",
  },
  rankingAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: "#f3f4f6",
    justifyContent: "center",
    alignItems: "center",
    overflow: "hidden",
  },
  rankingAvatarImage: {
    width: 36,
    height: 36,
    borderRadius: 18,
  },
  rankingAvatarPlaceholder: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: "#e5e7eb",
    justifyContent: "center",
    alignItems: "center",
  },
  rankingAvatarText: {
    fontSize: 20,
  },
  rankingNameRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  rankingTopBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 999,
  },
  rankingTopBadgeText: {
    fontSize: 10,
    fontWeight: "800",
    letterSpacing: 0.5,
  },
  rankingListContent: {
    flex: 1,
  },
  rankingName: {
    fontSize: 15,
    fontWeight: "700",
    color: "#111827",
  },
  rankingCheckins: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 2,
  },
  rankingLoading: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 8,
  },
  rankingLoadingText: {
    fontSize: 13,
    color: "#6b7280",
  },
  rankingErrorText: {
    fontSize: 13,
    color: "#b91c1c",
  },
  rankingDivider: {
    height: 1,
    backgroundColor: colors.primary + "30",
    marginVertical: 14,
  },
  rankingUserRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  rankingUserLabel: {
    fontSize: 13,
    color: colors.textSecondary,
    marginBottom: 2,
  },
  rankingUserName: {
    fontSize: 17,
    fontWeight: "700",
    color: "#111827",
  },
  rankingUserPosition: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 12,
    backgroundColor: colors.primary,
    borderWidth: 1,
    borderColor: colors.primary,
  },
  rankingUserPositionText: {
    fontSize: 14,
    fontWeight: "800",
    color: "#fff",
  },
  academiaCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    borderWidth: 1,
    borderColor: "#f0f1f4",
    ...withShadow("0px 6px 10px rgba(0, 0, 0, 0.06)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.06,
      shadowRadius: 10,
      elevation: 2,
    }),
  },
  academiaCardContent: {
    flex: 1,
  },
  modalidadeBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
    alignSelf: "flex-start",
    marginBottom: 8,
  },
  modalidadeDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  modalidadeText: {
    fontSize: 12,
    fontWeight: "600",
  },
  academiaName: {
    fontSize: 16,
    fontWeight: "700",
    color: colors.text,
    marginBottom: 10,
  },
  academiaInfo: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginBottom: 6,
  },
  academiaInfoText: {
    fontSize: 13,
    color: colors.textSecondary,
  },
  contratoCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    ...withShadow("0px 6px 10px rgba(0, 0, 0, 0.06)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.06,
      shadowRadius: 10,
      elevation: 2,
    }),
  },
  contratoHeader: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 16,
    gap: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#eef2f7",
  },
  contratoInfo: {
    flex: 1,
  },
  contratoNome: {
    fontSize: 16,
    fontWeight: "600",
    color: "#000",
  },
  contratoStatus: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: "500",
    marginTop: 2,
    textTransform: "capitalize",
  },
  contratoSection2: {
    marginVertical: 12,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#eef2f7",
  },
  contratoSectionTitle: {
    fontSize: 13,
    fontWeight: "600",
    color: "#666",
    marginBottom: 8,
    textTransform: "uppercase",
  },
  contratoItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 6,
  },
  contratoLabel: {
    fontSize: 12,
    color: "#999",
    fontWeight: "500",
  },
  contratoValue: {
    fontSize: 13,
    color: "#000",
    fontWeight: "600",
  },
  contratoDiasRestantes: {
    fontSize: 13,
    color: colors.primary,
    fontWeight: "600",
  },
  progressContainer: {
    width: "100%",
    height: 6,
    backgroundColor: "#f0f0f0",
    borderRadius: 3,
    marginTop: 8,
    overflow: "hidden",
  },
  progressBar: {
    height: "100%",
    backgroundColor: colors.primary,
  },
  contratoFeatures: {
    marginTop: 8,
  },
  featureItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 6,
  },
  featureText: {
    fontSize: 12,
    color: "#666",
    fontWeight: "500",
    flex: 1,
  },
  pagamentoItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 8,
    paddingHorizontal: 8,
    backgroundColor: "#f9f9f9",
    borderRadius: 6,
    marginVertical: 6,
  },
  pagamentoInfo: {
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  statusText: {
    fontSize: 11,
    color: "#fff",
    fontWeight: "600",
  },
  changePasswordButton: {
    backgroundColor: colors.primary,
    borderRadius: 16,
    paddingVertical: 16,
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    gap: 10,
    ...withShadow("0px 6px 10px rgba(0, 0, 0, 0.12)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.12,
      shadowRadius: 10,
      elevation: 3,
    }),
  },
  changePasswordText: {
    color: "#fff",
    fontSize: 17,
    fontWeight: "700",
  },
  tenantSwitchButton: {
    marginTop: 8,
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: "transparent",
    paddingHorizontal: 0,
    paddingVertical: 6,
    borderRadius: 8,
    alignSelf: "flex-start",
  },
  tenantSwitchButtonText: {
    color: colors.primary,
    fontSize: 13,
    fontWeight: "600",
  },
  tenantSwitchText: {
    color: "#fff",
    fontSize: 14,
    fontWeight: "500",
  },
  tenantOptionButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: colors.primary,
    paddingHorizontal: 12,
    paddingVertical: 12,
    borderRadius: 12,
    justifyContent: "center",
  },
  tenantOptionText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 16,
  },
  tenantModalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
  },
  tenantModalContainer: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 24,
  },
  tenantModalCard: {
    width: "100%",
    maxWidth: 520,
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 18,
    borderWidth: 1,
    borderColor: "#f0f1f4",
    ...withShadow("0px 6px 10px rgba(0, 0, 0, 0.1)", {
      shadowColor: "#000",
      shadowOffset: { width: 0, height: 6 },
      shadowOpacity: 0.1,
      shadowRadius: 10,
      elevation: 3,
    }),
  },
  tenantModalTitle: {
    fontSize: 18,
    fontWeight: "800",
    color: colors.text,
    marginBottom: 12,
    textAlign: "center",
  },
});
