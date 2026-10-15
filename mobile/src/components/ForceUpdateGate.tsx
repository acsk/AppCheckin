import {
  APP_BUILD_VERSION,
  APP_VERSION,
  compareBuildVersions,
  fetchMobileVersion,
  forceReloadApp,
  formatDateToPtBr,
  isProductionRuntime,
  startPwaUpdateWatcher,
} from "@/src/config/appVersion";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import React, { useCallback, useEffect, useState } from "react";
import {
  AppState,
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

  const checkVersion = useCallback(async (autoReload: boolean) => {
    if (!isProductionRuntime()) {
      setMustUpdate(false);
      return;
    }

    const remote = await fetchMobileVersion();
    if (!remote || compareBuildVersions(remote.version, APP_BUILD_VERSION) <= 0) {
      setMustUpdate(false);
      return;
    }

    setRemoteVersion(remote.version);
    setRemoteDate(remote.release_date);

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

  return <>{children}</>;
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
