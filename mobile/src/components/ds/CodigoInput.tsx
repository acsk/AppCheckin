import React, { useRef, useState } from "react";
import { Platform, Pressable, StyleSheet, Text, TextInput, View } from "react-native";
import { ds, radius, space, type } from "./tokens";

type Props = {
  value: string;
  onChange: (v: string) => void;
  /** Chamado quando os dígitos completam o tamanho (ex.: confirmar na hora). */
  onCompleto?: (v: string) => void;
  tamanho?: number;
  label?: string;
  error?: string | null;
  disabled?: boolean;
  autoFocus?: boolean;
};

/**
 * Código numérico em caixas grandes (uma por dígito). Por baixo é um único campo invisível sobre as
 * caixas: colar, autopreenchimento do código (one-time-code) e apagar funcionam como num campo comum.
 */
export default function CodigoInput({ value, onChange, onCompleto, tamanho = 6, label = "Código", error, disabled, autoFocus }: Props) {
  const ref = useRef<TextInput>(null);
  const [foco, setFoco] = useState(false);
  const atual = Math.min(value.length, tamanho - 1);

  const mudar = (texto: string) => {
    const v = texto.replace(/\D/g, "").slice(0, tamanho);
    onChange(v);
    if (v.length === tamanho && v !== value) onCompleto?.(v);
  };

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label}</Text>
      <Pressable onPress={() => ref.current?.focus()} disabled={disabled} accessible={false}>
        <View style={styles.row}>
          {Array.from({ length: tamanho }, (_, i) => {
            const ativo = foco && i === atual;
            return (
              <View key={i} style={[styles.box, !!value[i] && styles.boxPreenchida, ativo && styles.boxAtiva, !!error && styles.boxErro, disabled && styles.boxDesativada]}>
                <Text style={styles.digito}>{value[i] ?? ""}</Text>
              </View>
            );
          })}
        </View>
        <TextInput
          ref={ref}
          value={value}
          onChangeText={mudar}
          onFocus={() => setFoco(true)}
          onBlur={() => setFoco(false)}
          maxLength={tamanho}
          keyboardType="number-pad"
          inputMode="numeric"
          textContentType="oneTimeCode"
          autoComplete={Platform.OS === "android" ? "sms-otp" : "one-time-code"}
          autoFocus={autoFocus}
          editable={!disabled}
          caretHidden
          accessibilityLabel={`${label}, ${tamanho} dígitos`}
          style={styles.input}
        />
      </Pressable>
      {error ? <Text style={styles.erro}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: space[2] },
  label: { ...type.label, color: ds.inkMuted },
  row: { flexDirection: "row", justifyContent: "space-between", gap: space[2] },
  box: {
    flex: 1,
    maxWidth: 56,
    height: 60,
    borderRadius: radius.md,
    borderWidth: 1.5,
    borderColor: ds.line,
    backgroundColor: ds.surface,
    alignItems: "center",
    justifyContent: "center",
  },
  boxPreenchida: { borderColor: ds.lineStrong },
  boxAtiva: { borderColor: ds.brand, borderWidth: 2 },
  boxErro: { borderColor: ds.danger },
  boxDesativada: { opacity: 0.5 },
  digito: { fontSize: 28, fontWeight: "700", color: ds.ink, fontVariant: ["tabular-nums"] },
  // Campo real cobre as caixas (recebe toque, colar e autopreenchimento), mas não aparece
  input: { ...StyleSheet.absoluteFillObject, opacity: 0, color: "transparent", fontSize: 16 },
  erro: { fontSize: 13, color: ds.danger },
});
