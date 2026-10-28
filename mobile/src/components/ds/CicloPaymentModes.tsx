import { Feather } from "@expo/vector-icons";
import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { ds, radius, space } from "./tokens";

type Props = {
  value: "avista" | "assinatura";
  onChange: (value: "avista" | "assinatura") => void;
  total: string;
  mensal: string;
  meses: number;
  fidelidadeMeses?: number;
  bloqueio?: string | null;
};

export default function CicloPaymentModes({ value, onChange, total, mensal, meses, fidelidadeMeses = meses, bloqueio }: Props) {
  return (
    <View style={styles.group} accessibilityRole="radiogroup" accessibilityLabel="Como pagar este ciclo">
      {([
        { id: "avista", title: "À vista", price: total, description: `${meses === 1 ? "1 mês" : `${meses} meses`} de acesso, uma cobrança` },
        { id: "assinatura", title: "Mensal", price: mensal, description: fidelidadeMeses ? `${fidelidadeMeses} cobranças, com fidelidade` : "Todo mês, sem fidelidade" },
      ] as const).map(option => {
        const disabled = option.id === "assinatura" && !!bloqueio;
        const selected = value === option.id;
        return (
          <Pressable key={option.id} disabled={disabled} onPress={() => onChange(option.id)}
            accessibilityRole="radio" accessibilityState={{ checked: selected, disabled }}
            accessibilityLabel={`${option.title}, ${option.price}${option.id === "assinatura" ? "/mês" : ""}. ${option.description}${disabled ? `. ${bloqueio}` : ""}`}
            style={[styles.option, selected && styles.selected, disabled && styles.disabled]}>
            <View style={styles.top}>
              <Text style={[styles.title, disabled && styles.muted]}>{option.title}</Text>
              <View style={[styles.radio, selected && styles.radioSelected]} aria-hidden>
                {selected ? <Feather name="check" size={11} color={ds.surface} /> : null}
              </View>
            </View>
            <Text numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8} style={[styles.price, disabled && styles.muted]}>{option.price}{option.id === "assinatura" ? <Text style={styles.unit}>/mês</Text> : null}</Text>
            <Text style={styles.description}>{option.description}</Text>
            {disabled ? (
              <View style={styles.note}>
                <Feather name="lock" size={12} color={ds.ink} style={{ marginTop: 2 }} />
                <Text style={styles.noteText}>{bloqueio}</Text>
              </View>
            ) : null}
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  group: { flexDirection: "row", alignItems: "stretch", gap: space[2] },
  option: { flex: 1, minWidth: 0, gap: 1, paddingVertical: 10, paddingHorizontal: 10, borderWidth: 1.5, borderColor: ds.line, borderRadius: radius.md, backgroundColor: ds.surface },
  selected: { borderWidth: 2, borderColor: ds.ink, paddingVertical: 9.5, paddingHorizontal: 9.5 },
  disabled: { backgroundColor: ds.surfaceSunken, borderWidth: 1 },
  top: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 6 },
  title: { fontSize: 13, lineHeight: 18, fontWeight: "800", color: ds.ink },
  radio: { width: 16, height: 16, borderRadius: radius.full, borderWidth: 2, borderColor: ds.lineStrong, alignItems: "center", justifyContent: "center" },
  radioSelected: { backgroundColor: ds.ink, borderColor: ds.ink },
  price: { fontSize: 17, lineHeight: 22, fontWeight: "900", letterSpacing: -0.17, color: ds.ink, fontVariant: ["tabular-nums"] },
  unit: { fontSize: 14, fontWeight: "800" },
  description: { fontSize: 11, lineHeight: 15, fontWeight: "600", color: ds.inkMuted },
  muted: { color: ds.inkMuted },
  note: { flexDirection: "row", gap: 4, marginTop: 4 },
  noteText: { flex: 1, fontSize: 11, lineHeight: 15, fontWeight: "700", color: ds.ink },
});
