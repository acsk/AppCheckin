import React from "react";
import { StyleSheet, Text, View } from "react-native";
import { idProvedorPagamento, nomeProvedorPagamento } from "@/src/utils/metodosPagamento";
import {
  aplicarParcelaSelecionada,
  opcoesParcelasCartao,
  type TaxaCartaoSimulacao,
} from "@/src/utils/taxaCartao";
import Breakdown, { type BreakdownRow } from "./Breakdown";
import Installments from "./Installments";
import PaySheet from "./PaySheet";
import PlanChange from "./PlanChange";
import { ds, space } from "./tokens";

/** Resposta de /mobile/simular-migracao (+ taxa do cartão, quando houver). */
export type MigracaoSimulacao = {
  plano_atual?: { nome: string; valor_formatado: string; ciclo_nome?: string | null };
  plano_novo?: { nome?: string; valor_formatado: string; ciclo_nome?: string | null };
  ciclo?: { atual?: string | null; novo?: string | null; texto?: string | null };
  credito?: {
    valor_formatado: string;
    valor_consumido_formatado?: string;
    dias_restantes?: number;
    tipo?: string;
    motivo?: string;
  };
  valor_parcela_formatado?: string;
  valor_parcela?: number;
  taxa_cartao?: TaxaCartaoSimulacao | null;
};

type Props = {
  visible: boolean;
  simulacao: MigracaoSimulacao | null;
  metodo: string | null;
  /** Fallback do novo plano quando a simulação não traz nome/ciclo. */
  planoNovoNome?: string | null;
  cicloNovoNome?: string | null;
  loading?: boolean;
  onChangeTaxa: (next: TaxaCartaoSimulacao) => void;
  onConfirm: () => void;
  onClose: () => void;
};

// Folha "Confirme a troca" (design system · TelasPagamento → troca de plano): de → para, parcelas do
// cartão quando houver repasse de taxa, e a conta até "Você paga agora".
export default function MigracaoPlanoSheet({
  visible,
  simulacao,
  metodo,
  planoNovoNome,
  cicloNovoNome,
  loading,
  onChangeTaxa,
  onConfirm,
  onClose,
}: Props) {
  const sim = simulacao;
  const taxa = sim?.taxa_cartao?.aplica_repasse ? sim.taxa_cartao : null;
  const parcelas = taxa ? opcoesParcelasCartao(taxa) : [];
  const n = taxa?.parcela_count ?? 1;
  const m = String(metodo || "").toLowerCase();
  const cartao = m === "cartao";
  const semCobranca = Number(sim?.valor_parcela ?? 0) <= 0;
  const provedor = taxa && idProvedorPagamento(taxa.provider) ? nomeProvedorPagamento(taxa.provider) : null;

  const totalValor = taxa ? taxa.valor_cobrar_formatado : sim?.valor_parcela_formatado || "R$ 0,00";
  const totalCurto = taxa && n > 1 ? taxa.rotulo_parcela : totalValor;

  const novoNome = sim?.plano_novo?.nome || planoNovoNome || "Novo plano";
  const novoCiclo = sim?.plano_novo?.ciclo_nome || sim?.ciclo?.novo || cicloNovoNome || null;

  const rows: BreakdownRow[] = [];
  if (sim?.plano_novo?.valor_formatado) {
    rows.push({ label: "Novo plano", hint: [novoNome, novoCiclo].filter(Boolean).join(" · "), value: sim.plano_novo.valor_formatado });
  }
  const temCredito = !!sim?.credito?.valor_formatado && sim.credito.tipo !== "sem_credito";
  if (temCredito) {
    rows.push({
      label: "Crédito do plano atual",
      hint: sim?.credito?.dias_restantes
        ? `${sim.credito.dias_restantes} ${sim.credito.dias_restantes === 1 ? "dia" : "dias"} que você não usou`
        : null,
      value: `− ${sim!.credito!.valor_formatado}`,
      tone: "credit",
    });
  }
  if (taxa) {
    rows.push({
      label: "Taxa do cartão",
      hint: [provedor, n > 1 ? `${n}x` : "à vista"].filter(Boolean).join(" · "),
      value: taxa.tarifa_formatada,
    });
    if ((taxa.sobretaxa ?? 0) > 0.001) {
      rows.push({
        label: "Sobretaxa de antecipação",
        hint: taxa.sobretaxa_percentual
          ? `${String(taxa.sobretaxa_percentual).replace(".", ",")}% ao mês`
          : "D+2",
        value: taxa.sobretaxa_formatada || "",
      });
    }
  }

  const totalHint = semCobranca
    ? "o crédito cobre a troca"
    : taxa
      ? n > 1
        ? taxa.rotulo_parcela
        : "à vista no cartão"
      : m === "pix"
        ? "no PIX, sem taxa"
        : m === "boleto"
          ? "no boleto"
          : cartao
            ? "no cartão"
            : null;

  const primary = semCobranca
    ? { label: "Confirmar troca", icon: "check" as const }
    : m === "pix"
      ? { label: `Gerar PIX de ${totalValor}`, icon: "zap" as const }
      : m === "boleto"
        ? { label: `Gerar boleto de ${totalValor}`, icon: "file-text" as const }
        : { label: `Pagar ${totalCurto}`, iconRight: "external-link" as const };

  return (
    <PaySheet
      visible={visible && !!sim}
      icon="repeat"
      eyebrow="Simulação"
      title={semCobranca ? "Confirme a troca" : "Simular migração"}
      description={
        semCobranca
          ? "O crédito cobre o novo plano. Nada a pagar."
          : "Seu plano atual continua ativo. A troca só acontece depois do pagamento. Se desistir, nada muda."
      }
      secureNote={
        cartao && !semCobranca
          ? `Você conclui no site seguro ${provedor ? `do ${provedor}` : "do provedor"}. A academia recebe o valor do plano.`
          : null
      }
      primary={{ ...primary, loading, onPress: onConfirm }}
      onClose={onClose}
    >
      {sim?.plano_atual?.nome ? (
        <PlanChange
          from={{
            name: sim.plano_atual.nome,
            cycle: sim.plano_atual.ciclo_nome || sim.ciclo?.atual || null,
            price: sim.plano_atual.valor_formatado,
          }}
          to={{ name: novoNome, cycle: novoCiclo, price: sim.plano_novo?.valor_formatado || null }}
        />
      ) : null}

      {taxa && parcelas.length > 1 ? (
        <View style={styles.instHead}>
          <Text style={styles.instLabel}>Parcelas no cartão</Text>
          <Text style={styles.instHint}>a taxa muda com as parcelas</Text>
        </View>
      ) : null}
      {taxa && parcelas.length > 1 ? (
        <Installments
          label="Parcelas no cartão"
          value={String(n)}
          onChange={(id) => onChangeTaxa(aplicarParcelaSelecionada(taxa, Number(id)))}
          options={parcelas.map((p) => {
            const k = p.parcela_count ?? 1;
            return {
              id: String(k),
              label: k <= 1 ? "À vista" : `${k}x`,
              amount: k <= 1 ? p.valor_cobrar_formatado : p.valor_parcela_formatado,
            };
          })}
        />
      ) : null}

      <Breakdown rows={rows} total={{ label: "Você paga agora", hint: totalHint, value: totalValor }} />

      {sim?.credito?.tipo === "sem_credito" && sim.credito.motivo ? (
        <Text style={styles.note}>{sim.credito.motivo}</Text>
      ) : null}
    </PaySheet>
  );
}

const styles = StyleSheet.create({
  instHead: { flexDirection: "row", justifyContent: "space-between", alignItems: "baseline", marginTop: space[2] },
  instLabel: {
    fontSize: 11,
    lineHeight: 14,
    fontWeight: "800",
    letterSpacing: 0.88,
    textTransform: "uppercase",
    color: ds.inkMuted,
  },
  instHint: { fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted },
  note: { fontSize: 13, lineHeight: 18, fontWeight: "500", color: ds.inkMuted },
});
