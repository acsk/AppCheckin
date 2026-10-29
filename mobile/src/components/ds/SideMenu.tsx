import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React from "react";
import {
  ActivityIndicator,
  Image,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Svg, { Polygon } from "react-native-svg";
import Avatar from "./Avatar";
import Badge from "./Badge";
import Segmented from "./Segmented";
import { ds, radius, space, type } from "./tokens";
import { useThemePreference } from "@/src/theme/ThemePreference";

const LOGO_WHITE = require("../../../assets/images/appcheckin-logo-h-white.png");

type FeatherName = React.ComponentProps<typeof Feather>["name"];

export type SideMenuItem = {
  icon: FeatherName;
  /** Ícone do MaterialCommunityIcons no lugar do Feather (ex.: "whatsapp"). */
  mcIcon?: React.ComponentProps<typeof MaterialCommunityIcons>["name"];
  label: string;
  tone?: "brand" | "success" | "info" | null;
  hint?: string | null;
  hintTone?: "warning" | "danger" | null;
  count?: number | null;
  active?: boolean;
  onPress: () => void;
};

export type SideMenuGroup = { title?: string; items: SideMenuItem[] };

export type SideMenuStat = { value: string | number; label: string; icon?: "flame" };

export type SideMenuPlan = {
  name: string;
  status?: string | null;
  tone?: "success" | "warning" | "danger";
  /** Aulas usadas na semana. */
  used: number;
  /** Limite semanal do plano; 0 esconde a barra. */
  total: number;
  renews?: string | null;
  onPress?: () => void;
};

type Props = {
  user: { name: string; avatarUri?: string | null; roles?: string[]; memberSince?: string | null };
  gym?: { name: string; count: number; onSwitch?: () => void } | null;
  stats?: SideMenuStat[];
  plan?: SideMenuPlan | null;
  groups?: SideMenuGroup[];
  version?: string | null;
  updatingPhoto?: boolean;
  forcingUpdate?: boolean;
  onClose: () => void;
  onEditPhoto?: () => void;
  onLogout?: () => void;
  onForceUpdate?: () => void;
};

const TONE_BOX = {
  brand: { bg: ds.brandSoft, fg: ds.brandInk },
  success: { bg: ds.successSoft, fg: ds.success },
  info: { bg: ds.infoSoft, fg: ds.info },
} as const;

// Recortes low-poly do degradê da logo no canto do cabeçalho (como no design system).
function Facets() {
  return (
    <Svg style={StyleSheet.absoluteFill} viewBox="0 0 340 220" preserveAspectRatio="xMaxYMin slice" pointerEvents="none">
      <Polygon points="340,0 210,0 340,120" fill={ds.brandYellow} opacity={0.55} />
      <Polygon points="340,120 260,70 340,210" fill={ds.brandRed} opacity={0.45} />
      <Polygon points="210,0 150,0 262,70" fill={ds.brandYellow} opacity={0.25} />
      <Polygon points="196,26 256,8 242,72" fill="none" stroke={ds.onBrand} strokeOpacity={0.35} strokeWidth={1.5} />
    </Svg>
  );
}

// Menu lateral do app (substitui o ProfileDrawer): cabeçalho na marca com perfil e
// troca de academia → números → Meu plano → grupos de navegação → Sair.
export default function SideMenu({
  user,
  gym,
  stats = [],
  plan,
  groups = [],
  version,
  updatingPhoto,
  forcingUpdate,
  onClose,
  onEditPhoto,
  onLogout,
  onForceUpdate,
}: Props) {
  const insets = useSafeAreaInsets();
  const { preference, setPreference, error: themeError } = useThemePreference();
  const canSwitch = !!gym && gym.count > 1 && !!gym.onSwitch;
  const versionLabel = version
    ? version.toLowerCase().startsWith("v")
      ? version
      : `v${version}`
    : null;
  const foot = [user.memberSince ? `Membro desde ${user.memberSince}` : null, versionLabel]
    .filter(Boolean)
    .join(" · ");

  return (
    <View style={[styles.menu, { paddingBottom: insets.bottom }]} accessibilityViewIsModal accessibilityLabel="Menu">
      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        bounces={false}
        overScrollMode="never"
        contentInsetAdjustmentBehavior="never"
        removeClippedSubviews={false}
      >
        <LinearGradient
          colors={[ds.brandYellow, ds.brand, ds.brandRed]}
          locations={[0, 0.42, 1]}
          start={{ x: 0.33, y: 0 }}
          end={{ x: 0.67, y: 1 }}
          style={styles.hero}
        >
          <Facets />
          {/* Espaçamento da janela fixa: não recalcular a área segura conforme o cabeçalho rola. */}
          <View style={[styles.heroIn, { paddingTop: space[4] + insets.top }]}>
            <View style={styles.top}>
              <Image source={LOGO_WHITE} style={styles.logo} resizeMode="contain" accessibilityLabel="AppCheckin" />
              <TouchableOpacity style={styles.close} onPress={onClose} accessibilityRole="button" accessibilityLabel="Fechar menu">
                <Feather name="x" size={20} color={ds.onBrand} />
              </TouchableOpacity>
            </View>

            <View style={styles.me}>
              <View>
                <View style={styles.avatarRing}>
                  <Avatar name={user.name} uri={user.avatarUri} size={68} />
                </View>
                {onEditPhoto ? (
                  <TouchableOpacity
                    style={styles.cam}
                    onPress={onEditPhoto}
                    disabled={updatingPhoto}
                    accessibilityRole="button"
                    accessibilityLabel="Trocar foto"
                  >
                    {updatingPhoto ? (
                      <ActivityIndicator size="small" color="#ffffff" />
                    ) : (
                      <Feather name="camera" size={15} color="#ffffff" />
                    )}
                  </TouchableOpacity>
                ) : null}
              </View>
              <View style={styles.who}>
                <Text style={styles.name} numberOfLines={2}>
                  {user.name}
                </Text>
                {user.roles?.length ? (
                  <View style={styles.roles}>
                    {user.roles.map((r, i) => (
                      <View key={r} style={[styles.role, i === 0 && styles.roleMain]}>
                        <Text style={[styles.roleText, i === 0 && styles.roleTextMain]}>{r}</Text>
                      </View>
                    ))}
                  </View>
                ) : null}
              </View>
            </View>

            {gym ? (
              <TouchableOpacity
                style={styles.gym}
                activeOpacity={canSwitch ? 0.8 : 1}
                disabled={!canSwitch}
                onPress={gym.onSwitch}
                accessibilityRole={canSwitch ? "button" : undefined}
                accessibilityLabel={canSwitch ? `Academia ${gym.name}. Trocar de academia` : `Academia ${gym.name}`}
              >
                <Feather name="briefcase" size={18} color={ds.ink} />
                <View style={styles.gymText}>
                  <Text style={styles.gymLabel}>Academia</Text>
                  <Text style={styles.gymName} numberOfLines={1}>
                    {gym.name}
                  </Text>
                </View>
                {canSwitch ? (
                  <View style={styles.swap}>
                    <Feather name="repeat" size={16} color={ds.brandInk} />
                    <Text style={styles.swapText}>Trocar</Text>
                  </View>
                ) : null}
              </TouchableOpacity>
            ) : null}
          </View>
        </LinearGradient>

        {stats.length ? (
          <View style={styles.stats}>
            {stats.map((s, i) => (
              <View key={s.label} style={[styles.stat, i > 0 && styles.statDivided]}>
                <View style={styles.statValue}>
                  {s.icon === "flame" ? <MaterialCommunityIcons name="fire" size={20} color={ds.brandInk} /> : null}
                  <Text style={styles.statNum}>{s.value}</Text>
                </View>
                <Text style={styles.statLabel}>{s.label}</Text>
              </View>
            ))}
          </View>
        ) : null}

        <View style={styles.body}>
          {plan ? (
            <TouchableOpacity
              style={styles.plan}
              activeOpacity={0.85}
              disabled={!plan.onPress}
              onPress={plan.onPress}
              accessibilityRole={plan.onPress ? "button" : undefined}
            >
              <View style={styles.planHead}>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={styles.planLabel}>Meu plano</Text>
                  <Text style={styles.planName} numberOfLines={1}>
                    {plan.name}
                  </Text>
                </View>
                {plan.status ? (
                  <Badge
                    tone={plan.tone ?? "success"}
                    icon={plan.tone === "warning" ? "clock" : plan.tone === "danger" ? "alert-circle" : "check"}
                  >
                    {plan.status}
                  </Badge>
                ) : null}
              </View>
              {plan.total > 0 ? (
                <>
                  <View
                    style={styles.usage}
                    accessibilityLabel={`${plan.used} de ${plan.total} aulas nesta semana`}
                  >
                    {Array.from({ length: plan.total }, (_, i) => (
                      <View key={i} style={[styles.usageSeg, i < plan.used && styles.usageOn]} />
                    ))}
                  </View>
                  <View style={styles.planFoot}>
                    <Text style={styles.planFootText}>
                      <Text style={styles.planFootStrong}>
                        {Math.min(plan.used, plan.total)} de {plan.total}
                      </Text>{" "}
                      aulas nesta semana
                    </Text>
                    {plan.renews ? <Text style={styles.planFootText}>Renova {plan.renews}</Text> : null}
                  </View>
                </>
              ) : plan.renews ? (
                <Text style={styles.planFootText}>Renova {plan.renews}</Text>
              ) : null}
            </TouchableOpacity>
          ) : null}

          {groups
            .filter((g) => g.items.length)
            .map((g, gi) => (
              <View key={g.title ?? gi} style={styles.group}>
                {g.title ? <Text style={styles.groupTitle}>{g.title}</Text> : null}
                <View style={styles.list}>
                  {g.items.map((it, i) => {
                    const box = it.tone ? TONE_BOX[it.tone] : { bg: ds.surfaceSunken, fg: ds.ink };
                    return (
                      <TouchableOpacity
                        key={`${it.label}-${i}`}
                        style={[styles.item, i > 0 && styles.itemDivided]}
                        activeOpacity={0.7}
                        onPress={it.onPress}
                        accessibilityRole="button"
                      >
                        {it.active ? <View style={styles.activeBar} /> : null}
                        <View style={[styles.ico, { backgroundColor: box.bg }]}>
                          {it.mcIcon ? (
                            <MaterialCommunityIcons name={it.mcIcon} size={19} color={box.fg} />
                          ) : (
                            <Feather name={it.icon} size={19} color={box.fg} />
                          )}
                        </View>
                        <View style={styles.itemText}>
                          <Text style={styles.itemLabel} numberOfLines={1}>
                            {it.label}
                          </Text>
                          {it.hint ? (
                            <Text
                              style={[styles.itemHint, it.hintTone ? { color: ds[it.hintTone] } : null]}
                              numberOfLines={1}
                            >
                              {it.hint}
                            </Text>
                          ) : null}
                        </View>
                        {it.count != null ? (
                          <View style={styles.count}>
                            <Text style={styles.countText}>{it.count}</Text>
                          </View>
                        ) : null}
                        <Feather name="chevron-right" size={18} color={ds.inkMuted} />
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </View>
            ))}

          {Platform.OS === "web" ? (
            <View style={styles.group}>
              <Text style={styles.groupTitle}>Aparência</Text>
              <Segmented
                label="Tema da interface"
                value={preference}
                options={[
                  { id: "light", label: "Claro", icon: "sun" },
                  { id: "dark", label: "Escuro", icon: "moon" },
                  { id: "system", label: "Sistema", icon: "monitor" },
                ]}
                onChange={(next) => {
                  if (next === "light" || next === "dark" || next === "system") setPreference(next);
                }}
              />
              {themeError ? <Text style={styles.themeError} accessibilityRole="alert">{themeError}</Text> : null}
            </View>
          ) : null}

          {onForceUpdate ? (
            <TouchableOpacity
              style={styles.forceUpdate}
              onPress={onForceUpdate}
              disabled={forcingUpdate}
              accessibilityRole="button"
              accessibilityLabel={Platform.OS === "web" ? "Forçar atualização do app" : "Verificar atualização do app"}
            >
              {forcingUpdate ? (
                <ActivityIndicator size="small" color={ds.brandInk} />
              ) : (
                <Feather name="refresh-cw" size={18} color={ds.brandInk} />
              )}
              <Text style={styles.forceUpdateText}>
                {Platform.OS === "web" ? (forcingUpdate ? "Atualizando…" : "Forçar atualização") : forcingUpdate ? "Verificando…" : "Verificar atualização"}
              </Text>
            </TouchableOpacity>
          ) : null}

          {onLogout ? (
            <TouchableOpacity style={styles.logout} onPress={onLogout} accessibilityRole="button">
              <Feather name="log-out" size={19} color={ds.danger} />
              <Text style={styles.logoutText}>Sair da conta</Text>
            </TouchableOpacity>
          ) : null}

          {foot ? <Text style={styles.foot}>{foot}</Text> : null}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  menu: { flex: 1, minHeight: 0, backgroundColor: ds.ground },
  scroll: { flex: 1 },
  scrollContent: { flexGrow: 1 },
  hero: { overflow: "hidden", paddingBottom: 44 },
  heroIn: { paddingHorizontal: space[4], gap: space[4] },
  top: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  logo: { height: 26, width: 92 },
  close: {
    width: 40,
    height: 40,
    borderRadius: radius.full,
    backgroundColor: "rgba(23, 26, 33, 0.14)",
    alignItems: "center",
    justifyContent: "center",
  },
  me: { flexDirection: "row", alignItems: "center", gap: space[3] },
  avatarRing: { borderRadius: 37, borderWidth: 3, borderColor: "#ffffff", backgroundColor: "#ffffff" },
  cam: {
    position: "absolute",
    right: -4,
    bottom: -4,
    width: 28,
    height: 28,
    borderRadius: 14,
    borderWidth: 2,
    borderColor: "#ffffff",
    backgroundColor: ds.inverse,
    alignItems: "center",
    justifyContent: "center",
  },
  who: { flex: 1, minWidth: 0, gap: 6 },
  name: { fontSize: 20, lineHeight: 22, fontWeight: "900", letterSpacing: -0.4, textTransform: "uppercase", color: ds.onBrand },
  roles: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  role: { height: 22, paddingHorizontal: 9, borderRadius: radius.full, backgroundColor: "rgba(255, 255, 255, 0.55)", justifyContent: "center" },
  roleMain: { backgroundColor: ds.inverse },
  roleText: { fontSize: 11, fontWeight: "800", letterSpacing: 0.66, textTransform: "uppercase", color: ds.onBrand },
  roleTextMain: { color: "#ffffff" },
  gym: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: radius.md,
    backgroundColor: ds.surface,
  },
  gymText: { flex: 1, minWidth: 0 },
  gymLabel: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.66, textTransform: "uppercase", color: ds.inkMuted },
  gymName: { fontSize: 15, lineHeight: 20, fontWeight: "900", color: ds.ink },
  swap: { flexDirection: "row", alignItems: "center", gap: 4 },
  swapText: { fontSize: 13, fontWeight: "800", color: ds.brandInk },
  stats: {
    zIndex: 1,
    marginTop: -28,
    marginHorizontal: space[4],
    flexDirection: "row",
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
    borderRadius: radius.lg,
  },
  stat: { flex: 1, paddingVertical: space[3], paddingHorizontal: space[2], alignItems: "center" },
  statDivided: { borderLeftWidth: 1, borderLeftColor: ds.line },
  statValue: { flexDirection: "row", alignItems: "center", gap: 4 },
  statNum: { fontSize: 22, lineHeight: 26, fontWeight: "900", letterSpacing: -0.44, color: ds.ink, fontVariant: ["tabular-nums"] },
  statLabel: { fontSize: 11, lineHeight: 14, fontWeight: "700", color: ds.inkMuted, textAlign: "center" },
  body: { padding: space[4], paddingBottom: space[6], gap: space[5] },
  plan: {
    padding: space[4],
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: ds.line,
    backgroundColor: ds.surface,
    gap: space[3],
  },
  planHead: { flexDirection: "row", alignItems: "flex-start", justifyContent: "space-between", gap: space[2] },
  planLabel: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.88, textTransform: "uppercase", color: ds.inkMuted },
  planName: { fontSize: 18, lineHeight: 22, fontWeight: "900", letterSpacing: -0.18, color: ds.ink },
  usage: { flexDirection: "row", gap: 6 },
  usageSeg: { flex: 1, height: 8, borderRadius: radius.full, backgroundColor: ds.line },
  usageOn: { backgroundColor: ds.brand },
  planFoot: { flexDirection: "row", justifyContent: "space-between", gap: space[2] },
  planFootText: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  planFootStrong: { color: ds.ink, fontWeight: "900" },
  group: { gap: space[2] },
  groupTitle: { ...type.label, color: ds.inkMuted, paddingHorizontal: space[1] },
  themeError: { fontSize: 12, lineHeight: 16, color: ds.danger },
  list: { backgroundColor: ds.surface, borderWidth: 1, borderColor: ds.line, borderRadius: radius.lg, overflow: "hidden" },
  item: { flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 56, paddingVertical: 8, paddingHorizontal: space[3] },
  itemDivided: { borderTopWidth: 1, borderTopColor: ds.line },
  activeBar: { position: "absolute", left: 0, top: 10, bottom: 10, width: 3, borderTopRightRadius: 3, borderBottomRightRadius: 3, backgroundColor: ds.brand },
  ico: { width: 36, height: 36, borderRadius: 10, alignItems: "center", justifyContent: "center" },
  itemText: { flex: 1, minWidth: 0 },
  itemLabel: { fontSize: 16, lineHeight: 20, fontWeight: "800", color: ds.ink },
  itemHint: { fontSize: 12, lineHeight: 16, fontWeight: "700", color: ds.inkMuted },
  count: { minWidth: 22, height: 22, paddingHorizontal: 7, borderRadius: radius.full, backgroundColor: ds.brand, alignItems: "center", justifyContent: "center" },
  countText: { fontSize: 12, fontWeight: "900", color: ds.onBrand },
  forceUpdate: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: space[2],
    height: 48,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: ds.brand,
    backgroundColor: ds.brandSoft,
  },
  forceUpdateText: { fontSize: 16, fontWeight: "800", color: ds.brandInk },
  logout: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: space[2],
    height: 48,
    borderRadius: radius.md,
    backgroundColor: ds.dangerSoft,
  },
  logoutText: { fontSize: 16, fontWeight: "800", color: ds.danger },
  foot: { marginTop: -8, textAlign: "center", fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted },
});
