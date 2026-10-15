import { Feather } from "@expo/vector-icons";
import React, { useState } from "react";
import {
  ActivityIndicator,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import Avatar from "./Avatar";
import Badge from "./Badge";
import Button from "./Button";
import { ds, space } from "./tokens";

export type StudentResult = {
  key: string | number;
  name: string;
  detail?: string | null;
  avatarUri?: string | null;
  added?: boolean;
  loading?: boolean;
  extra?: React.ReactNode;
};

type Props = {
  value: string;
  onChangeText: (v: string) => void;
  onSubmit?: () => void;
  onClear?: () => void;
  results?: StudentResult[];
  onAdd?: (r: StudentResult) => void;
  searching?: boolean;
  error?: string | null;
  /** Mostra a lista (com "Nenhum aluno encontrado" quando vazia) só depois de buscar. */
  searched?: boolean;
};

// Busca de aluno por nome, CPF ou e-mail para adicionar à turma.
export default function StudentSearch({
  value,
  onChangeText,
  onSubmit,
  onClear,
  results = [],
  onAdd,
  searching,
  error,
  searched,
}: Props) {
  const [focused, setFocused] = useState(false);
  const has = !!value && value.trim().length > 0;

  return (
    <View style={styles.wrap}>
      <View style={[styles.field, focused && styles.fieldFocused]}>
        <Feather name="search" size={20} color={focused ? ds.ink : ds.inkMuted} />
        <TextInput
          value={value}
          onChangeText={onChangeText}
          onSubmitEditing={onSubmit}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          placeholder="Nome, CPF ou e-mail"
          placeholderTextColor={ds.inkMuted}
          accessibilityLabel="Buscar aluno"
          autoCapitalize="none"
          autoCorrect={false}
          returnKeyType="search"
          style={styles.input}
        />
        {searching ? (
          <ActivityIndicator size="small" color={ds.ink} style={{ marginRight: 8 }} />
        ) : has && onClear ? (
          <TouchableOpacity style={styles.clear} onPress={onClear} accessibilityLabel="Limpar busca">
            <Feather name="x" size={16} color={ds.ink} />
          </TouchableOpacity>
        ) : null}
        {has && onSubmit ? (
          <TouchableOpacity
            style={styles.go}
            onPress={onSubmit}
            disabled={searching}
            accessibilityRole="button"
            accessibilityLabel="Buscar"
          >
            <Feather name="arrow-right" size={18} color={ds.onBrand} />
          </TouchableOpacity>
        ) : null}
      </View>

      {error ? (
        <View style={styles.msg}>
          <Feather name="alert-circle" size={16} color={ds.danger} />
          <Text style={[styles.msgText, { color: ds.danger }]}>{error}</Text>
        </View>
      ) : null}

      {has && (results.length > 0 || searched) ? (
        <View style={styles.results}>
          {results.length ? (
            results.map((r, i) => (
              <View key={r.key} style={[styles.row, i > 0 && styles.rowDivider]}>
                <Avatar name={r.name} uri={r.avatarUri} size={40} />
                <View style={styles.who}>
                  <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                    <Text style={styles.name} numberOfLines={1}>
                      {r.name.toUpperCase()}
                    </Text>
                    {r.extra}
                  </View>
                  {r.detail ? (
                    <Text style={styles.detail} numberOfLines={1}>
                      {r.detail}
                    </Text>
                  ) : null}
                </View>
                {r.added ? (
                  <Badge tone="success" icon="check">
                    Na turma
                  </Badge>
                ) : (
                  <Button size="sm" variant="dark" icon="plus" loading={r.loading} onPress={() => onAdd?.(r)}>
                    Adicionar
                  </Button>
                )}
              </View>
            ))
          ) : (
            <Text style={styles.empty}>Nenhum aluno encontrado.</Text>
          )}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: space[2] },
  field: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[2],
    height: 52,
    paddingLeft: space[4],
    paddingRight: 6,
    borderRadius: 14,
    backgroundColor: ds.surface,
    borderWidth: 1.5,
    borderColor: ds.lineStrong,
  },
  fieldFocused: { borderWidth: 2, borderColor: ds.brand },
  input: {
    flex: 1,
    minWidth: 0,
    height: "100%",
    fontSize: 16,
    fontWeight: "700",
    color: ds.ink,
    ...(Platform.OS === "web" ? ({ outlineStyle: "none" } as object) : null),
  },
  clear: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: ds.surfaceSunken,
    alignItems: "center",
    justifyContent: "center",
  },
  go: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: ds.brand,
    alignItems: "center",
    justifyContent: "center",
  },
  msg: { flexDirection: "row", alignItems: "center", gap: 6 },
  msgText: { fontSize: 13, fontWeight: "600" },
  results: {
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
    borderRadius: 14,
    overflow: "hidden",
  },
  row: { flexDirection: "row", alignItems: "center", gap: space[3], paddingVertical: 10, paddingHorizontal: space[3] },
  rowDivider: { borderTopWidth: 1, borderTopColor: ds.line },
  who: { flex: 1, minWidth: 0 },
  name: { fontSize: 15, lineHeight: 19, fontWeight: "900", color: ds.ink, flexShrink: 1 },
  detail: { fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted },
  empty: { padding: space[4], textAlign: "center", fontWeight: "700", color: ds.inkMuted },
});
