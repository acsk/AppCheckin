import { colors } from "@/src/theme/colors";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { handleUnauthorizedResponse } from "@/src/utils/authHelpers";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import React, { useEffect, useState } from "react";
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
  forma_pagamento?: string | null;
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
  plano?: { nome: string; valor: number } | null;
  datas?: { vencimento?: string | null };
};

type Props = {
  alunoId: number;
  compact?: boolean;
};

const formatMoney = (value: number) => {
  const numeric =
    typeof value === "number" && !Number.isNaN(value) ? value : 0;
  return new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
  }).format(numeric);
};

const getStatusColor = (status: string) => {
  switch (status) {
    case "Pago":
      return "#4CAF50";
    case "Aguardando":
      return "#FF9800";
    case "Vencido":
      return "#f44336";
    default:
      return "#999";
  }
};

const formatDate = (value?: string | null) => {
  if (!value) return "—";
  return new Date(value).toLocaleDateString("pt-BR");
};

export function AlunoResumoFinanceiro({ alunoId, compact = false }: Props) {
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

  if (loading) {
    return (
      <View style={[styles.container, compact && styles.containerCompact]}>
        <ActivityIndicator size="small" color={colors.primary} />
        <Text style={styles.loadingText}>Carregando resumo...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={[styles.container, compact && styles.containerCompact]}>
        <Text style={styles.errorText}>{error}</Text>
      </View>
    );
  }

  if (!matricula) {
    return (
      <View style={[styles.container, compact && styles.containerCompact]}>
        <Text style={styles.emptyText}>Nenhuma matrícula encontrada</Text>
      </View>
    );
  }

  const progress =
    resumo && resumo.total_previsto > 0
      ? Math.min((resumo.total_pago / resumo.total_previsto) * 100, 100)
      : 0;

  return (
    <View style={[styles.container, compact && styles.containerCompact]}>
      <Text style={styles.sectionTitle}>Resumo financeiro</Text>

      <View style={styles.matriculaRow}>
        <View style={styles.matriculaInfo}>
          <Text style={styles.planoNome}>{matricula.plano?.nome || "Plano"}</Text>
          <Text style={styles.matriculaMeta}>
            {matricula.status}
            {matricula.datas?.vencimento
              ? ` · Vence ${formatDate(matricula.datas.vencimento)}`
              : ""}
          </Text>
        </View>
      </View>

      {resumo && (
        <>
          <View style={styles.resumoGrid}>
            <View style={styles.resumoItem}>
              <Text style={styles.resumoLabel}>Previsto</Text>
              <Text style={styles.resumoValue}>
                {formatMoney(resumo.total_previsto)}
              </Text>
            </View>
            <View style={[styles.resumoItem, styles.resumoItemPaid]}>
              <Text style={styles.resumoLabel}>Pago</Text>
              <Text style={[styles.resumoValue, styles.resumoValuePaid]}>
                {formatMoney(resumo.total_pago)}
              </Text>
            </View>
            <View style={[styles.resumoItem, styles.resumoItemPending]}>
              <Text style={styles.resumoLabel}>Pendente</Text>
              <Text style={[styles.resumoValue, styles.resumoValuePending]}>
                {formatMoney(resumo.total_pendente)}
              </Text>
            </View>
          </View>

          <View style={styles.progressTrack}>
            <View style={[styles.progressBar, { width: `${progress}%` }]} />
          </View>
          <Text style={styles.progressText}>
            {resumo.pagamentos_realizados} de {resumo.quantidade_pagamentos}{" "}
            pagamentos realizados
          </Text>
        </>
      )}

      {pagamentos.length > 0 && (
        <View style={styles.pagamentosSection}>
          <Text style={styles.pagamentosTitle}>Últimos pagamentos</Text>
          {pagamentos.map((pagamento) => (
            <View key={pagamento.id} style={styles.pagamentoItem}>
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
              </View>
              <View
                style={[
                  styles.statusBadge,
                  { backgroundColor: getStatusColor(pagamento.status) },
                ]}
              >
                <Text style={styles.statusBadgeText}>{pagamento.status}</Text>
              </View>
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
  );
}

const styles = StyleSheet.create({
  container: {
    width: "100%",
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#e5e7eb",
    padding: 12,
    gap: 10,
  },
  containerCompact: {
    marginTop: 8,
    maxHeight: 280,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: "800",
    color: colors.text,
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
    color: "#b3261e",
    textAlign: "center",
  },
  emptyText: {
    fontSize: 12,
    color: colors.textMuted,
  },
  matriculaRow: {
    flexDirection: "row",
    alignItems: "center",
  },
  matriculaInfo: {
    flex: 1,
    gap: 2,
  },
  planoNome: {
    fontSize: 14,
    fontWeight: "700",
    color: colors.text,
  },
  matriculaMeta: {
    fontSize: 12,
    color: colors.textSecondary,
    fontWeight: "600",
  },
  resumoGrid: {
    flexDirection: "row",
    gap: 8,
  },
  resumoItem: {
    flex: 1,
    borderWidth: 1,
    borderColor: "#e5e7eb",
    borderRadius: 8,
    padding: 8,
    backgroundColor: "#fff",
  },
  resumoItemPaid: {
    borderColor: "#4CAF50",
  },
  resumoItemPending: {
    borderColor: "#FF9800",
  },
  resumoLabel: {
    fontSize: 10,
    color: colors.textMuted,
    fontWeight: "600",
    marginBottom: 2,
  },
  resumoValue: {
    fontSize: 12,
    fontWeight: "800",
    color: colors.text,
  },
  resumoValuePaid: {
    color: "#4CAF50",
  },
  resumoValuePending: {
    color: "#FF9800",
  },
  progressTrack: {
    height: 6,
    backgroundColor: "#e5e7eb",
    borderRadius: 999,
    overflow: "hidden",
  },
  progressBar: {
    height: "100%",
    backgroundColor: "#4CAF50",
    borderRadius: 999,
  },
  progressText: {
    fontSize: 11,
    color: colors.textMuted,
  },
  pagamentosSection: {
    gap: 8,
  },
  pagamentosTitle: {
    fontSize: 12,
    fontWeight: "700",
    color: colors.textSecondary,
  },
  pagamentoItem: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
    backgroundColor: "#fff",
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#eef2f7",
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  pagamentoLeft: {
    flex: 1,
    gap: 2,
  },
  pagamentoValor: {
    fontSize: 13,
    fontWeight: "700",
    color: colors.text,
  },
  pagamentoData: {
    fontSize: 11,
    color: colors.textMuted,
  },
  statusBadge: {
    borderRadius: 999,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  statusBadgeText: {
    color: "#fff",
    fontSize: 10,
    fontWeight: "700",
  },
  emptyPayments: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
});
