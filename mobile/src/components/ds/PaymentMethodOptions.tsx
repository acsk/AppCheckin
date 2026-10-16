import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import {
  METODOS_PAGAMENTO_OPCOES,
  type MetodoPagamentoOpcao,
} from "@/src/utils/metodosPagamento";
import { ds, radius } from "./tokens";

type Props = {
  opcoes: MetodoPagamentoOpcao[];
  value: MetodoPagamentoOpcao | null;
  onChange: (id: MetodoPagamentoOpcao) => void;
};

export default function PaymentMethodOptions({ opcoes, value, onChange }: Props) {
  if (opcoes.length === 0) {
    return null;
  }

  return (
    <View accessibilityRole="radiogroup" accessibilityLabel="Forma de pagamento">
      <Text style={styles.title}>FORMA DE PAGAMENTO</Text>
      <View style={styles.row}>
        {METODOS_PAGAMENTO_OPCOES.filter((opcao) => opcoes.includes(opcao.id)).map(
          (opcao) => {
            const on = value === opcao.id;
            return (
              <TouchableOpacity
                key={opcao.id}
                activeOpacity={0.85}
                onPress={() => onChange(opcao.id)}
                accessibilityRole="radio"
                accessibilityState={{ selected: on }}
                style={[styles.card, on && styles.cardOn]}
              >
                <Feather
                  name={opcao.icon}
                  size={18}
                  color={on ? ds.brandInk : ds.inkMuted}
                />
                <Text style={[styles.label, on && styles.labelOn]}>{opcao.label}</Text>
                <Text style={styles.hint} numberOfLines={1}>
                  {opcao.hint}
                </Text>
              </TouchableOpacity>
            );
          },
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: 11,
    fontWeight: "800",
    letterSpacing: 0.66,
    color: ds.inkMuted,
    marginBottom: 8,
  },
  row: { flexDirection: "row", gap: 8 },
  card: {
    flex: 1,
    minWidth: 0,
    alignItems: "center",
    gap: 4,
    paddingVertical: 12,
    paddingHorizontal: 8,
    borderRadius: radius.md,
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
  },
  cardOn: {
    backgroundColor: ds.surface,
    borderColor: ds.brand,
  },
  label: { fontSize: 14, fontWeight: "800", color: ds.inkMuted },
  labelOn: { color: ds.ink },
  hint: { fontSize: 11, fontWeight: "600", color: ds.inkMuted, textAlign: "center" },
});
