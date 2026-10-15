import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React from "react";
import {
  ActivityIndicator,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Svg, { Polygon } from "react-native-svg";
import { ds, space } from "./tokens";

type FeatherName = React.ComponentProps<typeof Feather>["name"];

export type HeaderAction = {
  icon: FeatherName;
  label: string;
  onPress?: () => void;
  loading?: boolean;
  disabled?: boolean;
};

type Props = {
  title: React.ReactNode;
  eyebrow?: React.ReactNode;
  subtitle?: React.ReactNode;
  /** default: degradê da logo · brand: hero da home (maior) · surface: barra branca (exceções). */
  variant?: "default" | "brand" | "surface";
  onBack?: () => void;
  leading?: React.ReactNode;
  /** No máximo duas ações redondas. */
  actions?: HeaderAction[];
  /** Conteúdo extra à direita (ex.: um switch). */
  right?: React.ReactNode;
  /** Conteúdo dentro do mesmo bloco laranja, abaixo do título (ex.: DayPicker). */
  bottom?: React.ReactNode;
  /** Soma a área segura do topo (desligue se a tela já a trata). */
  safeTop?: boolean;
};

// Recortes low-poly no degradê da logo, à direita do cabeçalho (BrandFacets do design system).
export function BrandFacets() {
  return (
    <Svg
      style={StyleSheet.absoluteFill}
      viewBox="0 0 390 180"
      preserveAspectRatio="xMaxYMin slice"
      pointerEvents="none"
    >
      <Polygon points="390,0 252,0 390,118" fill={ds.brandYellow} opacity={0.55} />
      <Polygon points="390,118 304,70 390,196" fill={ds.brandRed} opacity={0.45} />
      <Polygon points="252,0 190,0 304,70" fill={ds.brandYellow} opacity={0.25} />
      <Polygon points="0,180 70,180 0,128" fill={ds.brandRed} opacity={0.22} />
      <Polygon points="286,14 326,4 318,46" fill="none" stroke={ds.onBrand} strokeOpacity={0.3} strokeWidth={1.5} />
    </Svg>
  );
}

export function HeaderButton({ icon, label, onPress, loading, disabled, surface }: HeaderAction & { surface?: boolean }) {
  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={disabled || loading}
      accessibilityRole="button"
      accessibilityLabel={label}
      activeOpacity={0.7}
      style={[styles.btn, surface ? styles.btnSurface : null, disabled ? { opacity: 0.5 } : null]}
    >
      {loading ? (
        <ActivityIndicator size="small" color={surface ? ds.ink : ds.onBrand} />
      ) : (
        <Feather name={icon} size={20} color={surface ? ds.ink : ds.onBrand} />
      )}
    </TouchableOpacity>
  );
}

// Cabeçalho de todas as telas: degradê da logo com recortes, título 24/900 em grafite,
// ações redondas de 42px em grafite translúcido e cantos inferiores de 22px.
export default function AppHeader({
  title,
  eyebrow,
  subtitle,
  variant = "default",
  onBack,
  leading,
  actions,
  right,
  bottom,
  safeTop = true,
}: Props) {
  const insets = useSafeAreaInsets();
  const surface = variant === "surface";
  const hero = variant === "brand";
  const topPad = safeTop ? insets.top : 0;

  const row = (
    <View
      style={[
        styles.row,
        hero && styles.rowHero,
        bottom ? { paddingBottom: space[2] } : null,
      ]}
    >
      {onBack ? <HeaderButton icon="arrow-left" label="Voltar" onPress={onBack} surface={surface} /> : null}
      {leading}
      <View style={styles.titles}>
        {eyebrow ? (
          <Text style={[styles.sub, surface && styles.subSurface]} numberOfLines={1}>
            {eyebrow}
          </Text>
        ) : null}
        <Text
          style={[styles.title, hero && styles.titleHero, surface && { color: ds.ink }]}
          numberOfLines={hero ? 2 : 1}
          accessibilityRole="header"
        >
          {title}
        </Text>
        {subtitle ? (
          <Text style={[styles.sub, surface && styles.subSurface]} numberOfLines={1}>
            {subtitle}
          </Text>
        ) : null}
      </View>
      {right}
      {actions && actions.length ? (
        <View style={styles.actions}>
          {actions.slice(0, 2).map((a) => (
            <HeaderButton key={a.label} {...a} surface={surface} />
          ))}
        </View>
      ) : null}
    </View>
  );

  if (surface) {
    return (
      <View style={[styles.surface, { paddingTop: topPad }]}>
        {row}
        {bottom}
      </View>
    );
  }

  return (
    <View style={styles.shadowWrap}>
      <LinearGradient
        colors={[ds.brandYellow, ds.brand, ds.brandRed]}
        locations={[0, 0.48, 1]}
        start={{ x: 0.33, y: 0 }}
        end={{ x: 0.67, y: 1 }}
        style={[styles.brand, { paddingTop: topPad }]}
      >
        <BrandFacets />
        {row}
        {bottom ? <View style={styles.layer}>{bottom}</View> : null}
      </LinearGradient>
    </View>
  );
}

const styles = StyleSheet.create({
  shadowWrap: {
    zIndex: 2,
    borderBottomLeftRadius: 22,
    borderBottomRightRadius: 22,
    shadowColor: ds.brandRed,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.3,
    shadowRadius: 14,
    elevation: 6,
    backgroundColor: ds.brand,
  },
  brand: {
    overflow: "hidden",
    borderBottomLeftRadius: 22,
    borderBottomRightRadius: 22,
  },
  surface: {
    backgroundColor: ds.surface,
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  // Acima dos recortes: na web o SVG absoluto seria pintado por cima do conteúdo.
  layer: { position: "relative", zIndex: 1 },
  row: {
    position: "relative",
    zIndex: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingHorizontal: space[4],
    paddingVertical: space[4],
    minHeight: 72,
  },
  rowHero: { alignItems: "flex-end", paddingTop: space[5], paddingBottom: space[6] },
  titles: { flex: 1, minWidth: 0 },
  title: {
    fontSize: 24,
    lineHeight: 28,
    fontWeight: "900",
    letterSpacing: -0.5,
    color: ds.onBrand,
  },
  titleHero: { fontSize: 32, lineHeight: 36 },
  sub: { fontSize: 13, lineHeight: 18, fontWeight: "700", color: ds.onBrand },
  subSurface: { color: ds.inkMuted, fontWeight: "600" },
  actions: { flexDirection: "row", gap: space[2] },
  btn: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(23, 26, 33, 0.14)",
  },
  btnSurface: { backgroundColor: "transparent" },
});
