export type PixGateway = {
  id: string;
  nome: string;
  environment?: string;
};

/** Contexto opcional da escolha (ex.: forma de pagamento, para o texto da folha). */
export type EscolherGatewayContexto = { metodo?: string | null };

export type EscolherGatewayPix = (
  gateways: PixGateway[],
  contexto?: EscolherGatewayContexto,
) => Promise<string | null>;

export function usuarioPodeVerSandboxPagamento(user: any): boolean {
  if (!user) return false;
  if (user.is_super_admin === true || user.is_super_admin === 1) return true;
  const papel = Number(user.papel_id ?? 0);
  if (papel === 3 || papel === 4) return true;
  if (!Array.isArray(user.papeis)) return false;
  return user.papeis.some((role: any) => {
    const id = Number(role?.id ?? role?.papel_id ?? 0);
    return id === 3 || id === 4;
  });
}

export function filtrarGatewaysPix(
  gateways: PixGateway[] | undefined,
  podeSandbox: boolean,
): PixGateway[] {
  if (!Array.isArray(gateways)) return [];
  if (podeSandbox) return gateways;
  return gateways.filter(
    (gateway) => String(gateway.environment || "production") !== "sandbox",
  );
}

type PixResult =
  | { ok: true; json: any; gateway?: string; status?: number; cancelled?: boolean }
  | { ok: false; status: number; json: any; cancelled?: boolean; gateway?: string };

function gatewaysDaResposta(json: any): PixGateway[] {
  const lista = json?.gateways ?? json?.data?.gateways ?? [];
  return Array.isArray(lista) ? lista : [];
}

function precisaEscolherGateway(result: { ok: boolean; json: any }): boolean {
  const code = result.json?.code;
  if (code !== "ESCOLHA_GATEWAY") return false;
  return gatewaysDaResposta(result.json).length > 0;
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
  escolherGateway?: EscolherGatewayPix;
  onEscolherGateway?: () => void;
  podeSandbox?: boolean;
}): Promise<PixResult> {
  const escolher = params.escolherGateway;
  const podeSandbox = params.podeSandbox === true;
  let gateway = params.gateway;

  const escolherEntre = async (lista: PixGateway[]): Promise<string | null> => {
    const visiveis = filtrarGatewaysPix(lista, podeSandbox);
    if (visiveis.length === 0) return null;
    if (visiveis.length === 1) return visiveis[0].id;
    if (!escolher) return null;
    params.onEscolherGateway?.();
    await new Promise((resolve) => setTimeout(resolve, 50));
    return escolher(visiveis, { metodo: "pix" });
  };

  if (!gateway && params.gateways && params.gateways.length > 0) {
    const chosen = await escolherEntre(params.gateways);
    if (params.gateways.length > 1 && chosen === null) {
      return { ok: false, status: 0, json: {}, cancelled: true };
    }
    if (chosen) gateway = chosen;
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

    const opcoes = gatewaysDaResposta(result.json);
    if (precisaEscolherGateway(result) && opcoes.length > 0) {
      const visiveis = filtrarGatewaysPix(opcoes, podeSandbox);
      if (visiveis.length === 0) {
        return {
          ok: false,
          status: 400,
          json: {
            code: "SEM_GATEWAY_PIX",
            error: "Nenhum provedor de PIX disponível.",
          },
        };
      }
      const chosen = await escolherEntre(opcoes);
      if (!chosen) {
        return { ...result, cancelled: true };
      }
      gateway = chosen;
      continue;
    }

    return result;
  }
}

export async function postComRetryGateway(params: {
  url: string;
  token: string;
  body: Record<string, unknown>;
  escolherGateway?: EscolherGatewayPix;
  onEscolherGateway?: () => void;
  podeSandbox?: boolean;
}): Promise<PixResult> {
  const escolher = params.escolherGateway;
  const podeSandbox = params.podeSandbox === true;
  let gateway =
    typeof params.body.gateway === "string" ? params.body.gateway : undefined;

  const escolherEntre = async (lista: PixGateway[]): Promise<string | null> => {
    const visiveis = filtrarGatewaysPix(lista, podeSandbox);
    if (visiveis.length === 0) return null;
    if (visiveis.length === 1) return visiveis[0].id;
    if (!escolher) return null;
    params.onEscolherGateway?.();
    await new Promise((resolve) => setTimeout(resolve, 50));
    return escolher(visiveis, {
      metodo:
        typeof params.body.metodo_pagamento === "string"
          ? params.body.metodo_pagamento
          : null,
    });
  };

  for (;;) {
    const body = { ...params.body };
    if (gateway) {
      body.gateway = gateway;
    }

    const response = await fetch(params.url, {
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

    const result: PixResult =
      response.ok && json.success
        ? { ok: true, json, gateway }
        : { ok: false, status: response.status, json, gateway };

    if (precisaEscolherGateway(result)) {
      const opcoes = gatewaysDaResposta(json);
      const visiveis = filtrarGatewaysPix(opcoes, podeSandbox);
      if (visiveis.length === 0) {
        return {
          ok: false,
          status: 400,
          json: {
            code: "SEM_GATEWAY",
            error: "Nenhum provedor disponível para este pagamento.",
            message: "Nenhum provedor disponível para este pagamento.",
          },
        };
      }
      const chosen = await escolherEntre(opcoes);
      if (!chosen) {
        return { ...result, cancelled: true };
      }
      gateway = chosen;
      continue;
    }

    return result;
  }
}
