import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import AsyncStorage from "@/src/utils/storage";
import { useFocusEffect } from "@react-navigation/native";
import { useCallback, useState } from "react";

export type MatriculaAcessoState = {
  loading: boolean;
  bloqueado: boolean;
  mensagem: string | null;
  codigo: string | null;
};

export function useMatriculaAcesso(): MatriculaAcessoState {
  const [state, setState] = useState<MatriculaAcessoState>({
    loading: true,
    bloqueado: false,
    mensagem: null,
    codigo: null,
  });

  const verificar = useCallback(async () => {
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        setState({
          loading: false,
          bloqueado: false,
          mensagem: null,
          codigo: null,
        });
        return;
      }

      const response = await fetch(`${getApiUrlRuntime()}/mobile/acesso`, {
        method: "GET",
        headers: { Authorization: `Bearer ${token}` },
      });
      const data = await response.json();

      if (response.ok && data?.success && data?.acesso) {
        setState({
          loading: false,
          bloqueado: Boolean(data.acesso.bloqueado),
          mensagem: data.acesso.mensagem ?? null,
          codigo: data.acesso.code ?? data.acesso.codigo ?? null,
        });
        return;
      }

      setState({
        loading: false,
        bloqueado: false,
        mensagem: null,
        codigo: null,
      });
    } catch {
      setState({
        loading: false,
        bloqueado: false,
        mensagem: null,
        codigo: null,
      });
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      verificar();
    }, [verificar]),
  );

  return state;
}
