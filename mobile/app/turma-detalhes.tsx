import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useFocusEffect, useLocalSearchParams, useRouter } from "expo-router";
import React, { useCallback, useEffect, useRef, useState } from "react";
import {
    Animated,
    Image,
    Modal,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { colors } from "../src/theme/colors";
import { normalizeUtf8 } from "../src/utils/utf8";

export default function TurmaDetalhesScreen() {
  const router = useRouter();
  const { turmaId, data } = useLocalSearchParams();
  const [loading, setLoading] = useState(true);
  const [participants, setParticipants] = useState<any[]>([]);
  const [turma, setTurma] = useState<any | null>(null);
  const [checkinLoading, setCheckinLoading] = useState(false);
  const [checkinsRecentes, setCheckinsRecentes] = useState<any[]>([]);
  const [resumoTurma, setResumoTurma] = useState<any | null>(null);
  const [alunosTotal, setAlunosTotal] = useState<number>(0);
  const [currentUserId, setCurrentUserId] = useState<number | null>(null);
  const [userCheckinId, setUserCheckinId] = useState<number | null>(null);
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

  const selectedDate = data ? new Date(String(data)) : new Date();

  useEffect(() => {
    loadCurrentUserId();
    loadTurmaDetails();
  }, [turmaId]);

  // Recarrega os dados quando a tela volta ao foco
  useFocusEffect(
    useCallback(() => {
      console.log("🔄 TELA TURMA-DETALHES VOLTOU AO FOCO - Recarregando dados");
      if (turmaId) {
        loadTurmaDetails();
      }
    }, [turmaId]),
  );

  useEffect(() => {
    if (turma && currentUserId) {
      checkUserHasCheckin();
    }
  }, [participants, checkinsRecentes, currentUserId]);

  const loadCurrentUserId = async () => {
    try {
      const userStr = await AsyncStorage.getItem("@appcheckin:user");
      if (userStr) {
        const user = JSON.parse(userStr);
        setCurrentUserId(user.id);
      }
    } catch (error) {
      console.error("Erro ao carregar ID do usuário:", error);
    }
  };

  const formatDateParam = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  const getAvatarUrl = (nome: string = "", userId?: number) => {
    const seed =
      userId ||
      Math.abs(
        nome.split("").reduce((acc, char) => acc + char.charCodeAt(0), 0),
      );
    const gender = seed % 2 === 0 ? "women" : "men";
    const photoId = (seed % 99) + 1;
    return `https://randomuser.me/api/portraits/${gender}/${photoId}.jpg`;
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

  const combineDateTime = (date: Date, timeHHMMSS: string) => {
    const [hh, mm, ss] = (timeHHMMSS || "00:00:00")
      .split(":")
      .map((n) => parseInt(n, 10) || 0);
    const d = new Date(date);
    d.setHours(hh, mm, ss || 0, 0);
    return d;
  };

  const getCheckinWindow = (turmaData: any) => {
    if (!turmaData?.horario?.inicio) return null;

    const diaAula = turmaData.dia_aula
      ? new Date(turmaData.dia_aula + "T00:00:00")
      : selectedDate;
    const toleranciaAntes = turmaData.horario.tolerancia_antes_minutos || 0;
    const toleranciaDepois = turmaData.horario.tolerancia_minutos || 0;

    const horarioInicio = combineDateTime(diaAula, turmaData.horario.inicio);
    const abre = new Date(
      horarioInicio.getTime() - toleranciaAntes * 60 * 1000,
    );
    const fecha = new Date(
      horarioInicio.getTime() + toleranciaDepois * 60 * 1000,
    );

    return { abre, fecha, horarioInicio };
  };

  const isWithinCheckinWindow = (
    turmaData: any,
  ): { allowed: boolean; reason?: string } => {
    const window = getCheckinWindow(turmaData);
    if (!window) return { allowed: true };

    const now = new Date();

    if (now < window.abre) {
      const diffMinutes = Math.ceil(
        (window.abre.getTime() - now.getTime()) / 60000,
      );
      const horaAbre = window.abre.toLocaleTimeString("pt-BR", {
        hour: "2-digit",
        minute: "2-digit",
      });

      if (diffMinutes > 60) {
        const horas = Math.floor(diffMinutes / 60);
        const minutos = diffMinutes % 60;
        return {
          allowed: false,
          reason: `Check-in abre às ${horaAbre} (em ${horas}h${minutos > 0 ? ` ${minutos}min` : ""})`,
        };
      }
      return {
        allowed: false,
        reason: `Check-in abre às ${horaAbre} (em ${diffMinutes} min)`,
      };
    }

    if (now > window.fecha) {
      const horaFecha = window.fecha.toLocaleTimeString("pt-BR", {
        hour: "2-digit",
        minute: "2-digit",
      });
      return {
        allowed: false,
        reason: `Check-in encerrou às ${horaFecha}`,
      };
    }

    return { allowed: true };
  };

  const isTurmaDisabled = (turmaData: any): boolean => {
    try {
      const now = new Date();
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      // Usar dia_aula se disponível, senão selectedDate
      const diaAula = turmaData?.dia_aula
        ? new Date(turmaData.dia_aula + "T00:00:00")
        : selectedDate;
      const ref = new Date(diaAula);
      ref.setHours(0, 0, 0, 0);

      if (ref < today) {
        return true;
      }
      if (ref > today) {
        return false;
      }

      if (!turmaData?.horario?.fim) {
        console.log("   ⚠️ Sem horário de fim, permitindo");
        return false;
      }
      const end = combineDateTime(
        diaAula,
        turmaData?.horario?.fim ?? "00:00:00",
      );
      const passou = now > end;
      console.log(
        "   Horário fim:",
        end.toLocaleString("pt-BR"),
        "- Passou?",
        passou,
      );
      return passou;
    } catch (e) {
      console.warn("Falha ao calcular disponibilidade da turma:", e);
      return false;
    }
  };

  const isCheckinDisabled = (turmaData: any): boolean => {
    if (!turmaData) {
      console.log("🔴 Botão desabilitado: turma não existe");
      return true;
    }

    const disabled = isTurmaDisabled(turmaData);
    if (disabled) {
      console.log("🔴 Botão desabilitado: turma desabilitada (horário passou)");
      return true;
    }

    const windowCheck = isWithinCheckinWindow(turmaData);
    if (!windowCheck.allowed) {
      console.log(
        "🔴 Botão desabilitado: fora da janela -",
        windowCheck.reason,
      );
      return true;
    }

    const hasVagasByField =
      typeof turmaData.vagas_disponiveis === "number"
        ? turmaData.vagas_disponiveis > 0
        : true;

    const hasVagasByCount =
      typeof turmaData.limite_alunos === "number" &&
      typeof turmaData.alunos_inscritos === "number"
        ? turmaData.alunos_inscritos < turmaData.limite_alunos
        : true;

    if (!hasVagasByField && !hasVagasByCount) {
      return true;
    }

    return false;
  };

  const getCheckinDisabledReason = (turmaData: any): string | null => {
    if (!turmaData) return "Turma não encontrada";
    if (isTurmaDisabled(turmaData)) return "Horário encerrado";

    const windowCheck = isWithinCheckinWindow(turmaData);
    if (!windowCheck.allowed)
      return windowCheck.reason || "Fora do horário de check-in";

    const hasVagasByField =
      typeof turmaData.vagas_disponiveis === "number"
        ? turmaData.vagas_disponiveis > 0
        : true;

    const hasVagasByCount =
      typeof turmaData.limite_alunos === "number" &&
      typeof turmaData.alunos_inscritos === "number"
        ? turmaData.alunos_inscritos < turmaData.limite_alunos
        : true;

    if (!hasVagasByField && !hasVagasByCount) return "Sem vagas disponíveis";
    return null;
  };

  const loadTurmaDetails = async () => {
    setLoading(true);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showErrorModal("Token não encontrado", "error");
        return;
      }

      const dataParam = formatDateParam(selectedDate);
      const baseUrl = getApiUrlRuntime();
      const url = `${baseUrl}/mobile/turma/${turmaId}/detalhes?data=${dataParam}`;

      const response = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      const text = await response.text();

      if (!response.ok) {
        console.error("❌ Erro ao carregar detalhes:", response.status, text);
        showErrorModal("Falha ao carregar detalhes da turma", "error");
        return;
      }

      const responseData = JSON.parse(text);

      const payload = responseData.data || responseData;

      // Converter turma da API para formato da listagem
      const turmaApi = payload.turma;

      console.log("� ========== RESPONSE DO BACKEND ==========");
      console.log("📦 Payload completo:", JSON.stringify(payload, null, 2));
      console.log("📋 Dados brutos da turma:", {
        id: turmaApi.id,
        horario_inicio: turmaApi.horario_inicio,
        horario_fim: turmaApi.horario_fim,
        tolerancia_antes_minutos: turmaApi.tolerancia_antes_minutos,
        tolerancia_minutos: turmaApi.tolerancia_minutos,
        dia_aula: turmaApi.dia_aula,
      });
      console.log("=========================================");

      // Buscar os dados completos da turma da listagem (que tem tolerância)
      let horarioCompleto = {
        inicio: turmaApi.horario_inicio,
        fim: turmaApi.horario_fim,
        tolerancia_antes_minutos: turmaApi.tolerancia_antes_minutos || 0,
        tolerancia_minutos: turmaApi.tolerancia_minutos || 0,
      };

      // Se não tiver tolerância, buscar da API de listagem
      if (!turmaApi.tolerancia_antes_minutos && !turmaApi.tolerancia_minutos) {
        console.log(
          "⚠️ Turma sem dados de tolerância, buscando da listagem...",
        );
        try {
          // Usar o dia_aula da turma para buscar na listagem
          const diaAula = turmaApi.dia_aula || formatDateParam(selectedDate);
          const baseUrl = getApiUrlRuntime();
          const listUrl = `${baseUrl}/mobile/horarios-disponiveis?data=${diaAula}`;
          console.log("   Buscando em:", listUrl);
          console.log("   Procurando turma ID:", turmaApi.id);
          const listResponse = await fetch(listUrl, {
            headers: { Authorization: `Bearer ${token}` },
          });
          const listData = await listResponse.json();
          console.log(
            "   Turmas disponíveis:",
            listData.data?.turmas?.length || 0,
          );
          const turmaFromList = listData.data?.turmas?.find(
            (t: any) => t.id === turmaApi.id,
          );
          console.log("   Turma encontrada na listagem?", !!turmaFromList);
          if (turmaFromList?.horario) {
            console.log(
              "📥 Dados de tolerância da listagem:",
              turmaFromList.horario,
            );
            horarioCompleto = turmaFromList.horario;
          } else {
            console.log(
              "❌ Turma não encontrada na listagem ou sem dados de horário",
            );
          }
        } catch (e) {
          console.warn("⚠️ Não foi possível buscar tolerância da listagem:", e);
        }
      } else {
        console.log("✅ Turma já tem dados de tolerância:", horarioCompleto);
      }

      const turmaFormatted = {
        id: turmaApi.id,
        nome: turmaApi.nome,
        professor: {
          nome: turmaApi.professor || turmaApi.professor_nome,
        },
        modalidade: {
          nome: turmaApi.modalidade || turmaApi.modalidade_nome,
        },
        horario: horarioCompleto,
        dia_aula: turmaApi.dia_aula, // Campo essencial para validação de data
        limite_alunos: turmaApi.limite_alunos,
        alunos_inscritos:
          turmaApi.total_alunos_matriculados || turmaApi.alunos_inscritos || 0,
        vagas_disponiveis: turmaApi.vagas_disponiveis,
        ativo: turmaApi.ativo,
      };

      const alunosLista = payload.alunos?.lista || payload.participantes || [];
      const checkinsLista = payload.checkins_recentes || [];
      const resumoData = payload.resumo || null;
      const alunosCount =
        payload.alunos?.total ??
        alunosLista.length ??
        turmaFormatted.alunos_inscritos ??
        0;

      console.log("✅ Dados carregados:", {
        participantes: alunosLista.length,
        checkinsRecentes: checkinsLista.length,
      });

      setTurma(turmaFormatted);
      setParticipants(alunosLista);
      setCheckinsRecentes(checkinsLista);
      setResumoTurma(resumoData);
      setAlunosTotal(alunosCount);

      // Verificar check-in do usuário SÍNCRONAMENTE
      // Usar os dados recém-carregados ao invés de state
      checkUserHasCheckinSync(alunosLista, checkinsLista);
    } catch (error) {
      console.error("❌ Erro ao carregar detalhes:", error);
      showErrorModal("Não foi possível carregar os detalhes", "error");
    } finally {
      setLoading(false);
    }
  };

  const handleCheckin = async () => {
    if (!turma?.id) return;

    const windowCheck = isWithinCheckinWindow(turma);
    if (!windowCheck.allowed) {
      showErrorModal(
        windowCheck.reason || "Check-in não disponível no momento",
        "warning",
      );
      return;
    }

    if (isTurmaDisabled(turma)) {
      showErrorModal("Este horário já foi encerrado.", "warning");
      return;
    }

    setCheckinLoading(true);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        showErrorModal("Token não encontrado", "error");
        return;
      }

      // Usar dia_aula da turma, não selectedDate do parâmetro da URL
      const dataCheckin = turma.dia_aula || formatDateParam(selectedDate);

      console.log("🗓️ DEBUG DATA CHECK-IN:");
      console.log("   turma.dia_aula:", turma.dia_aula);
      console.log("   selectedDate formatted:", formatDateParam(selectedDate));
      console.log("   dataCheckin escolhida:", dataCheckin);

      const payload: any = {
        turma_id: turma.id,
        data: dataCheckin,
      };

      if (turma.horario?.id) {
        payload.horario_id = turma.horario.id;
      }

      console.log("📦 PAYLOAD DO CHECK-IN:", JSON.stringify(payload, null, 2));
      console.log("📍 ENDPOINT:", `${getApiUrlRuntime()}/mobile/checkin`);
      console.log("🔑 Token:", token.substring(0, 20) + "...");

      const response = await fetch(`${getApiUrlRuntime()}/mobile/checkin`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });

      const text = await response.text();

      let responseData: any = {};
      try {
        responseData = text ? JSON.parse(text) : {};
      } catch (e) {
        console.error("❌ Erro ao fazer parse do JSON:", e);
        responseData = {};
      }

      if (!response.ok) {
        const apiMessage =
          responseData?.message ||
          responseData?.error ||
          text ||
          "Não foi possível realizar o check-in.";

        if (
          String(apiMessage).toLowerCase().includes("já realizou check-in") ||
          String(apiMessage).toLowerCase().includes("já fez check-in")
        ) {
          await loadTurmaDetails();
          showErrorModal(
            'Você já fez check-in nesta turma. Use o botão "Desfazer Check-in" para remover.',
            "warning",
          );
        } else {
          showErrorModal(normalizeUtf8(String(apiMessage)), "error");
        }
        return;
      }

      if (responseData.success) {
        showErrorModal(
          `Check-in realizado para ${normalizeUtf8(turma.nome)}`,
          "success",
        );
        await loadTurmaDetails();
      } else {
        showErrorModal(
          normalizeUtf8(
            responseData?.message ||
              responseData?.error ||
              "Não foi possível realizar o check-in.",
          ),
          "error",
        );
      }
    } catch (error) {
      console.error("❌ ERRO EXCEPTION check-in:", error);
      showErrorModal("Falha ao realizar o check-in.", "error");
    } finally {
      setCheckinLoading(false);
    }
  };

  const handleUndoCheckin = async (checkinId: number) => {
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

      const url = `${getApiUrlRuntime()}/checkin/${checkinId}/desfazer`;

      const response = await fetch(url, {
        method: "DELETE",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      const text = await response.text();
      let responseData: any = {};
      try {
        responseData = text ? JSON.parse(text) : {};
      } catch (e) {
        console.error("❌ Erro ao fazer parse do JSON:", e);
        responseData = {};
      }

      if (!response.ok) {
        const apiMessage =
          responseData?.error ||
          responseData?.message ||
          text ||
          "Não foi possível desfazer o check-in.";
        showErrorModal(normalizeUtf8(String(apiMessage)), "error");
        return;
      }

      setUserCheckinId(null);
      await loadTurmaDetails();
      showErrorModal(`Check-in desfeito com sucesso`, "warning");
    } catch (error) {
      console.error("❌ ERRO EXCEPTION ao desfazer check-in:", error);
      showErrorModal("Falha ao desfazer o check-in.", "error");
    } finally {
      setCheckinLoading(false);
    }
  };

  // Versão síncrona que recebe dados diretamente (evita race condition)
  const checkUserHasCheckinSync = (
    participantsList: any[],
    checkinsList: any[],
  ) => {
    if (!currentUserId) {
      setUserCheckinId(null);
      return false;
    }

    // Primeiro procura em checkinsRecentes
    if (checkinsList && checkinsList.length > 0) {
      const userCheckin = checkinsList.find(
        (c) => Number(c.usuario_id) === Number(currentUserId),
      );

      if (userCheckin) {
        const checkinId =
          userCheckin.id || userCheckin.checkin_id || userCheckin.id_checkin;
        if (checkinId) {
          setUserCheckinId(checkinId);
          return true;
        }
      }
    }

    // Depois procura em participants
    if (participantsList && participantsList.length > 0) {
      const userParticipant = participantsList.find(
        (p) => Number(p.usuario_id) === Number(currentUserId),
      );

      if (userParticipant) {
        // API deve retornar checkin_id diretamente no participante
        const checkinId =
          userParticipant.checkin_id || userParticipant.id_checkin;

        if (checkinId) {
          console.log("✅ Check-in encontrado:", checkinId);
          setUserCheckinId(checkinId);
          return true;
        }
      }
    }

    setUserCheckinId(null);
    return false;
  };

  // Versão que usa state (mantida para compatibilidade)
  const checkUserHasCheckin = () => {
    return checkUserHasCheckinSync(participants, checkinsRecentes);
  };

  const getHoraInicio = (turmaData: any) =>
    turmaData?.hora_inicio || turmaData?.horario?.inicio;
  const getHoraFim = (turmaData: any) =>
    turmaData?.hora_fim || turmaData?.horario?.fim;

  if (loading) {
    return (
      <SafeAreaView style={styles.container} edges={["top", "bottom"]}>
        <View style={styles.header}>
          <TouchableOpacity
            onPress={() => router.back()}
            style={styles.backButton}
          >
            <Feather name="arrow-left" size={24} color={colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Detalhes da Turma</Text>
          <View style={{ width: 40 }} />
        </View>
        <View style={styles.loadingContainer}>
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (!turma) {
    return (
      <SafeAreaView style={styles.container} edges={["top", "bottom"]}>
        <View style={styles.header}>
          <TouchableOpacity
            onPress={() => router.back()}
            style={styles.backButton}
          >
            <Feather name="arrow-left" size={24} color={colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Detalhes da Turma</Text>
          <View style={{ width: 40 }} />
        </View>
        <View style={styles.loadingContainer}>
          <Text style={styles.loadingText}>Turma não encontrada</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <>
      <SafeAreaView style={styles.container} edges={["top", "bottom"]}>
        <View style={styles.header}>
          <TouchableOpacity
            onPress={() => router.back()}
            style={styles.backButton}
          >
            <Feather name="arrow-left" size={24} color={colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Detalhes da Turma</Text>
          <View style={{ width: 40 }} />
        </View>

        <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
          <View style={styles.turmaHeader}>
            <Text style={styles.turmaNome}>
              {normalizeUtf8(turma.nome || "Turma")}
            </Text>
          </View>

          <View style={styles.metaRow}>
            <View style={styles.metaChip}>
              <Feather name="clock" size={14} color={colors.primary} />
              <Text style={styles.metaChipText}>
                {getHoraInicio(turma)?.slice(0, 5)} -{" "}
                {getHoraFim(turma)?.slice(0, 5)}
              </Text>
            </View>
            <View style={styles.metaChip}>
              <Feather name="users" size={14} color={colors.primary} />
              <Text style={styles.metaChipText}>
                {alunosTotal ||
                  participants?.length ||
                  turma.alunos_inscritos ||
                  0}
                /{turma.limite_alunos || "--"} inscritos
              </Text>
            </View>
          </View>

          <View style={styles.participantsSection}>
            {participants.length > 0 ? (
              <View style={styles.participantsList}>
                {participants.map((p, idx) => {
                  const isCurrentUser =
                    currentUserId &&
                    Number(p.usuario_id) === Number(currentUserId);
                  const hasFoto = p.foto_base64 || p.foto;
                  return (
                    <View
                      key={p.usuario_id || p.checkin_id || idx}
                      style={styles.participantItem}
                    >
                      {hasFoto ? (
                        <Image
                          source={{
                            uri: `data:image/jpeg;base64,${p.foto_base64 || p.foto}`,
                          }}
                          style={styles.participantAvatar}
                        />
                      ) : (
                        <View style={styles.participantAvatarPlaceholder}>
                          <Feather
                            name="user"
                            size={20}
                            color={isCurrentUser ? colors.primary : "#999"}
                          />
                        </View>
                      )}
                      <View style={styles.participantInfo}>
                        <Text
                          style={[
                            styles.participantName,
                            isCurrentUser && styles.participantNameCurrent,
                          ]}
                        >
                          {normalizeUtf8(p.nome || p.usuario_nome || "Aluno")}
                          {isCurrentUser && " (Você)"}
                        </Text>
                      </View>
                      {isCurrentUser && (
                        <TouchableOpacity
                          onPress={async () => {
                            // API agora retorna checkin_id diretamente
                            const checkinId = p.checkin_id || p.id_checkin;

                            if (checkinId) {
                              await handleUndoCheckin(checkinId);
                            } else {
                              console.log("❌ Check-in ID não encontrado");
                              showErrorModal(
                                "Não foi possível encontrar o ID do check-in",
                                "error",
                              );
                            }
                          }}
                          style={styles.undoButtonSmall}
                        >
                          <Feather name="x-circle" size={18} color="#ef4444" />
                        </TouchableOpacity>
                      )}
                    </View>
                  );
                })}
              </View>
            ) : (
              <Text style={styles.emptyText}>Nenhum participante ainda</Text>
            )}
          </View>

          {!userCheckinId &&
            (() => {
              const reason = getCheckinDisabledReason(turma);
              if (reason) {
                return (
                  <View style={styles.checkinWarning}>
                    <Feather name="info" size={16} color="#f57c00" />
                    <Text style={styles.checkinWarningText}>{reason}</Text>
                  </View>
                );
              }
              return null;
            })()}

          <TouchableOpacity
            style={[
              styles.checkinButton,
              userCheckinId ? styles.checkinButtonUndo : null,
              (checkinLoading ||
                (!userCheckinId && isCheckinDisabled(turma))) &&
                styles.checkinButtonDisabled,
            ]}
            onPress={() => {
              if (userCheckinId) {
                handleUndoCheckin(userCheckinId);
              } else {
                handleCheckin();
              }
            }}
            disabled={
              checkinLoading || (!userCheckinId && isCheckinDisabled(turma))
            }
          >
            {checkinLoading ? (
              <>
                <Feather name="loader" size={18} color="#fff" />
                <Text style={styles.checkinButtonText}>Processando...</Text>
              </>
            ) : userCheckinId ? (
              <>
                <Feather name="rotate-ccw" size={18} color="#fff" />
                <Text style={styles.checkinButtonText}>Desfazer Check-in</Text>
              </>
            ) : (
              <>
                <Feather name="check-circle" size={18} color="#fff" />
                <Text style={styles.checkinButtonText}>Fazer Check-in</Text>
              </>
            )}
          </TouchableOpacity>
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
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: "#fff",
    borderBottomWidth: 1,
    borderBottomColor: "#f0f0f0",
    elevation: 4,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: "#000",
  },
  content: {
    flex: 1,
    padding: 16,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  loadingText: {
    fontSize: 16,
    color: "#999",
  },
  turmaHeader: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
  },
  turmaNome: {
    fontSize: 20,
    fontWeight: "700",
    color: "#000",
    marginBottom: 4,
  },
  turmaProfessor: {
    fontSize: 14,
    color: "#666",
  },
  metaRow: {
    flexDirection: "row",
    gap: 8,
    marginBottom: 16,
  },
  metaChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: "#fff",
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  metaChipText: {
    fontSize: 12,
    color: "#333",
    fontWeight: "500",
  },
  checkinWindowInfo: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: "#f0f9ff",
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#bfdbfe",
    marginBottom: 16,
  },
  checkinWindowText: {
    fontSize: 12,
    color: "#1e40af",
    fontWeight: "500",
  },
  participantsSection: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "700",
    color: "#000",
    marginBottom: 12,
  },
  participantsList: {
    gap: 12,
  },
  participantItem: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 8,
    gap: 12,
  },
  participantAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: "#f0f0f0",
  },
  participantAvatarPlaceholder: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: "#f0f0f0",
    justifyContent: "center",
    alignItems: "center",
  },
  participantInfo: {
    flex: 1,
  },
  undoButtonSmall: {
    padding: 4,
  },
  participantName: {
    fontSize: 14,
    color: "#333",
    fontWeight: "500",
  },
  participantNameCurrent: {
    fontWeight: "700",
    color: colors.primary,
  },
  participantItemSimple: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 8,
    gap: 8,
  },
  participantNameSimple: {
    fontSize: 14,
    color: "#333",
  },
  emptyText: {
    textAlign: "center",
    color: "#999",
    fontSize: 14,
    paddingVertical: 20,
  },
  checkinWarning: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    backgroundColor: "#fff3e0",
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#ffe0b2",
    marginBottom: 16,
  },
  checkinWarningText: {
    fontSize: 13,
    color: "#e65100",
    fontWeight: "600",
    flex: 1,
  },
  checkinButton: {
    backgroundColor: "#10b981",
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
    marginBottom: 24,
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
    fontSize: 16,
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
  modalTitle: {
    fontSize: 22,
    fontWeight: "700",
    color: "#000",
    marginBottom: 12,
    textAlign: "center",
  },
  modalMessage: {
    fontSize: 16,
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
    fontSize: 16,
    fontWeight: "700",
  },
});
