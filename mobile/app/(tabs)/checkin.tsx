import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useFocusEffect } from "@react-navigation/native";
import { useLocalSearchParams, useRouter } from "expo-router";
import React, { useCallback, useEffect, useRef, useState } from "react";
import {
    ActivityIndicator,
    Animated,
    Image,
    Modal,
    Platform,
    Pressable,
    ScrollView,
    StyleSheet,
    Switch,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import AuthService from "../../src/services/authService";
import { colors } from "../../src/theme/colors";
import { handleAuthError } from "../../src/utils/authHelpers";
import { normalizeUtf8 } from "../../src/utils/utf8";

const getRouteParam = (value?: string | string[]) => {
  if (value == null) return undefined;
  return Array.isArray(value) ? value[0] : value;
};

const parseDataParam = (value?: string): Date | null => {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value).trim());
  if (match) {
    const parsed = new Date(
      Number(match[1]),
      Number(match[2]) - 1,
      Number(match[3]),
    );
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatDateParam = (date: Date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

export default function CheckinScreen() {
  const router = useRouter();
  const routeParams = useLocalSearchParams<{ data?: string | string[] }>();
  const routeData = getRouteParam(routeParams.data);
  const [selectedDate, setSelectedDate] = useState<Date>(() => {
    const parsed = parseDataParam(routeData);
    return parsed ?? new Date();
  });
  const [availableSchedules, setAvailableSchedules] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [calendarDays, setCalendarDays] = useState<Date[]>([]);
  const [participants, setParticipants] = useState<any[]>([]);
  const [participantsTurma, setParticipantsTurma] = useState<any | null>(null);
  const [checkinLoading, setCheckinLoading] = useState(false);
  const [checkinsRecentes, setCheckinsRecentes] = useState<any[]>([]);
  const [participantsLoading, setParticipantsLoading] = useState(false);
  const [alunosTotal, setAlunosTotal] = useState<number>(0);
  const [toastVisible, setToastVisible] = useState(false);
  const [toast, setToast] = useState<{
    message: string;
    type: "info" | "success" | "error" | "warning";
  }>({ message: "", type: "info" });
  const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [showOnlyAvailable, setShowOnlyAvailable] = useState(true);
  const [errorModal, setErrorModal] = useState<{
    visible: boolean;
    title: string;
    message: string;
    type: "error" | "warning" | "success";
  }>({
    visible: false,
    title: "",
    message: "",
    type: "error",
  });
  const modalScale = useRef(new Animated.Value(0)).current;
  const [currentUserId, setCurrentUserId] = useState<number | null>(null);
  const [currentAlunoId, setCurrentAlunoId] = useState<number | null>(null);
  const [currentUserEmail, setCurrentUserEmail] = useState<string | null>(null);
  const [currentUserPapelId, setCurrentUserPapelId] = useState<number | null>(
    null,
  );
  const [currentUserPapeis, setCurrentUserPapeis] = useState<any[]>([]);
  const [userCheckinId, setUserCheckinId] = useState<number | null>(null);
  const [presencas, setPresencas] = useState<Record<number, boolean | null>>(
    {},
  );
  const [confirmandoPresenca, setConfirmandoPresenca] = useState(false);
  const [turmaCheckinBloqueioLoading, setTurmaCheckinBloqueioLoading] =
    useState(false);
  const [manualSearchQuery, setManualSearchQuery] = useState("");
  const [manualSearchResults, setManualSearchResults] = useState<any[]>([]);
  const [manualSearchLoading, setManualSearchLoading] = useState(false);
  const [manualSearchError, setManualSearchError] = useState<string | null>(
    null,
  );
  const [manualCheckinLoading, setManualCheckinLoading] = useState<
    Record<number, boolean>
  >({});
  const [confirmManualModal, setConfirmManualModal] = useState<{
    visible: boolean;
    aluno: any | null;
  }>({ visible: false, aluno: null });
  const [currentTenant, setCurrentTenant] = useState<any | null>(null);
  const [tenantsList] = useState<any[]>([]);
  const [tenantModalVisible, setTenantModalVisible] = useState(false);
  const selectedDateRef = useRef<Date | null>(null);
  const isFetchingSchedulesRef = useRef(false);
  const latestSchedulesReqRef = useRef<number>(0);

  // papel_id: 1 = Aluno, 2 = Professor, 3 = Admin, 4 = Super Admin
  // Regras:
  // - Professor/Admin/Super Admin: controlam presenças e visualizam lista completa
  // - Aluno: pode fazer/desfazer check-in
  // - Em ambiente multi-tenant, se o usuário possui aluno_id no tenant atual
  //   ele deve ver o fluxo de aluno.
  const currentRoleIds = Array.isArray(currentUserPapeis)
    ? currentUserPapeis
        .map((papel) => Number(papel?.id ?? papel?.papel_id))
        .filter((id) => Number.isFinite(id))
    : [];
  const hasRole = (roleId: number) =>
    currentRoleIds.includes(roleId) || currentUserPapelId === roleId;
  const isProfessorOuAdmin = hasRole(2) || hasRole(3) || hasRole(4);
  const isAluno =
    hasRole(1) || currentAlunoId !== null || currentUserPapelId === null;

  // Debug: Mostrar valores de papel no console
  console.log(
    "🎭 PAPEL DEBUG - papel:",
    currentUserPapelId,
    "papeis:",
    currentRoleIds,
    "aluno_id:",
    currentAlunoId,
    "| isAluno:",
    isAluno,
    "| isProfessorOuAdmin:",
    isProfessorOuAdmin,
  );

  const participantsToShow = participants;
  // Removido: função não utilizada

  const getUserPhotoUrl = (fotoCaminho?: string | null) => {
    if (!fotoCaminho) return null;
    if (/^https?:\/\//i.test(fotoCaminho)) return fotoCaminho;
    return `${getApiUrlRuntime()}${fotoCaminho}`;
  };

  const mergeTurmaFromList = (
    turmaId: number | string,
    fallback: any = null,
  ) => {
    const fromList = availableSchedules.find(
      (t) => String(t.id) === String(turmaId),
    );
    if (fromList && fallback) return { ...fallback, ...fromList };
    if (fromList) return fromList;
    return fallback;
  };

  const cleanTurmaName = (nome?: string, modalidade?: any, professor?: any) => {
    let base = nome ? normalizeUtf8(nome) : "";
    // Remove padrões de horário dentro do nome (ex: " - 16:00" ou "16:00 - 17:00").
    base = base
      .replace(/\s*-?\s?\d{1,2}:\d{2}(\s*-\s*\d{1,2}:\d{2})?/g, "")
      .trim();

    // Remove nome da modalidade se estiver duplicado no nome
    const modalidadeNome = modalidade?.nome
      ? normalizeUtf8(modalidade.nome)
      : "";
    if (
      modalidadeNome &&
      base.toLowerCase().includes(modalidadeNome.toLowerCase())
    ) {
      base = base.replace(new RegExp(modalidadeNome, "gi"), "").trim();
    }

    // Remove nome do professor se estiver duplicado no nome
    const profNome = professor?.nome
      ? normalizeUtf8(professor.nome)
      : typeof professor === "string"
        ? normalizeUtf8(professor)
        : "";
    if (profNome && base.toLowerCase().includes(profNome.toLowerCase())) {
      base = base.replace(new RegExp(profNome, "gi"), "").trim();
    }

    // Remove traços e espaços extras que sobraram
    base = base
      .replace(/^\s*-\s*|\s*-\s*$/g, "")
      .replace(/\s+-\s+/g, " ")
      .trim();

    if (base.length > 0) return base;
    return modalidadeNome || "Turma";
  };

  const formatParticipantName = (name?: string | null) => {
    const base = normalizeUtf8(String(name || "")).trim();
    if (!base) return "Aluno";
    const parts = base.split(/\s+/);
    if (parts.length <= 2) return parts.join(" ");
    if (parts[1].length <= 2) return parts.slice(0, 3).join(" ");
    return parts.slice(0, 2).join(" ");
  };

  // Carregar tenant atual ao montar
  useEffect(() => {
    (async () => {
      try {
        const tenantJson = await AsyncStorage.getItem(
          "@appcheckin:current_tenant",
        );
        const tenant = tenantJson ? JSON.parse(tenantJson) : null;
        setCurrentTenant(tenant);
      } catch (e) {
        console.warn("Falha ao obter tenant atual", e);
      }
    })();
  }, []);

  // seletor de tenant removido do header; função de abertura do modal não é mais usada aqui

  const selectTenantAndReload = async (tenantId: number | string) => {
    try {
      await AuthService.selectTenant(tenantId);
      const tenant = await AuthService.getCurrentTenant();
      setCurrentTenant(tenant);
      setTenantModalVisible(false);
      // Recarregar dados da tela
      await generateCalendarDays();
    } catch (e) {
      console.error("Erro ao trocar de tenant:", e);
      showErrorModal("Não foi possível trocar de academia.", "error");
    }
  };

  const showToast = (
    message: string,
    type: "info" | "success" | "error" | "warning" = "info",
    duration = 3500,
  ) => {
    const msg = normalizeUtf8(String(message || ""));
    if (toastTimer.current) {
      clearTimeout(toastTimer.current);
      toastTimer.current = null;
    }
    setToast({ message: msg, type });
    setToastVisible(true);
    toastTimer.current = setTimeout(() => {
      setToastVisible(false);
      toastTimer.current = null;
    }, duration);
  };

  const showErrorModal = (
    message: string,
    type: "error" | "warning" | "success" = "error",
  ) => {
    const msg = normalizeUtf8(String(message || ""));
    const title =
      type === "error" ? "Erro!" : type === "warning" ? "Atenção!" : "Sucesso!";

    setErrorModal({
      visible: true,
      title,
      message: msg,
      type,
    });

    Animated.spring(modalScale, {
      toValue: 1,
      useNativeDriver: true,
      tension: 50,
      friction: 7,
    }).start();
  };

  const hideErrorModal = () => {
    Animated.timing(modalScale, {
      toValue: 0,
      duration: 200,
      useNativeDriver: true,
    }).start(() => {
      setErrorModal({ visible: false, title: "", message: "", type: "error" });
    });
  };

  useEffect(() => {
    console.log("\n🚀 CHECKIN SCREEN MONTADO");
    generateCalendarDays();
    loadCurrentUserId();
  }, []);

  // Mantém o selectedDate atual em ref para uso em efeitos sem dependência
  useEffect(() => {
    selectedDateRef.current = selectedDate;
  }, [selectedDate]);

  // Ao focar na aba Checkin, recarrega tenant e horários do dia selecionado sem alterar o estado da data
  useFocusEffect(
    useCallback(() => {
      console.log("🔁 Aba Checkin focada: atualizando horários do dia atual");
      (async () => {
        try {
          const tenantJson = await AsyncStorage.getItem(
            "@appcheckin:current_tenant",
          );
          const tenant = tenantJson ? JSON.parse(tenantJson) : null;
          setCurrentTenant(tenant);
        } catch (e) {
          console.warn("Falha ao atualizar tenant ao focar aba", e);
        }
        const dateToRefresh = selectedDateRef.current || new Date();
        const tenantIdOverride =
          currentTenant?.tenant?.id ?? currentTenant?.id ?? null;
        // Usa tenant lido do storage quando disponível para garantir cabeçalho correto na primeira chamada
        const tenantFromStorageJson = await AsyncStorage.getItem(
          "@appcheckin:current_tenant",
        );
        const tenantFromStorage = tenantFromStorageJson
          ? JSON.parse(tenantFromStorageJson)
          : null;
        const tenantIdFromStorage =
          tenantFromStorage?.tenant?.id ??
          tenantFromStorage?.id ??
          tenantIdOverride;
        await fetchAvailableSchedules(
          dateToRefresh,
          tenantIdFromStorage ?? undefined,
        );
      })();
      return () => {};
    }, []), // eslint-disable-line react-hooks/exhaustive-deps
  );

  const loadCurrentUserId = async () => {
    try {
      const userStr = await AsyncStorage.getItem("@appcheckin:user");
      console.log(
        "👤 Carregando usuário do AsyncStorage:",
        userStr?.substring(0, 100),
      );
      if (userStr) {
        const user = JSON.parse(userStr);
        console.log("👤 ID do usuário carregado:", user.id);
        console.log("👤 aluno_id do usuário:", user.aluno_id);
        console.log("👤 email do usuário:", user.email);
        console.log(
          "👤 papel_id do usuário:",
          user.papel_id,
          "tipo:",
          typeof user.papel_id,
        );
        console.log("👤 papeis do usuário:", user.papeis);
        setCurrentUserId(user.id);
        setCurrentAlunoId(user.aluno_id || null);
        setCurrentUserEmail(user.email || null);
        setCurrentUserPapeis(Array.isArray(user.papeis) ? user.papeis : []);
        // Garantir que papel_id seja número
        const papelIdNumero = user.papel_id ? Number(user.papel_id) : null;
        console.log("👤 papel_id convertido:", papelIdNumero);
        setCurrentUserPapelId(papelIdNumero);
      } else {
        console.log("❌ Nenhum usuário encontrado no AsyncStorage");
      }
    } catch (error) {
      console.error("Erro ao carregar ID do usuário:", error);
    }
  };

  useEffect(() => {
    return () => {
      if (toastTimer.current) {
        clearTimeout(toastTimer.current);
      }
    };
  }, []);

  const clearParticipantsState = useCallback(() => {
    setParticipantsTurma(null);
    setParticipants([]);
    setCheckinsRecentes([]);
    setAlunosTotal(0);
    setManualSearchQuery("");
    setManualSearchResults([]);
    setManualSearchError(null);
    setPresencas({});
    setUserCheckinId(null);
  }, []);


  const openTurmaCheckin = useCallback(
    (turma: any) => {
      if (!turma?.id) return;
      const data = formatDateParam(selectedDateRef.current || selectedDate);
      router.push(
        `/checkin-turma?turmaId=${encodeURIComponent(String(turma.id))}&data=${encodeURIComponent(data)}`,
      );
    },
    [router, selectedDate],
  );

  // Sincroniza data vinda da URL (ex.: voltar do detalhe da turma), sem sobrescrever clique no calendário
  useEffect(() => {
    if (!routeData) return;
    const parsed = parseDataParam(routeData);
    if (!parsed) return;
    const routeKey = formatDateParam(parsed);
    const currentKey = formatDateParam(selectedDate);
    if (routeKey === currentKey) return;
    selectedDateRef.current = parsed;
    setSelectedDate(parsed);
    void fetchAvailableSchedules(parsed);
  }, [routeData]); // eslint-disable-line react-hooks/exhaustive-deps

  const selectCalendarDay = useCallback((date: Date) => {
    const parsed = parseDataParam(formatDateParam(date)) ?? new Date(date);
    selectedDateRef.current = parsed;
    setSelectedDate(parsed);
    void fetchAvailableSchedules(parsed);
    const dataKey = formatDateParam(parsed);
    router.replace(
      `/(tabs)/checkin?data=${encodeURIComponent(dataKey)}` as any,
    );
  }, [router]); // eslint-disable-line react-hooks/exhaustive-deps

  const generateCalendarDays = () => {
    console.log("📅 GERANDO CALENDÁRIO");
    const today = new Date();
    console.log("   Data hoje:", today);
    const days: Date[] = [];

    // Começa do dia anterior (-1) até 6 dias à frente (total 8 dias)
    for (let i = -1; i < 7; i++) {
      const date = new Date(today);
      date.setDate(today.getDate() + i);
      days.push(date);
    }
    console.log("   Dias gerados:", days.length);
    setCalendarDays(days);
  };

  const fetchAvailableSchedules = async (
    date: Date,
    tenantIdOverride?: number,
  ) => {
    const formattedDate = formatDateParam(date);
    const reqId = Date.now();
    latestSchedulesReqRef.current = reqId;

    if (isFetchingSchedulesRef.current) {
      console.log("⏳ Já existe carregamento de horários em andamento");
    }

    isFetchingSchedulesRef.current = true;
    setLoading(true);
    try {
      console.log("\n🔄 INICIANDO CARREGAMENTO DE HORÁRIOS");

      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        console.error("❌ Token não encontrado");
        showToast("Token não encontrado", "error");
        return;
      }
      console.log("✅ Token encontrado:", token.substring(0, 20) + "...");

      console.log("📅 Data formatada:", formattedDate);

      const url = `${getApiUrlRuntime()}/mobile/horarios-disponiveis?data=${formattedDate}`;
      console.log("📍 URL:", url);

      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      console.log("📡 RESPOSTA DO SERVIDOR");
      console.log("   Status:", response.status);
      console.log("   Status Text:", response.statusText);
      console.log("   Content-Type:", response.headers.get("content-type"));

      const responseText = await response.text();
      console.log(
        "   Response Text (primeiros 500 chars):",
        responseText.substring(0, 500),
      );

      if (!response.ok) {
        // Tratar erro 401
        if (response.status === 401) {
          console.log("🔑 Token inválido/expirado no checkin");
          await handleAuthError();
          router.replace("/(auth)/login");
          return;
        }

        console.error("❌ ERRO NA REQUISIÇÃO");
        console.error("   Status:", response.status);
        console.error("   Body completo:", responseText);
        throw new Error(`HTTP ${response.status}: ${responseText}`);
      }

      let data;
      try {
        data = JSON.parse(responseText);
        console.log("✅ JSON parseado com sucesso");
      } catch (parseError) {
        console.error("❌ ERRO AO FAZER PARSE DO JSON");
        if (parseError instanceof Error) {
          console.error("   Erro:", parseError.message);
        }
        console.error("   Response recebida:", responseText.substring(0, 200));
        throw parseError;
      }

      if (latestSchedulesReqRef.current !== reqId) {
        console.warn("⏭️ Ignorando resposta de horários (requisição obsoleta)");
        return;
      }
      console.log("   Response completa:", JSON.stringify(data, null, 2));

      if (data.success && data.data?.turmas) {
        console.log("✅ Turmas carregadas com sucesso");
        console.log("   Quantidade:", data.data.turmas.length);
        console.log("   Total de turmas:", data.data.total);
        data.data.turmas.forEach((turma: any, index: number) => {
          console.log(`   [${index + 1}] ${turma.nome}`);
          console.log(`       Modalidade: ${turma.modalidade?.nome}`);
          console.log(
            `       Horário: ${turma.horario.inicio} - ${turma.horario.fim}`,
          );
          console.log(
            `       Vagas: ${turma.alunos_inscritos}/${turma.limite_alunos}`,
          );
        });
        setAvailableSchedules(data.data.turmas);
      } else {
        console.warn("⚠️ Resposta inválida ou sem turmas");
        console.log("   success:", data.success);
        console.log("   turmas:", data.data?.turmas);
        setAvailableSchedules([]);
      }
    } catch (error) {
      console.error("❌ ERRO AO CARREGAR HORÁRIOS");
      if (error instanceof Error) {
        console.error("   Nome do erro:", error.name);
        console.error("   Mensagem:", error.message);
        console.error("   Stack:", error.stack);
      } else {
        console.error("   Erro:", error);
      }
      showToast("Falha ao carregar horários disponíveis", "error");
    } finally {
      setLoading(false);
      isFetchingSchedulesRef.current = false;
    }
  };

  const reloadSchedulesList = useCallback(async () => {
    const dateToLoad = selectedDateRef.current ?? selectedDate;
    const tenantId =
      currentTenant?.tenant?.id ?? currentTenant?.id ?? undefined;
    await fetchAvailableSchedules(dateToLoad, tenantId);
  }, [selectedDate, currentTenant]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleCheckin = async (turma: any) => {
    if (!turma?.id) return;
    if (isTurmaDisabled(turma, selectedDate)) {
      showToast("Este horário já foi encerrado.", "warning");
      return;
    }

    setCheckinLoading(true);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showToast("Token não encontrado", "error");
        return;
      }

      const payload: any = {
        turma_id: turma.id,
        data: formatDateParam(selectedDate),
      };

      if (turma.horario?.id) {
        payload.horario_id = turma.horario.id;
      }

      const response = await fetch(`${getApiUrlRuntime()}/mobile/checkin`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });

      const text = await response.text();
      let data: any = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch {
        data = {};
      }

      if (!response.ok) {
        const apiMessage =
          data?.message ||
          data?.error ||
          text ||
          "Não foi possível realizar o check-in.";
        console.warn(
          "Erro ao registrar check-in:",
          response.status,
          apiMessage || "",
        );

        if (
          String(apiMessage).toLowerCase().includes("já realizou check-in") ||
          String(apiMessage).toLowerCase().includes("já fez check-in")
        ) {
          showErrorModal(normalizeUtf8(String(apiMessage)), "warning");
        } else {
          showErrorModal(normalizeUtf8(String(apiMessage)), "error");
        }
        return;
      }

      if (data.success) {
        showErrorModal(
          `Check-in realizado para ${normalizeUtf8(turma.nome)}`,
          "success",
        );
        await fetchAvailableSchedules(selectedDate);
      } else {
        showErrorModal(
          normalizeUtf8(
            data?.message ||
              data?.error ||
              "Não foi possível realizar o check-in.",
          ),
          "error",
        );
      }
    } catch (error) {
      console.error("Erro check-in:", error);
      showErrorModal("Falha ao realizar o check-in.", "error");
    } finally {
      setCheckinLoading(false);
    }
  };

  const handleUndoCheckin = async (checkinId: number, turma: any) => {
    if (!checkinId) {
      showErrorModal("ID de check-in não encontrado.", "error");
      return;
    }

    console.log("🔙 DESFAZENDO CHECK-IN");
    console.log("   checkinId:", checkinId);
    console.log("   turma.id:", turma?.id);

    setCheckinLoading(true);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showErrorModal("Token não encontrado. Faça login novamente.", "error");
        return;
      }

      const url = `${getApiUrlRuntime()}/mobile/checkin/${checkinId}/desfazer`;
      console.log("📍 URL DELETE:", url);

      const response = await fetch(url, {
        method: "DELETE",
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      const text = await response.text();
      let data: any = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch {
        data = {};
      }

      if (!response.ok) {
        const apiMessage =
          data?.error ||
          data?.message ||
          text ||
          "Não foi possível desfazer o check-in.";
        console.warn("Erro ao desfazer check-in:", response.status, apiMessage);
        showErrorModal(normalizeUtf8(String(apiMessage)), "error");
        return;
      }

      showErrorModal(`Check-in desfeito com sucesso`, "warning");
      setUserCheckinId(null);
      await fetchAvailableSchedules(selectedDate);
    } catch (error) {
      console.error("Erro ao desfazer check-in:", error);
      showErrorModal("Falha ao desfazer o check-in.", "error");
    } finally {
      setCheckinLoading(false);
    }
  };

  // Função para alternar presença de um aluno (apenas visual, local)
  const togglePresenca = (checkinId: number) => {
    setPresencas((prev) => {
      const atual = prev[checkinId];
      // Ciclo: null -> true -> false -> null
      let novo: boolean | null;
      if (atual === null || atual === undefined) {
        novo = true; // Presente
      } else if (atual === true) {
        novo = false; // Falta
      } else {
        novo = null; // Pendente
      }
      return { ...prev, [checkinId]: novo };
    });
  };

  // Função para confirmar todas as presenças marcadas
  const confirmarPresencas = async () => {
    if (!participantsTurma?.id) return;

    // Filtrar apenas os checkins que têm presença marcada (true ou false)
    const presencasParaEnviar = Object.entries(presencas)
      .filter(([_, valor]) => valor !== null)
      .map(([checkinId, presente]) => ({
        checkin_id: Number(checkinId),
        presente: presente as boolean,
      }));

    // Regra: professor/admin deve marcar TODOS os alunos (sem valores nulos)
    const totalCheckins = checkinsRecentes.length;
    const totalMarcados = presencasParaEnviar.length;
    if (totalCheckins === 0) {
      showToast("Nenhum check-in para confirmar", "warning");
      return;
    }
    if (totalMarcados < totalCheckins) {
      const faltantes = totalCheckins - totalMarcados;
      showErrorModal(
        `Você deve marcar presença ou falta para todos os alunos antes de confirmar. Faltam ${faltantes}.`,
        "warning",
      );
      return;
    }

    setConfirmandoPresenca(true);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showErrorModal("Token não encontrado. Faça login novamente.", "error");
        return;
      }

      const url = `${getApiUrlRuntime()}/mobile/turma/${participantsTurma.id}/confirmar-presenca`;
      console.log("📝 Confirmando presenças:", presencasParaEnviar);

      const response = await fetch(url, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ presencas: presencasParaEnviar }),
      });

      const text = await response.text();
      let data: any = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch {
        data = {};
      }

      if (!response.ok) {
        const apiMessage =
          data?.error ||
          data?.message ||
          text ||
          "Não foi possível confirmar as presenças.";
        console.warn(
          "Erro ao confirmar presenças:",
          response.status,
          apiMessage,
        );
        showErrorModal(normalizeUtf8(String(apiMessage)), "error");
        return;
      }

      const msg = data?.message || "Presenças confirmadas com sucesso!";
      showErrorModal(normalizeUtf8(msg), "success");

      // Recarregar os dados da turma
      await loadParticipantsForTurma(
        mergeTurmaFromList(participantsTurma.id, participantsTurma),
      );
    } catch (error) {
      console.error("Erro ao confirmar presenças:", error);
      showErrorModal("Falha ao confirmar as presenças.", "error");
    } finally {
      setConfirmandoPresenca(false);
    }
  };

  const checkUserHasCheckin = useCallback(() => {
    console.log("🔍 Verificando check-in do usuário");
    console.log("   currentUserId:", currentUserId);
    console.log("   currentAlunoId:", currentAlunoId);
    console.log("   currentUserEmail:", currentUserEmail);
    console.log("   participants.length:", participants.length);
    console.log("   checkinsRecentes.length:", checkinsRecentes.length);

    if (!currentUserId && !currentAlunoId && !currentUserEmail) {
      console.log("   ❌ Sem currentUserId, currentAlunoId e currentUserEmail");
      setUserCheckinId(null);
      return false;
    }

    // PRIORIDADE 1: Verificar nos participantes pelo email (mais confiável)
    // A API retorna email na lista de alunos
    if (participants.length > 0 && currentUserEmail) {
      console.log(
        "   Participantes:",
        JSON.stringify(
          participants.map((p) => ({
            aluno_id: p.aluno_id,
            email: p.email,
            checkins: p.checkins,
            nome: p.nome,
          })),
        ),
      );

      const userByEmail = participants.find(
        (p) =>
          p.email &&
          currentUserEmail &&
          p.email.toLowerCase() === currentUserEmail.toLowerCase(),
      );

      if (userByEmail && userByEmail.checkins > 0) {
        console.log("   ✅ Usuário encontrado pelo EMAIL:", userByEmail);

        // Buscar o checkin_id nos checkinsRecentes usando o aluno_id encontrado
        if (checkinsRecentes.length > 0) {
          const checkin = checkinsRecentes.find(
            (c) => Number(c.aluno_id) === Number(userByEmail.aluno_id),
          );
          if (checkin) {
            const checkinId = checkin.checkin_id || checkin.id;
            console.log("   📝 checkin_id encontrado:", checkinId);
            setUserCheckinId(checkinId);
            return true;
          }
        }
      }
    }

    // PRIORIDADE 2: Verificar em checkinsRecentes pelo aluno_id
    if (checkinsRecentes.length > 0 && currentAlunoId) {
      console.log(
        "   Check-ins recentes:",
        JSON.stringify(
          checkinsRecentes.map((c) => ({
            checkin_id: c.checkin_id,
            aluno_id: c.aluno_id,
            usuario_nome: c.usuario_nome,
          })),
        ),
      );

      const userCheckin = checkinsRecentes.find(
        (c) => Number(c.aluno_id) === Number(currentAlunoId),
      );

      if (userCheckin) {
        console.log(
          "   ✅ Usuário encontrado em check-ins recentes:",
          userCheckin,
        );
        const checkinId = userCheckin.checkin_id || userCheckin.id;
        console.log("   📝 checkin_id:", checkinId);
        setUserCheckinId(checkinId);
        return true;
      }
    }

    // NOVO: Verificar em checkinsRecentes pelo EMAIL do usuário (c.usuario_email)
    if (checkinsRecentes.length > 0 && currentUserEmail) {
      const userByEmailCheckin = checkinsRecentes.find(
        (c) =>
          c.usuario_email &&
          currentUserEmail &&
          String(c.usuario_email).toLowerCase() ===
            String(currentUserEmail).toLowerCase(),
      );
      if (userByEmailCheckin) {
        const checkinId =
          userByEmailCheckin.checkin_id || userByEmailCheckin.id;
        console.log("   ✅ Usuário encontrado em check-ins pelo EMAIL:", {
          checkinId,
          usuario_email: userByEmailCheckin.usuario_email,
        });
        setUserCheckinId(checkinId);
        return true;
      }
    }

    // NOVO: Verificar em checkinsRecentes pelo usuario_id
    if (checkinsRecentes.length > 0 && currentUserId) {
      const userByIdCheckin = checkinsRecentes.find(
        (c) => Number(c.usuario_id) === Number(currentUserId),
      );
      if (userByIdCheckin) {
        const checkinId = userByIdCheckin.checkin_id || userByIdCheckin.id;
        console.log("   ✅ Usuário encontrado em check-ins pelo usuario_id:", {
          checkinId,
          usuario_id: userByIdCheckin.usuario_id,
        });
        setUserCheckinId(checkinId);
        return true;
      }
    }

    // PRIORIDADE 3: Verificar nos participantes pelo aluno_id
    if (participants.length > 0 && currentAlunoId) {
      const userParticipant = participants.find(
        (p) => Number(p.aluno_id) === Number(currentAlunoId),
      );

      if (userParticipant && userParticipant.checkins > 0) {
        console.log(
          "   ✅ Usuário encontrado nos participantes com checkins:",
          userParticipant,
        );

        if (checkinsRecentes.length > 0) {
          const checkin = checkinsRecentes.find(
            (c) => Number(c.aluno_id) === Number(userParticipant.aluno_id),
          );
          if (checkin) {
            const checkinId = checkin.checkin_id || checkin.id;
            console.log("   📝 checkin_id encontrado:", checkinId);
            setUserCheckinId(checkinId);
            return true;
          }
        }
      }
    }

    console.log("   ❌ Usuário não encontrado - sem check-in");
    setUserCheckinId(null);
    return false;
  }, [
    participants,
    checkinsRecentes,
    currentUserId,
    currentAlunoId,
    currentUserEmail,
  ]);

  const loadParticipantsForTurma = async (turma: any) => {
    if (!turma?.id) return;
    setParticipantsLoading(true);
    setParticipants([]);
    setCheckinsRecentes([]);
    setAlunosTotal(0);
    setManualSearchQuery("");
    setManualSearchResults([]);
    setManualSearchError(null);
    setParticipantsTurma(mergeTurmaFromList(turma.id, turma));

    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showToast("Token não encontrado", "error");
        return;
      }

      const url = `${getApiUrlRuntime()}/mobile/turma/${turma.id}/detalhes`;
      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      const text = await response.text();
      console.log("🔎 detalhes turma raw:", text.substring(0, 400));
      if (!response.ok) {
        console.error("Erro ao carregar participantes:", response.status, text);
        showToast("Falha ao carregar participantes", "error");
        return;
      }

      const data = JSON.parse(text);

      const payload = data.data || data; // suportar formato com ou sem wrapper
      const turmaApi = payload.turma || turma;
      const alunosLista = payload.alunos?.lista || payload.participantes || [];
      const checkinsLista = payload.checkins_recentes?.lista || [];
      const resumoData = payload.resumo || null;
      const alunosCount =
        payload.alunos?.total ??
        alunosLista.length ??
        turmaApi.alunos_inscritos ??
        0;

      console.log("🔎 detalhes turma parse:", {
        turmaId: turmaApi.id,
        alunosCount,
        alunosListaLen: alunosLista.length,
        checkinsRecentes: checkinsLista.length,
        resumoKeys: resumoData ? Object.keys(resumoData) : [],
      });

      setParticipants(alunosLista);
      setCheckinsRecentes(checkinsLista);
      // resumoTurma removido; mantemos apenas o log e demais estados
      setAlunosTotal(alunosCount);
      setParticipantsTurma(mergeTurmaFromList(turmaApi.id, turmaApi));

      // Inicializar estado de presenças com valores da API
      const presencasIniciais: Record<number, boolean | null> = {};
      checkinsLista.forEach((c: any) => {
        if (c.checkin_id) {
          presencasIniciais[c.checkin_id] = c.presente ?? null;
        }
      });
      setPresencas(presencasIniciais);
    } catch (error) {
      console.error("Erro participantes:", error);
      showToast("Não foi possível carregar participantes", "error");
    } finally {
      setParticipantsLoading(false);
    }
  };

  const openParticipants = (turma: any) => {
    openTurmaCheckin(turma);
  };

  const parseAlunosSearchResponse = (payload: any): any[] => {
    if (!payload) return [];
    const data = payload.data ?? payload;
    if (Array.isArray(data?.alunos)) return data.alunos;
    if (Array.isArray(data?.lista)) return data.lista;
    if (Array.isArray(data?.resultados)) return data.resultados;
    if (Array.isArray(data)) return data;
    return [];
  };

  const buildAlunoSearchUrl = (query: string) => {
    const trimmed = normalizeUtf8(String(query || "")).trim();
    if (!trimmed) return null;
    if (trimmed.includes("@")) {
      return `${getApiUrlRuntime()}/mobile/alunos/buscar?email=${encodeURIComponent(
        trimmed,
      )}`;
    }
    const digits = trimmed.replace(/\D/g, "");
    if (digits.length === 11) {
      return `${getApiUrlRuntime()}/mobile/alunos/buscar?cpf=${encodeURIComponent(
        digits,
      )}`;
    }
    return `${getApiUrlRuntime()}/mobile/alunos/buscar?q=${encodeURIComponent(
      trimmed,
    )}`;
  };

  const handleManualSearch = async () => {
    if (!participantsTurma?.id) return;
    const url = buildAlunoSearchUrl(manualSearchQuery);
    if (!url) {
      setManualSearchError("Informe nome, CPF ou e-mail.");
      setManualSearchResults([]);
      return;
    }

    setManualSearchLoading(true);
    setManualSearchError(null);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        setManualSearchError("Token não encontrado.");
        return;
      }

      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      const text = await response.text();
      let data: any = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch {
        data = {};
      }

      if (!response.ok) {
        const apiMessage =
          data?.error ||
          data?.message ||
          text ||
          "Não foi possível buscar o aluno.";
        setManualSearchError(normalizeUtf8(String(apiMessage)));
        setManualSearchResults([]);
        return;
      }

      const alunos = parseAlunosSearchResponse(data);
      setManualSearchResults(alunos);
      if (!alunos.length) {
        setManualSearchError("Nenhum aluno encontrado.");
      }
    } catch (error) {
      console.error("Erro ao buscar aluno:", error);
      setManualSearchError("Falha ao buscar aluno.");
    } finally {
      setManualSearchLoading(false);
    }
  };

  const handleManualCheckin = async (aluno: any) => {
    if (!participantsTurma?.id || !aluno?.id) return;
    if (manualCheckinLoading[aluno.id]) return;

    setManualCheckinLoading((prev) => ({ ...prev, [aluno.id]: true }));
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showErrorModal("Token não encontrado. Faça login novamente.", "error");
        return;
      }

      const response = await fetch(
        `${getApiUrlRuntime()}/mobile/checkin/manual`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            turma_id: participantsTurma.id,
            aluno_id: aluno.id,
          }),
        },
      );

      const text = await response.text();
      let data: any = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch {
        data = {};
      }

      if (!response.ok) {
        const apiMessage =
          data?.error ||
          data?.message ||
          text ||
          "Não foi possível adicionar o aluno.";
        showErrorModal(normalizeUtf8(String(apiMessage)), "error");
        return;
      }

      showErrorModal(
        normalizeUtf8(data?.message || "Check-in manual realizado."),
        "success",
      );
      await loadParticipantsForTurma(
        mergeTurmaFromList(participantsTurma.id, participantsTurma),
      );
    } catch (error) {
      console.error("Erro ao fazer check-in manual:", error);
      showErrorModal("Falha ao realizar o check-in manual.", "error");
    } finally {
      setManualCheckinLoading((prev) => ({ ...prev, [aluno.id]: false }));
    }
  };

  const openManualConfirm = (aluno: any) => {
    setConfirmManualModal({ visible: true, aluno });
    Animated.spring(modalScale, {
      toValue: 1,
      useNativeDriver: true,
      tension: 50,
      friction: 7,
    }).start();
  };

  const closeManualConfirm = () => {
    Animated.timing(modalScale, {
      toValue: 0,
      duration: 200,
      useNativeDriver: true,
    }).start(() => {
      setConfirmManualModal({ visible: false, aluno: null });
    });
  };

  // Efeito para verificar se o usuário tem check-in, após definir o callback
  useEffect(() => {
    if (
      participantsTurma &&
      (currentUserId || currentAlunoId || currentUserEmail)
    ) {
      checkUserHasCheckin();
    }
  }, [
    participantsTurma,
    checkUserHasCheckin,
    currentUserId,
    currentAlunoId,
    currentUserEmail,
  ]);

  // Helpers para cálculo de disponibilidade por horário
  // Removido: helper sameDay não utilizado

  const combineDateTime = (date: Date, timeHHMMSS: string) => {
    const [hh, mm, ss] = (timeHHMMSS || "00:00:00")
      .split(":")
      .map((n) => parseInt(n, 10) || 0);
    const d = new Date(date);
    d.setHours(hh, mm, ss || 0, 0);
    return d;
  };

  const isTurmaDisabled = (
    turma: any,
    refDate: Date = selectedDate,
  ): boolean => {
    try {
      const now = new Date();
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const ref = new Date(refDate);
      ref.setHours(0, 0, 0, 0);

      // Se a data selecionada já passou, tudo desabilitado
      if (ref < today) return true;
      // Se é uma data futura, tudo habilitado
      if (ref > today) return false;

      // Mesmo dia: comparar horário atual com horário de fim da turma
      if (!turma?.horario?.fim) return false;
      const end = combineDateTime(refDate, turma?.horario?.fim ?? "00:00:00");
      return now > end;
    } catch (e) {
      console.warn("Falha ao calcular disponibilidade da turma:", e);
      return false;
    }
  };

  // Exibe verdadeiro somente durante o período da aula no dia selecionado (início <= agora <= fim)
  const isDurantePeriodo = (
    turma: any,
    refDate: Date = selectedDate,
  ): boolean => {
    try {
      const today = new Date();
      const ref = new Date(refDate);
      ref.setHours(0, 0, 0, 0);
      const dayStart = new Date(today);
      dayStart.setHours(0, 0, 0, 0);

      // Só durante o dia atual
      if (ref.getTime() !== dayStart.getTime()) return false;

      const inicio = getHoraInicio(turma);
      const fim = getHoraFim(turma);
      if (!inicio || !fim) return false;

      const start = combineDateTime(refDate, inicio ?? "00:00:00");
      const end = combineDateTime(refDate, fim ?? "23:59:59");
      return today >= start && today <= end;
    } catch {
      return false;
    }
  };

  const isCheckinDisabled = (turma: any): boolean => {
    if (!turma) return true;
    if (turma.checkin_bloqueado) return true;
    if (isTurmaDisabled(turma, selectedDate)) return true;
    const hasVagasByField =
      typeof turma.vagas_disponiveis === "number"
        ? turma.vagas_disponiveis > 0
        : true;

    const hasVagasByCount =
      typeof turma.limite_alunos === "number" &&
      typeof turma.alunos_inscritos === "number"
        ? turma.alunos_inscritos < turma.limite_alunos
        : true;

    if (!hasVagasByField && !hasVagasByCount) return true;
    return false;
  };

  const formatDateDisplay = (date: Date) => {
    console.log("📅 Formatando data:", date);
    const day = date.getDate();
    const dayNames = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];
    const dayName = dayNames[date.getDay()] || "";
    return { day, dayName: dayName.toUpperCase() };
  };

  const getHoraInicio = (turma: any) =>
    turma?.hora_inicio || turma?.horario?.inicio;
  const getHoraFim = (turma: any) => turma?.hora_fim || turma?.horario?.fim;

  // Hora limite para check-in (inclui tolerância): extrai HH:mm de turma.checkin.fechamento
  const getHoraLimiteCheckin = (turma: any): string | null => {
    try {
      const fechamento: string | undefined = turma?.checkin?.fechamento;
      if (!fechamento) return null;
      // formatos possíveis: "YYYY-MM-DD HH:mm:ss" ou "HH:mm:ss"
      const timePart = fechamento.includes(" ")
        ? fechamento.split(" ")[1]
        : fechamento;
      return timePart?.slice(0, 5) || null;
    } catch {
      return null;
    }
  };

  // Data/hora de abertura do check-in, se fornecida pela API
  const getDataAberturaCheckin = (turma: any): Date | null => {
    try {
      const abertura: string | undefined = turma?.checkin?.abertura;
      if (!abertura) return null;
      // formatos possíveis: "YYYY-MM-DD HH:mm:ss" ou ISO
      if (abertura.includes(" ")) {
        const [datePart, timePart] = abertura.split(" ");
        const [yyyy, mm, dd] = datePart.split("-").map((n) => parseInt(n, 10));
        const [hh, mi, ss] = timePart.split(":").map((n) => parseInt(n, 10));
        const d = new Date(
          yyyy,
          (mm || 1) - 1,
          dd || 1,
          hh || 0,
          mi || 0,
          ss || 0,
          0,
        );
        return d;
      }
      const d = new Date(abertura);
      return isNaN(d.getTime()) ? null : d;
    } catch {
      return null;
    }
  };

  // Minutos restantes para abrir o check-in (<=0 se já abriu)
  const getMinutosParaAbrirCheckin = (turma: any): number => {
    try {
      const dtAbertura = getDataAberturaCheckin(turma);
      if (!dtAbertura) return 0;
      const now = new Date();
      const diffMs = dtAbertura.getTime() - now.getTime();
      return Math.ceil(diffMs / 60000);
    } catch {
      return 0;
    }
  };

  const formatMinutos = (min: number): string => {
    if (min <= 0) return "agora";
    const h = Math.floor(min / 60);
    const m = min % 60;
    if (h > 0 && m > 0) return `${h}h ${m}min`;
    if (h > 0) return `${h}h`;
    return `${m}min`;
  };

  const schedulesToRender = (() => {
    let list = availableSchedules;
    if (!isProfessorOuAdmin) {
      list = list.filter((turma) => !turma?.checkin_bloqueado);
    }
    if (showOnlyAvailable && !isProfessorOuAdmin) {
      list = list.filter((turma) => !isCheckinDisabled(turma));
    }
    return list;
  })();

  const handleToggleCheckinBloqueioTurma = async () => {
    if (!participantsTurma?.id || !isProfessorOuAdmin) return;

    const bloquear = !participantsTurma.checkin_bloqueado;
    setTurmaCheckinBloqueioLoading(true);

    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showToast("Token não encontrado", "error");
        return;
      }

      const endpoint = bloquear ? "bloquear-checkin" : "desbloquear-checkin";
      const response = await fetch(
        `${getApiUrlRuntime()}/mobile/turma/${participantsTurma.id}/${endpoint}`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
          body: bloquear ? JSON.stringify({}) : undefined,
        },
      );

      const text = await response.text();
      let data: any = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch {
        data = {};
      }

      if (!response.ok) {
        showToast(
          data?.error || data?.message || "Não foi possível alterar o bloqueio",
          "error",
        );
        return;
      }

      const novoEstado = data?.checkin_bloqueado ?? bloquear;
      setParticipantsTurma((prev: any) =>
        prev ? { ...prev, checkin_bloqueado: novoEstado } : prev,
      );
      setAvailableSchedules((prev) =>
        prev.map((t) =>
          Number(t.id) === Number(participantsTurma.id)
            ? { ...t, checkin_bloqueado: novoEstado }
            : t,
        ),
      );

      showToast(
        data?.message ||
          (novoEstado
            ? "Check-in bloqueado para alunos"
            : "Check-in liberado para alunos"),
        "success",
      );
    } catch (error) {
      console.error("Erro ao alterar bloqueio de check-in:", error);
      showToast("Erro ao alterar bloqueio de check-in", "error");
    } finally {
      setTurmaCheckinBloqueioLoading(false);
    }
  };

  // Debug: Log dos estados para verificar papel do usuário
  console.log(
    "🎯 RENDER - currentUserPapelId:",
    currentUserPapelId,
    "isAluno:",
    isAluno,
    "isProfessorOuAdmin:",
    isProfessorOuAdmin,
  );

  return (
    <>
      <SafeAreaView style={styles.container} edges={["top"]}>
        {/* Header com Botão Recarregar */}
        <View style={styles.headerTop}>
          <View style={styles.headerTopRow}>
            <View style={styles.headerLeft}>
              <Text style={styles.headerTitle}>Checkin</Text>
            </View>
            <View style={styles.headerRight}>
              <TouchableOpacity
                style={styles.refreshButton}
                onPress={() => void reloadSchedulesList()}
                disabled={loading}
                accessibilityLabel="Recarregar horários"
              >
                {loading ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Feather name="refresh-cw" size={22} color="#fff" />
                )}
              </TouchableOpacity>
              <View style={styles.switchRow}>
                <Text style={styles.switchLabel}>Só disponíveis</Text>
                <Switch
                  value={showOnlyAvailable}
                  onValueChange={setShowOnlyAvailable}
                  trackColor={{
                    false: "rgba(255,255,255,0.25)",
                    true: "rgba(255,255,255,0.5)",
                  }}
                  thumbColor="#fff"
                />
              </View>
            </View>
          </View>
        </View>

        {/* Modal para trocar de tenant */}
        <Modal
          visible={tenantModalVisible}
          transparent
          animationType="fade"
          onRequestClose={() => setTenantModalVisible(false)}
        >
          <View style={styles.modalOverlay}>
            <View style={[styles.modalContainer, { maxWidth: 420 }]}>
              <View style={[styles.modalContent, styles.modalContentInfo]}>
                <View
                  style={[
                    styles.modalIconContainer,
                    styles.modalIconContainerInfo,
                  ]}
                >
                  <Feather name="home" size={36} color={colors.primary} />
                </View>
                <Text style={styles.modalTitle}>Trocar de Academia</Text>
                <Text style={styles.modalMessage}>
                  Selecione uma academia para focar apenas nos seus recursos.
                </Text>
                <View style={{ width: "100%", gap: 10 }}>
                  {tenantsList && tenantsList.length > 0 ? (
                    tenantsList.map((t) => (
                      <TouchableOpacity
                        key={t.id}
                        style={styles.tenantOptionButton}
                        onPress={() => selectTenantAndReload(t.id)}
                      >
                        <Feather name="home" size={16} color="#fff" />
                        <Text style={styles.tenantOptionText}>
                          {normalizeUtf8(t.nome || String(t.id))}
                        </Text>
                      </TouchableOpacity>
                    ))
                  ) : (
                    <Text style={styles.modalMessage}>
                      Nenhuma academia disponível.
                    </Text>
                  )}
                </View>
                <TouchableOpacity
                  style={[styles.modalButton, styles.modalButtonInfo]}
                  onPress={() => setTenantModalVisible(false)}
                >
                  <Text style={{ color: "#fff", fontWeight: "700" }}>
                    Fechar
                  </Text>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        </Modal>

        <ScrollView
          contentContainerStyle={[styles.scrollContent, styles.scrollGrow]}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          {/* Calendar */}
          <View style={styles.calendarSection} className="notranslate">
              <ScrollView
                horizontal
                nestedScrollEnabled
                showsHorizontalScrollIndicator={false}
                contentContainerStyle={styles.calendarContainer}
                keyboardShouldPersistTaps="handled"
              >
                {calendarDays.map((date) => {
                  const { day, dayName } = formatDateDisplay(date);
                  const dayKey = formatDateParam(date);
                  const isSelected =
                    selectedDate &&
                    formatDateParam(selectedDate) === dayKey;

                  return (
                    <Pressable
                      key={dayKey}
                      focusable={Platform.OS === "web" ? false : undefined}
                      onPress={() => selectCalendarDay(date)}
                      style={({ pressed }) => [
                        styles.calendarDay,
                        isSelected && styles.calendarDaySelected,
                        pressed && styles.calendarDayPressed,
                      ]}
                    >
                      <Text
                        style={[
                          styles.calendarDayName,
                          isSelected && styles.calendarDayNameSelected,
                        ]}
                        className="notranslate"
                      >
                        {dayName}
                      </Text>
                      <Text
                        style={[
                          styles.calendarDayNumber,
                          isSelected && styles.calendarDayNumberSelected,
                        ]}
                      >
                        {day}
                      </Text>
                    </Pressable>
                  );
                })}
              </ScrollView>
            </View>

          {/* Available Schedules */}
          <View style={styles.schedulesSection}>
            {loading ? (
              <Text style={styles.loadingText}>Carregando...</Text>
            ) : schedulesToRender.length > 0 ? (
              <View style={styles.schedulesList}>
                {schedulesToRender.map((turma) => {
                  const isClosed = isTurmaDisabled(turma, selectedDate);
                  const isCheckinBloqueado = !!turma?.checkin_bloqueado;
                  const disabled = isProfessorOuAdmin ? false : isClosed;
                  const statusColor = isClosed ? "#d9534f" : "#2e7d32";
                  const professorName =
                    turma.professor?.nome || turma.professor || "";
                  const horaLimite = getHoraLimiteCheckin(turma);
                  const displayName = cleanTurmaName(
                    turma.nome,
                    turma.modalidade,
                    turma.professor,
                  );
                  const modalidadeNome = normalizeUtf8(
                    String(turma.modalidade?.nome || ""),
                  ).trim();
                  const shouldShowName =
                    !!displayName &&
                    displayName !== "Turma" &&
                    (!modalidadeNome ||
                      displayName.toLowerCase() !==
                        modalidadeNome.toLowerCase());

                  return (
                    <TouchableOpacity
                      key={turma.id}
                      disabled={disabled}
                      style={[
                        styles.scheduleItem,
                        disabled && styles.scheduleItemDisabled,
                        isProfessorOuAdmin &&
                          isCheckinBloqueado &&
                          styles.scheduleItemBloqueado,
                        {
                          borderLeftColor: disabled
                            ? "#cccccc"
                            : isProfessorOuAdmin && isCheckinBloqueado
                              ? "#DC2626"
                              : turma.modalidade?.cor || colors.primary,
                        },
                      ]}
                      onPress={() => openParticipants(turma)}
                    >
                      <View style={styles.scheduleContent}>
                        {isCheckinBloqueado ? (
                          <View style={styles.scheduleCompactBlocked}>
                            <Text style={styles.scheduleTimeText}>
                              {turma.horario.inicio.slice(0, 5)} -{" "}
                              {turma.horario.fim.slice(0, 5)}
                            </Text>
                            <View style={styles.scheduleCompactBadges}>
                              {turma.modalidade ? (
                                <View
                                  style={[
                                    styles.modalidadeBadge,
                                    {
                                      backgroundColor:
                                        turma.modalidade.cor + "20",
                                    },
                                  ]}
                                >
                                  {turma.modalidade.icone ? (
                                    <MaterialCommunityIcons
                                      name={turma.modalidade.icone as any}
                                      size={12}
                                      color={turma.modalidade.cor}
                                    />
                                  ) : null}
                                  <Text
                                    style={[
                                      styles.modalidadeText,
                                      { color: turma.modalidade.cor },
                                    ]}
                                  >
                                    {normalizeUtf8(turma.modalidade.nome)}
                                  </Text>
                                </View>
                              ) : null}
                              <View style={styles.scheduleBlockedBadge}>
                                <MaterialCommunityIcons
                                  name="lock"
                                  size={14}
                                  color="#B91C1C"
                                />
                                <Text style={styles.scheduleBlockedBadgeText}>
                                  Bloqueado
                                </Text>
                              </View>
                            </View>
                          </View>
                        ) : (
                          <>
                        <View style={styles.scheduleHeader}>
                          <View style={{ flex: 1 }}>
                            <Text style={styles.scheduleTimeText}>
                              {turma.horario.inicio.slice(0, 5)} -{" "}
                              {turma.horario.fim.slice(0, 5)}
                            </Text>
                            {shouldShowName && (
                              <Text style={styles.scheduleName}>
                                {displayName}
                              </Text>
                            )}
                            {!!professorName && (
                              <Text style={styles.scheduleSubtitle}>
                                {normalizeUtf8(professorName)}
                              </Text>
                            )}
                          </View>
                          <View style={styles.scheduleHeaderBadges}>
                            {turma.modalidade ? (
                              <View
                                style={[
                                  styles.modalidadeBadge,
                                  {
                                    backgroundColor:
                                      turma.modalidade.cor + "20",
                                  },
                                ]}
                              >
                                {turma.modalidade.icone ? (
                                  <MaterialCommunityIcons
                                    name={turma.modalidade.icone as any}
                                    size={12}
                                    color={turma.modalidade.cor}
                                  />
                                ) : null}
                                <Text
                                  style={[
                                    styles.modalidadeText,
                                    { color: turma.modalidade.cor },
                                  ]}
                                >
                                  {normalizeUtf8(turma.modalidade.nome)}
                                </Text>
                              </View>
                            ) : null}
                          </View>
                        </View>

                        <View style={styles.scheduleInfoRow}>
                          <View style={styles.infoItem}>
                            <Feather name="user" size={14} color="#999" />
                            <Text style={styles.infoText}>
                              {turma.alunos_inscritos}/{turma.limite_alunos}
                            </Text>
                          </View>
                          {(() => {
                            const minutos = getMinutosParaAbrirCheckin(turma);
                            const isFuture = minutos > 0;
                            return isFuture ? (
                              <View style={styles.infoItem}>
                                <Feather name="clock" size={14} color="#999" />
                                <Text style={styles.infoText}>
                                  Abre em {formatMinutos(minutos)}
                                </Text>
                              </View>
                            ) : null;
                          })()}
                          {!disabled && horaLimite ? (
                            <View style={styles.infoItem}>
                              <Feather name="clock" size={14} color="#999" />
                              <Text style={styles.infoText}>
                                Até {horaLimite}
                              </Text>
                            </View>
                          ) : null}
                          {isClosed && (
                            <>
                              <View style={styles.statusDot} />
                              <View style={styles.infoItem}>
                                <Feather
                                  name="x-circle"
                                  size={14}
                                  color={statusColor}
                                />
                                <Text
                                  style={[
                                    styles.infoText,
                                    styles.statusClosedText,
                                  ]}
                                >
                                  Encerrado
                                </Text>
                              </View>
                            </>
                          )}
                        </View>
                          </>
                        )}
                      </View>
                      <Feather
                        name="chevron-right"
                        size={20}
                        color={
                          disabled
                            ? "#cccccc"
                            : turma.modalidade?.cor || colors.primary
                        }
                      />
                    </TouchableOpacity>
                  );
                })}
              </View>
            ) : (
              <View style={styles.emptyState}>
                <View style={styles.emptyIconCircle}>
                  <Feather name="moon" size={48} color={colors.primary} />
                </View>
                <Text style={styles.emptyTitle}>Rest Day</Text>
                <Text style={styles.emptySubtitle}>
                  Sem turmas disponíveis neste dia
                </Text>
              </View>
            )}
          </View>
        </ScrollView>
      </SafeAreaView>

      {/* Modal de Erro/Aviso Customizado */}
      <Modal
        visible={errorModal.visible}
        transparent
        animationType="none"
        onRequestClose={hideErrorModal}
      >
        <TouchableOpacity
          style={styles.modalOverlay}
          activeOpacity={1}
          onPress={hideErrorModal}
        >
          <Animated.View
            style={[
              styles.modalContainer,
              {
                transform: [{ scale: modalScale }],
              },
            ]}
          >
            <TouchableOpacity
              activeOpacity={1}
              onPress={(e) => e.stopPropagation()}
            >
              <View
                style={[
                  styles.modalContent,
                  errorModal.type === "error" && styles.modalContentError,
                  errorModal.type === "warning" && styles.modalContentWarning,
                  errorModal.type === "success" && styles.modalContentSuccess,
                ]}
              >
                {/* Ícone */}
                <View
                  style={[
                    styles.modalIconContainer,
                    errorModal.type === "error" &&
                      styles.modalIconContainerError,
                    errorModal.type === "warning" &&
                      styles.modalIconContainerWarning,
                    errorModal.type === "success" &&
                      styles.modalIconContainerSuccess,
                  ]}
                >
                  <Feather
                    name={
                      errorModal.type === "error"
                        ? "x-circle"
                        : errorModal.type === "warning"
                          ? "alert-triangle"
                          : "check-circle"
                    }
                    size={48}
                    color={
                      errorModal.type === "error"
                        ? "#d32f2f"
                        : errorModal.type === "warning"
                          ? "#f57c00"
                          : "#388e3c"
                    }
                  />
                </View>

                {/* Título */}
                <Text style={styles.modalTitle}>{errorModal.title}</Text>

                {/* Mensagem */}
                <Text style={styles.modalMessage}>{errorModal.message}</Text>

                {/* Botão Fechar */}
                <TouchableOpacity
                  style={[
                    styles.modalButton,
                    errorModal.type === "error" && styles.modalButtonError,
                    errorModal.type === "warning" && styles.modalButtonWarning,
                    errorModal.type === "success" && styles.modalButtonSuccess,
                  ]}
                  onPress={hideErrorModal}
                >
                  <Text style={styles.modalButtonText}>OK, Entendi</Text>
                </TouchableOpacity>
              </View>
            </TouchableOpacity>
          </Animated.View>
        </TouchableOpacity>
      </Modal>

      {toastVisible && (
        <View style={[styles.toastContainer, styles[`toast_${toast.type}`]]}>
          <Feather
            name={
              toast.type === "success"
                ? "check-circle"
                : toast.type === "error"
                  ? "alert-circle"
                  : toast.type === "warning"
                    ? "alert-triangle"
                    : "info"
            }
            size={16}
            color={
              toast.type === "success"
                ? "#0a7f3c"
                : toast.type === "error"
                  ? "#b3261e"
                  : toast.type === "warning"
                    ? "#b26a00"
                    : "#0b5cff"
            }
          />
          <Text style={styles.toastText}>{toast.message}</Text>
        </View>
      )}
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
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
  headerTopDetailed: {
    flexDirection: "column",
    alignItems: "stretch",
    gap: 10,
  },
  headerTopRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    width: "100%",
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: "800",
    color: "#fff",
  },
  headerSubtitle: {
    fontSize: 12,
    fontWeight: "700",
    color: "rgba(255,255,255,0.8)",
  },
  headerLeft: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    flex: 1,
  },
  headerRight: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginLeft: 12,
  },
  headerBackButton: {
    padding: 6,
    borderRadius: 10,
    backgroundColor: "rgba(255,255,255,0.18)",
  },
  headerIconCircle: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
  },
  headerTextBlock: {
    flexShrink: 1,
  },
  headerInfoRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
  },
  headerChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    backgroundColor: "rgba(255,255,255,0.2)",
  },
  headerChipText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "700",
  },
  headerChipBloqueado: {
    backgroundColor: "#B91C1C",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.45)",
  },
  bloqueioCheckinButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    marginTop: 12,
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderRadius: 14,
    borderWidth: 2,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.2,
    shadowRadius: 6,
    elevation: 4,
  },
  bloqueioCheckinButtonBloquear: {
    backgroundColor: "#DC2626",
    borderColor: "#FECACA",
  },
  bloqueioCheckinButtonLiberar: {
    backgroundColor: "#FFFFFF",
    borderColor: "#059669",
  },
  bloqueioCheckinIconWrap: {
    width: 52,
    height: 52,
    borderRadius: 26,
    alignItems: "center",
    justifyContent: "center",
  },
  bloqueioCheckinIconWrapBloquear: {
    backgroundColor: "rgba(0,0,0,0.18)",
  },
  bloqueioCheckinIconWrapLiberar: {
    backgroundColor: "#D1FAE5",
  },
  bloqueioCheckinTextBlock: {
    flex: 1,
    gap: 2,
  },
  bloqueioCheckinButtonText: {
    color: "#fff",
    fontSize: 15,
    fontWeight: "800",
  },
  bloqueioCheckinButtonTextLiberar: {
    color: "#047857",
  },
  bloqueioCheckinButtonHint: {
    color: "rgba(255,255,255,0.9)",
    fontSize: 12,
    fontWeight: "600",
  },
  bloqueioCheckinButtonHintLiberar: {
    color: "#059669",
  },
  headerActions: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  switchRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  switchLabel: {
    fontSize: 13,
    color: "#fff",
    fontWeight: "700",
  },
  refreshButton: {
    padding: 8,
  },
  scrollContent: {
    paddingBottom: 48,
    paddingTop: 0,
  },
  scrollGrow: {
    flexGrow: 1,
  },
  calendarSection: {
    paddingVertical: 0,
    paddingHorizontal: 0,
    backgroundColor: "#fff",
    borderBottomWidth: 0,
    borderBottomColor: "transparent",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
  },
  calendarContainer: {
    paddingHorizontal: 16,
    paddingVertical: 14,
    gap: 8,
  },
  calendarDay: {
    backgroundColor: "#fff",
    borderRadius: 16,
    paddingVertical: 12,
    paddingHorizontal: 18,
    alignItems: "center",
    minWidth: 70,
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  calendarDaySelected: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  calendarDayPressed: {
    opacity: 0.9,
  },
  calendarDayName: {
    fontSize: 13,
    color: colors.textMuted,
    marginBottom: 4,
    fontWeight: "500",
  },
  calendarDayNameSelected: {
    color: "#fff",
  },
  calendarDayNumber: {
    fontSize: 20,
    fontWeight: "bold",
    color: colors.primary,
  },
  calendarDayNumberSelected: {
    color: "#fff",
  },
  schedulesSection: {
    paddingHorizontal: 16,
    paddingVertical: 18,
  },
  schedulesList: {
    gap: 10,
  },
  scheduleItem: {
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 16,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
    marginBottom: 0,
  },
  scheduleContent: {
    flex: 1,
    marginRight: 12,
  },
  scheduleItemDisabled: {
    opacity: 0.5,
  },
  scheduleItemBloqueado: {
    backgroundColor: "#FEF2F2",
    borderColor: "#FECACA",
  },
  scheduleCompactBlocked: {
    gap: 8,
  },
  scheduleCompactBadges: {
    flexDirection: "row",
    flexWrap: "wrap",
    alignItems: "center",
    gap: 8,
  },
  scheduleHeaderBadges: {
    flexDirection: "row",
    flexWrap: "wrap",
    alignItems: "center",
    justifyContent: "flex-end",
    gap: 6,
  },
  scheduleBlockedBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    backgroundColor: "#FEE2E2",
    borderWidth: 1,
    borderColor: "#FECACA",
  },
  scheduleBlockedBadgeText: {
    color: "#B91C1C",
    fontSize: 11,
    fontWeight: "800",
  },
  scheduleBlockedInfoText: {
    color: "#B91C1C",
    fontSize: 12,
    fontWeight: "700",
  },
  scheduleHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 12,
    gap: 12,
  },
  scheduleTimeText: {
    fontSize: 18,
    fontWeight: "bold",
    color: colors.text,
    marginBottom: 4,
  },
  scheduleName: {
    fontSize: 14,
    color: colors.textSecondary,
    fontWeight: "600",
  },
  scheduleSubtitle: {
    fontSize: 13,
    color: colors.textMuted,
    marginTop: 2,
  },
  modalidadeBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    justifyContent: "center",
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  modalidadeText: {
    fontSize: 12,
    fontWeight: "700",
  },
  scheduleInfo: {
    flexDirection: "row",
    gap: 16,
  },
  scheduleInfoRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  statusDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: "#cbd5f5",
  },
  statusOpenText: {
    color: "#2e7d32",
  },
  statusClosedText: {
    color: "#d9534f",
  },
  infoItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  infoText: {
    fontSize: 13,
    color: colors.textMuted,
    fontWeight: "600",
  },
  participantsWrapper: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 18,
    gap: 12,
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
  },
  participantsTopRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    marginBottom: 10,
  },
  turmaIconCircle: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: "center",
    justifyContent: "center",
  },
  backButtonInline: {
    padding: 8,
    borderRadius: 10,
    backgroundColor: "#f3f4f6",
  },
  participantsListContainer: {
    borderTopWidth: 1,
    borderTopColor: "#eef2f7",
    paddingTop: 6,
    gap: 2,
  },
  participantsContent: {
    gap: 12,
  },
  manualCheckinBox: {
    backgroundColor: "#f9fafb",
    borderRadius: 14,
    padding: 16,
    gap: 10,
    borderWidth: 1,
    borderColor: "#eef2f7",
    marginBottom: 8,
  },
  manualCheckinTitle: {
    fontSize: 13,
    fontWeight: "700",
    color: colors.textSecondary,
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  manualCheckinRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  manualCheckinInput: {
    flex: 1,
    height: 42,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e7eb",
    paddingHorizontal: 12,
    fontSize: 14,
    color: colors.text,
    backgroundColor: "#fff",
  },
  manualCheckinSearchButton: {
    width: 42,
    height: 42,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: colors.primary,
  },
  manualCheckinSearchButtonDisabled: {
    opacity: 0.5,
  },
  manualCheckinClearButton: {
    width: 42,
    height: 42,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#e5e7eb",
  },
  manualCheckinError: {
    color: "#b3261e",
    fontSize: 13,
    fontWeight: "600",
  },
  manualCheckinResults: {
    gap: 8,
  },
  manualCheckinItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 10,
    borderWidth: 1,
    borderColor: "#eef2f7",
  },
  manualCheckinAvatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: "#e5e7eb",
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  manualCheckinAvatarImage: {
    width: "100%",
    height: "100%",
  },
  manualCheckinItemInfo: {
    flex: 1,
  },
  manualCheckinItemName: {
    fontSize: 14,
    fontWeight: "700",
    color: colors.text,
  },
  manualCheckinItemMeta: {
    fontSize: 12,
    color: colors.textMuted,
  },
  manualCheckinAddButton: {
    backgroundColor: "#10b981",
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 10,
  },
  manualCheckinAddButtonDisabled: {
    backgroundColor: "#9ca3af",
  },
  manualCheckinAddButtonText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "700",
  },
  checkinButton: {
    marginTop: 12,
    backgroundColor: "#10b981",
    paddingVertical: 14,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.12,
    shadowRadius: 10,
    elevation: 3,
  },
  checkinButtonUndo: {
    backgroundColor: "#ef4444",
  },
  checkinButtonDisabled: {
    opacity: 0.6,
  },
  checkinButtonText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 17,
  },
  outOfPeriodHint: {
    marginTop: 6,
    textAlign: "center",
    color: colors.textSecondary,
    fontSize: 13,
    fontWeight: "600",
  },
  loadingText: {
    textAlign: "center",
    color: colors.textMuted,
    fontSize: 16,
    marginVertical: 20,
  },
  noSchedulesText: {
    textAlign: "center",
    color: colors.textMuted,
    fontSize: 16,
    marginVertical: 20,
  },
  participantItem: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: "#eef2f7",
    gap: 8,
  },
  participantAvatar: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: "#e5e7eb",
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  participantAvatarCurrent: {
    borderWidth: 3,
    borderColor: "#10b981",
  },
  participantAvatarImage: {
    width: "100%",
    height: "100%",
  },
  participantAvatarText: {
    fontSize: 15,
    fontWeight: "700",
    color: colors.textSecondary,
  },
  participantAvatarTextCurrent: {
    color: "#fff",
  },
  participantInfo: {
    flex: 1,
  },
  participantName: {
    fontSize: 16,
    fontWeight: "700",
    color: colors.text,
  },
  participantTime: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  participantTimeText: {
    fontSize: 13,
    color: colors.textSecondary,
  },
  presencaButtons: {
    flexDirection: "row",
    gap: 10,
  },
  presencaBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: "#f3f4f6",
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 2,
    borderColor: "#e5e7eb",
  },
  presencaBtnPresente: {
    backgroundColor: "#10b981",
    borderColor: "#10b981",
  },
  presencaBtnFalta: {
    backgroundColor: "#ef4444",
    borderColor: "#ef4444",
  },
  sectionLabel: {
    fontSize: 13,
    fontWeight: "700",
    color: colors.textSecondary,
    marginBottom: 8,
    textTransform: "uppercase",
    letterSpacing: 0.5,
  },
  statsRow: {
    flexDirection: "row",
    gap: 10,
    marginTop: 4,
  },
  statCard: {
    flex: 1,
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 12,
  },
  statLabel: {
    fontSize: 13,
    color: colors.textSecondary,
    marginBottom: 4,
  },
  statValue: {
    fontSize: 18,
    fontWeight: "700",
    color: colors.text,
  },
  checkinsList: {
    borderTopWidth: 1,
    borderTopColor: "#eef2f7",
    paddingTop: 8,
    gap: 8,
  },
  checkinItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  participantAvatarSmall: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: "#e5e7eb",
    alignItems: "center",
    justifyContent: "center",
  },
  checkinHourChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: colors.primary + "12",
    borderRadius: 12,
  },
  checkinHourText: {
    fontSize: 13,
    color: colors.primary,
    fontWeight: "700",
  },
  toastContainer: {
    position: "absolute",
    bottom: 24,
    left: 16,
    right: 16,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 14,
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.12,
    shadowRadius: 10,
    elevation: 5,
  },
  toastText: {
    color: "#111827",
    fontSize: 14,
    flex: 1,
    fontWeight: "600",
  },
  toast_success: {
    backgroundColor: "#e6f4ec",
  },
  toast_error: {
    backgroundColor: "#fdecea",
  },
  toast_warning: {
    backgroundColor: "#fff4e5",
  },
  toast_info: {
    backgroundColor: "#e7f1ff",
  },
  emptyState: {
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 60,
    gap: 12,
  },
  emptyIconCircle: {
    width: 96,
    height: 96,
    borderRadius: 48,
    backgroundColor: "#fff",
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
    marginBottom: 8,
  },
  emptyTitle: {
    fontSize: 20,
    fontWeight: "800",
    color: colors.text,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  // Modal de Erro Customizado
  modalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0, 0, 0, 0.6)",
    justifyContent: "center",
    alignItems: "center",
    padding: 20,
  },
  modalContainer: {
    width: "100%",
    maxWidth: 400,
  },
  modalContent: {
    backgroundColor: "#fff",
    borderRadius: 20,
    padding: 24,
    alignItems: "center",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.25,
    shadowRadius: 16,
    elevation: 10,
  },
  modalContentError: {
    borderTopWidth: 4,
    borderTopColor: "#d32f2f",
  },
  modalContentWarning: {
    borderTopWidth: 4,
    borderTopColor: "#f57c00",
  },
  modalContentSuccess: {
    borderTopWidth: 4,
    borderTopColor: "#388e3c",
  },
  modalContentInfo: {
    borderTopWidth: 4,
    borderTopColor: colors.primary,
  },
  modalIconContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 16,
  },
  modalIconContainerError: {
    backgroundColor: "#ffebee",
  },
  modalIconContainerWarning: {
    backgroundColor: "#fff3e0",
  },
  modalIconContainerSuccess: {
    backgroundColor: "#e8f5e9",
  },
  modalIconContainerInfo: {
    backgroundColor: colors.primary + "15",
  },
  modalTitle: {
    fontSize: 24,
    fontWeight: "700",
    color: "#000",
    marginBottom: 12,
    textAlign: "center",
  },
  modalMessage: {
    fontSize: 18,
    color: "#555",
    textAlign: "center",
    marginBottom: 24,
    lineHeight: 24,
  },
  modalButton: {
    width: "100%",
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: "center",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 3,
  },
  modalButtonError: {
    backgroundColor: "#d32f2f",
  },
  modalButtonWarning: {
    backgroundColor: "#f57c00",
  },
  modalButtonSuccess: {
    backgroundColor: "#388e3c",
  },
  modalButtonInfo: {
    backgroundColor: colors.primary,
  },
  modalButtonText: {
    color: "#fff",
    fontSize: 17,
    fontWeight: "700",
  },
  confirmButtonsRow: {
    flexDirection: "row",
    width: "100%",
    gap: 10,
  },
  confirmButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: "center",
  },
  confirmButtonCancel: {
    backgroundColor: "#e5e7eb",
  },
  confirmButtonConfirm: {
    backgroundColor: colors.primary,
  },
  confirmButtonText: {
    color: "#111827",
    fontSize: 16,
    fontWeight: "700",
  },
  confirmButtonTextLight: {
    color: "#fff",
  },
  tenantSwitchButton: {
    marginTop: 2,
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: "rgba(255,255,255,0.18)",
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
    alignSelf: "flex-start",
  },
  tenantSwitchText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "700",
  },
  tenantOptionButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: colors.primary,
    paddingHorizontal: 12,
    paddingVertical: 12,
    borderRadius: 12,
  },
  tenantOptionText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 16,
  },
});
