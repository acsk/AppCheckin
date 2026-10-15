import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React from "react";
import {
  Modal,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { BrandFacets } from "./AppHeader";
import Button from "./Button";
import { ds, radius, space } from "./tokens";

type TenantId = string | number;
type Academy = {
  id?: TenantId;
  nome?: string;
  name?: string;
  cidade?: string;
};

export type TenantOption = Academy & {
  tenant?: Academy;
  status?: string;
  papeis?: { nome?: string; papel_nome?: string }[];
};

type Props = {
  visible: boolean;
  userName?: string;
  options: TenantOption[];
  selectedId: TenantId | null;
  lastAccessId: TenantId | null;
  remember: boolean;
  loading: boolean;
  error?: string;
  onSelect: (id: TenantId) => void;
  onRememberChange: (remember: boolean) => void;
  onConfirm: (option: TenantOption) => void;
  onClose: () => void;
};

const sameId = (a: TenantId | null | undefined, b: TenantId | null | undefined) =>
  a != null && b != null && String(a) === String(b);

export default function TenantSelectionModal({
  visible,
  userName,
  options,
  selectedId,
  lastAccessId,
  remember,
  loading,
  error,
  onSelect,
  onRememberChange,
  onConfirm,
  onClose,
}: Props) {
  const selected = options.find((option) =>
    sameId((option.tenant || option).id, selectedId),
  );
  const selectedAcademy = selected?.tenant || selected;
  const selectedName = selectedAcademy?.nome || selectedAcademy?.name;
  const firstName = userName?.trim().split(/\s+/)[0];
  const close = () => {
    if (!loading) onClose();
  };

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={close}>
      <SafeAreaView style={styles.overlay}>
        <View style={styles.card} accessibilityViewIsModal>
          <LinearGradient
            colors={[ds.brandSoft, ds.surface]}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={styles.header}
          >
            <View style={styles.facets} pointerEvents="none">
              <BrandFacets />
            </View>
            <View style={styles.headerIcon}>
              <Feather name="home" size={22} color={ds.onBrand} />
            </View>
            <View style={styles.headerText}>
              <Text style={styles.eyebrow}>{firstName ? `Olá, ${firstName}` : "Sua conta"}</Text>
              <Text style={styles.title} accessibilityRole="header">Escolha a academia</Text>
              <Text style={styles.description}>
                Sua conta está em {options.length} academias. Você pode trocar depois pelo menu.
              </Text>
            </View>
            <TouchableOpacity
              onPress={close}
              disabled={loading}
              accessibilityRole="button"
              accessibilityLabel="Fechar seleção de academia"
              style={[styles.close, loading && styles.disabled]}
            >
              <Feather name="x" size={20} color={ds.ink} />
            </TouchableOpacity>
          </LinearGradient>

          <ScrollView
            style={styles.scroll}
            contentContainerStyle={styles.options}
            showsVerticalScrollIndicator={false}
            keyboardShouldPersistTaps="handled"
          >
            <View style={styles.list} accessibilityRole="radiogroup" accessibilityLabel="Academias">
              {options.map((option, index) => {
                const academy = option.tenant || option;
                const name = academy.nome || academy.name || "Academia";
                const checked = sameId(academy.id, selectedId);
                const lastAccess = sameId(academy.id, lastAccessId);
                const words = name.trim().split(/\s+/);
                const initials = `${words[0]?.[0] || ""}${words.length > 1 ? words[words.length - 1][0] : ""}`.toUpperCase();
                const roles = (option.papeis || [])
                  .map((role) => role.nome || role.papel_nome)
                  .filter(Boolean);
                const detail = [...roles, academy.cidade, option.status].filter(Boolean).join(" · ");

                return (
                  <TouchableOpacity
                    key={academy.id ?? index}
                    accessibilityRole="radio"
                    aria-checked={checked}
                    accessibilityState={{ checked, disabled: loading || academy.id == null }}
                    accessibilityLabel={`${name}${lastAccess ? ", último acesso" : ""}${detail ? `, ${detail}` : ""}`}
                    disabled={loading || academy.id == null}
                    onPress={() => {
                      if (academy.id != null) onSelect(academy.id);
                    }}
                    activeOpacity={0.85}
                    style={[styles.option, checked && styles.optionSelected, loading && styles.disabled]}
                  >
                    <View style={[styles.avatar, checked && styles.avatarSelected]}>
                      <Text style={[styles.initials, checked && styles.initialsSelected]}>{initials}</Text>
                    </View>
                    <View style={styles.optionText}>
                      {lastAccess ? <Text style={styles.lastAccess}>Último acesso</Text> : null}
                      <Text style={styles.name}>{name}</Text>
                      {detail ? <Text style={styles.detail}>{detail}</Text> : null}
                    </View>
                    <View style={[styles.radio, checked && styles.radioSelected]}>
                      {checked ? <Feather name="check" size={14} color={ds.onBrand} /> : null}
                    </View>
                  </TouchableOpacity>
                );
              })}
            </View>
            {error ? <Text style={styles.error} accessibilityRole="alert">{error}</Text> : null}
          </ScrollView>

          <View style={styles.footer}>
            <TouchableOpacity
              onPress={() => onRememberChange(!remember)}
              disabled={loading}
              accessibilityRole="checkbox"
              aria-checked={remember}
              accessibilityLabel="Lembrar minha escolha"
              accessibilityState={{ checked: remember, disabled: loading }}
              style={[styles.remember, loading && styles.disabled]}
            >
              <View style={[styles.checkbox, remember && styles.checkboxSelected]}>
                {remember ? <Feather name="check" size={14} color={ds.onInverse} /> : null}
              </View>
              <Text style={styles.rememberText}>Lembrar minha escolha</Text>
            </TouchableOpacity>
            <Button
              block
              size="lg"
              iconRight="arrow-right"
              loading={loading}
              disabled={!selected}
              onPress={() => {
                if (selected && !loading) onConfirm(selected);
              }}
              textStyle={styles.confirmText}
            >
              {selectedName ? `Entrar na ${selectedName}` : "Escolha uma academia"}
            </Button>
          </View>
        </View>
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: ds.scrim,
    alignItems: "center",
    justifyContent: "center",
    padding: space[5],
  },
  card: {
    width: "100%",
    maxWidth: 440,
    maxHeight: "90%",
    backgroundColor: ds.surface,
    borderRadius: 24,
    overflow: "hidden",
    shadowColor: ds.ink,
    shadowOffset: { width: 0, height: 16 },
    shadowOpacity: 0.2,
    shadowRadius: 32,
    elevation: 12,
  },
  header: { flexDirection: "row", alignItems: "flex-start", gap: space[3], padding: space[5] },
  facets: { ...StyleSheet.absoluteFillObject, opacity: 0.08 },
  headerIcon: {
    width: 44, height: 44, borderRadius: radius.md, backgroundColor: ds.brand,
    alignItems: "center", justifyContent: "center",
  },
  headerText: { flex: 1, minWidth: 0 },
  eyebrow: { fontSize: 11, lineHeight: 16, fontWeight: "800", letterSpacing: 0.8, textTransform: "uppercase", color: ds.brandInk },
  title: { fontSize: 22, lineHeight: 26, fontWeight: "900", letterSpacing: -0.4, color: ds.ink },
  description: { marginTop: 6, fontSize: 13, lineHeight: 18, fontWeight: "500", color: ds.inkMuted },
  close: { width: 36, height: 36, borderRadius: radius.full, backgroundColor: ds.surfaceSunken, alignItems: "center", justifyContent: "center" },
  scroll: { flexShrink: 1 },
  options: { padding: space[5], paddingTop: space[2] },
  list: { gap: space[3] },
  option: { flexDirection: "row", alignItems: "center", gap: space[3], borderWidth: 1.5, borderColor: ds.line, borderRadius: radius.lg, padding: space[3], backgroundColor: ds.surface },
  optionSelected: { borderColor: ds.brand, backgroundColor: ds.brandSoft },
  avatar: { width: 44, height: 44, borderRadius: radius.md, backgroundColor: ds.inverse, alignItems: "center", justifyContent: "center" },
  avatarSelected: { backgroundColor: ds.brand },
  initials: { color: ds.onInverse, fontSize: 16, fontWeight: "900" },
  initialsSelected: { color: ds.onBrand },
  optionText: { flex: 1, minWidth: 0 },
  lastAccess: { fontSize: 10, lineHeight: 14, fontWeight: "800", letterSpacing: 0.6, textTransform: "uppercase", color: ds.brandInk },
  name: { fontSize: 16, lineHeight: 21, fontWeight: "800", color: ds.ink },
  detail: { marginTop: 2, fontSize: 12, lineHeight: 17, fontWeight: "500", color: ds.inkMuted },
  radio: { width: 22, height: 22, borderRadius: radius.full, borderWidth: 1.5, borderColor: ds.lineStrong, alignItems: "center", justifyContent: "center" },
  radioSelected: { borderColor: ds.brand, backgroundColor: ds.brand },
  footer: { borderTopWidth: 1, borderTopColor: ds.line, padding: space[5], gap: space[3] },
  remember: { flexDirection: "row", alignItems: "center", gap: space[2], minHeight: 36 },
  checkbox: { width: 20, height: 20, borderWidth: 1.5, borderColor: ds.lineStrong, borderRadius: 5, alignItems: "center", justifyContent: "center" },
  checkboxSelected: { backgroundColor: ds.inverse, borderColor: ds.inverse },
  rememberText: { fontSize: 13, fontWeight: "600", color: ds.inkMuted },
  confirmText: { flexShrink: 1 },
  disabled: { opacity: 0.55 },
  error: { marginTop: space[3], color: ds.danger, fontSize: 13, lineHeight: 18, fontWeight: "600" },
});
