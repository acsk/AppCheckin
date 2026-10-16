import AsyncStorage from "@react-native-async-storage/async-storage";
import GatewayPickerModal from "@/src/components/ds/GatewayPickerModal";
import type { EscolherGatewayPix, PixGateway } from "@/src/utils/pagamentoPix";
import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from "react";

type Ctx = {
  escolherGateway: EscolherGatewayPix;
};

const GatewayPickerContext = createContext<Ctx | null>(null);

// Provedor escolhido da última vez, para pré-selecionar e marcar "Último usado".
const ULTIMO_KEY = "pagamento:ultimo-gateway";

export function GatewayPickerProvider({ children }: { children: React.ReactNode }) {
  const [visible, setVisible] = useState(false);
  const [gateways, setGateways] = useState<PixGateway[]>([]);
  const [ultimoUsado, setUltimoUsado] = useState<string | null>(null);
  const resolveRef = useRef<((id: string | null) => void) | null>(null);

  useEffect(() => {
    AsyncStorage.getItem(ULTIMO_KEY)
      .then((v) => setUltimoUsado(v))
      .catch(() => {});
  }, []);

  const escolherGateway = useCallback<EscolherGatewayPix>((opcoes) => {
    if (!Array.isArray(opcoes) || opcoes.length === 0) {
      return Promise.resolve(null);
    }
    if (opcoes.length === 1) {
      return Promise.resolve(opcoes[0].id);
    }
    return new Promise<string | null>((resolve) => {
      resolveRef.current = resolve;
      setGateways(opcoes);
      setVisible(true);
    });
  }, []);

  const fechar = (id: string | null) => {
    setVisible(false);
    if (id) {
      setUltimoUsado(id);
      AsyncStorage.setItem(ULTIMO_KEY, id).catch(() => {});
    }
    const resolve = resolveRef.current;
    resolveRef.current = null;
    resolve?.(id);
  };

  return (
    <GatewayPickerContext.Provider value={{ escolherGateway }}>
      {children}
      <GatewayPickerModal
        visible={visible}
        gateways={gateways}
        ultimoUsado={ultimoUsado}
        onSelect={(id) => fechar(id)}
        onCancel={() => fechar(null)}
      />
    </GatewayPickerContext.Provider>
  );
}

export function useGatewayPicker() {
  const ctx = useContext(GatewayPickerContext);
  const escolherGateway = ctx?.escolherGateway ?? (async () => null);
  return { escolherGateway, modal: null as React.ReactNode };
}
