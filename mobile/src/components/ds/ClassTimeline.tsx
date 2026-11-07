import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import React, { useEffect, useRef, useState } from "react";
import { AccessibilityInfo, Animated, Easing, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import Avatar from "./Avatar";
import Badge from "./Badge";
import Button, { ButtonVariant } from "./Button";
import { ds, radius, space, toneColors } from "./tokens";

type FeatherName = React.ComponentProps<typeof Feather>["name"];

export type SlotStatus = "live" | "open" | "soon" | "checked" | "closed" | "full" | "blocked";

export type SlotAction = {
  label: string;
  icon?: FeatherName;
  variant?: ButtonVariant;
  disabled?: boolean;
  loading?: boolean;
  onPress?: () => void;
};

export type ClassSlotProps = {
  id: string | number;
  start: string;
  end: string;
  instructor: string;
  modality?: { name: string; icon?: string | null } | null;
  /** Linha de apoio abaixo do professor (nome da turma, sala). */
  room?: string | null;
  booked?: number | null;
  capacity?: number | null;
  status?: SlotStatus;
  reposicao?: boolean;
  /** Destaque visual independente das permissões de check-in/cancelamento. */
  inProgress?: boolean;
  /** HH:mm limite do check-in, para "Aberta até …". */
  deadline?: string | null;
  /** "3h" ou "27min", para "Abre em …". */
  opensIn?: string | null;
  /** Nomes de quem vai: mostra 3 rostos e "+n". */
  attendees?: string[];
  note?: string | null;
  action?: SlotAction | null;
  viewAction?: SlotAction | null;
  withoutTimeline?: boolean;
  compact?: boolean;
  onPress?: () => void;
};

const SLOT: Record<SlotStatus, { tone: "brand" | "neutral" | "success" | "warning" | "danger"; icon: FeatherName; text: string | null }> = {
  live: { tone: "brand", icon: "radio", text: "Em andamento" },
  open: { tone: "brand", icon: "zap", text: "Aberta" },
  soon: { tone: "neutral", icon: "clock", text: null },
  checked: { tone: "success", icon: "check", text: "Check-in feito" },
  closed: { tone: "neutral", icon: "x", text: "Encerrada" },
  full: { tone: "warning", icon: "users", text: "Lotada" },
  blocked: { tone: "danger", icon: "lock", text: "Bloqueada" },
};

const RAIL_W = 24;
const TIME_W = 52;
const COL_GAP = 8;
const ROW_GAP = space[3];

function useReducedMotion() {
  const [reduce, setReduce] = useState(true);
  useEffect(() => {
    let active = true;
    let changed = false;
    const subscription = AccessibilityInfo.addEventListener("reduceMotionChanged", value => {
      changed = true;
      if (active) setReduce(value);
    });
    AccessibilityInfo.isReduceMotionEnabled().then(value => {
      if (active && !changed) setReduce(value);
    }).catch(() => {});
    return () => { active = false; subscription.remove(); };
  }, []);
  return reduce;
}

export function PulseDot({ color = ds.brand, size = 18, ring = 2 }: { color?: string; size?: number; ring?: number }) {
  const pulse = useRef(new Animated.Value(0)).current;
  const reduce = useReducedMotion();
  useEffect(() => {
    pulse.setValue(0);
    if (reduce) return;
    const loop = Animated.loop(Animated.timing(pulse, {
      toValue: 1, duration: 1600, easing: Easing.out(Easing.cubic), useNativeDriver: true,
    }));
    loop.start();
    return () => loop.stop();
  }, [pulse, reduce]);
  return (
    <View style={[styles.dotWrap, { width: size, height: size }]}>
      {!reduce ? <Animated.View style={{
        position: "absolute", width: size, height: size, borderRadius: size / 2, borderWidth: 3, borderColor: color,
        opacity: pulse.interpolate({ inputRange: [0, 0.8, 1], outputRange: [0.9, 0, 0] }),
        transform: [{ scale: pulse.interpolate({ inputRange: [0, 1], outputRange: [1, ring] }) }],
      }} /> : null}
      <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: color }} />
    </View>
  );
}

function Blink() {
  const opacity = useRef(new Animated.Value(1)).current;
  const reduce = useReducedMotion();
  useEffect(() => {
    opacity.setValue(1);
    if (reduce) return;
    const loop = Animated.loop(Animated.sequence([
      Animated.timing(opacity, { toValue: 0.25, duration: 500, useNativeDriver: true }),
      Animated.timing(opacity, { toValue: 1, duration: 500, useNativeDriver: true }),
    ]));
    loop.start();
    return () => loop.stop();
  }, [opacity, reduce]);
  return <Animated.View style={[styles.liveIndicator, { opacity }]} />;
}

function Dot({ status }: { status: SlotStatus }) {
  if (status === "live") return <PulseDot color={ds.brandRed} size={22} ring={2.6} />;
  if (status === "open") return <PulseDot />;
  const fill =
    status === "checked" ? ds.success : status === "blocked" ? ds.danger : status === "full" ? ds.warning : status === "closed" ? ds.line : null;
  return (
    <View style={styles.dotWrap}>
      <View style={[styles.dot, fill ? { backgroundColor: fill, borderWidth: 0 } : null]}>
        {status === "checked" ? <Feather name="check" size={11} color={ds.onSuccess} /> : null}
        {status === "blocked" ? <Feather name="lock" size={10} color="#ffffff" /> : null}
      </View>
    </View>
  );
}

// Uma aula na linha do tempo: horário fixo à esquerda, ponto no trilho e o card.
export function ClassSlot(p: ClassSlotProps & { first?: boolean; last?: boolean }) {
  const st = p.status ?? "open";
  const s = SLOT[st];
  const live = !p.compact && st !== "blocked" && st !== "closed" && (p.inProgress || st === "live");
  const cap = Number(p.capacity) || 0;
  const booked = Number(p.booked) || 0;
  const pct = cap ? Math.max(0, Math.min(100, Math.round((100 * booked) / cap))) : 0;
  const left = cap ? cap - booked : null;
  const people = p.attendees ?? [];
  const chip =
    live ? `Em andamento · até ${p.end}` : st === "soon" ? (p.opensIn ? `Abre em ${p.opensIn}` : "Em breve") : st === "open" && p.deadline ? `Aberta até ${p.deadline}` : s.text;
  const muted = st === "closed" || st === "blocked";
  const act = p.action;
  const chipColors = toneColors[live ? "brand" : s.tone];
  const barColor = live ? ds.brandRed : st === "open" ? ds.brand : st === "full" ? ds.warning : st === "checked" ? ds.success : ds.ink;

  return (
    <View style={styles.slot}>
      {!p.withoutTimeline ? (
        <>
          <View style={styles.time}>
            <Text style={[styles.start, muted && { color: ds.inkMuted }, live && { color: ds.brandInk }]} maxFontSizeMultiplier={1.2}>{p.start}</Text>
            <Text style={styles.end} maxFontSizeMultiplier={1.2}>{p.end}</Text>
          </View>

          <View style={styles.rail} importantForAccessibility="no-hide-descendants">
            <View style={[styles.line, p.first && { top: 16 }, p.last && { bottom: undefined, height: 16 }]} />
            <View style={{ marginTop: live ? 12 : 14 }}><Dot status={live ? "live" : st} /></View>
          </View>
        </>
      ) : null}

      <TouchableOpacity
        activeOpacity={0.85}
        disabled={!p.onPress}
        onPress={p.onPress}
        accessibilityRole={p.onPress ? "button" : undefined}
        accessibilityLabel={`Aula das ${p.start} às ${p.end}, ${p.instructor}${chip ? `, ${chip}` : ""}`}
        style={[
          styles.card,
          p.compact && styles.cardCompact,
          st === "open" && styles.cardOpen,
          st === "checked" && styles.cardChecked,
          live && styles.cardLive,
          (muted || p.compact) && { backgroundColor: ds.surfaceSunken },
        ]}
      >
        {p.withoutTimeline ? (
          <Text style={styles.cardTime} maxFontSizeMultiplier={1.3}>
            {p.start} – {p.end}
          </Text>
        ) : null}
        {!p.compact && (chip || p.reposicao) ? (
          <View style={styles.status}>
            {chip ? (
              <View style={[styles.statusChip, { backgroundColor: chipColors.bg }]}>
                {live ? <Blink /> : <Feather name={s.icon} size={12} color={chipColors.fg} />}
                <Text style={[styles.statusText, { color: chipColors.fg }]} maxFontSizeMultiplier={1.3}>{chip}</Text>
              </View>
            ) : null}
            {p.reposicao ? <Badge tone="warning" icon="repeat">Reposição</Badge> : null}
            {live && (st === "checked" || st === "full") ? <Badge tone={st === "checked" ? "success" : "warning"}>{s.text}</Badge> : null}
          </View>
        ) : null}

        <View style={styles.head}>
          <Avatar name={p.instructor} size={p.compact ? 28 : 36} maxFontSizeMultiplier={1.1} />
          <View style={styles.who}>
            <Text style={styles.whoName} numberOfLines={2} maxFontSizeMultiplier={1.3}>
              {p.instructor.toUpperCase()}
            </Text>
            {p.modality?.name || p.room ? (
              <View style={styles.mod}>
                {p.modality?.icon ? <MaterialCommunityIcons name={p.modality.icon as any} size={14} color={ds.inkMuted} /> : null}
                <Text style={styles.whoSub} maxFontSizeMultiplier={1.3}>{[p.modality?.name, p.room].filter(Boolean).join(" · ")}</Text>
              </View>
            ) : null}
          </View>
        </View>

        {!p.compact && st !== "blocked" && cap ? (
          <View style={styles.cap}>
            <View
              style={styles.bar}
              accessibilityRole="progressbar"
              accessibilityLabel="Vagas ocupadas"
              accessibilityValue={{ min: 0, max: cap, now: Math.max(0, Math.min(booked, cap)) }}
            >
              <View style={[styles.barFill, { width: `${pct}%`, backgroundColor: barColor }]} />
            </View>
            <View style={styles.capLine}>
              <Text style={styles.capText} maxFontSizeMultiplier={1.3}>
                <Text style={styles.capStrong}>
                  {booked}/{cap}
                </Text>
                {left != null && left > 0 ? ` · ${left} ${left === 1 ? "vaga" : "vagas"}` : " · sem vagas"}
              </Text>
              {people.length ? (
                <View style={styles.faces}>
                  {people.slice(0, 3).map((n, i) => (
                    <View key={`${n}-${i}`} style={[styles.face, i > 0 && { marginLeft: -7 }]}>
                      <Avatar name={n} size={26} maxFontSizeMultiplier={1.1} />
                    </View>
                  ))}
                  {people.length > 3 ? <Text style={styles.more}>+{people.length - 3}</Text> : null}
                </View>
              ) : null}
            </View>
          </View>
        ) : null}

        {(act || p.viewAction) && !p.compact ? (
          <View style={styles.actions}>
            {p.note ? (
              <Text style={styles.note} maxFontSizeMultiplier={1.3}>
                {p.note}
              </Text>
            ) : (
              <View />
            )}
            {act ? (
              <Button
                size="sm"
                style={styles.actionButton}
                variant={act.variant ?? (st === "open" ? "primary" : "secondary")}
                icon={act.icon}
                disabled={act.disabled}
                loading={act.loading}
                onPress={act.onPress}
              >
                {act.label}
              </Button>
            ) : null}
            {p.viewAction ? (
              <Button size="sm" style={styles.actionButton} variant={p.viewAction.variant ?? "secondary"}
                icon={p.viewAction.icon} disabled={p.viewAction.disabled} loading={p.viewAction.loading} onPress={p.viewAction.onPress}>
                {p.viewAction.label}
              </Button>
            ) : null}
          </View>
        ) : null}
      </TouchableOpacity>
    </View>
  );
}

type TimelineProps = {
  items: ClassSlotProps[];
  /** HH:mm para o marcador "Agora". Sem ele, o marcador não aparece. */
  now?: string | null;
  /** Índice (entre as não encerradas) antes do qual o marcador aparece. */
  nowBefore?: number;
  collapsePast?: boolean;
  emptyText?: string;
};

// As aulas do dia como linha do tempo vertical, com o marcador Agora e as encerradas recolhidas.
export default function ClassTimeline({ items, now, nowBefore = 0, collapsePast = true, emptyText }: TimelineProps) {
  const [showPast, setShowPast] = useState(false);
  const past = collapsePast ? items.filter((x) => x.status === "closed") : [];
  const rest = collapsePast ? items.filter((x) => x.status !== "closed") : items;

  if (!items.length) {
    return (
      <View style={styles.empty}>
        <Text style={styles.emptyText}>{emptyText ?? "Nenhuma aula neste dia."}</Text>
      </View>
    );
  }

  const visible = [...(showPast ? past.map((x) => ({ ...x, compact: true })) : []), ...rest];
  const nowIdx = now ? (showPast ? past.length : 0) + Math.min(nowBefore, rest.length) : -1;

  const nowMarker = (
    <View style={styles.now} accessibilityLabel={`Agora, ${now}`}>
      <View style={styles.nowLabel}>
        <Text style={styles.nowTag}>AGORA</Text>
        <Text style={styles.nowTime}>{now}</Text>
      </View>
      <View style={styles.nowDotCol}>
        <View style={styles.nowDot} />
      </View>
      <View style={styles.nowLine} />
    </View>
  );

  return (
    <View style={styles.tl} accessibilityLabel="Aulas do dia">
      {past.length ? (
        <TouchableOpacity
          style={styles.fold}
          activeOpacity={0.8}
          onPress={() => setShowPast((v) => !v)}
          accessibilityRole="button"
          accessibilityState={{ expanded: showPast }}
        >
          <Feather name={showPast ? "minus" : "clock"} size={16} color={ds.inkMuted} />
          <Text style={styles.foldText}>
            {past.length} {past.length === 1 ? "aula encerrada" : "aulas encerradas"}
          </Text>
          <Text style={styles.foldAct}>{showPast ? "Ocultar" : "Mostrar"}</Text>
        </TouchableOpacity>
      ) : null}

      {visible.map((x, i) => (
        <React.Fragment key={String(x.id)}>
          {i === nowIdx ? nowMarker : null}
          <ClassSlot {...x} first={i === 0} last={i === visible.length - 1 && nowIdx !== visible.length} />
        </React.Fragment>
      ))}
      {nowIdx === visible.length ? nowMarker : null}
    </View>
  );
}

const styles = StyleSheet.create({
  tl: { gap: ROW_GAP },
  slot: { flexDirection: "row", alignItems: "stretch", columnGap: COL_GAP },
  time: { width: TIME_W, flexShrink: 0, paddingTop: 12, alignItems: "flex-end" },
  start: { fontSize: 17, lineHeight: 20, fontWeight: "900", letterSpacing: -0.34, color: ds.ink, fontVariant: ["tabular-nums"] },
  end: { fontSize: 12, lineHeight: 16, fontWeight: "700", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  rail: { width: RAIL_W, flexShrink: 0, alignItems: "center" },
  line: { position: "absolute", top: -ROW_GAP, bottom: -ROW_GAP, left: RAIL_W / 2 - 1, width: 2, backgroundColor: ds.line },
  dotWrap: { width: 18, height: 18, alignItems: "center", justifyContent: "center" },
  dot: {
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: ds.surface,
    borderWidth: 2,
    borderColor: ds.lineStrong,
    alignItems: "center",
    justifyContent: "center",
  },
  card: {
    flex: 1,
    minWidth: 0,
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
    borderRadius: radius.lg,
    padding: space[3],
    gap: space[3],
  },
  cardCompact: { paddingVertical: 10 },
  cardTime: { fontSize: 15, lineHeight: 20, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  cardOpen: { borderColor: ds.brand },
  cardLive: { borderColor: ds.brandRed, borderWidth: 2, backgroundColor: ds.brandSoft },
  cardChecked: { borderColor: ds.success, backgroundColor: ds.successSoft },
  status: { alignItems: "flex-start", gap: space[2] },
  statusChip: { flexDirection: "row", alignItems: "center", gap: 6, borderRadius: radius.md, paddingHorizontal: 10, paddingVertical: 4, maxWidth: "100%" },
  statusText: { flexShrink: 1, fontSize: 11, lineHeight: 15, fontWeight: "900", letterSpacing: 0.5, textTransform: "uppercase" },
  liveIndicator: { width: 8, height: 8, borderRadius: 4, backgroundColor: ds.brandRed },
  mod: { flexDirection: "row", alignItems: "flex-start", gap: 4, marginTop: 2 },
  head: { flexDirection: "row", alignItems: "center", gap: space[2], minWidth: 0 },
  who: { flex: 1, minWidth: 0 },
  whoName: { fontSize: 16, lineHeight: 19, fontWeight: "900", color: ds.ink },
  whoSub: { flex: 1, minWidth: 0, fontSize: 12, lineHeight: 16, fontWeight: "700", color: ds.inkMuted },
  cap: { gap: 6 },
  bar: { height: 6, borderRadius: radius.full, backgroundColor: ds.line, overflow: "hidden" },
  barFill: { height: "100%", borderRadius: radius.full },
  capLine: { flexDirection: "row", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: space[2] },
  capText: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  capStrong: { color: ds.ink, fontWeight: "900" },
  faces: { flexDirection: "row", alignItems: "center" },
  face: { borderRadius: 15, borderWidth: 2, borderColor: ds.surface },
  more: { marginLeft: 4, fontSize: 12, fontWeight: "800", color: ds.inkMuted },
  actions: {
    alignItems: "stretch",
    gap: space[2],
    paddingTop: space[3],
    borderTopWidth: 1,
    borderTopColor: ds.line,
    borderStyle: "dashed",
  },
  actionButton: { minHeight: 40 },
  note: { flexShrink: 1, minWidth: 0, fontSize: 12, lineHeight: 16, fontWeight: "700", color: ds.inkMuted },
  fold: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[2],
    height: 44,
    paddingHorizontal: space[3],
    borderRadius: radius.md,
    borderWidth: 1,
    borderStyle: "dashed",
    borderColor: ds.lineStrong,
  },
  foldText: { fontSize: 14, fontWeight: "800", color: ds.inkMuted },
  foldAct: { marginLeft: "auto", fontSize: 14, fontWeight: "800", color: ds.brandInk },
  now: { flexDirection: "row", alignItems: "center", columnGap: COL_GAP, marginVertical: -4 },
  nowLabel: { width: TIME_W, alignItems: "flex-end" },
  nowTag: { fontSize: 10, lineHeight: 12, fontWeight: "900", letterSpacing: 0.8, color: ds.brandInk },
  nowTime: { fontSize: 13, lineHeight: 16, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  nowDotCol: { width: RAIL_W, alignItems: "center" },
  nowDot: { width: 12, height: 12, borderRadius: 6, backgroundColor: ds.brandRed, borderWidth: 3, borderColor: ds.brandSoft },
  nowLine: { flex: 1, height: 2, backgroundColor: ds.brandRed, opacity: 0.6 },
  empty: { padding: space[6], borderRadius: radius.lg, borderWidth: 1, borderStyle: "dashed", borderColor: ds.lineStrong },
  emptyText: { textAlign: "center", fontWeight: "700", color: ds.inkMuted },
});
