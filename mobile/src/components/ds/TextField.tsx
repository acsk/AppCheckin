import { Feather } from "@expo/vector-icons";
import React, { forwardRef, useState } from "react";
import {
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TextInputProps,
  TouchableOpacity,
  View,
} from "react-native";
import { control, ds, radius, space, type } from "./tokens";

type Props = TextInputProps & {
  label?: string;
  icon?: React.ComponentProps<typeof Feather>["name"];
  error?: string | null;
  hint?: string | null;
  /** Mostra o botão de olho e controla secureTextEntry sozinho. */
  password?: boolean;
};

// Campo do design system: borda line-strong; em foco, borda ink de 2px
// (sem o contorno azul do navegador); com erro, borda e mensagem em danger.
const TextField = forwardRef<TextInput, Props>(function TextField(
  { label, icon, error, hint, password, style, onFocus, onBlur, ...rest },
  ref,
) {
  const [focused, setFocused] = useState(false);
  const [visible, setVisible] = useState(false);
  const borderColor = error ? ds.danger : focused ? ds.ink : ds.lineStrong;
  const borderWidth = error || focused ? 2 : 1.5;

  return (
    <View>
      {label ? <Text style={styles.label}>{label}</Text> : null}
      <View style={[styles.box, { borderColor, borderWidth, paddingLeft: space[4] - (borderWidth - 1.5) }]}>
        {icon ? (
          <Feather
            name={icon}
            size={20}
            color={focused || error ? ds.ink : ds.inkMuted}
            style={{ marginRight: space[2] }}
          />
        ) : null}
        <TextInput
          ref={ref}
          placeholderTextColor={ds.inkMuted}
          {...rest}
          secureTextEntry={password ? !visible : rest.secureTextEntry}
          style={[styles.input, style]}
          onFocus={(e) => {
            setFocused(true);
            onFocus?.(e);
          }}
          onBlur={(e) => {
            setFocused(false);
            onBlur?.(e);
          }}
          aria-invalid={error ? true : undefined}
        />
        {password ? (
          <TouchableOpacity
            style={styles.eye}
            onPress={() => setVisible((v) => !v)}
            accessibilityRole="button"
            accessibilityLabel={visible ? "Ocultar senha" : "Mostrar senha"}
          >
            <Feather name={visible ? "eye-off" : "eye"} size={20} color={ds.inkMuted} />
          </TouchableOpacity>
        ) : null}
      </View>
      {error ? (
        <View style={styles.msg} accessibilityLiveRegion="polite">
          <Feather name="alert-circle" size={16} color={ds.danger} />
          <Text style={[styles.msgText, { color: ds.danger }]}>{error}</Text>
        </View>
      ) : hint ? (
        <View style={styles.msg}>
          <Text style={styles.msgText}>{hint}</Text>
        </View>
      ) : null}
    </View>
  );
});

export default TextField;

const styles = StyleSheet.create({
  label: { ...type.label, color: ds.inkMuted, marginBottom: space[2] },
  box: {
    flexDirection: "row",
    alignItems: "center",
    height: control.md,
    borderRadius: radius.md,
    backgroundColor: ds.surface,
    paddingRight: space[1],
  },
  input: {
    flex: 1,
    minWidth: 0,
    height: "100%",
    fontSize: 16,
    fontWeight: "600",
    color: ds.ink,
    paddingRight: space[3],
    ...(Platform.OS === "web" ? ({ outlineStyle: "none", outlineWidth: 0 } as object) : null),
  },
  eye: {
    width: 44,
    height: 44,
    alignItems: "center",
    justifyContent: "center",
  },
  msg: { flexDirection: "row", alignItems: "center", gap: 6, marginTop: space[2] },
  msgText: { ...type.caption, color: ds.inkMuted, flexShrink: 1 },
});
