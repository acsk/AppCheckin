import React from "react";
import { useFuncionalidade } from "@/src/hooks/useConfigRemota";
import type { Funcionalidade } from "@/src/services/remoteConfig";

type Props = {
  f: Funcionalidade;
  children: React.ReactNode;
  /** O que mostrar com a funcionalidade desligada (padrão: nada). */
  senao?: React.ReactNode;
};

/** Mostra o conteúdo só com a funcionalidade ligada na configuração remota. */
export default function SeFuncionalidade({ f, children, senao = null }: Props) {
  return <>{useFuncionalidade(f) ? children : senao}</>;
}
