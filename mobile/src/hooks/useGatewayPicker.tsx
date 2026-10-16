import GatewayPickerModal from "@/src/components/ds/GatewayPickerModal";
import type { EscolherGatewayPix, PixGateway } from "@/src/utils/pagamentoPix";
import React, { createContext, useCallback, useContext, useRef, useState } from "react";

type Ctx = {
  escolherGateway: EscolherGatewayPix;
};

const GatewayPickerContext = createContext<Ctx | null>(null);

export function GatewayPickerProvider({ children }: { children: React.ReactNode }) {
  const [visible, setVisible] = useState(false);
  const [gateways, setGateways] = useState<PixGateway[]>([]);
  const resolveRef = useRef<((id: string | null) => void) | null>(null);

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
