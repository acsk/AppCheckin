export type MetodoPagamentoOpcao = "pix" | "boleto" | "cartao";

export const METODOS_PAGAMENTO_OPCOES: {
  id: MetodoPagamentoOpcao;
  label: string;
  hint: string;
  icon: "zap" | "file-text" | "credit-card";
}[] = [
  { id: "pix", label: "PIX", hint: "QR Code na hora", icon: "zap" },
  { id: "boleto", label: "Boleto", hint: "Pague no banco", icon: "file-text" },
  { id: "cartao", label: "Cartão", hint: "Crédito no checkout", icon: "credit-card" },
];

export function opcoesPagamentoVisiveis(
  metodos?: string[] | null,
  pixDisponivel?: boolean,
): MetodoPagamentoOpcao[] {
  const set = new Set(
    (metodos ?? []).map((metodo) => String(metodo).toLowerCase()),
  );
  if (pixDisponivel) {
    set.add("pix");
  }
  if (set.has("checkout") && !set.has("cartao") && !set.has("boleto")) {
    set.add("cartao");
  }

  return METODOS_PAGAMENTO_OPCOES.map((opcao) => opcao.id).filter((id) => {
    if (id === "cartao") {
      return set.has("cartao") || set.has("checkout");
    }
    return set.has(id);
  });
}

export function rotuloPagarMetodo(
  metodo: MetodoPagamentoOpcao,
  contexto: "pagar" | "renovar" | "migrar" = "pagar",
): string {
  const nome =
    metodo === "pix" ? "PIX" : metodo === "boleto" ? "boleto" : "cartão";
  if (contexto === "renovar") {
    return `Renovar com ${nome}`;
  }
  if (contexto === "migrar") {
    return `Migrar com ${nome}`;
  }
  return `Pagar com ${nome}`;
}
