import { AlunoResumoFinanceiro } from "@/src/components/AlunoResumoFinanceiro";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { Feather } from "@expo/vector-icons";
import { useLocalSearchParams, useRouter } from "expo-router";
import React from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { normalizeUtf8 } from "../src/utils/utf8";

import AppHeader from "@/src/components/ds/AppHeader";
import Avatar from "@/src/components/ds/Avatar";
import Badge from "@/src/components/ds/Badge";
import InfoChips, { InfoChip } from "@/src/components/ds/InfoChips";
import Section from "@/src/components/ds/Section";
import { ds, space } from "@/src/components/ds/tokens";

const getPhotoUrl = (foto?: string | null) => {
  if (!foto) return null;
  if (/^https?:\/\//i.test(foto)) return foto;
  return `${getApiUrlRuntime()}${foto}`;
};

// "2026-10-15 15:20:00" ou ISO → Date (sem deslocar o fuso de datas sem hora).
const parseData = (raw: string): Date | null => {
  if (!raw) return null;
  const s = raw.trim();
  const d = /^\d{4}-\d{2}-\d{2}$/.test(s) ? new Date(`${s}T12:00:00`) : new Date(s.replace(" ", "T"));
  return Number.isNaN(d.getTime()) ? null : d;
};

// "15/10/2026 às 15:20"
const formatDataHora = (raw: string) => {
  const d = parseData(raw);
  if (!d) return raw || null;
  const data = d.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric" });
  const temHora = /\d{2}:\d{2}/.test(raw);
  return temHora
    ? `${data} às ${d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`
    : data;
};

type Estado = "present" | "absent" | "pending";

const ESTADO: Record<Estado, { label: string; tone: "success" | "danger" | "neutral"; icon: "check" | "x" | "clock"; ring: string }> = {
  present: { label: "Presente", tone: "success", icon: "check", ring: ds.success },
  absent: { label: "Faltou", tone: "danger", icon: "x", ring: ds.danger },
  pending: { label: "Aguardando confirmação", tone: "neutral", icon: "clock", ring: ds.line },
};

export default function CheckinDetalhesScreen() {
  const router = useRouter();
  const params = useLocalSearchParams();

  const alunoNome = normalizeUtf8(String(params.alunoNome || "Aluno"));
  const turmaNome = normalizeUtf8(String(params.turmaNome || "Turma")).replace(/\s+-\s+/g, " · ");
  const horario = normalizeUtf8(String(params.horario || ""));
  const dataAulaRaw = String(params.dataAula || "");
  const presenteRaw = String(params.presente ?? "");
  const presente = presenteRaw === "true" ? true : presenteRaw === "false" ? false : null;
  const presencaConfirmadaEm = normalizeUtf8(String(params.presencaConfirmadaEm || ""));
  const dataCheckin = normalizeUtf8(String(params.dataCheckin || ""));
  const foto = typeof params.foto === "string" ? params.foto : "";
  const photoUrl = getPhotoUrl(foto);
  const alunoId = Number(params.alunoId || 0);
  const showResumoFinanceiro = String(params.showResumoFinanceiro || "") === "1";

  const estado: Estado = presente === true ? "present" : presente === false ? "absent" : "pending";
  const e = ESTADO[estado];

  const dataAula = parseData(dataAulaRaw);
  const chips: InfoChip[] = [];
  if (horario) chips.push({ icon: "clock", text: horario.replace(" - ", " – ") });
  if (dataAula) {
    const t = dataAula.toLocaleDateString("pt-BR", { weekday: "short", day: "2-digit", month: "2-digit" });
    chips.push({ icon: "calendar", text: t.charAt(0).toUpperCase() + t.slice(1).replace(".", "") });
  }

  const linhas = [
    dataCheckin ? { icon: "check-square" as const, label: "Check-in feito em", value: formatDataHora(dataCheckin) } : null,
    {
      icon: "user-check" as const,
      label: "Presença",
      value: presencaConfirmadaEm ? `Confirmada em ${formatDataHora(presencaConfirmadaEm)}` : "Ainda não confirmada pelo professor",
    },
  ].filter(Boolean) as { icon: "check-square" | "user-check"; label: string; value: string | null }[];

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      <AppHeader
        eyebrow="Detalhes do check-in"
        title={turmaNome}
        onBack={() => router.back()}
        bottom={chips.length ? <InfoChips items={chips} /> : undefined}
      />

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        {/* Aluno e situação da presença */}
        <View style={styles.card}>
          <View style={[styles.ring, { borderColor: e.ring }]}>
            <Avatar name={alunoNome} uri={photoUrl} size={72} />
          </View>
          {estado !== "pending" ? (
            <View style={[styles.mark, { backgroundColor: e.ring }]}>
              <Feather name={e.icon} size={14} color={ds.onInverse} />
            </View>
          ) : null}
          <Text style={styles.name}>{alunoNome.toUpperCase()}</Text>
          <View style={{ alignItems: "center" }}>
            <Badge tone={e.tone} icon={e.icon}>
              {e.label}
            </Badge>
          </View>
        </View>

        <Section title="Check-in">
          {linhas.map((l) => (
            <View key={l.label} style={styles.kvRow}>
              <View style={styles.kvIcon}>
                <Feather name={l.icon} size={18} color={ds.inkMuted} />
              </View>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text style={styles.kvK}>{l.label.toUpperCase()}</Text>
                <Text style={styles.kvV}>{l.value}</Text>
              </View>
            </View>
          ))}
        </Section>

        {showResumoFinanceiro && alunoId > 0 ? (
          <Section title="Financeiro" bare>
            <AlunoResumoFinanceiro alunoId={alunoId} />
          </Section>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  content: { padding: space[4], paddingBottom: space[8], gap: space[5] },
  card: {
    alignItems: "center",
    gap: 10,
    paddingVertical: space[5],
    paddingHorizontal: space[4],
    backgroundColor: ds.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: ds.line,
  },
  ring: { borderWidth: 3, borderRadius: 999, padding: 3, backgroundColor: ds.surface },
  mark: {
    marginTop: -26,
    marginLeft: 56,
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 2,
    borderColor: ds.surface,
  },
  name: {
    fontSize: 20,
    lineHeight: 24,
    fontWeight: "900",
    letterSpacing: -0.2,
    color: ds.ink,
    textAlign: "center",
  },
  kvRow: { flexDirection: "row", alignItems: "center", gap: space[3], paddingVertical: 10, paddingHorizontal: space[4] },
  kvIcon: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: ds.surfaceSunken,
    alignItems: "center",
    justifyContent: "center",
  },
  kvK: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.66, color: ds.inkMuted },
  kvV: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: ds.ink, marginTop: 2 },
});
