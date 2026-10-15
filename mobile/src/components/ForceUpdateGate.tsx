import {
  APP_BUILD_VERSION,
  APP_VERSION,
  compareBuildVersions,
  fetchMobileVersion,
  formatDateToPtBr,
  isProductionRuntime,
} from "@/src/config/appVersion";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import React, { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
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
  const [checking, setChecking] = useState(() => isProductionRuntime());
  const [mustUpdate, setMustUpdate] = useState(false);
  const [remoteVersion, setRemoteVersion] = useState("");
  const [remoteDate, setRemoteDate] = useState("");

  const checkVersion = useCallback(async () => {
    if (!isProductionRuntime()) {
      setChecking(false);
      setMustUpdate(false);
      return;
    }

    setChecking(true);
    const remote = await fetchMobileVersion();
    if (remote && compareBuildVersions(remote.version, APP_BUILD_VERSION) > 0) {
      setRemoteVersion(remote.version);
      setRemoteDate(remote.release_date);
      setMustUpdate(true);
    } else {
      setMustUpdate(false);
    }
    setChecking(false);
  }, []);

  useEffect(() => {
    void checkVersion();
  }, [checkVersion]);

  const handleUpdate = async () => {
    if (Platform.OS !== "web" || typeof window === "undefined") {
      await checkVersion();
      return;
    }

    try {
      if ("caches" in window) {
        const keys = await caches.keys();
        await Promise.all(keys.map((key) => caches.delete(key)));
      }
      if ("serviceWorker" in navigator) {
        const regs = await navigator.serviceWorker.getRegistrations();
        await Promise.all(regs.map((reg) => reg.unregister()));
      }
    } catch {
      /* segue o reload mesmo se o cache falhar */
    }

    const url = new URL(window.location.href);
    url.searchParams.set("_v", String(Date.now()));
    window.location.replace(url.toString());
  };

  if (checking) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.checkingText}>Verificando versão…</Text>
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

  return <>{children}</>;
}

const styles = StyleSheet.create({
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
    gap: 12,
  },
  checkingText: {
    color: "#64748b",
    fontSize: 14,
  },
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
