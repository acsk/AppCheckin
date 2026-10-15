import { colors } from "@/src/theme/colors";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { handleUnauthorizedResponse } from "@/src/utils/authHelpers";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import React, { useEffect, useState } from "react";
import Avatar from "./ds/Avatar";
import Badge from "./ds/Badge";
import { ds } from "./ds/tokens";
import {
  ActivityIndicator,
  StyleSheet,
  Text,
  View,
} from "react-native";

type Pagamento = {
  id: number;
  valor: number;
  data_vencimento: string;
  data_pagamento: string | null;
  status: string;
  status_pagamento_id?: number;
  forma_pagamento?: string | null;
  baixado_por_nome?: string | null;
  criado_por_nome?: string | null;
  tipo_baixa_nome?: string | null;
  origem?: string | null;
  pendente: boolean;
};

type ResumoFinanceiro = {
  total_previsto: number;
  total_pago: number;
  total_pendente: number;
  quantidade_pagamentos: number;
  pagamentos_realizados: number;
};

type MatriculaResumo = {
  id: number;
  status: string;
  plano?: { nome: string; valor: number; checkins_semanais?: number } | null;
  datas?: { vencimento?: string | null };
};

type Props = {
  alunoId: number;
  compact?: boolean;
  studentName?: string;
};

const formatMoney = (value: number) => {
  const numeric =
    typeof value === "number" && !Number.isNaN(value) ? value : 0;
  return new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
  }).format(numeric);
};

const formatDate = (value?: string | null) => {
  if (!value) return "—";
  const date = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
  if (date) return `${date[3]}/${date[2]}/${date[1]}`;
  return new Date(value).toLocaleDateString("pt-BR");
};

const isPaid = (pagamento: Pagamento) =>
  pagamento.status_pagamento_id === 2 || pagamento.status.toLowerCase() === "pago";

const isOverdue = (pagamento: Pagamento) => {
  if (isPaid(pagamento)) return false;
  if (pagamento.status_pagamento_id === 3 || /vencido|atrasado/i.test(pagamento.status)) return true;
  const today = new Date();
  const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
  return pagamento.pendente && /^\d{4}-\d{2}-\d{2}/.test(pagamento.data_vencimento) &&
    pagamento.data_vencimento.slice(0, 10) < todayKey;
};

export function AlunoResumoFinanceiro({ alunoId, compact = false, studentName }: Props) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [matricula, setMatricula] = useState<MatriculaResumo | null>(null);
  const [pagamentos, setPagamentos] = useState<Pagamento[]>([]);
  const [resumo, setResumo] = useState<ResumoFinanceiro | null>(null);

  useEffect(() => {
    if (!alunoId || alunoId <= 0) {
      setLoading(false);
      setError("Aluno inválido");
      return;
    }

    let cancelled = false;

    const load = async () => {
      setLoading(true);
      setError(null);

      try {
        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) {
          if (!cancelled) setError("Sessão expirada");
          return;
        }

        const response = await fetch(
          `${getApiUrlRuntime()}/mobile/alunos/${alunoId}/resumo-financeiro`,
          {
            headers: {
              Authorization: `Bearer ${token}`,
              "Content-Type": "application/json",
            },
          },
        );

        if (!response.ok) {
          if (await handleUnauthorizedResponse(response)) {
            return;
          }
          if (!cancelled) {
            setError(
              response.status === 404
                ? "Aluno sem matrícula"
                : "Não foi possível carregar o resumo",
            );
          }
          return;
        }

        const data = await response.json();
        if (!data.success) {
          if (!cancelled) setError(data.error || "Erro ao carregar resumo");
          return;
        }

        if (!cancelled) {
          setMatricula(data.data?.matricula ?? null);
          setPagamentos(data.data?.pagamentos ?? []);
          setResumo(data.data?.resumo_financeiro ?? null);
        }
      } catch {
        if (!cancelled) setError("Erro ao carregar resumo financeiro");
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    void load();
    return () => {
      cancelled = true;
    };
  }, [alunoId]);

  const weekly = matricula?.plano?.checkins_semanais;
  const studentCard = studentName ? (
    <View style={styles.student}>
      <Avatar name={studentName} size={44} />
      <View style={styles.matriculaInfo}>
        <Text style={styles.studentName}>{studentName}</Text>
        <Text style={styles.matriculaMeta}>Aluno{weekly ? ` · ${weekly}x por semana` : ""}</Text>
      </View>
    </View>
  ) : null;

  if (loading || error || !matricula) {
    return (
      <View style={styles.stack}>
        {studentCard}
        <View style={[styles.container, styles.status]}>
          {loading ? (
            <>
              <ActivityIndicator size="small" color={colors.primary} />
              <Text style={styles.loadingText}>Carregando resumo...</Text>
            </>
          ) : error ? (
            <Text style={styles.errorText} accessibilityRole="alert">{error}</Text>
          ) : (
            <Text style={styles.emptyText}>Nenhuma matrícula encontrada</Text>
          )}
        </View>
      </View>
    );
  }

  const progress =
    resumo && resumo.total_previsto > 0
      ? Math.max(0, Math.min((resumo.total_pago / resumo.total_previsto) * 100, 100))
      : 0;
  const overdue = pagamentos.filter(isOverdue);
  const overdueTotal = overdue.reduce((total, pagamento) => total + pagamento.valor, 0);
  const oldestOverdue = overdue.map(p => p.data_vencimento).sort()[0];

  return (
    <View style={styles.stack}>
      {studentCard}
      {compact && overdue.length > 0 ? (
        <View style={styles.notice} accessibilityRole="alert">
          <Feather name="alert-circle" size={18} color={ds.danger} />
          <View style={styles.matriculaInfo}>
            <Text style={styles.noticeTitle}>{overdue.length} {overdue.length === 1 ? "pagamento atrasado" : "pagamentos atrasados"}</Text>
            <Text style={styles.noticeText}>{formatMoney(overdueTotal)} {overdue.length === 1 ? "venceu" : "venceram"} em {formatDate(oldestOverdue)}. O check-in pode ser feito mesmo assim.</Text>
          </View>
        </View>
      ) : null}
      <View style={styles.container}>
        <View style={styles.matriculaRow}>
          <View style={styles.matriculaInfo}>
            <Text style={styles.sectionTitle}>Resumo financeiro</Text>
            <Text style={styles.planoNome}>{matricula.plano?.nome || "Plano"}</Text>
          </View>
          <View style={styles.matriculaStatus}>
            <Badge tone={overdue.length ? "danger" : /ativa/i.test(matricula.status) ? "success" : "warning"} icon={overdue.length ? "alert-circle" : "clock"}>
              {overdue.length ? "Atrasado" : matricula.status}
            </Badge>
            {matricula.datas?.vencimento ? <Text style={styles.due}>Vence {formatDate(matricula.datas.vencimento)}</Text> : null}
          </View>
        </View>
      {resumo && (
        <>
          <View style={styles.resumoGrid}>
            <View style={styles.resumoItem}>
              <Text style={styles.resumoLabel}>Previsto</Text>
              <Text style={styles.resumoValue} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.75}>
                {formatMoney(resumo.total_previsto)}
              </Text>
            </View>
            <View style={[styles.resumoItem, styles.resumoItemPaid]}>
              <Text style={styles.resumoLabel}>Pago</Text>
              <Text style={[styles.resumoValue, styles.resumoValuePaid]} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.75}>
                {formatMoney(resumo.total_pago)}
              </Text>
            </View>
            <View style={[styles.resumoItem, styles.resumoItemPending]}>
              <Text style={styles.resumoLabel}>Pendente</Text>
              <Text style={[styles.resumoValue, styles.resumoValuePending]} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.75}>
                {formatMoney(resumo.total_pendente)}
              </Text>
            </View>
          </View>

          <View style={styles.progress}>
            <View style={styles.progressTrack} accessibilityRole="progressbar" accessibilityLabel="Pagamentos realizados" accessibilityValue={{ min: 0, max: 100, now: progress }}>
              <View style={[styles.progressBar, { width: `${progress}%` }]} />
            </View>
            <Text style={styles.progressText}>
              <Text style={styles.progressStrong}>{resumo.pagamentos_realizados} de {resumo.quantidade_pagamentos}</Text>{" "}pagamentos realizados
            </Text>
          </View>
        </>
      )}

      {pagamentos.length > 0 && (
        <View style={styles.pagamentosSection}>
          <Text style={styles.pagamentosTitle}>Últimos pagamentos</Text>
          {pagamentos.map((pagamento) => (
            <View key={pagamento.id} style={styles.pagamentoItem}>
              <View style={[styles.paymentIcon, { backgroundColor: isPaid(pagamento) ? ds.successSoft : isOverdue(pagamento) ? ds.dangerSoft : ds.warningSoft }]}>
                <Feather name="file-text" size={18} color={isPaid(pagamento) ? ds.success : isOverdue(pagamento) ? ds.danger : ds.warning} />
              </View>
              <View style={styles.pagamentoLeft}>
                <Text style={styles.pagamentoValor}>
                  {formatMoney(pagamento.valor)}
                </Text>
                <Text style={styles.pagamentoData}>
                  Venc. {formatDate(pagamento.data_vencimento)}
                  {pagamento.data_pagamento
                    ? ` · Pago ${formatDate(pagamento.data_pagamento)}`
                    : ""}
                </Text>
                {(pagamento.forma_pagamento ||
                  pagamento.baixado_por_nome ||
                  pagamento.criado_por_nome) && (
                  <Text style={styles.pagamentoData}>
                    {[
                      pagamento.forma_pagamento,
                      pagamento.baixado_por_nome || pagamento.criado_por_nome
                        ? `por ${pagamento.baixado_por_nome || pagamento.criado_por_nome}`
                        : null,
                    ]
                      .filter(Boolean)
                      .join(" · ")}
                  </Text>
                )}
              </View>
              <Badge tone={isPaid(pagamento) ? "success" : isOverdue(pagamento) ? "danger" : "warning"} icon={isPaid(pagamento) ? "check" : "alert-circle"}>
                {isOverdue(pagamento) ? "Atrasado" : pagamento.status}
              </Badge>
            </View>
          ))}
        </View>
      )}

      {pagamentos.length === 0 && (
        <View style={styles.emptyPayments}>
          <Feather name="info" size={14} color={colors.textMuted} />
          <Text style={styles.emptyText}>Nenhum pagamento registrado</Text>
        </View>
      )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  stack: { gap: 12 },
  student: { flexDirection: "row", alignItems: "center", gap: 12, padding: 16, backgroundColor: ds.surfaceSunken, borderRadius: 16 },
  studentName: { fontSize: 16, lineHeight: 21, fontWeight: "900", textTransform: "uppercase", color: ds.ink },
  notice: { flexDirection: "row", alignItems: "flex-start", gap: 12, padding: 16, borderRadius: 16, backgroundColor: ds.dangerSoft },
  noticeTitle: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: ds.danger },
  noticeText: { fontSize: 13, lineHeight: 18, fontWeight: "500", color: ds.ink },
  matriculaStatus: { alignItems: "flex-end", gap: 6, flexShrink: 1 },
  due: { fontSize: 12, lineHeight: 16, fontWeight: "700", color: ds.inkMuted },
  paymentIcon: { width: 36, height: 36, borderRadius: 12, alignItems: "center", justifyContent: "center" },
  progress: { padding: 16, gap: 8 },
  progressStrong: { color: ds.ink, fontWeight: "800" },
  container: {
    width: "100%",
    backgroundColor: ds.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: ds.line,
    overflow: "hidden",
  },
  status: {
    padding: 16,
    gap: 8,
  },
  sectionTitle: {
    fontSize: 11,
    fontWeight: "800",
    color: ds.inkMuted,
    textTransform: "uppercase",
    letterSpacing: 0.4,
  },
  loadingText: {
    fontSize: 12,
    color: colors.textMuted,
    textAlign: "center",
  },
  errorText: {
    fontSize: 12,
    color: ds.danger,
    textAlign: "center",
  },
  emptyText: {
    fontSize: 12,
    color: colors.textMuted,
  },
  matriculaRow: {
    flexDirection: "row",
    alignItems: "center",
    padding: 16,
    gap: 8,
  },
  matriculaInfo: {
    flex: 1,
    gap: 2,
  },
  planoNome: {
    fontSize: 16,
    lineHeight: 21,
    fontWeight: "800",
    color: colors.text,
  },
  matriculaMeta: {
    fontSize: 12,
    color: colors.textSecondary,
    fontWeight: "600",
  },
  resumoGrid: {
    flexDirection: "row",
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: ds.line,
  },
  resumoItem: {
    flex: 1,
    paddingHorizontal: 12,
    paddingVertical: 14,
    backgroundColor: ds.surface,
  },
  resumoItemPaid: {
    borderLeftWidth: 1,
    borderLeftColor: ds.line,
  },
  resumoItemPending: {
    borderLeftWidth: 1,
    borderLeftColor: ds.line,
  },
  resumoLabel: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: "800",
    textTransform: "uppercase",
    marginBottom: 2,
  },
  resumoValue: {
    fontSize: 17,
    lineHeight: 22,
    fontWeight: "900",
    color: colors.text,
  },
  resumoValuePaid: {
    color: ds.success,
  },
  resumoValuePending: {
    color: ds.danger,
  },
  progressTrack: {
    height: 8,
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
    borderRadius: 999,
    overflow: "hidden",
  },
  progressBar: {
    height: "100%",
    backgroundColor: ds.success,
    borderRadius: 999,
  },
  progressText: {
    fontSize: 13,
    fontWeight: "600",
    color: colors.textMuted,
  },
  pagamentosSection: {
    gap: 8,
    backgroundColor: ds.surfaceSunken,
    borderTopWidth: 1,
    borderTopColor: ds.line,
    padding: 16,
  },
  pagamentosTitle: {
    fontSize: 11,
    fontWeight: "800",
    textTransform: "uppercase",
    color: colors.textSecondary,
  },
  pagamentoItem: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
    flexWrap: "wrap",
    paddingVertical: 6,
  },
  pagamentoLeft: {
    flex: 1,
    gap: 2,
  },
  pagamentoValor: {
    fontSize: 17,
    fontWeight: "900",
    color: colors.text,
  },
  pagamentoData: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: "500",
    color: colors.textMuted,
  },
  emptyPayments: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    padding: 16,
  },
});
