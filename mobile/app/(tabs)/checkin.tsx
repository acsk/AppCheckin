import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import React, { useEffect, useRef, useState } from "react";
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
import { colors } from "../../src/theme/colors";
import { handleAuthError } from "../../src/utils/authHelpers";
import { normalizeUtf8 } from "../../src/utils/utf8";

export default function CheckinScreen() {
  const router = useRouter();
  const [selectedDate, setSelectedDate] = useState<Date>(new Date());
  const [availableSchedules, setAvailableSchedules] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [calendarDays, setCalendarDays] = useState<Date[]>([]);
  const [participantsLoading, setParticipantsLoading] = useState(false);
  const [participants, setParticipants] = useState<any[]>([]);
  const [participantsTurma, setParticipantsTurma] = useState<any | null>(null);
  const [checkinLoading, setCheckinLoading] = useState(false);
  const [checkinsRecentes, setCheckinsRecentes] = useState<any[]>([]);
  const [resumoTurma, setResumoTurma] = useState<any | null>(null);
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
  const [userCheckinId, setUserCheckinId] = useState<number | null>(null);

  const participantsToShow = participants;
  const participantsCount = participantsTurma
    ? participantsToShow.length ||
      alunosTotal ||
      participantsTurma.alunos_inscritos ||
      0
    : 0;

  const getInitials = (nome: string = "") => {
    const parts = normalizeUtf8(nome).split(" ").filter(Boolean);
    if (parts.length === 0) return "?";
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (
      parts[0].charAt(0) + parts[parts.length - 1].charAt(0)
    ).toUpperCase();
  };

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
        setCurrentUserId(user.id);
        setCurrentAlunoId(user.aluno_id || null);
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
    setResumoTurma(null);
    setAlunosTotal(0);
    // Depois carrega os horários
    if (selectedDate) {
      fetchAvailableSchedules(selectedDate);
    }
  }, [selectedDate]);

  useEffect(() => {
    if (participantsTurma && (currentUserId || currentAlunoId)) {
      checkUserHasCheckin();
    }
  }, [participants, checkinsRecentes, currentUserId, currentAlunoId]);

  const generateCalendarDays = () => {
    console.log("📅 GERANDO CALENDÁRIO");
    const today = new Date();
    console.log("   Data hoje:", today);
    const days: Date[] = [];

    for (let i = 0; i < 7; i++) {
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

  const fetchAvailableSchedules = async (date: Date) => {
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

      const formattedDate = formatDateParam(date);
      console.log("📅 Data formatada:", formattedDate);

      const url = `${getApiUrlRuntime()}/mobile/horarios-disponiveis?data=${formattedDate}`;
      console.log("📍 URL:", url);

      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
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
      } catch (e) {
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
          "Content-Type": "application/json",
        },
      });

      const text = await response.text();
      let data: any = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch (e) {
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

  const checkUserHasCheckin = () => {
    console.log("🔍 Verificando check-in do usuário");
    console.log("   currentUserId:", currentUserId);
    console.log("   currentAlunoId:", currentAlunoId);
    console.log("   participants.length:", participants.length);
    console.log("   checkinsRecentes.length:", checkinsRecentes.length);

    if (!currentUserId && !currentAlunoId) {
      console.log("   ❌ Sem currentUserId e currentAlunoId");
      setUserCheckinId(null);
      return false;
    }

    // PRIORIDADE 1: Verificar primeiro em checkinsRecentes (tem o ID real do checkin)
    if (checkinsRecentes.length > 0) {
      console.log(
        "   Check-ins recentes:",
        JSON.stringify(
          checkinsRecentes.map((c) => ({
            id: c.id,
            checkin_id: c.checkin_id,
            aluno_id: c.aluno_id,
            usuario_id: c.usuario_id,
          })),
        ),
      );

      // Buscar por aluno_id ou usuario_id
      const userCheckin = checkinsRecentes.find(
        (c) =>
          (currentAlunoId && Number(c.aluno_id) === Number(currentAlunoId)) ||
          Number(c.usuario_id) === Number(currentUserId) ||
          Number(c.aluno_id) === Number(currentUserId),
      );

      if (userCheckin) {
        console.log(
          "   ✅ Usuário encontrado em check-ins recentes:",
          userCheckin,
        );
        const checkinId = userCheckin.checkin_id || userCheckin.id;
        console.log("   📝 checkin_id dos recentes:", checkinId);
        setUserCheckinId(checkinId);
        return true;
      }
    }

    // PRIORIDADE 2: Verificar nos participantes (alunos matriculados com check-in)
    if (participants.length > 0) {
      console.log(
        "   Participantes:",
        JSON.stringify(
          participants.map((p) => ({
            aluno_id: p.aluno_id,
            id: p.id,
            usuario_id: p.usuario_id,
            checkins: p.checkins,
            nome: p.nome || p.usuario_nome,
          })),
        ),
      );

      // Buscar por aluno_id ou usuario_id
      const userParticipant = participants.find(
        (p) =>
          (currentAlunoId && Number(p.aluno_id) === Number(currentAlunoId)) ||
          Number(p.usuario_id) === Number(currentUserId) ||
          Number(p.aluno_id) === Number(currentUserId) ||
          Number(p.id) === Number(currentUserId),
      );

      if (userParticipant) {
        console.log(
          "   ✅ Usuário encontrado nos participantes:",
          userParticipant,
        );

        // Se tem checkins > 0, precisamos buscar o checkin_id nos checkinsRecentes
        if (userParticipant.checkins > 0 && checkinsRecentes.length > 0) {
          const checkin = checkinsRecentes.find(
            (c) => Number(c.aluno_id) === Number(userParticipant.aluno_id),
          );
          if (checkin) {
            const checkinId = checkin.checkin_id || checkin.id;
            console.log(
              "   📝 checkin_id encontrado via checkins_recentes:",
              checkinId,
            );
            setUserCheckinId(checkinId);
            return true;
          }
        }

        const checkinId = userParticipant.checkin_id || userParticipant.id;
        console.log("   📝 checkin_id dos participantes:", checkinId);

        if (checkinId && userParticipant.checkins > 0) {
          setUserCheckinId(checkinId);
          return true;
        } else {
          console.log(
            "   ⚠️ Participante encontrado mas sem checkin_id válido ou sem checkins",
          );
        }
      }
    }

    console.log("   ❌ Usuário não encontrado - sem check-in");
    setUserCheckinId(null);
    return false;
  };

  const openParticipants = async (turma: any) => {
    if (!turma?.id) return;
    setParticipantsLoading(true);
    setParticipants([]);
    setCheckinsRecentes([]);
    setResumoTurma(null);
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
          "Content-Type": "application/json",
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
      setResumoTurma(resumoData);
      setAlunosTotal(alunosCount);
      setParticipantsTurma(mergeTurmaFromList(turmaApi.id, turmaApi));
    } catch (error) {
      console.error("Erro participantes:", error);
      showToast("Não foi possível carregar participantes", "error");
    } finally {
      setParticipantsLoading(false);
    }
  };

  // Helpers para cálculo de disponibilidade por horário
  const sameDay = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();

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

  const schedulesToRender = showOnlyAvailable
    ? availableSchedules.filter((turma) => !isCheckinDisabled(turma))
    : availableSchedules;

  return (
    <>
      <SafeAreaView style={styles.container} edges={["top"]}>
        {/* Header com Botão Recarregar */}
        <View style={styles.headerTop}>
          <Text style={styles.headerTitle}>Checkin</Text>
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
                <View style={styles.participantsWrapper}>
                  <View style={styles.participantsHeaderRow}>
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
                    <View style={{ flex: 1 }}>
                      <Text style={styles.participantsTitle}>
                        {normalizeUtf8(participantsTurma.nome || "Turma")}
                      </Text>
                      <Text style={styles.participantsSubtitle}>
                        {participantsTurma.professor?.nome
                          ? `Professor: ${normalizeUtf8(participantsTurma.professor.nome)}`
                          : participantsTurma.professor
                            ? `Professor: ${normalizeUtf8(String(participantsTurma.professor))}`
                            : ""}
                      </Text>
                    </View>
                  </View>

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
                    <View style={styles.metaChip}>
                      <Feather
                        name="users"
                        size={14}
                        color={
                          participantsTurma?.modalidade?.cor || colors.primary
                        }
                      />
                      <Text style={styles.metaChipText}>
                        {participantsCount}/
                        {participantsTurma.limite_alunos || "--"} inscritos
                      </Text>
                    </View>
                  </View>

                  <View style={styles.participantsContent}>
                    {participantsLoading ? (
                      <Text style={styles.loadingText}>Carregando...</Text>
                    ) : (
                      <>
                        {participantsToShow.length > 0 ? (
                          <View style={styles.participantsListContainer}>
                            {participantsToShow.map((p, idx) => {
                              const isCurrentUser =
                                currentUserId &&
                                Number(p.usuario_id) === Number(currentUserId);
                              const photoUrl = getUserPhotoUrl(p.foto_caminho);
                              return (
                                <View
                                  key={p.usuario_id || p.checkin_id || idx}
                                  style={styles.participantItem}
                                >
                                  <View
                                    style={[
                                      styles.participantAvatar,
                                      isCurrentUser &&
                                        styles.participantAvatarCurrent,
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
                                        p.nome || p.usuario_nome || "Aluno",
                                      ).toUpperCase()}
                                      {isCurrentUser && " (Você)"}
                                    </Text>
                                  </View>
                                </View>
                              );
                            })}
                          </View>
                        ) : (
                          <Text style={styles.loadingText}>
                            Nenhum participante ainda
                          </Text>
                        )}
                      </>
                    )}
                  </View>

                  <TouchableOpacity
                    style={[
                      styles.checkinButton,
                      {
                        backgroundColor:
                          participantsTurma?.modalidade?.cor || colors.primary,
                      },
                      {
                        shadowColor:
                          participantsTurma?.modalidade?.cor || colors.primary,
                      },
                      userCheckinId ? styles.checkinButtonUndo : null,
                      (checkinLoading ||
                        (!userCheckinId &&
                          isCheckinDisabled(participantsTurma))) &&
                        styles.checkinButtonDisabled,
                    ]}
                    onPress={() => {
                      if (userCheckinId) {
                        handleUndoCheckin(userCheckinId, participantsTurma);
                      } else {
                        handleCheckin(participantsTurma);
                      }
                    }}
                    disabled={
                      checkinLoading ||
                      (!userCheckinId && isCheckinDisabled(participantsTurma))
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
                        <Feather name="rotate-ccw" size={18} color="#fff" />
                        <Text style={styles.checkinButtonText}>
                          Desfazer Check-in
                        </Text>
                      </>
                    ) : (
                      <>
                        <Feather name="check-circle" size={18} color="#fff" />
                        <Text style={styles.checkinButtonText}>
                          Fazer Check-in
                        </Text>
                      </>
                    )}
                  </TouchableOpacity>
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
  participantsHeaderRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
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
  participantsSubtitle: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 2,
  },
  participantsListContainer: {
    borderTopWidth: 1,
    borderTopColor: "#eef2f7",
    paddingTop: 8,
    gap: 4,
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
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: "#eef2f7",
    gap: 12,
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
  modalButtonText: {
    color: "#fff",
    fontSize: 17,
    fontWeight: "700",
  },
});
