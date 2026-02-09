import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useFocusEffect } from "@react-navigation/native";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect, useRef, useState } from "react";
import {
  Animated,
  Image,
  Modal,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import AuthService from "../../src/services/authService";
import { colors } from "../../src/theme/colors";
import { handleAuthError } from "../../src/utils/authHelpers";
import { normalizeUtf8 } from "../../src/utils/utf8";

export default function CheckinScreen() {
  const router = useRouter();
  const [selectedDate, setSelectedDate] = useState<Date>(new Date());
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
  const [userCheckinId, setUserCheckinId] = useState<number | null>(null);
  const [presencas, setPresencas] = useState<Record<number, boolean | null>>(
    {},
  );
  const [confirmandoPresenca, setConfirmandoPresenca] = useState(false);
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
  const isProfessorOuAdmin =
    currentUserPapelId === 2 ||
    currentUserPapelId === 3 ||
    currentUserPapelId === 4;
  const isAluno =
    currentUserPapelId === 1 ||
    currentAlunoId !== null ||
    currentUserPapelId === null;

  // Debug: Mostrar valores de papel no console
  console.log(
    "🎭 PAPEL DEBUG - papel:",
    currentUserPapelId,
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
        setCurrentUserId(user.id);
        setCurrentAlunoId(user.aluno_id || null);
        setCurrentUserEmail(user.email || null);
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

  useEffect(() => {
    console.log("📅 DATA SELECIONADA MUDOU:", selectedDate);
    // Quando a data muda, limpa os detalhes primeiro
    setParticipantsTurma(null);
    setParticipants([]);
    setCheckinsRecentes([]);
    setAlunosTotal(0);
    // Depois carrega os horários
    if (selectedDate) {
      fetchAvailableSchedules(selectedDate);
    }
  }, [selectedDate]); // eslint-disable-line react-hooks/exhaustive-deps

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

  const formatDateParam = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  const fetchAvailableSchedules = async (
    date: Date,
    tenantIdOverride?: number,
  ) => {
    if (isFetchingSchedulesRef.current) {
      console.log("⏳ Ignorando fetch duplicado de horários");
      return;
    }
    isFetchingSchedulesRef.current = true;
    setLoading(true);
    try {
      console.log("\n🔄 INICIANDO CARREGAMENTO DE HORÁRIOS");
      const reqId = Date.now();
      latestSchedulesReqRef.current = reqId;

      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        console.error("❌ Token não encontrado");
        showToast("Token não encontrado", "error");
        return;
      }
      console.log("✅ Token encontrado:", token.substring(0, 20) + "...");

      const formattedDate = formatDateParam(date);
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
        await Promise.all([
          fetchAvailableSchedules(selectedDate),
          openParticipants(mergeTurmaFromList(turma.id, turma)),
        ]);
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
      await Promise.all([
        fetchAvailableSchedules(selectedDate),
        openParticipants(mergeTurmaFromList(turma.id, turma)),
      ]);
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
      await openParticipants(
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

  const openParticipants = async (turma: any) => {
    if (!turma?.id) return;
    setParticipantsLoading(true);
    setParticipants([]);
    setCheckinsRecentes([]);
    setAlunosTotal(0);
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
    const dayName = date.toLocaleDateString("pt-BR", { weekday: "short" });
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

  const schedulesToRender = showOnlyAvailable
    ? availableSchedules.filter((turma) => !isCheckinDisabled(turma))
    : availableSchedules;

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
          <View style={{ flexDirection: "column" }}>
            <Text style={styles.headerTitle}>Checkin</Text>
          </View>
          <View style={styles.headerActions}>
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
            <TouchableOpacity
              style={styles.refreshButton}
              onPress={generateCalendarDays}
            >
              <Feather name="refresh-cw" size={20} color="#fff" />
            </TouchableOpacity>
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
        >
          {/* Calendar */}
          <View style={styles.calendarSection}>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.calendarContainer}
            >
              {calendarDays.map((date, index) => {
                const { day, dayName } = formatDateDisplay(date);
                const isSelected =
                  selectedDate &&
                  selectedDate.toDateString() === date.toDateString();

                return (
                  <TouchableOpacity
                    key={index}
                    style={[
                      styles.calendarDay,
                      isSelected && styles.calendarDaySelected,
                    ]}
                    onPress={() => setSelectedDate(new Date(date))}
                  >
                    <Text
                      style={[
                        styles.calendarDayName,
                        isSelected && styles.calendarDayNameSelected,
                      ]}
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
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          </View>

          {/* Available Schedules */}
          <View style={styles.schedulesSection}>
            {participantsTurma ? (
              <>
                <View style={styles.participantsTopRow}>
                  <TouchableOpacity
                    onPress={() => {
                      setParticipantsTurma(null);
                      setParticipants([]);
                    }}
                    style={[
                      styles.backButtonInline,
                      {
                        backgroundColor: `${participantsTurma?.modalidade?.cor || colors.primary}15`,
                      },
                    ]}
                  >
                    <Feather
                      name="arrow-left"
                      size={20}
                      color={
                        participantsTurma?.modalidade?.cor || colors.primary
                      }
                    />
                  </TouchableOpacity>
                  <View
                    style={[
                      styles.turmaIconCircle,
                      {
                        backgroundColor: `${participantsTurma?.modalidade?.cor || colors.primary}20`,
                      },
                    ]}
                  >
                    {participantsTurma?.modalidade?.icone ? (
                      <MaterialCommunityIcons
                        name={participantsTurma.modalidade.icone as any}
                        size={18}
                        color={
                          participantsTurma?.modalidade?.cor || colors.primary
                        }
                      />
                    ) : (
                      <Feather
                        name="activity"
                        size={18}
                        color={
                          participantsTurma?.modalidade?.cor || colors.primary
                        }
                      />
                    )}
                  </View>
                </View>

                <View style={styles.participantsWrapper}>
                  <Text style={styles.participantsTitle}>
                    {normalizeUtf8(participantsTurma.nome || "Turma")}
                  </Text>

                  <View style={styles.participantsMetaRow}>
                    <View style={styles.metaChip}>
                      <Feather
                        name="clock"
                        size={14}
                        color={
                          participantsTurma?.modalidade?.cor || colors.primary
                        }
                      />
                      <Text style={styles.metaChipText}>
                        {getHoraInicio(participantsTurma)?.slice(0, 5)} -{" "}
                        {getHoraFim(participantsTurma)?.slice(0, 5)}
                      </Text>
                    </View>
                    {getHoraLimiteCheckin(participantsTurma) ? (
                      <View style={styles.metaChip}>
                        <Feather
                          name="clock"
                          size={14}
                          color={
                            participantsTurma?.modalidade?.cor || colors.primary
                          }
                        />
                        <Text style={styles.metaChipText}>
                          Check-in até {getHoraLimiteCheckin(participantsTurma)}
                        </Text>
                      </View>
                    ) : null}
                  </View>

                  <View style={styles.participantsContent}>
                    {participantsLoading ? (
                      <Text style={styles.loadingText}>Carregando...</Text>
                    ) : (
                      <>
                        {/* Lista de check-ins recentes (visível para todos; botões só para professor/admin) */}
                        {checkinsRecentes.length > 0 && (
                          <View style={styles.participantsListContainer}>
                            {checkinsRecentes.map((c, idx) => {
                              const presencaAtual = presencas[c.checkin_id];
                              // Foto pode não vir em checkins_recentes; buscar em alunos.lista (participants)
                              const alunoFoto = participants.find(
                                (p) =>
                                  Number(p.aluno_id) === Number(c.aluno_id),
                              )?.foto_caminho;
                              const photoUrl = getUserPhotoUrl(
                                c.foto_caminho || alunoFoto || null,
                              );
                              // Borda de status: cinza (não confirmada), verde (presente), vermelha (falta)
                              const isConfirmed = Boolean(
                                c.presenca_confirmada_em,
                              );
                              let statusBorderColor = "#9ca3af"; // cinza padrão
                              if (isConfirmed) {
                                statusBorderColor = c.presente
                                  ? "#10b981"
                                  : "#ef4444";
                              }
                              return (
                                <View
                                  key={c.checkin_id || idx}
                                  style={styles.participantItem}
                                >
                                  <View
                                    style={[
                                      styles.participantAvatar,
                                      {
                                        borderWidth: 3,
                                        borderColor: statusBorderColor,
                                      },
                                    ]}
                                  >
                                    {photoUrl ? (
                                      <Image
                                        source={{ uri: photoUrl }}
                                        style={styles.participantAvatarImage}
                                      />
                                    ) : (
                                      <Feather
                                        name="user"
                                        size={18}
                                        color="#9ca3af"
                                      />
                                    )}
                                  </View>
                                  <View style={styles.participantInfo}>
                                    <Text style={styles.participantName}>
                                      {normalizeUtf8(
                                        c.usuario_nome || "Aluno",
                                      ).toUpperCase()}
                                    </Text>
                                  </View>
                                  {/* Botões de presença (somente para professor/admin) */}
                                  {isProfessorOuAdmin && (
                                    <View style={styles.presencaButtons}>
                                      <TouchableOpacity
                                        style={[
                                          styles.presencaBtn,
                                          presencaAtual === true &&
                                            styles.presencaBtnPresente,
                                        ]}
                                        onPress={() =>
                                          togglePresenca(c.checkin_id)
                                        }
                                      >
                                        <Feather
                                          name="check"
                                          size={22}
                                          color={
                                            presencaAtual === true
                                              ? "#fff"
                                              : "#9ca3af"
                                          }
                                        />
                                      </TouchableOpacity>
                                      <TouchableOpacity
                                        style={[
                                          styles.presencaBtn,
                                          presencaAtual === false &&
                                            styles.presencaBtnFalta,
                                        ]}
                                        onPress={() => {
                                          setPresencas((prev) => ({
                                            ...prev,
                                            [c.checkin_id]:
                                              prev[c.checkin_id] === false
                                                ? null
                                                : false,
                                          }));
                                        }}
                                      >
                                        <Feather
                                          name="x"
                                          size={22}
                                          color={
                                            presencaAtual === false
                                              ? "#fff"
                                              : "#9ca3af"
                                          }
                                        />
                                      </TouchableOpacity>
                                    </View>
                                  )}
                                </View>
                              );
                            })}
                          </View>
                        )}

                        {/* Mensagem quando não há check-ins */}
                        {(!isProfessorOuAdmin ||
                          checkinsRecentes.length === 0) &&
                          participantsToShow.length === 0 && (
                            <Text style={styles.loadingText}>
                              Nenhum participante ainda
                            </Text>
                          )}
                      </>
                    )}
                  </View>

                  {/* Botão de confirmar presenças (professor/admin sempre vê; fora do período alerta ao clicar) */}
                  {isProfessorOuAdmin &&
                    checkinsRecentes.length > 0 &&
                    (() => {
                      const foraPeriodo = !isDurantePeriodo(
                        participantsTurma,
                        selectedDate,
                      );
                      const horaInicio = getHoraInicio(
                        participantsTurma,
                      )?.slice(0, 5);
                      const horaFim = getHoraFim(participantsTurma)?.slice(
                        0,
                        5,
                      );
                      return (
                        <>
                          <TouchableOpacity
                            style={[
                              styles.checkinButton,
                              { backgroundColor: "#3b82f6" },
                              confirmandoPresenca &&
                                styles.checkinButtonDisabled,
                            ]}
                            onPress={() => {
                              if (foraPeriodo) {
                                const msg =
                                  horaInicio && horaFim
                                    ? `Fora do período da aula. Horário: ${horaInicio} - ${horaFim}.`
                                    : horaInicio
                                      ? `Fora do período da aula. Abre às ${horaInicio}.`
                                      : "Fora do período da aula.";
                                showErrorModal(msg, "warning");
                                return;
                              }
                              confirmarPresencas();
                            }}
                            disabled={confirmandoPresenca}
                          >
                            {confirmandoPresenca ? (
                              <>
                                <Feather name="loader" size={18} color="#fff" />
                                <Text style={styles.checkinButtonText}>
                                  Confirmando...
                                </Text>
                              </>
                            ) : (
                              <>
                                <Feather
                                  name="check-square"
                                  size={18}
                                  color="#fff"
                                />
                                <Text style={styles.checkinButtonText}>
                                  Confirmar Presenças
                                </Text>
                              </>
                            )}
                          </TouchableOpacity>
                          {foraPeriodo && horaInicio && horaFim ? (
                            <Text style={styles.outOfPeriodHint}>
                              Disponível das {horaInicio} às {horaFim}
                            </Text>
                          ) : null}
                        </>
                      );
                    })()}

                  {/* Botão de check-in só aparece para alunos */}
                  {isAluno &&
                    (() => {
                      const minutosParaAbrir =
                        getMinutosParaAbrirCheckin(participantsTurma);
                      const checkinNaoAbriu = minutosParaAbrir > 0;
                      return (
                        <>
                          <TouchableOpacity
                            style={[
                              styles.checkinButton,
                              {
                                backgroundColor:
                                  participantsTurma?.modalidade?.cor ||
                                  colors.primary,
                              },
                              {
                                shadowColor:
                                  participantsTurma?.modalidade?.cor ||
                                  colors.primary,
                              },
                              userCheckinId ? styles.checkinButtonUndo : null,
                              (checkinLoading ||
                                checkinNaoAbriu ||
                                (!userCheckinId &&
                                  isCheckinDisabled(participantsTurma))) &&
                                styles.checkinButtonDisabled,
                            ]}
                            onPress={() => {
                              if (userCheckinId) {
                                handleUndoCheckin(
                                  userCheckinId,
                                  participantsTurma,
                                );
                              } else {
                                if (checkinNaoAbriu) {
                                  const aberturaStr =
                                    getDataAberturaCheckin(participantsTurma);
                                  const label =
                                    aberturaStr instanceof Date
                                      ? `Abre em ${formatMinutos(
                                          minutosParaAbrir,
                                        )}`
                                      : "Ainda não disponível";
                                  showErrorModal(label, "warning");
                                  return;
                                }
                                handleCheckin(participantsTurma);
                              }
                            }}
                            disabled={
                              checkinLoading ||
                              checkinNaoAbriu ||
                              (!userCheckinId &&
                                isCheckinDisabled(participantsTurma))
                            }
                          >
                            {checkinLoading ? (
                              <>
                                <Feather name="loader" size={18} color="#fff" />
                                <Text style={styles.checkinButtonText}>
                                  Processando...
                                </Text>
                              </>
                            ) : userCheckinId ? (
                              <>
                                <Feather
                                  name="rotate-ccw"
                                  size={18}
                                  color="#fff"
                                />
                                <Text style={styles.checkinButtonText}>
                                  Desfazer Check-in
                                </Text>
                              </>
                            ) : (
                              <>
                                <Feather
                                  name="check-circle"
                                  size={18}
                                  color="#fff"
                                />
                                <Text style={styles.checkinButtonText}>
                                  Fazer Check-in
                                </Text>
                              </>
                            )}
                          </TouchableOpacity>
                          {checkinNaoAbriu ? (
                            <Text style={styles.outOfPeriodHint}>
                              Abre em {formatMinutos(minutosParaAbrir)}
                            </Text>
                          ) : null}
                        </>
                      );
                    })()}

                  {/* Card 'Meu check-in' removido para manter layout original */}
                </View>
              </>
            ) : loading ? (
              <Text style={styles.loadingText}>Carregando...</Text>
            ) : schedulesToRender.length > 0 ? (
              <View style={styles.schedulesList}>
                {schedulesToRender.map((turma) => {
                  const disabled = isTurmaDisabled(turma, selectedDate);
                  const statusColor = disabled ? "#d9534f" : "#2e7d32";
                  const professorName =
                    turma.professor?.nome || turma.professor || "";
                  const horaLimite = getHoraLimiteCheckin(turma);

                  return (
                    <TouchableOpacity
                      key={turma.id}
                      disabled={disabled}
                      style={[
                        styles.scheduleItem,
                        disabled && styles.scheduleItemDisabled,
                        {
                          borderLeftColor: disabled
                            ? "#cccccc"
                            : turma.modalidade?.cor || colors.primary,
                        },
                      ]}
                      onPress={() => openParticipants(turma)}
                    >
                      <View style={styles.scheduleContent}>
                        <View style={styles.scheduleHeader}>
                          <View style={{ flex: 1 }}>
                            <Text style={styles.scheduleTimeText}>
                              {turma.horario.inicio.slice(0, 5)} -{" "}
                              {turma.horario.fim.slice(0, 5)}
                            </Text>
                            <Text style={styles.scheduleName}>
                              {cleanTurmaName(
                                turma.nome,
                                turma.modalidade,
                                turma.professor,
                              )}
                            </Text>
                            {!!professorName && (
                              <Text style={styles.scheduleSubtitle}>
                                {normalizeUtf8(professorName)}
                              </Text>
                            )}
                          </View>
                          {turma.modalidade && (
                            <View
                              style={[
                                styles.modalidadeBadge,
                                {
                                  backgroundColor: turma.modalidade.cor + "20",
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
                          )}
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
                          {disabled && (
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
  headerTitle: {
    fontSize: 24,
    fontWeight: "800",
    color: "#fff",
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
  participantsTitle: {
    fontSize: 19,
    fontWeight: "800",
    color: colors.text,
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
  participantsMetaRow: {
    flexDirection: "row",
    gap: 8,
    flexWrap: "wrap",
  },
  metaChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: "#f3f4f6",
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  metaChipText: {
    fontSize: 13,
    color: colors.textSecondary,
    fontWeight: "600",
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
    width: 40,
    height: 40,
    borderRadius: 20,
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
