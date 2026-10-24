import { useSyncExternalStore } from "react";
import {
  assinarConfigRemota,
  type ConfigRemota,
  type Funcionalidade,
  obterConfigRemota,
  type Parametros,
} from "@/src/services/remoteConfig";

/** Configuração remota inteira; re-renderiza quando uma nova é publicada e buscada. */
export function useConfigRemota(): ConfigRemota {
  return useSyncExternalStore(assinarConfigRemota, obterConfigRemota, obterConfigRemota);
}

export function useFuncionalidade(f: Funcionalidade): boolean {
  return useConfigRemota().funcionalidades[f] !== false;
}

export function useParametro<K extends keyof Parametros>(k: K): Parametros[K] {
  return useConfigRemota().parametros[k];
}
