import { DebugLogsViewer } from "@/components/DebugLogsViewer";
import PasswordRecoveryModal from "@/components/PasswordRecoveryModal";
import AuthService from "@/src/services/authService";
import SideMenu, { SideMenuGroup } from "@/src/components/ds/SideMenu";
import { ds } from "@/src/components/ds/tokens";
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
import { isSessionExpiredVisible } from "@/src/utils/sessionExpired";
import {
    compressImage,
    logCompressionInfo,
} from "@/src/utils/imageCompression";
import AsyncStorage from "@/src/utils/storage";
import { weekdayAbbrev } from "@/src/utils/weekdayAbbrev";
import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import { useFocusEffect } from "@react-navigation/native";
import Constants from "expo-constants";
import * as ImagePicker from "expo-image-picker";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect, useRef, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    Animated,
    Dimensions,
    Image,
    Linking,
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
  const [switchingTenantId, setSwitchingTenantId] = useState<number | null>(null);
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
  const [unreadNotificationsCount, setUnreadNotificationsCount] = useState(0);
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

  const loadUnreadNotificationsCount = useCallback(async () => {
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        setUnreadNotificationsCount(0);
        return;
      }

      const response = await fetch(
        `${getApiUrlRuntime()}/notificacoes/unread`,
        {
          method: "GET",
          headers: {
            Authorization: `Bearer ${token}`,
          },
        },
      );

      const text = await response.text();
      let json: any = {};
      try {
        json = text ? JSON.parse(text) : {};
      } catch {
        json = {};
      }

      if (!response.ok || json?.success === false) {
        setUnreadNotificationsCount(0);
        return;
      }

      const total =
        typeof json?.total === "number"
          ? json.total
          : Array.isArray(json?.data)
            ? json.data.length
            : 0;

      setUnreadNotificationsCount(total);
    } catch (error) {
      console.warn("⚠️ Falha ao carregar notificações não lidas:", error);
      setUnreadNotificationsCount(0);
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

  useFocusEffect(
    useCallback(() => {
      loadUnreadNotificationsCount();
      return () => {};
    }, [loadUnreadNotificationsCount]),
  );

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
        if (!isSessionExpiredVisible()) {
          router.replace("/(auth)/login");
        }
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

  // Props do menu lateral (SideMenu do design system) a partir do perfil carregado.
  const renderSideMenu = (profile: UserProfile) => {
    const currentId = currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
    const tenantAtual =
      profile.tenants?.find((t) => Number(t.id) === Number(currentId)) ??
      (profile.tenants?.length === 1 ? profile.tenants[0] : undefined);
    const whatsappLinks = Array.isArray(tenantAtual?.whatsapp_links) ? tenantAtual.whatsapp_links : [];

    const plano = profile.plano;
    const statusCodigo = plano?.matricula_status?.codigo;
    const vencido = (plano?.vencimento?.dias_restantes ?? 0) < 0;
    const pendente = statusCodigo === "pendente";
    // Check-ins da semana na modalidade do plano (todas, se o plano não tiver modalidade).
    const modalidadePlano = plano?.modalidade?.nome?.trim().toLowerCase();
    const usadosSemana = weekDias.filter(
      (d) => !modalidadePlano || d.modalidade?.nome?.trim().toLowerCase() === modalidadePlano,
    ).length;
    const renovaEm = plano?.vencimento?.data
      ? new Date(`${String(plano.vencimento.data).slice(0, 10)}T00:00:00`).toLocaleDateString("pt-BR", {
          day: "2-digit",
          month: "2-digit",
        })
      : null;

    const posicaoRanking =
      profile.ranking_modalidades?.find((item) => item.modalidade_id === selectedModalidadeId)?.posicao ??
      profile.ranking_modalidades?.[0]?.posicao ??
      null;
    const stats = [
      profile.estatisticas ? { value: profile.estatisticas.checkins_mes ?? 0, label: "check-ins no mês" } : null,
      profile.estatisticas
        ? { value: profile.estatisticas.sequencia_dias ?? 0, label: "dias seguidos", icon: "flame" as const }
        : null,
      posicaoRanking ? { value: `${posicaoRanking}º`, label: "no ranking" } : null,
    ].filter((x): x is NonNullable<typeof x> => Boolean(x));

    const goTo = (path: string) => () => {
      closeSidebar();
      router.push(path as any);
    };

    const groups: SideMenuGroup[] = [
      {
        title: "Minha conta",
        items: [
          { icon: "user", label: "Dados pessoais", hint: profile.email, onPress: goTo("/(tabs)/conta") },
          {
            icon: "file-text",
            tone: "brand",
            label: "Minhas assinaturas",
            hint: pendente ? "Pagamento pendente" : vencido ? "Bloqueado" : plano?.nome || null,
            hintTone: pendente ? "warning" : vencido ? "danger" : null,
            count: pendente ? 1 : null,
            onPress: goTo("/minhas-assinaturas"),
          },
          {
            icon: "key",
            label: "Alterar senha",
            onPress: () => {
              closeSidebar();
              setShowRecoveryModal(true);
            },
          },
        ],
      },
      {
        title: "Grupos do WhatsApp",
        items: whatsappLinks.map((link) => ({
          icon: "message-circle" as const,
          mcIcon: "whatsapp" as const,
          tone: "success" as const,
          label: link.nome,
          hint: "Toque para entrar",
          onPress: () => {
            closeSidebar();
            void Linking.openURL(link.url);
          },
        })),
      },
    ];

    return (
      <SideMenu
        user={{
          name: profile.nome,
          avatarUri: userPhotoUri,
          roles: userRoleLabels,
          memberSince: profile.membro_desde
            ? new Date(profile.membro_desde).toLocaleDateString("pt-BR")
            : null,
        }}
        gym={
          currentId || tenants.length
            ? {
                name: getTenantDisplayName(),
                count: tenants.length,
                onSwitch: () => {
                  closeSidebar();
                  setShowTenantModal(true);
                },
              }
            : null
        }
        stats={stats}
        plan={
          plano?.nome
            ? {
                name: plano.nome,
                status: plano.matricula_status?.nome ?? null,
                tone: pendente ? "warning" : vencido || statusCodigo === "vencida" ? "danger" : "success",
                used: usadosSemana,
                total: Number(plano.checkins_semanais) || 0,
                renews: renovaEm,
                onPress: goTo("/minhas-assinaturas"),
              }
            : null
        }
        groups={groups}
        version={Constants.expoConfig?.version ?? null}
        updatingPhoto={updatingPhoto}
        onClose={closeSidebar}
        onEditPhoto={handleChangePhoto}
        onLogout={() => {
          closeSidebar();
          router.push("/logout");
        }}
      />
    );
  };

  const handleSelectTenant = async (tenantId: number) => {
    const currentId = currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
    if (switchingTenantId != null) return;
    if (currentId != null && Number(currentId) === Number(tenantId)) {
      setShowTenantModal(false);
      return;
    }
    setSwitchingTenantId(Number(tenantId));
    try {
      await AuthService.selectTenant(tenantId);
      setShowTenantModal(false);
      const ct = await AuthService.getCurrentTenant();
      setCurrentTenant(ct);
      // Perfil, semana e ranking são recarregados pelos efeitos que dependem de currentTenant
    } catch (e: any) {
      console.warn("⚠️ Falha ao selecionar tenant", e);
      Alert.alert("Erro", "Não foi possível trocar de academia.");
    } finally {
      setSwitchingTenantId(null);
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
    if (weekSemanaInicio) {
      // Usar data de início da semana da API
      const startDate = new Date(weekSemanaInicio + "T00:00:00");
      for (let i = 0; i < 7; i++) {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + i);
        const dateStr = date.toISOString().split("T")[0];
        days.push({
          date,
          dayName: weekdayAbbrev(date),
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
          dayName: weekdayAbbrev(date),
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

  // CPF mascarado no menu: só os dois últimos dígitos visíveis.
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
            <TouchableOpacity
              style={styles.headerPhotoWrapper}
              onPress={openSidebar}
              accessibilityRole="button"
              accessibilityLabel="Abrir menu do perfil"
            >
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
                        userProfile.plano.matricula_status.codigo === "ativa"
                          ? ds.successSoft
                          : userProfile.plano.matricula_status.codigo === "pendente"
                            ? ds.warningSoft
                            : ds.dangerSoft,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.matriculaStatusBadgeText,
                      {
                        color:
                          userProfile.plano.matricula_status.codigo === "ativa"
                            ? ds.success
                            : userProfile.plano.matricula_status.codigo === "pendente"
                              ? ds.warning
                              : ds.danger,
                      },
                    ]}
                  >
                    {userProfile.plano.matricula_status.nome}
                  </Text>
                </View>
              ) : null}
            </TouchableOpacity>
            <View style={styles.headerUserInfo}>
              {userRoleLabels.length > 0 || getTenantDisplayName() ? (
                <Text style={styles.headerUserRoles} numberOfLines={1}>
                  {[getTenantDisplayName(), userRoleLabels.join(" · ").toLowerCase()]
                    .filter(Boolean)
                    .join(" · ")}
                </Text>
              ) : null}
              <Text style={styles.headerUserName} numberOfLines={2}>
                {`Olá, ${(userProfile?.nome || "").trim().split(/\s+/)[0]?.replace(/^(.)(.*)$/, (_m, a, b) => a.toUpperCase() + b.toLowerCase()) || "aluno"}`}
              </Text>
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
            <Feather name="menu" size={22} color={ds.onBrand} />
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
            <Text style={styles.sectionTitle}>MINHA SEMANA</Text>
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
                      autoCorrect={false}
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
            RANKING{rankingPeriodo ? ` · ${rankingPeriodo.toUpperCase()}` : ""}
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
                        borderColor: active ? ds.inverse : ds.lineStrong,
                        backgroundColor: active ? ds.inverse : ds.surface,
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
                          color={active ? ds.onInverse : chipColor}
                        />
                      ) : null}
                      <Text
                        style={[
                          styles.modalidadeChipText,
                          active && styles.modalidadeChipTextActive,
                          { color: active ? ds.onInverse : ds.ink },
                        ]}
                      >
                        {modalidade.nome}
                      </Text>
                    </View>

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
                      ? ds.medalGold
                      : isSilver
                        ? ds.medalSilver
                        : ds.medalBronze;
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
                              color={ds.ink}
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
                    <Text style={styles.rankingUserLabel}>SUA POSIÇÃO</Text>
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

      </ScrollView>

      <Modal transparent visible={showSidebar} animationType="none" onRequestClose={closeSidebar}>
        <Pressable
          style={styles.sidebarOverlay}
          onPress={closeSidebar}
          accessibilityLabel="Fechar menu"
        />
        <Animated.View
          style={[
            styles.sidebarContainer,
            { transform: [{ translateX: sidebarTranslateX }] },
          ]}
        >
          {renderSideMenu(userProfile)}
        </Animated.View>
      </Modal>

      {/* Troca de academia (aberta pela pílula "Academia · Trocar" do menu lateral) */}
      <Modal
        transparent
        visible={showTenantModal}
        animationType="fade"
        onRequestClose={() => setShowTenantModal(false)}
      >
        <Pressable
          style={styles.tenantModalOverlay}
          onPress={() => setShowTenantModal(false)}
          accessibilityLabel="Fechar"
        />
        <View style={styles.tenantModalContainer} pointerEvents="box-none">
          <View style={styles.tenantModalCard} accessibilityViewIsModal>
            <Text style={styles.tenantModalTitle}>Trocar de academia</Text>
            <Text style={styles.tenantModalSubtitle}>
              Escolha a academia que você quer ver no app.
            </Text>
            <View style={styles.tenantList}>
              {tenants.map((t, i) => {
                const id = Number(t?.tenant?.id ?? t?.id);
                const nome = t?.tenant?.nome ?? t?.nome ?? "Academia";
                const currentId = currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
                const isCurrent = currentId != null && Number(currentId) === id;
                const isSwitching = switchingTenantId === id;
                return (
                  <TouchableOpacity
                    key={String(id)}
                    style={[styles.tenantOption, i > 0 && styles.tenantOptionDivided]}
                    onPress={() => handleSelectTenant(id)}
                    disabled={switchingTenantId != null}
                    activeOpacity={0.7}
                    accessibilityRole="button"
                    accessibilityState={{ selected: isCurrent, busy: isSwitching }}
                  >
                    <View style={[styles.tenantOptionIcon, isCurrent && styles.tenantOptionIconCurrent]}>
                      <Feather name="briefcase" size={18} color={isCurrent ? ds.brandInk : ds.ink} />
                    </View>
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text style={styles.tenantOptionText} numberOfLines={1}>
                        {nome}
                      </Text>
                      {isCurrent ? <Text style={styles.tenantOptionHint}>Academia atual</Text> : null}
                    </View>
                    {isSwitching ? (
                      <ActivityIndicator size="small" color={ds.ink} />
                    ) : isCurrent ? (
                      <Feather name="check" size={20} color={ds.success} />
                    ) : (
                      <Feather name="chevron-right" size={18} color={ds.inkMuted} />
                    )}
                  </TouchableOpacity>
                );
              })}
            </View>
            <TouchableOpacity
              style={styles.tenantCancel}
              onPress={() => setShowTenantModal(false)}
              accessibilityRole="button"
            >
              <Text style={styles.tenantCancelText}>Cancelar</Text>
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
    backgroundColor: ds.ground,
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
    paddingHorizontal: 16,
    paddingTop: 20,
    paddingBottom: 24,
    backgroundColor: ds.brand,
    borderBottomLeftRadius: 16,
    borderBottomRightRadius: 16,
  },
  headerUserRow: {
    flexDirection: "row",
    alignItems: "flex-end",
    justifyContent: "space-between",
    gap: 12,
  },
  headerUserLeft: {
    flexDirection: "row",
    alignItems: "flex-end",
    gap: 12,
    flex: 1,
  },
  headerUserInfo: {
    flex: 1,
    gap: 4,
    justifyContent: "center",
  },
  headerUserName: {
    fontSize: 32,
    lineHeight: 36,
    fontWeight: "800",
    letterSpacing: -0.6,
    color: ds.onBrand,
    flexShrink: 1,
  },
  headerUserRoles: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "700",
    color: ds.onBrand,
    opacity: 0.8,
  },
  headerMenuButton: {
    width: 44,
    height: 44,
    borderRadius: 12,
    justifyContent: "center",
    alignItems: "center",
  },
  headerPhotoWrapper: {
    position: "relative",
    width: 56,
    height: 56,
    borderRadius: 28,
    marginBottom: 10,
    overflow: "visible",
  },
  headerPhotoContainer: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: ds.surfaceSunken,
    justifyContent: "center",
    alignItems: "center",
    overflow: "hidden",
    borderWidth: 2,
    borderColor: ds.surface,
  },
  headerPhotoImage: {
    width: "100%",
    height: "100%",
    borderRadius: 999,
  },
  headerPhotoInitials: {
    fontSize: 20,
    fontWeight: "800",
    color: ds.ink,
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
    left: -8,
    right: -8,
    height: 20,
    paddingHorizontal: 6,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 2,
    borderColor: ds.surface,
  },
  matriculaStatusBadgeText: {
    fontSize: 10,
    fontWeight: "800",
    letterSpacing: 0.4,
    textTransform: "uppercase",
    textAlign: "center",
  },
  sidebarOverlay: {
    flex: 1,
    backgroundColor: ds.scrim,
  },
  sidebarContainer: {
    position: "absolute",
    top: 0,
    left: 0,
    bottom: 0,
    width: 340,
    maxWidth: "88%",
    backgroundColor: ds.ground,
    shadowColor: "#000",
    shadowOffset: { width: 16, height: 0 },
    shadowOpacity: 0.35,
    shadowRadius: 40,
    elevation: 16,
  },
  scrollContent: {
    padding: 16,
    paddingTop: 24,
    paddingBottom: 48,
    gap: 24,
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
    color: ds.onBrand,
    fontWeight: "600",
  },
  statisticsSection: {
    marginBottom: 20,
  },
  whatsappHomeSection: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 18,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: "#e8f5ee",
    ...withShadow("0px 8px 16px rgba(37, 211, 102, 0.08)", {
      shadowColor: "#25D366",
      shadowOffset: { width: 0, height: 8 },
      shadowOpacity: 0.12,
      shadowRadius: 14,
      elevation: 3,
    }),
  },
  whatsappHomeHeader: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    marginBottom: 16,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: "#eef7f1",
  },
  whatsappHomeHeaderIcon: {
    width: 44,
    height: 44,
    borderRadius: 14,
    backgroundColor: "#25D366",
    justifyContent: "center",
    alignItems: "center",
    flexShrink: 0,
  },
  whatsappHomeHeaderText: {
    flex: 1,
  },
  whatsappHomeTitle: {
    fontSize: 17,
    fontWeight: "700",
    color: colors.text,
    marginBottom: 2,
  },
  whatsappHomeSubtitle: {
    fontSize: 12,
    color: colors.textMuted,
    lineHeight: 16,
  },
  whatsappHomeLinks: {
    gap: 10,
  },
  whatsappHomeLink: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 14,
    paddingHorizontal: 14,
    borderRadius: 14,
    backgroundColor: "#f6fbf8",
    borderWidth: 1,
    borderColor: "#dcefe3",
  },
  whatsappHomeLinkIconWrap: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: "#25D366",
    justifyContent: "center",
    alignItems: "center",
    flexShrink: 0,
  },
  whatsappHomeLinkContent: {
    flex: 1,
    marginHorizontal: 12,
    minWidth: 0,
  },
  whatsappHomeLinkText: {
    fontSize: 15,
    fontWeight: "700",
    color: colors.text,
    lineHeight: 20,
  },
  whatsappHomeLinkHint: {
    marginTop: 2,
    fontSize: 12,
    color: "#128C7E",
    fontWeight: "500",
  },
  whatsappHomeLinkChevron: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: "rgba(18, 140, 126, 0.1)",
    justifyContent: "center",
    alignItems: "center",
    flexShrink: 0,
  },
  // Calendário Semanal
  weekCalendarSection: {
    backgroundColor: ds.surface,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: ds.line,
  },
  weekCalendarHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "baseline",
    marginBottom: 8,
  },
  weekPeriodText: {
    fontSize: 13,
    fontWeight: "600",
    color: ds.inkMuted,
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
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
    minWidth: 38,
  },
  weekDayItemToday: {
    backgroundColor: ds.inverse,
    borderColor: ds.inverse,
  },
  weekDayItemChecked: {
    backgroundColor: ds.successSoft,
    borderColor: ds.success,
  },
  weekDayItemMissed: {
    backgroundColor: ds.surfaceSunken,
    borderColor: ds.line,
  },
  weekDayName: {
    fontSize: 11,
    fontWeight: "800",
    letterSpacing: 0.6,
    textTransform: "uppercase",
    color: ds.inkMuted,
    marginBottom: 2,
  },
  weekDayNameToday: {
    color: ds.onInverse,
  },
  weekDayNameChecked: {
    color: ds.success,
  },
  weekDayNumber: {
    fontSize: 18,
    fontWeight: "800",
    color: ds.ink,
    marginBottom: 6,
    fontVariant: ["tabular-nums"],
  },
  weekDayNumberToday: {
    color: ds.onInverse,
  },
  weekDayNumberChecked: {
    color: ds.success,
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
    backgroundColor: ds.success,
    justifyContent: "center",
    alignItems: "center",
  },
  weekDayMissedIcon: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: ds.dangerSoft,
    justifyContent: "center",
    alignItems: "center",
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
    borderTopColor: ds.line,
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
    fontSize: 12,
    fontWeight: "600",
    color: ds.inkMuted,
  },
  sectionTitle: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: "800",
    letterSpacing: 0.96,
    color: ds.inkMuted,
    marginBottom: 8,
    paddingHorizontal: 4,
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
    fontWeight: "600",
    color: ds.inkMuted,
  },
  contratosSection: {
    marginBottom: 20,
  },
  academiasSection: {
    marginBottom: 20,
  },
  rankingSection: {

  },
  modalidadeTabs: {
    gap: 8,
    paddingVertical: 4,
    paddingHorizontal: 2,
    marginBottom: 12,
  },
  modalidadeTab: {
    height: 36,
    paddingHorizontal: 14,
    justifyContent: "center",
    borderRadius: 12,
    borderWidth: 1.5,
  },
  modalidadeTabActive: {

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
    fontSize: 14,
    fontWeight: "800",
  },
  modalidadeChipTextActive: {
    color: "#fff",
  },
  rankingCard: {
    backgroundColor: ds.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: ds.line,
    overflow: "hidden",
  },
  rankingList: {

  },
  rankingListItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    minHeight: 64,
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  rankingListItemGold: {

  },
  rankingListItemSilver: {

  },
  rankingListItemBronze: {

  },
  rankingPosition: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: "center",
    justifyContent: "center",
  },
  rankingPositionNumber: {
    fontSize: 15,
    fontWeight: "900",
    color: ds.ink,
    fontVariant: ["tabular-nums"],
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
    backgroundColor: ds.surfaceSunken,
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
    lineHeight: 20,
    fontWeight: "700",
    color: ds.ink,
    textTransform: "uppercase",
  },
  rankingCheckins: {
    fontSize: 13,
    fontWeight: "600",
    color: ds.inkMuted,
    marginTop: 2,
  },
  rankingLoading: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 16,
  },
  rankingLoadingText: {
    fontSize: 13,
    fontWeight: "600",
    color: ds.inkMuted,
  },
  rankingErrorText: {
    fontSize: 13,
    fontWeight: "600",
    color: ds.danger,
    padding: 16,
  },
  rankingDivider: {
    height: 0,
  },
  rankingUserRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: ds.brandSoft,
  },
  rankingUserLabel: {
    fontSize: 11,
    lineHeight: 14,
    fontWeight: "800",
    letterSpacing: 0.66,
    color: ds.brandInk,
    marginBottom: 2,
  },
  rankingUserName: {
    fontSize: 15,
    fontWeight: "700",
    color: ds.ink,
    textTransform: "uppercase",
  },
  rankingUserPosition: {
    minWidth: 44,
    height: 36,
    paddingHorizontal: 10,
    borderRadius: 8,
    backgroundColor: ds.brand,
    alignItems: "center",
    justifyContent: "center",
  },
  rankingUserPositionText: {
    fontSize: 15,
    fontWeight: "900",
    color: ds.onBrand,
    fontVariant: ["tabular-nums"],
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
  tenantModalOverlay: {
    flex: 1,
    backgroundColor: ds.scrim,
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
    maxWidth: 420,
    backgroundColor: ds.ground,
    borderRadius: 16,
    padding: 16,
    gap: 12,
  },
  tenantModalTitle: {
    fontSize: 22,
    lineHeight: 28,
    fontWeight: "800",
    color: ds.ink,
  },
  tenantModalSubtitle: {
    marginTop: -8,
    fontSize: 15,
    lineHeight: 22,
    fontWeight: "500",
    color: ds.inkMuted,
  },
  tenantList: {
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
    borderRadius: 16,
    overflow: "hidden",
  },
  tenantOption: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    minHeight: 60,
    paddingVertical: 10,
    paddingHorizontal: 12,
  },
  tenantOptionDivided: {
    borderTopWidth: 1,
    borderTopColor: ds.line,
  },
  tenantOptionIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: ds.surfaceSunken,
    alignItems: "center",
    justifyContent: "center",
  },
  tenantOptionIconCurrent: {
    backgroundColor: ds.brandSoft,
  },
  tenantOptionText: {
    color: ds.ink,
    fontWeight: "800",
    fontSize: 16,
    lineHeight: 20,
  },
  tenantOptionHint: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: "700",
    color: ds.brandInk,
  },
  tenantCancel: {
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: ds.lineStrong,
    backgroundColor: ds.surface,
    alignItems: "center",
    justifyContent: "center",
  },
  tenantCancelText: {
    fontSize: 16,
    fontWeight: "800",
    color: ds.ink,
  },
});
