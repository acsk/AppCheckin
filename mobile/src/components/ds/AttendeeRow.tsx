import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React from "react";
import { ActivityIndicator, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import Avatar from "./Avatar";
import { ds, space } from "./tokens";

export type AttendeeState = "pending" | "present" | "absent";

const LABEL: Record<AttendeeState, string> = { pending: "Aguardando", present: "Presente", absent: "Faltou" };

type Props = {
  name: string;
  avatarUri?: string | null;
  state?: AttendeeState;
  note?: string | null;
  /** Conteúdo junto ao nome (ex.: selo de aniversário). */
  extra?: React.ReactNode;
  onPress?: () => void;
  onPresent?: () => void;
  onAbsent?: () => void;
  onRemove?: () => void;
  removing?: boolean;
};

// Aluno na chamada: foto com anel e marca do estado, nome em MAIÚSCULAS e
// alternador Presente/Faltou. Sem onPresent/onAbsent, a linha é só leitura.
export default function AttendeeRow({
  name,
  avatarUri,
  state = "pending",
  note,
  extra,
  onPress,
  onPresent,
  onAbsent,
  onRemove,
  removing,
}: Props) {
  const ring = state === "present" ? ds.success : state === "absent" ? ds.danger : ds.line;
  const stateColor = state === "present" ? ds.success : state === "absent" ? ds.danger : ds.inkMuted;
  const toggles = !!(onPresent || onAbsent);

  const body = (
    <>
      {state !== "pending" ? (
        <LinearGradient
          colors={[state === "present" ? ds.successSoft : ds.dangerSoft, "rgba(255,255,255,0)"]}
          locations={[0, 0.7]}
          start={{ x: 0, y: 0.5 }}
          end={{ x: 1, y: 0.5 }}
          style={StyleSheet.absoluteFill}
          pointerEvents="none"
        />
      ) : null}
      <View style={styles.photo}>
        <View style={[styles.ring, { borderColor: ring }]}>
          <Avatar name={name} uri={avatarUri} size={44} />
        </View>
        {state !== "pending" ? (
          <View style={[styles.mark, { backgroundColor: state === "present" ? ds.success : ds.danger }]}>
            <Feather name={state === "present" ? "check" : "x"} size={11} color={ds.onInverse} />
          </View>
        ) : null}
      </View>
      <View style={styles.text}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
          <Text style={styles.name} numberOfLines={2}>
            {name.toUpperCase()}
          </Text>
          {extra}
        </View>
        <Text style={[styles.state, { color: stateColor }]}>
          {LABEL[state]}
          {note ? ` · ${note}` : ""}
        </Text>
      </View>
      {toggles ? (
        <View style={styles.toggle} accessibilityRole="radiogroup" accessibilityLabel={`Presença de ${name}`}>
          <TouchableOpacity
            onPress={onPresent}
            accessibilityRole="button"
            accessibilityLabel="Presente"
            accessibilityState={{ selected: state === "present" }}
            style={[styles.tbtn, state === "present" && { backgroundColor: ds.success }]}
          >
            <Feather name="check" size={18} color={state === "present" ? ds.onInverse : ds.inkMuted} />
          </TouchableOpacity>
          <TouchableOpacity
            onPress={onAbsent}
            accessibilityRole="button"
            accessibilityLabel="Faltou"
            accessibilityState={{ selected: state === "absent" }}
            style={[styles.tbtn, state === "absent" && { backgroundColor: ds.danger }]}
          >
            <Feather name="x" size={18} color={state === "absent" ? ds.onInverse : ds.inkMuted} />
          </TouchableOpacity>
        </View>
      ) : null}
      {onRemove ? (
        <TouchableOpacity
          onPress={onRemove}
          disabled={removing}
          style={styles.rm}
          accessibilityRole="button"
          accessibilityLabel={`Remover ${name} da turma`}
        >
          {removing ? (
            <ActivityIndicator size="small" color={ds.danger} />
          ) : (
            <Feather name="user-minus" size={18} color={ds.inkMuted} />
          )}
        </TouchableOpacity>
      ) : null}
    </>
  );

  if (onPress) {
    return (
      <TouchableOpacity style={styles.row} onPress={onPress} activeOpacity={0.8}>
        {body}
      </TouchableOpacity>
    );
  }
  return <View style={styles.row}>{body}</View>;
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingVertical: 10,
    paddingLeft: space[4],
    paddingRight: space[3],
    overflow: "hidden",
  },
  photo: { position: "relative" },
  ring: { borderWidth: 2, borderRadius: 999, padding: 2, backgroundColor: ds.surface },
  mark: {
    position: "absolute",
    right: -3,
    bottom: -3,
    width: 18,
    height: 18,
    borderRadius: 9,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 2,
    borderColor: ds.surface,
  },
  text: { flex: 1, minWidth: 0 },
  name: { fontSize: 15, lineHeight: 19, fontWeight: "900", color: ds.ink, flexShrink: 1 },
  state: { marginTop: 2, fontSize: 12, lineHeight: 16, fontWeight: "700" },
  toggle: {
    flexDirection: "row",
    padding: 3,
    gap: 3,
    borderRadius: 14,
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
  },
  tbtn: { width: 40, height: 38, borderRadius: 11, alignItems: "center", justifyContent: "center" },
  rm: { width: 36, height: 36, borderRadius: 18, alignItems: "center", justifyContent: "center" },
});
