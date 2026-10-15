import { Feather } from "@expo/vector-icons";
import React from "react";
import {
  ActivityIndicator,
  Image,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import Avatar from "./Avatar";
import Badge from "./Badge";
import Button from "./Button";
import ListRow from "./ListRow";
import Section from "./Section";
import { ds, space, Tone, type } from "./tokens";

const LOGO_H = require("../../../assets/images/appcheckin-logo-h.png");

type FeatherName = React.ComponentProps<typeof Feather>["name"];

export type DrawerInfo = { icon: FeatherName; label: string; value: string };
export type DrawerGym = {
  name: string;
  phone?: string | null;
  email?: string | null;
  current?: boolean;
  onPress?: () => void;
};
export type DrawerMenuItem = {
  icon: FeatherName;
  label: string;
  hint?: string | null;
  hintTone?: Extract<Tone, "warning" | "danger" | "success"> | null;
  onPress: () => void;
};

type Props = {
  user: { name: string; avatarUri?: string | null; roles?: string[]; memberSince?: string | null };
  info?: DrawerInfo[];
  gyms?: DrawerGym[];
  menu?: DrawerMenuItem[];
  version?: string | null;
  updatingPhoto?: boolean;
  onClose: () => void;
  onEditPhoto?: () => void;
  onLogout?: () => void;
};

// Menu lateral do design system (ProfileDrawer): logo e fechar → perfil →
// informações pessoais → minhas academias → atalhos → Sair → "Membro desde".
export default function ProfileDrawer({
  user,
  info = [],
  gyms = [],
  menu = [],
  version,
  updatingPhoto,
  onClose,
  onEditPhoto,
  onLogout,
}: Props) {
  return (
    <SafeAreaView style={styles.drawer} edges={["top", "bottom"]} accessibilityViewIsModal accessibilityLabel="Menu">
      <View style={styles.top}>
        <Image source={LOGO_H} style={styles.logo} resizeMode="contain" accessibilityLabel="AppCheckin" />
        <Button variant="ghost" icon="x" accessibilityLabel="Fechar menu" onPress={onClose} />
      </View>

      <View style={styles.profile}>
        <View>
          <Avatar name={user.name} uri={user.avatarUri} size={64} ring />
          {onEditPhoto ? (
            <TouchableOpacity
              style={styles.cam}
              onPress={onEditPhoto}
              disabled={updatingPhoto}
              accessibilityRole="button"
              accessibilityLabel="Trocar foto"
            >
              {updatingPhoto ? (
                <ActivityIndicator size="small" color={ds.onInverse} />
              ) : (
                <Feather name="camera" size={16} color={ds.onInverse} />
              )}
            </TouchableOpacity>
          ) : null}
        </View>
        <View style={styles.who}>
          <Text style={styles.name} numberOfLines={2}>
            {user.name}
          </Text>
          {user.roles && user.roles.length ? (
            <View style={styles.roles}>
              {user.roles.map((r, i) => (
                <Badge key={r} tone={i === 0 ? "solid" : "neutral"}>
                  {r}
                </Badge>
              ))}
            </View>
          ) : null}
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.body} showsVerticalScrollIndicator={false}>
        {info.length ? (
          <Section title="Informações pessoais">
            {info.map((x) => (
              <View key={x.label} style={styles.kvRow}>
                <View style={styles.kvIcon}>
                  <Feather name={x.icon} size={18} color={ds.inkMuted} />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={styles.kvK}>{x.label}</Text>
                  <Text style={styles.kvV} numberOfLines={1}>
                    {x.value}
                  </Text>
                </View>
              </View>
            ))}
          </Section>
        ) : null}

        {gyms.length ? (
          <Section title="Minhas academias">
            {gyms.map((g) => (
              <ListRow
                key={g.name}
                icon="briefcase"
                tone={g.current ? "brand" : null}
                title={g.name}
                subtitle={
                  g.current ? (
                    <Text style={styles.current}>Academia atual</Text>
                  ) : (
                    [g.phone, g.email].filter(Boolean).join(" · ") || null
                  )
                }
                onPress={g.onPress ?? onClose}
              />
            ))}
          </Section>
        ) : null}

        {menu.length ? (
          <Section>
            {menu.map((m) => (
              <ListRow
                key={m.label}
                icon={m.icon}
                title={m.label}
                subtitle={
                  m.hint ? (
                    <View style={styles.hint}>
                      {m.hintTone === "warning" ? <Feather name="clock" size={13} color={ds.warning} /> : null}
                      <Text
                        style={[
                          styles.hintText,
                          m.hintTone ? { color: ds[m.hintTone], fontWeight: "800" } : null,
                        ]}
                      >
                        {m.hint}
                      </Text>
                    </View>
                  ) : null
                }
                onPress={m.onPress}
              />
            ))}
          </Section>
        ) : null}

        {onLogout ? (
          <Button variant="danger" block icon="log-out" onPress={onLogout}>
            Sair da conta
          </Button>
        ) : null}

        {user.memberSince || version ? (
          <Text style={styles.foot}>
            {[user.memberSince ? `Membro desde ${user.memberSince}` : null, version ? `v${version}` : null]
              .filter(Boolean)
              .join(" · ")}
          </Text>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  drawer: { flex: 1, backgroundColor: ds.ground },
  top: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingTop: space[3],
    paddingBottom: space[3],
    paddingLeft: space[5],
    paddingRight: space[2],
    backgroundColor: ds.surface,
  },
  logo: { height: 30, width: 116 },
  profile: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingTop: space[2],
    paddingBottom: space[4],
    paddingHorizontal: space[5],
    backgroundColor: ds.surface,
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  cam: {
    position: "absolute",
    right: -4,
    bottom: -4,
    width: 30,
    height: 30,
    borderRadius: 15,
    borderWidth: 2,
    borderColor: ds.surface,
    backgroundColor: ds.inverse,
    alignItems: "center",
    justifyContent: "center",
  },
  who: { flex: 1, minWidth: 0, gap: 6 },
  name: {
    fontSize: 18,
    lineHeight: 22,
    fontWeight: "900",
    letterSpacing: -0.2,
    textTransform: "uppercase",
    color: ds.ink,
  },
  roles: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  body: { padding: space[4], paddingBottom: space[6], gap: space[4] },
  kvRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingVertical: space[2],
    paddingHorizontal: space[4],
  },
  kvIcon: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: ds.surfaceSunken,
    alignItems: "center",
    justifyContent: "center",
  },
  kvK: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.66, textTransform: "uppercase", color: ds.inkMuted },
  kvV: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: ds.ink, marginTop: 2 },
  current: { ...type.caption, color: ds.brandInk, fontWeight: "800" },
  hint: { flexDirection: "row", alignItems: "center", gap: 4 },
  hintText: { ...type.caption, color: ds.inkMuted },
  foot: { textAlign: "center", fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted },
});
