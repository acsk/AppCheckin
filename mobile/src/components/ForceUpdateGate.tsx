import {
  AndroidRelease,
  APP_BUILD_VERSION,
  APP_VERSION,
  compareBuildVersions,
  fetchMobileVersion,
  forceReloadApp,
  formatDateToPtBr,
  isProductionRuntime,
  startPwaUpdateWatcher,
} from "@/src/config/appVersion";
import Modal from "@/src/components/ds/Modal";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import React, { useCallback, useEffect, useState } from "react";
import {
  AppState,
  Linking,
  Platform,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

type Props = {
  children: React.ReactNode;
};

export default function ForceUpdateGate({ children }: Props) {
  const [mustUpdate, setMustUpdate] = useState(false);
  const [remoteVersion, setRemoteVersion] = useState("");
  const [remoteDate, setRemoteDate] = useState("");
  const [apk, setApk] = useState<AndroidRelease | null>(null);
  const [apkDismissed, setApkDismissed] = useState("");
  // Web com atualização opcional (definida pelo super admin): só avisa, sem bloquear
  const [webOpcional, setWebOpcional] = useState("");
  const [webDismissed, setWebDismissed] = useState("");

  const checkVersion = useCallback(async (autoReload: boolean) => {
    // APK: compara com o expo.version embutido no build e oferece o download do novo APK
    if (Platform.OS !== "web") {
      const remote = await fetchMobileVersion();
      const release = remote?.android;
      setApk(release && compareBuildVersions(release.version, APP_VERSION) > 0 ? release : null);
      return;
    }

    if (!isProductionRuntime()) {
      setMustUpdate(false);
      return;
    }

    const remote = await fetchMobileVersion();
    if (!remote || compareBuildVersions(remote.version, APP_BUILD_VERSION) <= 0) {
      setMustUpdate(false);
      setWebOpcional("");
      return;
    }

    setRemoteVersion(remote.version);
    setRemoteDate(remote.release_date);

    if (!remote.obrigatoria) {
      setMustUpdate(false);
      setWebOpcional(remote.version);
      return;
    }
    setWebOpcional("");

    if (autoReload && Platform.OS === "web") {
      try {
        const key = "appcheckin_auto_reload_for";
        const last = sessionStorage.getItem(key);
        if (last !== remote.version) {
          sessionStorage.setItem(key, remote.version);
          await forceReloadApp();
          return;
        }
      } catch {
        await forceReloadApp();
        return;
      }
    }

    setMustUpdate(true);
  }, []);

  useEffect(() => {
    const stop = startPwaUpdateWatcher();
    void checkVersion(true);
    const sub = AppState.addEventListener("change", (state) => {
      if (state === "active") void checkVersion(true);
    });
    const onVisible = () => {
      if (typeof document !== "undefined" && document.visibilityState === "visible") {
        void checkVersion(true);
      }
    };
    if (typeof document !== "undefined") {
      document.addEventListener("visibilitychange", onVisible);
    }
    return () => {
      stop();
      sub.remove();
      if (typeof document !== "undefined") {
        document.removeEventListener("visibilitychange", onVisible);
      }
    };
  }, [checkVersion]);

  const handleUpdate = async () => {
    if (Platform.OS !== "web" || typeof window === "undefined") {
      await checkVersion(false);
      return;
    }
    await forceReloadApp();
  };

  const downloadApk = () => {
    if (apk?.apk_url) void Linking.openURL(apk.apk_url);
  };

  if (apk?.obrigatoria) {
    return (
      <View style={styles.overlay}>
        <View style={styles.card}>
          <View style={styles.iconWrap}>
            <Feather name="download" size={28} color={colors.primary} />
          </View>
          <Text style={styles.title}>Atualização obrigatória</Text>
          <Text style={styles.body}>
            Baixe e instale a nova versão do AppCheckin para continuar.
          </Text>
          <Text style={styles.meta}>Seu app: {APP_VERSION} · Disponível: {apk.version}</Text>
          <TouchableOpacity style={styles.button} onPress={downloadApk}>
            <Text style={styles.buttonText}>Baixar atualização</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  if (mustUpdate) {
    return (
      <View style={styles.overlay}>
        <View style={styles.card}>
          <View style={styles.iconWrap}>
            <Feather name="download" size={28} color={colors.primary} />
          </View>
          <Text style={styles.title}>Atualização obrigatória</Text>
          <Text style={styles.body}>
            Uma nova versão do AppCheckin foi lançada. Atualize para continuar.
          </Text>
          <Text style={styles.meta}>
            Seu app: {APP_BUILD_VERSION} (app {APP_VERSION})
          </Text>
          <Text style={styles.meta}>
            Disponível: {remoteVersion}
            {remoteDate ? ` · ${formatDateToPtBr(remoteDate)}` : ""}
          </Text>
          <TouchableOpacity style={styles.button} onPress={() => void handleUpdate()}>
            <Text style={styles.buttonText}>Atualizar agora</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  return (
    <>
      {children}
      <Modal
        visible={!!apk && apkDismissed !== apk.version}
        variant="dialog"
        icon="download"
        title="Nova versão disponível"
        description={`A versão ${apk?.version ?? ""} do AppCheckin já está disponível (a sua é ${APP_VERSION}). Baixe e instale para receber as novidades.`}
        primary={{ label: "Baixar atualização", icon: "download", onPress: downloadApk }}
        onClose={() => setApkDismissed(apk?.version ?? "")}
      />
      <Modal
        visible={!!webOpcional && webDismissed !== webOpcional}
        variant="dialog"
        icon="refresh-cw"
        title="Nova versão disponível"
        description={`A versão ${webOpcional} do AppCheckin já está disponível (a sua é ${APP_BUILD_VERSION}). Atualize para receber as novidades.`}
        primary={{ label: "Atualizar agora", icon: "refresh-cw", onPress: () => void forceReloadApp() }}
        onClose={() => setWebDismissed(webOpcional)}
      />
    </>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: "#0f172a",
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  card: {
    width: "100%",
    maxWidth: 420,
    backgroundColor: "#fff",
    borderRadius: 20,
    padding: 24,
    gap: 10,
  },
  iconWrap: {
    width: 52,
    height: 52,
    borderRadius: 16,
    backgroundColor: "#fff4ed",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  title: {
    fontSize: 22,
    fontWeight: "800",
    color: "#0f172a",
  },
  body: {
    fontSize: 15,
    lineHeight: 22,
    color: "#475569",
  },
  meta: {
    fontSize: 13,
    color: "#64748b",
  },
  button: {
    marginTop: 12,
    backgroundColor: colors.primary,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
  },
  buttonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "700",
  },
});
