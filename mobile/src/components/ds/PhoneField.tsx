import React, { useEffect, useRef, useState } from "react";
import { FlatList, Modal, Pressable, StyleSheet, Text, View } from "react-native";
import type { TextInputProps } from "react-native";
import { changePhoneCountry, countries, DEFAULT_COUNTRY, parsePhone, phoneEditor, PHONE_ERROR, validPhone } from "@appcheckin/phone";
import TextField from "./TextField";
import { ds, radius, space, type } from "./tokens";

type Props = TextInputProps & { label?: string; icon?: React.ComponentProps<typeof TextField>["icon"]; error?: string | null; hint?: string | null };

export default function PhoneField({ value = "", onChangeText, label, error, hint, editable = true, ...rest }: Props) {
  const initial = parsePhone(value);
  const [country, setCountry] = useState(initial?.country ?? DEFAULT_COUNTRY);
  const [display, setDisplay] = useState(initial ? initial.formatNational() : value);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [touched, setTouched] = useState(false);
  const lastEmitted = useRef(value);
  useEffect(() => {
    if (value === lastEmitted.current) return;
    const phone = parsePhone(value);
    if (phone?.country) setCountry(phone.country);
    setDisplay(phone ? phone.formatNational() : value);
    lastEmitted.current = value;
  }, [value]);
  function emit(next: string) {
    lastEmitted.current = next;
    onChangeText?.(next);
  }
  const filtered = countries.filter((item) => item.label.toLocaleLowerCase().includes(query.toLocaleLowerCase()) || item.country.toLowerCase() === query.toLowerCase());
  const selected = countries.find((item) => item.country === country)!;
  return (
    <View style={{ gap: space[2] }}>
      {label ? <Text style={{ ...type.label, color: ds.inkMuted }}>{label}</Text> : null}
      <Pressable disabled={!editable} accessibilityRole="button" accessibilityLabel={`País de ${label || "telefone"}: ${selected.label}`} onPress={() => { setQuery(""); setOpen(true); }} style={styles.country}>
        <Text style={{ color: ds.ink }}>{selected.label} ▾</Text>
      </Pressable>
      <TextField
        {...rest}
        keyboardType="phone-pad"
        autoComplete="tel-national"
        editable={editable}
        value={display}
        placeholder="Número de telefone"
        error={error || (touched && value && !validPhone(value) ? PHONE_ERROR : null)}
        hint={hint}
        onChangeText={(text) => {
          const state = phoneEditor(text, country);
          setCountry(state.country);
          setDisplay(state.display);
          emit(state.value);
        }}
        onBlur={(event) => { setTouched(true); rest.onBlur?.(event); }}
      />
      <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
        <View style={styles.overlay}>
          <View style={styles.sheet}>
            <TextField label="Buscar país ou DDI" value={query} onChangeText={setQuery} autoCorrect={false} />
            <FlatList data={filtered} keyExtractor={(item) => item.country} renderItem={({ item }) => (
              <Pressable accessibilityRole="button" accessibilityState={{ selected: item.country === country }} onPress={() => {
                const state = changePhoneCountry(display, item.country);
                setCountry(item.country);
                setDisplay(state.display);
                emit(state.value);
                setOpen(false);
              }} style={styles.item}>
                <Text style={{ color: ds.ink }}>{item.label}</Text>
              </Pressable>
            )} />
            <Pressable accessibilityRole="button" onPress={() => setOpen(false)} style={styles.item}><Text style={{ color: ds.ink }}>Fechar</Text></Pressable>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  country: { padding: space[3], borderWidth: 1, borderColor: ds.lineStrong, borderRadius: radius.md, backgroundColor: ds.surface },
  overlay: { flex: 1, justifyContent: "center", padding: space[4], backgroundColor: "rgba(0,0,0,0.5)" },
  sheet: { maxHeight: "80%", padding: space[4], borderRadius: radius.md, backgroundColor: ds.surface },
  item: { paddingVertical: space[3] },
});
