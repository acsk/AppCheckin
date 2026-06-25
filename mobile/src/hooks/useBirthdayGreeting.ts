import AsyncStorage from "@/src/utils/storage";
import MobileService from "@/src/services/mobileService";
import { useCallback, useEffect, useState } from "react";

const STORAGE_KEY = "@appcheckin:birthday_modal_date";

function hojeChave(): string {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

export function useBirthdayGreeting(enabled = true) {
  const [visible, setVisible] = useState(false);
  const [nome, setNome] = useState("");
  const [idade, setIdade] = useState<number | null>(null);

  const dismiss = useCallback(async () => {
    setVisible(false);
    await AsyncStorage.setItem(STORAGE_KEY, hojeChave());
  }, []);

  useEffect(() => {
    if (!enabled) return;

    let cancelled = false;

    (async () => {
      try {
        const token = await AsyncStorage.getItem("@appcheckin:token");
        if (!token) return;

        const jaMostrado = await AsyncStorage.getItem(STORAGE_KEY);
        if (jaMostrado === hojeChave()) return;

        const profileData = await MobileService.getPerfil();
        if (cancelled || !profileData?.success) return;

        const data = profileData.data;
        if (!data?.aniversario_hoje) return;

        setNome(data.nome || "Atleta");
        setIdade(
          typeof data.idade === "number" ? data.idade : null,
        );
        setVisible(true);
      } catch {
        // Silencioso — modal de aniversário não deve bloquear o app
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [enabled]);

  return { visible, nome, idade, dismiss };
}
