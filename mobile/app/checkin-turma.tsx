import { AlunoResumoFinanceiro } from "@/src/components/AlunoResumoFinanceiro";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useFocusEffect, useLocalSearchParams, useRouter } from "expo-router";
import React, { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Animated,
  Image,
  Modal,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import BirthdayBadge from "../src/components/BirthdayBadge";
import { colors } from "../src/theme/colors";
import { normalizeUtf8 } from "../src/utils/utf8";
import { weekdayAbbrev } from "../src/utils/weekdayAbbrev";

const getRouteParam = (value?: string | string[]) => {
  if (value == null) return undefined;
  return Array.isArray(value) ? value[0] : value;
};

const isAniversariante = (
  alunoId: number | string | undefined,
  flags?: { aniversario_hoje?: boolean },
  participantsList?: any[],
): boolean => {
  if (flags?.aniversario_hoje) return true;
  if (!alunoId || !participantsList?.length) return false;
  const p = participantsList.find(
    (item) => Number(item.aluno_id) === Number(alunoId),
  );
  return !!p?.aniversario_hoje;
};

const formatDateParam = (date: Date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

/** Evita que YYYY-MM-DD vire dia anterior no fuso ao usar new Date(string). */
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

const startOfLocalDay = (date: Date) => {
  const d = new Date(date);
  d.setHours(0, 0, 0, 0);
  return d;
};

const cleanTurmaName = (
  nome?: string,
  modalidade?: any,
  professor?: any,
) => {
  let base = nome ? normalizeUtf8(nome) : "";
  base = base
    .replace(/\s*-?\s?\d{1,2}:\d{2}(\s*-\s*\d{1,2}:\d{2})?/g, "")
    .trim();

  const modalidadeNome = modalidade?.nome
    ? normalizeUtf8(modalidade.nome)
    : "";
  if (
    modalidadeNome &&
    base.toLowerCase().includes(modalidadeNome.toLowerCase())
  ) {
    base = base.replace(new RegExp(modalidadeNome, "gi"), "").trim();
  }

  const profNome = professor?.nome
    ? normalizeUtf8(professor.nome)
    : typeof professor === "string"
      ? normalizeUtf8(professor)
      : "";
  if (profNome && base.toLowerCase().includes(profNome.toLowerCase())) {
    base = base.replace(new RegExp(profNome, "gi"), "").trim();
  }

  base = base
    .replace(/^\s*-\s*|\s*-\s*$/g, "")
    .replace(/\s+-\s+/g, " ")
    .trim();

  if (base.length > 0) return base;
  return modalidadeNome || "Turma";
};

const getHeaderTitle = (turma: any | null) => {
  if (!turma) return "Turma";
  const modalidadeNome = normalizeUtf8(
    String(turma?.modalidade?.nome || ""),
  ).trim();
  const professorRaw = turma?.professor;
  const professorNome = normalizeUtf8(
    String(
      (professorRaw &&
        typeof professorRaw === "object" &&
        professorRaw.nome) ||
        (typeof professorRaw === "string" ? professorRaw : ""),
    ),
  ).trim();
  if (modalidadeNome && professorNome) {
    return `${modalidadeNome} - ${professorNome}`;
  }
  if (modalidadeNome) return modalidadeNome;
  if (professorNome) return professorNome;
  return cleanTurmaName(turma?.nome, turma?.modalidade, turma?.professor);
};

export default function CheckinTurmaScreen() {
  const router = useRouter();
  const routeParams = useLocalSearchParams<{
    turmaId?: string | string[];
    data?: string | string[];
  }>();
  const routeTurmaId = getRouteParam(routeParams.turmaId);
  const routeData = getRouteParam(routeParams.data);

  const [selectedDate, setSelectedDate] = useState<Date>(() => {
    const parsed = parseDataParam(routeData);
    return parsed ?? new Date();
  });

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
  const [errorModal, setErrorModal] = useState<{
    visible: boolean;
    title: string;
    message: string;
    type: "error" | "warning" | "success";
    limite?: {
      plano?: string;
      checkins_semanais?: number;
      limite_mensal?: number;
      checkins_mes?: number;
      bonus_cinco_semanas?: boolean;
      mes_referencia?: string;
      dias_checkin?: {
        data: string;
        horario?: string | null;
        modalidade?: string | null;
        status?: string;
        registrado_por_admin?: boolean;
      }[];
    } | null;
  }>({
    visible: false,
    title: "",
    message: "",
    type: "error",
    limite: null,
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
  const [confirmBloqueioModal, setConfirmBloqueioModal] = useState(false);
  const bloqueioModalScale = useRef(new Animated.Value(0)).current;

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

  const getUserPhotoUrl = (fotoCaminho?: string | null) => {
    if (!fotoCaminho) return null;
    if (/^https?:\/\//i.test(fotoCaminho)) return fotoCaminho;
    return `${getApiUrlRuntime()}${fotoCaminho}`;
  };

  const formatParticipantName = (name?: string | null) => {
    const base = normalizeUtf8(String(name || "")).trim();
    if (!base) return "Aluno";
    const parts = base.split(/\s+/);
    if (parts.length <= 2) return parts.join(" ");
    if (parts[1].length <= 2) return parts.slice(0, 3).join(" ");
    return parts.slice(0, 2).join(" ");
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
    limite: typeof errorModal.limite = null,
  ) => {
    const msg = normalizeUtf8(String(message || ""));
    const title =
      type === "error" ? "Erro!" : type === "warning" ? "Atenção!" : "Sucesso!";

    setErrorModal({
      visible: true,
      title,
      message: msg,
      type,
      limite: limite ?? null,
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
      setErrorModal({
        visible: false,
        title: "",
        message: "",
        type: "error",
        limite: null,
      });
    });
  };

  const formatDiaCheckin = (data: string) => {
    const partes = String(data || "").split("-");
    if (partes.length !== 3) return data;
    const [ano, mes, dia] = partes;
    const dt = new Date(Number(ano), Number(mes) - 1, Number(dia));
    const semana = weekdayAbbrev(dt);
    return `${semana} ${dia}/${mes}`;
  };

  const goBackToCheckin = useCallback(() => {
    router.replace(
      `/(tabs)/checkin?data=${formatDateParam(selectedDate)}` as any,
    );
  }, [router, selectedDate]);

  const loadCurrentUserId = async () => {
    try {
      const userStr = await AsyncStorage.getItem("@appcheckin:user");
      if (userStr) {
        const user = JSON.parse(userStr);
        setCurrentUserId(user.id);
        setCurrentAlunoId(user.aluno_id || null);
        setCurrentUserEmail(user.email || null);
        setCurrentUserPapeis(Array.isArray(user.papeis) ? user.papeis : []);
        const papelIdNumero = user.papel_id ? Number(user.papel_id) : null;
        setCurrentUserPapelId(papelIdNumero);
      }
    } catch (error) {
      console.error("Erro ao carregar ID do usuário:", error);
    }
  };

  useEffect(() => {
    loadCurrentUserId();
    return () => {
      if (toastTimer.current) {
        clearTimeout(toastTimer.current);
      }
    };
  }, []);

  useEffect(() => {
    if (!routeData) return;
    const parsed = parseDataParam(routeData);
    if (!parsed) return;
    const routeKey = formatDateParam(parsed);
    const currentKey = formatDateParam(selectedDate);
    if (routeKey !== currentKey) {
      setSelectedDate(parsed);
    }
  }, [routeData]); // eslint-disable-line react-hooks/exhaustive-deps

  const loadParticipantsForTurma = async (turma: any) => {
    if (!turma?.id) return;
    setParticipantsLoading(true);
    setParticipants([]);
    setCheckinsRecentes([]);
    setAlunosTotal(0);
    setManualSearchQuery("");
    setManualSearchResults([]);
    setManualSearchError(null);
    setParticipantsTurma(turma);

    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showToast("Token não encontrado", "error");
        return;
      }

      const dataParam = formatDateParam(selectedDate);
      const url = `${getApiUrlRuntime()}/mobile/turma/${turma.id}/detalhes?data=${dataParam}`;
      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      const text = await response.text();
      if (!response.ok) {
        console.error("Erro ao carregar participantes:", response.status, text);
        showToast("Falha ao carregar participantes", "error");
        return;
      }

      const data = JSON.parse(text);
      const payload = data.data || data;
      const turmaApi = payload.turma || turma;
      const alunosLista = payload.alunos?.lista || payload.participantes || [];
      const checkinsLista = payload.checkins_recentes?.lista || [];
      const alunosCount =
        payload.alunos?.total ??
        alunosLista.length ??
        turmaApi.alunos_inscritos ??
        0;

      setParticipants(alunosLista);
      setCheckinsRecentes(checkinsLista);
      setAlunosTotal(alunosCount);
      setParticipantsTurma({
        ...turma,
        ...turmaApi,
        professor: turmaApi.professor ?? turma.professor,
        modalidade: turmaApi.modalidade ?? turma.modalidade,
        horario: turmaApi.horario ?? turma.horario,
        checkin: turmaApi.checkin ?? turma.checkin,
      });

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

  const reloadTurma = useCallback(() => {
    if (!routeTurmaId) return;
    void loadParticipantsForTurma({ id: routeTurmaId });
  }, [routeTurmaId, selectedDate]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!routeTurmaId) return;
    void loadParticipantsForTurma({ id: routeTurmaId });
  }, [routeTurmaId, selectedDate]); // eslint-disable-line react-hooks/exhaustive-deps

  useFocusEffect(
    useCallback(() => {
      if (routeTurmaId) {
        reloadTurma();
      }
    }, [routeTurmaId, reloadTurma]),
  );

  const checkUserHasCheckin = useCallback(() => {
    if (!currentUserId && !currentAlunoId && !currentUserEmail) {
      setUserCheckinId(null);
      return false;
    }

    if (participants.length > 0 && currentUserEmail) {
      const userByEmail = participants.find(
        (p) =>
          p.email &&
          currentUserEmail &&
          p.email.toLowerCase() === currentUserEmail.toLowerCase(),
      );

      if (userByEmail && userByEmail.checkins > 0) {
        if (checkinsRecentes.length > 0) {
          const checkin = checkinsRecentes.find(
            (c) => Number(c.aluno_id) === Number(userByEmail.aluno_id),
          );
          if (checkin) {
            const checkinId = checkin.checkin_id || checkin.id;
            setUserCheckinId(checkinId);
            return true;
          }
        }
      }
    }

    if (checkinsRecentes.length > 0 && currentAlunoId) {
      const userCheckin = checkinsRecentes.find(
        (c) => Number(c.aluno_id) === Number(currentAlunoId),
      );

      if (userCheckin) {
        const checkinId = userCheckin.checkin_id || userCheckin.id;
        setUserCheckinId(checkinId);
        return true;
      }
    }

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
        setUserCheckinId(checkinId);
        return true;
      }
    }

    if (checkinsRecentes.length > 0 && currentUserId) {
      const userByIdCheckin = checkinsRecentes.find(
        (c) => Number(c.usuario_id) === Number(currentUserId),
      );
      if (userByIdCheckin) {
        const checkinId = userByIdCheckin.checkin_id || userByIdCheckin.id;
        setUserCheckinId(checkinId);
        return true;
      }
    }

    if (participants.length > 0 && currentAlunoId) {
      const userParticipant = participants.find(
        (p) => Number(p.aluno_id) === Number(currentAlunoId),
      );

      if (userParticipant && userParticipant.checkins > 0) {
        if (checkinsRecentes.length > 0) {
          const checkin = checkinsRecentes.find(
            (c) => Number(c.aluno_id) === Number(userParticipant.aluno_id),
          );
          if (checkin) {
            const checkinId = checkin.checkin_id || checkin.id;
            setUserCheckinId(checkinId);
            return true;
          }
        }
      }
    }

    setUserCheckinId(null);
    return false;
  }, [
    participants,
    checkinsRecentes,
    currentUserId,
    currentAlunoId,
    currentUserEmail,
  ]);

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

  const combineDateTime = (date: Date, timeHHMMSS: string) => {
    const [hh, mm, ss] = (timeHHMMSS || "00:00:00")
      .split(":")
      .map((n) => parseInt(n, 10) || 0);
    const d = new Date(date);
    d.setHours(hh, mm, ss || 0, 0);
    return d;
  };

  const getHoraInicio = (turma: any) =>
    turma?.hora_inicio || turma?.horario?.inicio;
  const getHoraFim = (turma: any) => turma?.hora_fim || turma?.horario?.fim;

  /** Aula em dia anterior ao calendário (bloqueio só neste caso). */
  const isAulaEmDataPassada = (refDate: Date = selectedDate): boolean => {
    const today = startOfLocalDay(new Date());
    const ref = startOfLocalDay(refDate);
    return ref.getTime() < today.getTime();
  };

  /** Check-in: dia passado ou horário de fim já passou no dia da aula. */
  const isTurmaDisabled = (
    turma: any,
    refDate: Date = selectedDate,
  ): boolean => {
    try {
      const now = new Date();
      const today = startOfLocalDay(new Date());
      const ref = startOfLocalDay(refDate);

      if (ref.getTime() < today.getTime()) return true;
      if (ref.getTime() > today.getTime()) return false;

      const horaFim = getHoraFim(turma);
      if (!horaFim) return false;
      const end = combineDateTime(refDate, horaFim);
      return now > end;
    } catch (e) {
      console.warn("Falha ao calcular disponibilidade da turma:", e);
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

  const getHoraLimiteCheckin = (turma: any): string | null => {
    try {
      const fechamento: string | undefined = turma?.checkin?.fechamento;
      if (!fechamento) return null;
      const timePart = fechamento.includes(" ")
        ? fechamento.split(" ")[1]
        : fechamento;
      return timePart?.slice(0, 5) || null;
    } catch {
      return null;
    }
  };

  const getDataAberturaCheckin = (turma: any): Date | null => {
    try {
      const abertura: string | undefined = turma?.checkin?.abertura;
      if (!abertura) return null;
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
          `Check-in realizado para ${normalizeUtf8(turma.nome || "Turma")}`,
          "success",
        );
        await loadParticipantsForTurma(participantsTurma || turma);
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

    setCheckinLoading(true);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showErrorModal("Token não encontrado. Faça login novamente.", "error");
        return;
      }

      const url = `${getApiUrlRuntime()}/mobile/checkin/${checkinId}/desfazer`;

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
        showErrorModal(normalizeUtf8(String(apiMessage)), "error");
        return;
      }

      showErrorModal(`Check-in desfeito com sucesso`, "warning");
      setUserCheckinId(null);
      await loadParticipantsForTurma(participantsTurma || turma);
    } catch (error) {
      console.error("Erro ao desfazer check-in:", error);
      showErrorModal("Falha ao desfazer o check-in.", "error");
    } finally {
      setCheckinLoading(false);
    }
  };

  const togglePresenca = (checkinId: number) => {
    setPresencas((prev) => {
      const atual = prev[checkinId];
      let novo: boolean | null;
      if (atual === null || atual === undefined) {
        novo = true;
      } else if (atual === true) {
        novo = false;
      } else {
        novo = null;
      }
      return { ...prev, [checkinId]: novo };
    });
  };

  const confirmarPresencas = async () => {
    if (!participantsTurma?.id) return;

    const presencasParaEnviar = Object.entries(presencas)
      .filter(([_, valor]) => valor !== null)
      .map(([checkinId, presente]) => ({
        checkin_id: Number(checkinId),
        presente: presente as boolean,
      }));

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
        showErrorModal(normalizeUtf8(String(apiMessage)), "error");
        return;
      }

      const msg = data?.message || "Presenças confirmadas com sucesso!";
      showErrorModal(normalizeUtf8(msg), "success");
      await loadParticipantsForTurma(participantsTurma);
    } catch (error) {
      console.error("Erro ao confirmar presenças:", error);
      showErrorModal("Falha ao confirmar as presenças.", "error");
    } finally {
      setConfirmandoPresenca(false);
    }
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
        // Mostra o box de limite sempre que vier o resumo do limite mensal
        // (identificado por limite_mensal). A lista de dias é opcional — o render
        // já trata dias_checkin ausente/malformado. Não confundir com outros
        // erros que trazem 'detalhes' de formato diferente (ex.: limite diário).
        const limiteDetalhes =
          data?.detalhes && data.detalhes.limite_mensal !== undefined
            ? data.detalhes
            : null;
        showErrorModal(
          normalizeUtf8(String(apiMessage)),
          "warning",
          limiteDetalhes,
        );
        return;
      }

      showErrorModal(
        normalizeUtf8(data?.message || "Check-in manual realizado."),
        "success",
      );
      await loadParticipantsForTurma(participantsTurma);
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

  const openConfirmBloqueioModal = () => {
    setConfirmBloqueioModal(true);
    bloqueioModalScale.setValue(0);
    Animated.spring(bloqueioModalScale, {
      toValue: 1,
      useNativeDriver: true,
      tension: 50,
      friction: 7,
    }).start();
  };

  const closeConfirmBloqueioModal = () => {
    Animated.timing(bloqueioModalScale, {
      toValue: 0,
      duration: 200,
      useNativeDriver: true,
    }).start(() => setConfirmBloqueioModal(false));
  };

  const executarBloqueioCheckinTurma = async (bloquear: boolean) => {
    if (!participantsTurma?.id || !isProfessorOuAdmin) return;

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

      if (bloquear) {
        setCheckinsRecentes([]);
        setPresencas({});
        setParticipants((prev) =>
          prev.map((p) => ({
            ...p,
            checkins: 0,
            checkin_id: null,
          })),
        );
      }

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

  const bloqueioAulaDesabilitado =
    !!participantsTurma && isAulaEmDataPassada(selectedDate);

  const onPressBloqueioCheckin = () => {
    if (!participantsTurma?.id || !isProfessorOuAdmin || bloqueioAulaDesabilitado) {
      return;
    }

    if (participantsTurma.checkin_bloqueado) {
      void executarBloqueioCheckinTurma(false);
      return;
    }

    openConfirmBloqueioModal();
  };

  const navigateToCheckinDetalhes = (c: any) => {
    const horarioInicio = getHoraInicio(participantsTurma)?.slice(0, 5) || "";
    const horarioFim = getHoraFim(participantsTurma)?.slice(0, 5) || "";
    const alunoFoto = participants.find(
      (p) => Number(p.aluno_id) === Number(c.aluno_id),
    )?.foto_caminho;

    router.push({
      pathname: "/checkin-detalhes",
      params: {
        checkinId: String(c.checkin_id || c.id || ""),
        alunoId: String(c.aluno_id || ""),
        alunoNome: normalizeUtf8(c.usuario_nome || "Aluno"),
        foto: c.foto_caminho || alunoFoto || "",
        presente: String(c.presente ?? ""),
        presencaConfirmadaEm: c.presenca_confirmada_em || "",
        dataCheckin: c.data_checkin || c.created_at || "",
        turmaId: String(participantsTurma?.id || ""),
        turmaNome: normalizeUtf8(participantsTurma?.nome || "Turma"),
        dataAula: selectedDate ? new Date(selectedDate).toISOString() : "",
        horario:
          horarioInicio && horarioFim
            ? `${horarioInicio} - ${horarioFim}`
            : "",
        showResumoFinanceiro: isProfessorOuAdmin ? "1" : "0",
      },
    });
  };

  if (!routeTurmaId) {
    return (
      <SafeAreaView style={styles.container} edges={["top"]}>
        <View style={[styles.headerTop, styles.headerTopDetailed]}>
          <View style={styles.headerTopRow}>
            <View style={styles.headerLeft}>
              <TouchableOpacity
                style={styles.headerBackButton}
                onPress={goBackToCheckin}
              >
                <Feather name="arrow-left" size={18} color="#fff" />
              </TouchableOpacity>
              <Text style={styles.headerTitle}>Turma</Text>
            </View>
          </View>
        </View>
        <View style={styles.loadingContainer}>
          <Text style={styles.loadingText}>Turma não informada</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <>
      <SafeAreaView style={styles.container} edges={["top"]}>
        <View style={[styles.headerTop, styles.headerTopDetailed]}>
          <View style={styles.headerTopRow}>
            <View style={styles.headerLeft}>
              <TouchableOpacity
                style={styles.headerBackButton}
                onPress={goBackToCheckin}
              >
                <Feather name="arrow-left" size={18} color="#fff" />
              </TouchableOpacity>
              <View
                style={[
                  styles.headerIconCircle,
                  {
                    backgroundColor: `${participantsTurma?.modalidade?.cor || colors.primary}35`,
                  },
                ]}
              >
                {participantsTurma?.modalidade?.icone ? (
                  <MaterialCommunityIcons
                    name={participantsTurma.modalidade.icone as any}
                    size={18}
                    color="#fff"
                  />
                ) : (
                  <Feather name="activity" size={18} color="#fff" />
                )}
              </View>
              <View style={styles.headerTextBlock}>
                <Text style={styles.headerTitle} numberOfLines={2}>
                  {getHeaderTitle(participantsTurma)}
                </Text>
              </View>
            </View>
          </View>

          {participantsTurma ? (
            <View style={styles.headerMetaRow}>
              <View style={styles.headerChipsWrap}>
                <View style={styles.headerChip}>
                  <Feather name="clock" size={14} color="#fff" />
                  <Text style={styles.headerChipText}>
                    {getHoraInicio(participantsTurma)?.slice(0, 5)} -{" "}
                    {getHoraFim(participantsTurma)?.slice(0, 5)}
                  </Text>
                </View>
                {!participantsTurma.checkin_bloqueado &&
                getHoraLimiteCheckin(participantsTurma) ? (
                  <View style={styles.headerChip}>
                    <Feather name="clock" size={14} color="#fff" />
                    <Text style={styles.headerChipText}>
                      Check-in até {getHoraLimiteCheckin(participantsTurma)}
                    </Text>
                  </View>
                ) : null}
                {participantsTurma.checkin_bloqueado ? (
                  <View style={[styles.headerChip, styles.headerChipBloqueado]}>
                    <MaterialCommunityIcons
                      name="lock"
                      size={14}
                      color="#fff"
                    />
                    <Text style={styles.headerChipText}>
                      Check-in bloqueado
                    </Text>
                  </View>
                ) : null}
              </View>
              {isProfessorOuAdmin ? (
                <TouchableOpacity
                  style={[
                    styles.bloqueioCheckinButtonCompact,
                    participantsTurma.checkin_bloqueado
                      ? styles.bloqueioCheckinButtonCompactLiberar
                      : styles.bloqueioCheckinButtonCompactBloquear,
                    bloqueioAulaDesabilitado &&
                      styles.bloqueioCheckinButtonCompactDisabled,
                  ]}
                  onPress={onPressBloqueioCheckin}
                  disabled={
                    turmaCheckinBloqueioLoading || bloqueioAulaDesabilitado
                  }
                  activeOpacity={bloqueioAulaDesabilitado ? 1 : 0.85}
                >
                  {turmaCheckinBloqueioLoading ? (
                    <ActivityIndicator
                      size="small"
                      color={
                        participantsTurma.checkin_bloqueado ? "#047857" : "#fff"
                      }
                    />
                  ) : (
                    <>
                      <MaterialCommunityIcons
                        name={
                          participantsTurma.checkin_bloqueado
                            ? "lock-open-variant"
                            : "lock"
                        }
                        size={18}
                        color={
                          bloqueioAulaDesabilitado
                            ? "#9ca3af"
                            : participantsTurma.checkin_bloqueado
                              ? "#047857"
                              : "#fff"
                        }
                      />
                      <Text
                        style={[
                          styles.bloqueioCheckinButtonCompactText,
                          participantsTurma.checkin_bloqueado &&
                            styles.bloqueioCheckinButtonCompactTextLiberar,
                          bloqueioAulaDesabilitado &&
                            styles.bloqueioCheckinButtonCompactTextDisabled,
                        ]}
                      >
                        {participantsTurma.checkin_bloqueado
                          ? "Liberar"
                          : "Bloquear"}
                      </Text>
                    </>
                  )}
                </TouchableOpacity>
              ) : null}
            </View>
          ) : null}
        </View>

        <ScrollView
          contentContainerStyle={[styles.scrollContent, styles.scrollGrow]}
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.schedulesSection}>
            <View style={styles.participantsWrapper}>
              <View style={styles.participantsContent}>
                {participantsLoading ? (
                  <View style={styles.loadingContainer}>
                    <ActivityIndicator size="large" color={colors.primary} />
                    <Text style={styles.loadingText}>Carregando...</Text>
                  </View>
                ) : (
                  <>
                    {isProfessorOuAdmin && !participantsTurma?.checkin_bloqueado && (
                      <View style={styles.manualCheckinBox}>
                        <Text style={styles.manualCheckinTitle}>
                          Adicionar aluno ao check-in
                        </Text>
                        <View style={styles.manualCheckinRow}>
                          <TextInput
                            style={styles.manualCheckinInput}
                            placeholder="Nome, CPF ou e-mail"
                            placeholderTextColor="#9ca3af"
                            value={manualSearchQuery}
                            onChangeText={(text) => {
                              setManualSearchQuery(text);
                              if (manualSearchError) {
                                setManualSearchError(null);
                              }
                              if (!text.trim()) {
                                setManualSearchResults([]);
                              }
                            }}
                            autoCapitalize="none"
                            autoCorrect={false}
                            returnKeyType="search"
                            onSubmitEditing={handleManualSearch}
                          />
                          {!!manualSearchQuery.trim() && (
                            <TouchableOpacity
                              style={styles.manualCheckinClearButton}
                              onPress={() => {
                                setManualSearchQuery("");
                                setManualSearchResults([]);
                                setManualSearchError(null);
                              }}
                            >
                              <Feather name="x" size={16} color="#6b7280" />
                            </TouchableOpacity>
                          )}
                          <TouchableOpacity
                            style={[
                              styles.manualCheckinSearchButton,
                              (!manualSearchQuery.trim() ||
                                manualSearchLoading) &&
                                styles.manualCheckinSearchButtonDisabled,
                            ]}
                            onPress={handleManualSearch}
                            disabled={
                              !manualSearchQuery.trim() || manualSearchLoading
                            }
                          >
                            <Feather name="search" size={16} color="#fff" />
                          </TouchableOpacity>
                        </View>
                        {!!manualSearchError && (
                          <Text style={styles.manualCheckinError}>
                            {manualSearchError}
                          </Text>
                        )}
                        {manualSearchLoading ? (
                          <View style={styles.inlineLoadingRow}>
                            <ActivityIndicator
                              size="small"
                              color={colors.primary}
                            />
                            <Text style={styles.loadingText}>Buscando...</Text>
                          </View>
                        ) : null}
                        {manualSearchResults.length > 0 && (
                          <View style={styles.manualCheckinResults}>
                            {manualSearchResults.map((aluno, idx) => {
                              const alunoId = Number(
                                aluno.id ?? aluno.aluno_id ?? 0,
                              );
                              const alreadyCheckedIn =
                                checkinsRecentes.some(
                                  (c) =>
                                    Number(c.aluno_id) === Number(alunoId),
                                ) ||
                                participants.some(
                                  (p) =>
                                    Number(p.aluno_id) === Number(alunoId) &&
                                    Number(p.checkins) > 0,
                                );
                              const photoUrl = getUserPhotoUrl(
                                aluno.foto_caminho || null,
                              );
                              const isLoading =
                                !!manualCheckinLoading[alunoId];

                              return (
                                <View
                                  key={alunoId || idx}
                                  style={styles.manualCheckinItem}
                                >
                                  <View style={styles.manualCheckinAvatar}>
                                    {photoUrl ? (
                                      <Image
                                        source={{ uri: photoUrl }}
                                        style={styles.manualCheckinAvatarImage}
                                      />
                                    ) : (
                                      <Feather
                                        name="user"
                                        size={18}
                                        color="#9ca3af"
                                      />
                                    )}
                                  </View>
                                  <View style={styles.manualCheckinItemInfo}>
                                    <View style={styles.participantNameRow}>
                                      <Text style={styles.manualCheckinItemName}>
                                        {normalizeUtf8(
                                          aluno.nome ||
                                            aluno.usuario_nome ||
                                            "Aluno",
                                        )}
                                      </Text>
                                      <BirthdayBadge
                                        show={!!aluno.aniversario_hoje}
                                      />
                                    </View>
                                    {!!aluno.email && (
                                      <Text
                                        style={styles.manualCheckinItemMeta}
                                      >
                                        {normalizeUtf8(aluno.email)}
                                      </Text>
                                    )}
                                  </View>
                                  <TouchableOpacity
                                    style={[
                                      styles.manualCheckinAddButton,
                                      (alreadyCheckedIn || isLoading) &&
                                        styles.manualCheckinAddButtonDisabled,
                                    ]}
                                    onPress={() =>
                                      openManualConfirm({
                                        ...aluno,
                                        id: alunoId || aluno.id,
                                      })
                                    }
                                    disabled={alreadyCheckedIn || isLoading}
                                  >
                                    <Text
                                      style={styles.manualCheckinAddButtonText}
                                    >
                                      {alreadyCheckedIn
                                        ? "Já incluído"
                                        : isLoading
                                          ? "Incluindo..."
                                          : "Adicionar"}
                                    </Text>
                                  </TouchableOpacity>
                                </View>
                              );
                            })}
                          </View>
                        )}
                      </View>
                    )}

                    {checkinsRecentes.length > 0 && (
                      <View style={styles.participantsListContainer}>
                        {checkinsRecentes.map((c, idx) => {
                          const presencaAtual = presencas[c.checkin_id];
                          const alunoFoto = participants.find(
                            (p) =>
                              Number(p.aluno_id) === Number(c.aluno_id),
                          )?.foto_caminho;
                          const photoUrl = getUserPhotoUrl(
                            c.foto_caminho || alunoFoto || null,
                          );
                          const isConfirmed = Boolean(
                            c.presenca_confirmada_em,
                          );
                          let statusBorderColor = "#9ca3af";
                          if (isConfirmed) {
                            statusBorderColor = c.presente
                              ? "#10b981"
                              : "#ef4444";
                          }
                          return (
                            <TouchableOpacity
                              key={c.checkin_id || idx}
                              style={styles.participantItem}
                              activeOpacity={0.7}
                              onPress={() => navigateToCheckinDetalhes(c)}
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
                                <View style={styles.participantNameRow}>
                                  <Text style={styles.participantName}>
                                    {formatParticipantName(
                                      c.usuario_nome || "Aluno",
                                    ).toUpperCase()}
                                  </Text>
                                  <BirthdayBadge
                                    show={isAniversariante(
                                      c.aluno_id,
                                      c,
                                      participants,
                                    )}
                                  />
                                </View>
                              </View>
                              {isProfessorOuAdmin && (
                                <View style={styles.presencaButtons}>
                                  <TouchableOpacity
                                    style={[
                                      styles.presencaBtn,
                                      presencaAtual === true &&
                                        styles.presencaBtnPresente,
                                    ]}
                                    onPress={() => togglePresenca(c.checkin_id)}
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
                            </TouchableOpacity>
                          );
                        })}
                      </View>
                    )}

                    {(!isProfessorOuAdmin ||
                      checkinsRecentes.length === 0) &&
                      participants.length === 0 &&
                      manualSearchResults.length === 0 &&
                      !manualSearchLoading && (
                        <Text style={styles.loadingText}>
                          Nenhum participante ainda
                        </Text>
                      )}
                  </>
                )}
              </View>

              {isProfessorOuAdmin && checkinsRecentes.length > 0 && (
                <TouchableOpacity
                  style={[
                    styles.checkinButton,
                    { backgroundColor: "#3b82f6" },
                    confirmandoPresenca && styles.checkinButtonDisabled,
                  ]}
                  onPress={confirmarPresencas}
                  disabled={confirmandoPresenca}
                >
                  {confirmandoPresenca ? (
                    <>
                      <ActivityIndicator size="small" color="#fff" />
                      <Text style={styles.checkinButtonText}>
                        Confirmando...
                      </Text>
                    </>
                  ) : (
                    <>
                      <Feather name="check-square" size={18} color="#fff" />
                      <Text style={styles.checkinButtonText}>
                        Confirmar Presenças
                      </Text>
                    </>
                  )}
                </TouchableOpacity>
              )}

              {isAluno &&
                !isProfessorOuAdmin &&
                participantsTurma &&
                (() => {
                  const minutosParaAbrir =
                    getMinutosParaAbrirCheckin(participantsTurma);
                  const checkinNaoAbriu = minutosParaAbrir > 0;
                  const checkinBloqueado =
                    !!participantsTurma.checkin_bloqueado && !userCheckinId;

                  if (checkinBloqueado) {
                    return (
                      <View style={styles.checkinBlockedBanner}>
                        <MaterialCommunityIcons
                          name="lock"
                          size={20}
                          color="#B91C1C"
                        />
                        <Text style={styles.checkinBlockedBannerText}>
                          Check-in bloqueado para esta aula
                        </Text>
                      </View>
                    );
                  }

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
                                  ? `Abre em ${formatMinutos(minutosParaAbrir)}`
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
                            <ActivityIndicator size="small" color="#fff" />
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
            </View>
          </View>
        </ScrollView>
      </SafeAreaView>

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
              { transform: [{ scale: modalScale }] },
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
                <Text style={styles.modalTitle}>{errorModal.title}</Text>
                <Text style={styles.modalMessage}>{errorModal.message}</Text>

                {errorModal.limite ? (
                  <View style={styles.limiteBox}>
                    {!!errorModal.limite.plano && (
                      <View style={styles.limiteHeaderRow}>
                        <Feather name="award" size={16} color="#92400e" />
                        <Text style={styles.limitePlano}>
                          {normalizeUtf8(errorModal.limite.plano)}
                        </Text>
                      </View>
                    )}

                    <Text style={styles.limiteResumo}>
                      {errorModal.limite.checkins_mes ?? 0} de{" "}
                      {errorModal.limite.limite_mensal ?? 0} check-ins
                      {errorModal.limite.mes_referencia
                        ? ` no ciclo ${errorModal.limite.mes_referencia}`
                        : " neste ciclo"}
                    </Text>
                    {errorModal.limite.bonus_cinco_semanas && (
                      <Text style={styles.limiteBonus}>
                        Inclui +1 bônus (mês com 5 semanas)
                      </Text>
                    )}

                    {Array.isArray(errorModal.limite.dias_checkin) &&
                      errorModal.limite.dias_checkin.length > 0 && (
                        <View style={styles.limiteDiasList}>
                          {errorModal.limite.dias_checkin.map((d, idx) => (
                            <View
                              key={`${d.data}-${idx}`}
                              style={styles.limiteDiaRow}
                            >
                              <Feather
                                name={
                                  d.status === "pendente"
                                    ? "clock"
                                    : "check-circle"
                                }
                                size={14}
                                color={
                                  d.status === "pendente" ? "#f59e0b" : "#16a34a"
                                }
                              />
                              <Text style={styles.limiteDiaTexto}>
                                {formatDiaCheckin(d.data)}
                                {d.horario ? `  ${d.horario}` : ""}
                                {d.modalidade
                                  ? `  ·  ${normalizeUtf8(d.modalidade)}`
                                  : ""}
                                {d.status === "pendente"
                                  ? "  (presença não marcada)"
                                  : ""}
                              </Text>
                            </View>
                          ))}
                        </View>
                      )}
                  </View>
                ) : null}

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

      <Modal
        visible={confirmBloqueioModal}
        transparent
        animationType="fade"
        onRequestClose={closeConfirmBloqueioModal}
      >
        <TouchableOpacity
          style={styles.modalOverlay}
          activeOpacity={1}
          onPress={closeConfirmBloqueioModal}
        >
          <Animated.View
            style={[
              styles.modalContainer,
              { transform: [{ scale: bloqueioModalScale }] },
            ]}
          >
            <TouchableOpacity
              activeOpacity={1}
              onPress={(e) => e.stopPropagation()}
            >
              <View style={styles.modalContent}>
                <View
                  style={[
                    styles.modalIconContainer,
                    styles.modalIconContainerWarning,
                  ]}
                >
                  <MaterialCommunityIcons
                    name="lock"
                    size={48}
                    color="#b45309"
                  />
                </View>
                <Text style={styles.modalTitle}>Bloquear check-in?</Text>
                <Text style={styles.modalMessage}>
                  {checkinsRecentes.length > 0
                    ? `Esta ação impedirá novos check-ins nesta aula e removerá ${checkinsRecentes.length} check-in${checkinsRecentes.length === 1 ? "" : "s"} já registrado${checkinsRecentes.length === 1 ? "" : "s"}. Deseja continuar?`
                    : "Esta ação impedirá que alunos façam check-in nesta aula. Deseja continuar?"}
                </Text>
                <View style={styles.confirmButtonsRow}>
                  <TouchableOpacity
                    style={[styles.confirmButton, styles.confirmButtonCancel]}
                    onPress={closeConfirmBloqueioModal}
                    disabled={turmaCheckinBloqueioLoading}
                  >
                    <Text style={styles.confirmButtonText}>Cancelar</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[
                      styles.confirmButton,
                      styles.confirmButtonDanger,
                    ]}
                    onPress={async () => {
                      closeConfirmBloqueioModal();
                      await executarBloqueioCheckinTurma(true);
                    }}
                    disabled={turmaCheckinBloqueioLoading}
                  >
                    <Text
                      style={[
                        styles.confirmButtonText,
                        styles.confirmButtonTextLight,
                      ]}
                    >
                      Bloquear
                    </Text>
                  </TouchableOpacity>
                </View>
              </View>
            </TouchableOpacity>
          </Animated.View>
        </TouchableOpacity>
      </Modal>

      <Modal
        visible={confirmManualModal.visible}
        transparent
        animationType="fade"
        onRequestClose={closeManualConfirm}
      >
        <TouchableOpacity
          style={styles.modalOverlay}
          activeOpacity={1}
          onPress={closeManualConfirm}
        >
          <Animated.View
            style={[
              styles.modalContainer,
              { transform: [{ scale: modalScale }] },
            ]}
          >
            <TouchableOpacity
              activeOpacity={1}
              onPress={(e) => e.stopPropagation()}
            >
              <View style={styles.modalContent}>
                <View
                  style={[
                    styles.modalIconContainer,
                    styles.modalIconContainerInfo,
                  ]}
                >
                  <Feather
                    name="help-circle"
                    size={48}
                    color={colors.primary}
                  />
                </View>
                <Text style={styles.modalTitle}>Confirmar check-in</Text>
                <Text style={styles.modalMessage}>
                  Deseja fazer check-in de{" "}
                  {normalizeUtf8(
                    confirmManualModal.aluno?.nome ||
                      confirmManualModal.aluno?.usuario_nome ||
                      "Aluno",
                  )}
                  ?
                </Text>
                {isProfessorOuAdmin && (
                  <ScrollView
                    style={styles.modalFinanceScroll}
                    nestedScrollEnabled
                    showsVerticalScrollIndicator={false}
                  >
                    <AlunoResumoFinanceiro
                      compact
                      alunoId={Number(
                        confirmManualModal.aluno?.id ??
                          confirmManualModal.aluno?.aluno_id ??
                          0,
                      )}
                    />
                  </ScrollView>
                )}
                <View style={styles.confirmButtonsRow}>
                  <TouchableOpacity
                    style={[styles.confirmButton, styles.confirmButtonCancel]}
                    onPress={closeManualConfirm}
                  >
                    <Text style={styles.confirmButtonText}>Cancelar</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.confirmButton, styles.confirmButtonConfirm]}
                    onPress={async () => {
                      const aluno = confirmManualModal.aluno;
                      closeManualConfirm();
                      if (aluno?.id) {
                        await handleManualCheckin(aluno);
                      }
                    }}
                  >
                    <Text
                      style={[
                        styles.confirmButtonText,
                        styles.confirmButtonTextLight,
                      ]}
                    >
                      Confirmar
                    </Text>
                  </TouchableOpacity>
                </View>
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
  headerLeft: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    flex: 1,
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
  headerMetaRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
    width: "100%",
  },
  headerChipsWrap: {
    flex: 1,
    flexDirection: "row",
    flexWrap: "wrap",
    alignItems: "center",
    gap: 8,
    minWidth: 0,
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
  bloqueioCheckinButtonCompact: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    flexShrink: 0,
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 999,
    borderWidth: 2,
  },
  bloqueioCheckinButtonCompactBloquear: {
    backgroundColor: "#DC2626",
    borderColor: "#FECACA",
  },
  bloqueioCheckinButtonCompactLiberar: {
    backgroundColor: "#FFFFFF",
    borderColor: "#059669",
  },
  bloqueioCheckinButtonCompactText: {
    color: "#fff",
    fontSize: 13,
    fontWeight: "800",
  },
  checkinBlockedBanner: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderRadius: 14,
    backgroundColor: "#FEF2F2",
    borderWidth: 1,
    borderColor: "#FECACA",
  },
  checkinBlockedBannerText: {
    color: "#B91C1C",
    fontSize: 15,
    fontWeight: "700",
    flexShrink: 1,
  },
  bloqueioCheckinButtonCompactTextLiberar: {
    color: "#047857",
  },
  bloqueioCheckinButtonCompactDisabled: {
    opacity: 0.45,
    backgroundColor: "#9ca3af",
    borderColor: "#d1d5db",
  },
  bloqueioCheckinButtonCompactTextDisabled: {
    color: "#6b7280",
  },
  scrollContent: {
    paddingBottom: 48,
    paddingTop: 0,
  },
  scrollGrow: {
    flexGrow: 1,
  },
  schedulesSection: {
    paddingHorizontal: 16,
    paddingVertical: 18,
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
  participantsContent: {
    gap: 12,
  },
  participantsListContainer: {
    borderTopWidth: 1,
    borderTopColor: "#eef2f7",
    paddingTop: 6,
    gap: 2,
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
  loadingContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingVertical: 32,
    gap: 12,
  },
  inlineLoadingRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 8,
  },
  loadingText: {
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
  participantAvatarImage: {
    width: "100%",
    height: "100%",
  },
  participantInfo: {
    flex: 1,
  },
  participantNameRow: {
    flexDirection: "row",
    alignItems: "center",
    flexWrap: "wrap",
    gap: 2,
  },
  participantName: {
    fontSize: 16,
    fontWeight: "700",
    color: colors.text,
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
  modalFinanceScroll: {
    width: "100%",
    maxHeight: 280,
    marginBottom: 16,
  },
  limiteBox: {
    width: "100%",
    backgroundColor: "#fffbeb",
    borderWidth: 1,
    borderColor: "#fcd34d",
    borderRadius: 12,
    padding: 14,
    marginBottom: 20,
  },
  limiteHeaderRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    marginBottom: 6,
  },
  limitePlano: {
    fontSize: 15,
    fontWeight: "700",
    color: "#92400e",
  },
  limiteResumo: {
    fontSize: 15,
    fontWeight: "600",
    color: "#b45309",
    marginBottom: 2,
  },
  limiteBonus: {
    fontSize: 12,
    color: "#a16207",
    fontStyle: "italic",
    marginBottom: 8,
  },
  limiteDiasList: {
    marginTop: 8,
    borderTopWidth: 1,
    borderTopColor: "#fde68a",
    paddingTop: 8,
    gap: 6,
  },
  limiteDiaRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  limiteDiaTexto: {
    fontSize: 14,
    color: "#374151",
    flexShrink: 1,
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
  confirmButtonDanger: {
    backgroundColor: "#DC2626",
  },
  confirmButtonText: {
    color: "#111827",
    fontSize: 16,
    fontWeight: "700",
  },
  confirmButtonTextLight: {
    color: "#fff",
  },
});
