import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, space } from "./tokens";

const NAV_BG = "#171a21";
const ON_NAV = "#ffffff";
const ON_NAV_MUTED = "#a3aab6";

type Props = {
  name: string;
  /** Não exibido: a modalidade fica no cabeçalho da tela. */
  modality?: string | null;
  detail?: string | null;
  /** "3x", "1" */
  freq: string;
  /** "semana", "aula" */
  freqUnit: string;
  /** "R$ 150" */
  price: string;
  /** padrão "/mês"; "/aula" para avulso */
  unit?: string;
  /** Não exibido: "a partir de, por mês" fica no `aside` da seção. */
  from?: boolean;
  /** "≈ R$ 12,50" */
  perClass?: string | null;
  features?: string[];
  tag?: string | null;
  current?: boolean;
  /** Só para leitor de tela. */
  actionLabel?: string;
  onSelect?: () => void;
};

// Um plano em uma linha, sem repetir informação: o quadro da frequência ("2x / semana")
// é o nome do plano → preço por mês → uma linha de apoio → seta redonda.
export default function PlanCard({
  name,
  detail,
  freq,
  freqUnit,
  price,
  unit = "/mês",
  perClass,
  features,
  tag,
  current,
  actionLabel,
  onSelect,
}: Props) {
  // O título só aparece quando diz algo que o quadro não diz ("Diária").
  const showTitle = !!name && !name.toLowerCase().startsWith(String(freq || "").toLowerCase());
  const meta = [detail, perClass ? `${perClass} por aula` : null, ...(features || [])].filter(Boolean);
  const cta = actionLabel || (current ? "Ver meu plano" : `Escolher ${name}`);
  const showTag = !!(tag || current);

  return (
    <View style={showTag ? { marginTop: 10 } : null}>
      <TouchableOpacity
        onPress={onSelect}
        activeOpacity={0.88}
        accessibilityRole="button"
        accessibilityLabel={[name, `${price}${unit}`, cta].join(", ")}
        style={[styles.card, current && styles.cardCurrent, showTag && { paddingTop: space[3] + 6 }]}
      >
        <View style={[styles.freq, current && styles.freqCurrent]} accessibilityElementsHidden>
          <Text style={[styles.freqN, { color: current ? ds.onBrand : ds.brandInk }]} numberOfLines={1} adjustsFontSizeToFit>
            {freq}
          </Text>
          <Text style={[styles.freqU, { color: current ? ds.onBrand : ds.brandInk }]} numberOfLines={1}>
            {freqUnit}
          </Text>
        </View>

        <View style={styles.info} accessibilityElementsHidden>
          {showTitle ? (
            <Text style={[styles.title, current && { color: ON_NAV }]} numberOfLines={1}>
              {name}
            </Text>
          ) : null}
          <View style={styles.amount}>
            <Text style={[styles.price, current && { color: ON_NAV }]}>{price}</Text>
            <Text style={[styles.unit, current && { color: ON_NAV_MUTED }]}>{unit}</Text>
          </View>
          {meta.length ? (
            <Text style={[styles.meta, current && { color: ON_NAV_MUTED }]} numberOfLines={1}>
              {meta.join(" · ")}
            </Text>
          ) : null}
        </View>

        <View style={[styles.go, current && styles.goCurrent]} accessibilityElementsHidden>
          <Feather name="chevron-right" size={20} color={current ? NAV_BG : ds.onBrand} />
        </View>
      </TouchableOpacity>

      {showTag ? (
        <View style={[styles.tag, current && styles.tagCurrent]} pointerEvents="none">
          <Feather name={current ? "check" : "award"} size={13} color={ds.onBrand} />
          <Text style={styles.tagText}>{current ? tag || "Seu plano" : tag}</Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    padding: space[3],
    borderRadius: 18,
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
  },
  cardCurrent: {
    backgroundColor: NAV_BG,
    borderColor: "rgba(255,255,255,0.06)",
    shadowColor: NAV_BG,
    shadowOffset: { width: 0, height: 14 },
    shadowOpacity: 0.35,
    shadowRadius: 18,
    elevation: 8,
  },
  freq: {
    width: 60,
    height: 60,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 4,
    backgroundColor: ds.brandSoft,
    borderWidth: 1,
    borderColor: "rgba(255,122,0,0.2)",
  },
  freqCurrent: { backgroundColor: ds.brand, borderColor: ds.brand },
  freqN: { fontSize: 26, lineHeight: 26, fontWeight: "900", letterSpacing: -1, fontVariant: ["tabular-nums"] },
  freqU: { fontSize: 10, lineHeight: 12, fontWeight: "800", letterSpacing: 0.4, textTransform: "uppercase", marginTop: 2 },
  info: { flex: 1, minWidth: 0, gap: 1 },
  title: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: ds.ink },
  amount: { flexDirection: "row", alignItems: "baseline", gap: 2 },
  price: { fontSize: 24, lineHeight: 28, fontWeight: "900", letterSpacing: -0.7, color: ds.ink, fontVariant: ["tabular-nums"] },
  unit: { fontSize: 13, fontWeight: "700", color: ds.inkMuted },
  meta: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  go: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.brand,
    shadowColor: ds.brandRed,
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.3,
    shadowRadius: 10,
    elevation: 3,
  },
  goCurrent: { backgroundColor: "#ffffff", shadowOpacity: 0, elevation: 0 },
  tag: {
    position: "absolute",
    top: -11,
    left: space[3],
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    height: 22,
    paddingHorizontal: 10,
    borderRadius: 999,
    backgroundColor: ds.brand,
  },
  tagCurrent: { backgroundColor: ds.brandYellow },
  tagText: { fontSize: 11, lineHeight: 14, fontWeight: "900", letterSpacing: 0.66, textTransform: "uppercase", color: ds.onBrand },
});
