import PasswordRecoveryModal from "@/components/PasswordRecoveryModal";
import MobileService from "@/src/services/mobileService";
import { colors } from "@/src/theme/colors";
import { UserProfile } from "@/src/types";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { getTokenTenantId, handleAuthError } from "@/src/utils/authHelpers";
import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import { useFocusEffect } from "@react-navigation/native";
import { useRefreshOnFocus } from "@/src/hooks/useRefreshOnFocus";
import AppHeader from "@/src/components/ds/AppHeader";
import { useRouter } from "expo-router";
import React, { useCallback, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Image,
  Linking,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

// Tokens do design system AppCheckin (ground, surface, line, ink, ink-muted).
const ds = {
  ground: "#f2f3f5",
  surface: "#ffffff",
  line: "#e4e6ea",
  ink: "#171a21",
  inkMuted: "#5b6270",
  danger: "#b91c1c",
  dangerSoft: "#fdecec",
  successSoft: "#e7f6ec",
  success: "#15703a",
  brandSoft: "#fff3e6",
  brandInk: "#c2410c",
  infoSoft: "#e8f0fe",
  info: "#1d5fd1",
};

const getInitials = (nome: string = "") => {
  const parts = nome.split(" ").filter(Boolean);
  if (parts.length === 0) return "?";
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
};

const buildPhotoUri = (profile: UserProfile | null): string | null => {
  if (!profile) return null;
  if (profile.foto_caminho) {
    const raw = profile.foto_caminho.trim();
    if (/^https?:\/\//i.test(raw)) return raw;
    const base = (getApiUrlRuntime() || "").trim().replace(/\/+$/, "");
    const path = raw.replace(/^\/+/, "");
    if (base && path) return `${base}/${path}`;
  }
  if (profile.foto_base64) {
    return `data:image/jpeg;base64,${profile.foto_base64}`;
  }
  return null;
};

type RowProps = {
  icon: React.ComponentProps<typeof Feather>["name"];
  tone: "brand" | "info" | "success";
  title: string;
  subtitle?: string;
  onPress: () => void;
  whatsapp?: boolean;
};

function Row({ icon, tone, title, subtitle, onPress, whatsapp }: RowProps) {
  const toneStyle = {
    brand: { bg: ds.brandSoft, fg: ds.brandInk },
    info: { bg: ds.infoSoft, fg: ds.info },
    success: { bg: ds.successSoft, fg: ds.success },
  }[tone];
  return (
    <TouchableOpacity
      style={styles.row}
      onPress={onPress}
      activeOpacity={0.7}
      accessibilityRole="button"
      accessibilityLabel={title}
    >
      <View style={[styles.rowIcon, { backgroundColor: toneStyle.bg }]}>
        {whatsapp ? (
          <MaterialCommunityIcons name="whatsapp" size={20} color={toneStyle.fg} />
        ) : (
          <Feather name={icon} size={20} color={toneStyle.fg} />
        )}
      </View>
      <View style={styles.rowText}>
        <Text style={styles.rowTitle} numberOfLines={2}>
          {title}
        </Text>
        {subtitle ? <Text style={styles.rowSub}>{subtitle}</Text> : null}
      </View>
      <Feather name="chevron-right" size={20} color={ds.inkMuted} />
    </TouchableOpacity>
  );
}

function Section({ title, children }: { title?: string; children: React.ReactNode }) {
  const items = React.Children.toArray(children).filter(Boolean);
  return (
    <View style={styles.section}>
      {title ? <Text style={styles.sectionTitle}>{title}</Text> : null}
      <View style={styles.card}>
        {items.map((child, i) => (
          <View key={i} style={i > 0 ? styles.divided : undefined}>
            {child}
          </View>
        ))}
      </View>
    </View>
  );
}

export default function ContaScreen() {
  const router = useRouter();
  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [tenantId, setTenantId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [photoError, setPhotoError] = useState(false);
  const [showRecoveryModal, setShowRecoveryModal] = useState(false);

  const load = useCallback(async () => {
    try {
      const [res, tid] = await Promise.all([
        MobileService.getPerfil(),
        getTokenTenantId(),
      ]);
      if (res?.success) {
        setProfile(res.data);
        setPhotoError(false);
      }
      setTenantId(tid);
    } catch (error: any) {
      if (error?.response?.status === 401) {
        await handleAuthError();
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  // Atualiza sozinho ao reabrir o app/aba.
  useRefreshOnFocus(load);

  const whatsappLinks = useMemo(() => {
    if (!profile?.tenants?.length) return [];
    const tenant =
      profile.tenants.find((t) => Number(t.id) === tenantId) ??
      (profile.tenants.length === 1 ? profile.tenants[0] : undefined);
    return Array.isArray(tenant?.whatsapp_links) ? tenant.whatsapp_links : [];
  }, [profile, tenantId]);

  const tenantNome = useMemo(() => {
    const tenant = profile?.tenants?.find((t) => Number(t.id) === tenantId);
    return tenant?.nome ?? profile?.tenant?.nome ?? null;
  }, [profile, tenantId]);

  if (loading) {
    return (
      <SafeAreaView style={[styles.container, styles.center]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </SafeAreaView>
    );
  }

  const photoUri = buildPhotoUri(profile);
  const stats = profile?.estatisticas;
  const plano = profile?.plano;

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      <AppHeader title="Conta" subtitle={tenantNome || undefined} />

      <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
        {/* Perfil */}
        <View style={styles.card}>
          <View style={styles.profile}>
            <View style={styles.avatar}>
              {photoUri && !photoError ? (
                <Image
                  source={{ uri: photoUri }}
                  style={styles.avatarImg}
                  onError={() => setPhotoError(true)}
                />
              ) : (
                <Text style={styles.avatarText}>{getInitials(profile?.nome)}</Text>
              )}
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.profileName} numberOfLines={2}>
                {profile?.nome || "Usuário"}
              </Text>
              <Text style={styles.profileSub} numberOfLines={1}>
                {[tenantNome, profile?.email].filter(Boolean).join(" · ")}
              </Text>
            </View>
          </View>
          {stats ? (
            <View style={styles.stats}>
              <View style={styles.stat}>
                <Text style={styles.statNum}>{stats.checkins_mes ?? 0}</Text>
                <Text style={styles.statLabel}>check-ins no mês</Text>
              </View>
              <View style={[styles.stat, styles.statDivider]}>
                <Text style={styles.statNum}>{stats.total_checkins ?? 0}</Text>
                <Text style={styles.statLabel}>check-ins no total</Text>
              </View>
            </View>
          ) : null}
        </View>

        <Section title="MINHA CONTA">
          <Row
            icon="file-text"
            tone="brand"
            title="Minhas assinaturas"
            subtitle={plano?.nome ? `${plano.nome}${plano.matricula_status?.nome ? ` · ${plano.matricula_status.nome}` : ""}` : undefined}
            onPress={() => router.push("/minhas-assinaturas")}
          />
          <Row
            icon="shopping-cart"
            tone="brand"
            title="Planos"
            subtitle="Contratar ou renovar"
            onPress={() => router.push("/planos")}
          />
          <Row
            icon="key"
            tone="info"
            title="Alterar senha"
            onPress={() => setShowRecoveryModal(true)}
          />
        </Section>

        {whatsappLinks.length > 0 ? (
          <Section title="GRUPOS DO WHATSAPP">
            {whatsappLinks.map((link) => (
              <Row
                key={link.url}
                icon="message-circle"
                whatsapp
                tone="success"
                title={link.nome}
                subtitle="Toque para entrar"
                onPress={() => Linking.openURL(link.url)}
              />
            ))}
          </Section>
        ) : null}

        <TouchableOpacity
          style={styles.logout}
          onPress={() => router.push("/logout")}
          activeOpacity={0.8}
          accessibilityRole="button"
        >
          <Feather name="log-out" size={20} color={ds.danger} />
          <Text style={styles.logoutText}>Sair da conta</Text>
        </TouchableOpacity>

        {profile?.membro_desde ? (
          <Text style={styles.memberSince}>
            Membro desde {new Date(profile.membro_desde).toLocaleDateString("pt-BR")}
          </Text>
        ) : null}
      </ScrollView>

      <PasswordRecoveryModal
        visible={showRecoveryModal}
        onClose={() => setShowRecoveryModal(false)}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  center: { alignItems: "center", justifyContent: "center" },
  header: {
    minHeight: 64,
    justifyContent: "center",
    paddingHorizontal: 16,
    backgroundColor: ds.surface,
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  headerTitle: { fontSize: 22, lineHeight: 28, fontWeight: "800", color: ds.ink },
  scroll: { padding: 16, paddingBottom: 32, gap: 24 },
  section: { gap: 8 },
  sectionTitle: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: "700",
    letterSpacing: 0.96,
    color: ds.inkMuted,
    paddingHorizontal: 4,
  },
  card: {
    backgroundColor: ds.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: ds.line,
    overflow: "hidden",
  },
  divided: { borderTopWidth: 1, borderTopColor: ds.line },
  profile: { flexDirection: "row", alignItems: "center", gap: 16, padding: 16 },
  avatar: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: colors.primary,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  avatarImg: { width: 56, height: 56 },
  avatarText: { fontSize: 20, fontWeight: "800", color: ds.ink },
  profileName: { fontSize: 17, lineHeight: 22, fontWeight: "700", color: ds.ink },
  profileSub: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted, marginTop: 2 },
  stats: { flexDirection: "row", borderTopWidth: 1, borderTopColor: ds.line },
  stat: { flex: 1, padding: 16 },
  statDivider: { borderLeftWidth: 1, borderLeftColor: ds.line },
  statNum: { fontSize: 28, lineHeight: 30, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  statLabel: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  row: { flexDirection: "row", alignItems: "center", gap: 12, minHeight: 64, paddingHorizontal: 16, paddingVertical: 12 },
  rowIcon: { width: 40, height: 40, borderRadius: 8, alignItems: "center", justifyContent: "center" },
  rowText: { flex: 1 },
  rowTitle: { fontSize: 17, lineHeight: 22, fontWeight: "700", color: ds.ink },
  rowSub: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  logout: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    height: 48,
    borderRadius: 12,
    backgroundColor: ds.dangerSoft,
  },
  logoutText: { fontSize: 16, fontWeight: "800", color: ds.danger },
  memberSince: { textAlign: "center", fontSize: 13, fontWeight: "600", color: ds.inkMuted },
});
