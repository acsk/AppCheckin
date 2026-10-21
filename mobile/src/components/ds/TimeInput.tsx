import React, { useRef } from "react";
import { StyleSheet, Text, TextInput, View } from "react-native";
import { type TempoPartes } from "@/src/utils/recordes";
import { ds, space } from "./tokens";

type Props = { label: string; value: TempoPartes; onChange: (value: TempoPartes) => void; disabled?: boolean };

export default function TimeInput({ label, value, onChange, disabled }: Props) {
  const segundos = useRef<TextInput>(null);
  const centesimos = useRef<TextInput>(null);
  return <View accessibilityLabel={label} style={styles.row}>
    {(["m", "s", "c"] as const).map((key, index) => <React.Fragment key={key}>
      {index ? <Text style={styles.sep}>{index === 1 ? ":" : ","}</Text> : null}
      <View style={styles.box}>
        <TextInput ref={key === "s" ? segundos : key === "c" ? centesimos : undefined}
          accessibilityLabel={`${label} ${key === "m" ? "minutos" : key === "s" ? "segundos" : "centésimos"}`}
          style={styles.input} placeholder={key === "m" ? "0" : "00"} placeholderTextColor={ds.inkMuted}
          keyboardType="number-pad" inputMode="numeric" editable={!disabled} value={value[key]} maxLength={key === "m" ? 7 : 2}
          onChangeText={text => {
            const next = text.replace(/\D/g, "");
            onChange({ ...value, [key]: next });
            if (next.length === 2 && key === "s") centesimos.current?.focus();
          }} onSubmitEditing={() => key === "m" ? segundos.current?.focus() : key === "s" ? centesimos.current?.focus() : undefined} />
        <Text style={styles.caption}>{key === "m" ? "min" : key === "s" ? "seg" : "cent"}</Text>
      </View>
    </React.Fragment>)}
  </View>;
}
const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "center", gap: space[2] },
  box: { flex: 1, minWidth: 0, backgroundColor: ds.surfaceSunken, borderWidth: 1.5, borderColor: ds.lineStrong, borderRadius: 14, padding: space[2], alignItems: "center" },
  input: { color: ds.ink, width: "100%", textAlign: "center", fontSize: 30, fontWeight: "800", fontVariant: ["tabular-nums"], padding: 4 },
  sep: { fontSize: 26, fontWeight: "800", color: ds.inkMuted },
  caption: { color: ds.inkMuted, fontSize: 12, fontWeight: "700" },
});
