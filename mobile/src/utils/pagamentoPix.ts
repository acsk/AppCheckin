import { Alert } from "react-native";

export type PixGateway = {
  id: string;
  nome: string;
  environment?: string;
};

type PixResult =
  | { ok: true; json: any }
  | { ok: false; status: number; json: any; cancelled?: boolean };

export async function escolherGatewayPix(
  gateways: PixGateway[],
): Promise<string | null> {
  if (!Array.isArray(gateways) || gateways.length === 0) {
    return null;
  }
  if (gateways.length === 1) {
    return gateways[0].id;
  }

  return new Promise((resolve) => {
    Alert.alert(
      "Como deseja pagar?",
      "Escolha Mercado Pago ou Asaas.",
      [
        ...gateways.map((gateway) => ({
          text: gateway.nome,
          onPress: () => resolve(gateway.id),
        })),
        { text: "Cancelar", style: "cancel" as const, onPress: () => resolve(null) },
      ],
      { cancelable: true, onDismiss: () => resolve(null) },
    );
  });
}

export async function postPagamentoPix(params: {
  apiUrl: string;
  token: string;
  matriculaId: number;
  gateway?: string;
}): Promise<PixResult> {
  const body: Record<string, unknown> = { matricula_id: params.matriculaId };
  if (params.gateway) {
    body.gateway = params.gateway;
  }

  const response = await fetch(`${params.apiUrl}/mobile/pagamento/pix`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${params.token}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
  });

  const text = await response.text();
  let json: any = {};
  try {
    json = text ? JSON.parse(text) : {};
  } catch {
    json = {};
  }

  if (response.ok && json.success) {
    return { ok: true, json };
  }

  return { ok: false, status: response.status, json };
}

export async function solicitarPix(params: {
  apiUrl: string;
  token: string;
  matriculaId: number;
  gateway?: string;
  gateways?: PixGateway[];
}): Promise<PixResult> {
  let gateway = params.gateway;
  if (!gateway && params.gateways && params.gateways.length > 1) {
    const chosen = await escolherGatewayPix(params.gateways);
    if (!chosen) {
      return { ok: false, status: 0, json: {}, cancelled: true };
    }
    gateway = chosen;
  }

  for (;;) {
    const result = await postPagamentoPix({
      apiUrl: params.apiUrl,
      token: params.token,
      matriculaId: params.matriculaId,
      gateway,
    });

    if (result.ok) {
      return result;
    }

    const code = result.json?.code;
    const opcoes: PixGateway[] = result.json?.gateways ?? [];
    if (code === "ESCOLHA_GATEWAY" && opcoes.length > 0) {
      const chosen = await escolherGatewayPix(opcoes);
      if (!chosen) {
        return { ...result, cancelled: true };
      }
      gateway = chosen;
      continue;
    }

    return result;
  }
}
