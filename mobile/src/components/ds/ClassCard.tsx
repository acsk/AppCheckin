import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import Badge from "./Badge";
import { ds, radius, space } from "./tokens";

export type ClassStatus = "open" | "checked" | "closed" | "full" | "blocked";

const STATUS: Record<ClassStatus, { tone: "neutral" | "danger" | "success" | "warning"; icon: React.ComponentProps<typeof Feather>["name"]; text: string } | null> = {
  open: null,
  closed: { tone: "neutral", icon: "x", text: "Encerrado" },
  blocked: { tone: "danger", icon: "lock", text: "Bloqueado" },
  checked: { tone: "success", icon: "check", text: "Check-in feito" },
  full: { tone: "warning", icon: "users", text: "Lotado" },
};

type Props = {
  start: string;
  end: string;
  instructor?: string | null;
  /** Nome da turma, quando diferente da modalidade. */
  title?: string | null;
  modality?: { name: string; icon?: string | null } | null;
  booked?: number | null;
  capacity?: number | null;
  /** Horário limite do check-in (HH:mm). */
  deadline?: string | null;
  /** Texto "Abre em …" quando o check-in ainda não abriu. */
  opensIn?: string | null;
  status?: ClassStatus;
  actionLabel?: string;
  onPress?: () => void;
};

// Card de aula do design system: horário em coluna própria (o maior texto do card),
// professor em MAIÚSCULAS, ocupação, prazo e estado com palavra + ícone.
export default function ClassCard({
  start,
  end,
  instructor,
  title,
  modality,
  booked,
  capacity,
  deadline,
  opensIn,
  status = "open",
  actionLabel = "Check-in",
  onPress,
}: Props) {
  const s = STATUS[status];
  const pct = capacity ? Math.min(100, Math.round((100 * (booked || 0)) / capacity)) : 0;
  const muted = status === "closed" || status === "blocked";

  return (
    <TouchableOpacity
      activeOpacity={0.8}
      disabled={!onPress}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`Aula das ${start} às ${end}${instructor ? `, ${instructor}` : ""}${s ? `, ${s.text}` : ""}`}
      style={[
        styles.card,
        status === "checked" && { borderColor: ds.success },
      ]}
    >
      <View
        style={[
          styles.time,
          status === "blocked" && { backgroundColor: ds.dangerSoft },
          status === "checked" && { backgroundColor: ds.successSoft },
        ]}
      >
        <Text style={[styles.start, muted && { color: ds.inkMuted }]}>{start}</Text>
        <Text style={styles.end}>até {end}</Text>
      </View>

      <View style={styles.body}>
        <View style={styles.top}>
          <Text style={styles.who} numberOfLines={1}>
            {(instructor || "Sem professor").toUpperCase()}
          </Text>
          {status === "open" && capacity ? (
            <View style={styles.bar} accessibilityElementsHidden>
              <View style={[styles.barFill, { width: `${pct}%` }]} />
            </View>
          ) : null}
        </View>

        {title ? (
          <Text style={styles.title} numberOfLines={1}>
            {title}
          </Text>
        ) : null}

        {status !== "blocked" && (capacity || deadline || opensIn) ? (
          <View style={styles.meta}>
            {capacity ? (
              <View style={styles.metaItem}>
                <Feather name="users" size={15} color={ds.inkMuted} />
                <Text style={styles.metaText}>
                  {booked || 0}/{capacity}
                </Text>
              </View>
            ) : null}
            {opensIn ? (
              <View style={styles.metaItem}>
                <Feather name="clock" size={15} color={ds.inkMuted} />
                <Text style={styles.metaText}>Abre em {opensIn}</Text>
              </View>
            ) : deadline ? (
              <View style={styles.metaItem}>
                <Feather name="clock" size={15} color={ds.inkMuted} />
                <Text style={styles.metaText}>Check-in até {deadline}</Text>
              </View>
            ) : null}
          </View>
        ) : null}

        <View style={styles.foot}>
          <View style={styles.badges}>
            {s ? (
              <Badge tone={s.tone} icon={s.icon}>
                {s.text}
              </Badge>
            ) : null}
            {modality?.name ? (
              <View style={[styles.modBadge]}>
                {modality.icon ? (
                  <MaterialCommunityIcons name={modality.icon as any} size={14} color={ds.info} />
                ) : (
                  <Feather name="activity" size={14} color={ds.info} />
                )}
                <Text style={styles.modText} numberOfLines={1}>
                  {modality.name}
                </Text>
              </View>
            ) : null}
          </View>
          {(status === "open" || status === "full") && onPress ? (
            <View style={styles.action}>
              <Text style={styles.actionText}>{actionLabel}</Text>
            </View>
          ) : onPress ? (
            <Feather name="chevron-right" size={20} color={ds.inkMuted} />
          ) : null}
        </View>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: "row",
    alignItems: "stretch",
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
    borderRadius: radius.lg,
    overflow: "hidden",
  },
  time: {
    width: 88,
    paddingVertical: space[4],
    paddingHorizontal: space[3],
    justifyContent: "center",
    borderRightWidth: 1,
    borderRightColor: ds.line,
    backgroundColor: ds.surfaceSunken,
  },
  start: {
    fontSize: 24,
    lineHeight: 26,
    fontWeight: "800",
    letterSpacing: -0.5,
    color: ds.ink,
    fontVariant: ["tabular-nums"],
  },
  end: { fontSize: 13, lineHeight: 18, fontWeight: "700", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  body: { flex: 1, minWidth: 0, paddingVertical: space[3], paddingHorizontal: space[4], gap: 6, justifyContent: "center" },
  top: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: space[2] },
  who: { flex: 1, fontSize: 16, lineHeight: 20, fontWeight: "800", letterSpacing: 0.2, color: ds.ink },
  title: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  bar: { width: 64, height: 4, borderRadius: radius.full, backgroundColor: ds.line, overflow: "hidden" },
  barFill: { height: "100%", backgroundColor: ds.ink, borderRadius: radius.full },
  meta: { flexDirection: "row", flexWrap: "wrap", columnGap: space[3], rowGap: 4 },
  metaItem: { flexDirection: "row", alignItems: "center", gap: 4 },
  metaText: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  foot: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: space[2] },
  badges: { flexDirection: "row", flexWrap: "wrap", gap: 6, flexShrink: 1 },
  modBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    height: 24,
    paddingHorizontal: 10,
    borderRadius: radius.full,
    backgroundColor: ds.infoSoft,
    maxWidth: 160,
  },
  modText: { fontSize: 12, lineHeight: 16, fontWeight: "800", letterSpacing: 0.48, textTransform: "uppercase", color: ds.info },
  action: {
    height: 36,
    paddingHorizontal: space[3],
    borderRadius: radius.sm,
    backgroundColor: ds.brand,
    alignItems: "center",
    justifyContent: "center",
  },
  actionText: { fontSize: 14, fontWeight: "800", color: ds.onBrand },
});
