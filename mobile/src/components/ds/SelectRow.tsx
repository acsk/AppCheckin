import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, space } from "./tokens";

type Props = {
  title: string;
  subtitle?: string | null;
  /** Linha pequena acima do título (ex.: "Último usado"). */
  tag?: string | null;
  /** Iniciais no quadro (padrão: a partir do título). */
  mark?: string | null;
  selected?: boolean;
  disabled?: boolean;
  onSelect?: () => void;
};

const iniciais = (t: string) => {
  const p = t.trim().split(/\s+/).filter(Boolean);
  return ((p[0]?.[0] || "") + (p.length > 1 ? p[p.length - 1][0] : p[0]?.[1] || "")).toUpperCase();
};

// Opção de escolha única dentro de uma folha (rádio). Selecionada: fundo brand-soft,
// contorno brand de 2px, iniciais em degradê e rádio laranja com ✓.
export default function SelectRow({ title, subtitle, tag, mark, selected, disabled, onSelect }: Props) {
  const texto = mark || iniciais(title);
  return (
    <TouchableOpacity
      onPress={onSelect}
      disabled={disabled}
      activeOpacity={0.85}
      accessibilityRole="radio"
      accessibilityState={{ checked: !!selected, disabled: !!disabled }}
      accessibilityLabel={[tag, title, subtitle].filter(Boolean).join(", ")}
      style={[styles.row, selected && styles.rowOn, disabled && { opacity: 0.5 }]}
    >
      {selected ? (
        <LinearGradient colors={[ds.brandYellow, ds.brand, ds.brandRed]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.mark}>
          <Text style={[styles.markText, { color: ds.onBrand }]}>{texto}</Text>
        </LinearGradient>
      ) : (
        <View style={[styles.mark, { backgroundColor: ds.inverse }]}>
          <Text style={[styles.markText, { color: ds.onInverse }]}>{texto}</Text>
        </View>
      )}
      <View style={styles.text}>
        {tag ? <Text style={[styles.tag, selected && { color: ds.brandInk }]}>{tag}</Text> : null}
        <Text style={styles.title} numberOfLines={1}>
          {title}
        </Text>
        {subtitle ? (
          <Text style={styles.sub} numberOfLines={1}>
            {subtitle}
          </Text>
        ) : null}
      </View>
      <View style={[styles.radio, selected && styles.radioOn]}>
        {selected ? <Feather name="check" size={14} color={ds.onBrand} /> : null}
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    minHeight: 72,
    paddingVertical: space[3],
    paddingHorizontal: space[4],
    borderRadius: 18,
    backgroundColor: ds.surface,
    borderWidth: 1.5,
    borderColor: ds.line,
  },
  rowOn: { backgroundColor: ds.brandSoft, borderWidth: 2, borderColor: ds.brand },
  mark: { width: 44, height: 44, borderRadius: 14, alignItems: "center", justifyContent: "center" },
  markText: { fontSize: 16, fontWeight: "900", letterSpacing: -0.3 },
  text: { flex: 1, minWidth: 0, gap: 2 },
  tag: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.88, textTransform: "uppercase", color: ds.inkMuted },
  title: { fontSize: 17, lineHeight: 22, fontWeight: "800", letterSpacing: -0.2, color: ds.ink },
  sub: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  radio: { width: 24, height: 24, borderRadius: 12, borderWidth: 2, borderColor: ds.lineStrong, alignItems: "center", justifyContent: "center" },
  radioOn: { backgroundColor: ds.brand, borderWidth: 0 },
});
