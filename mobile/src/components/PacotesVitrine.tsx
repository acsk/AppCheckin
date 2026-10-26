import Button from "@/src/components/ds/Button";
import Section from "@/src/components/ds/Section";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { usePacotePagamento } from "@/src/hooks/usePacotePagamento";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import AsyncStorage from "@/src/utils/storage";
import { Feather } from "@expo/vector-icons";
import React, { useCallback, useEffect, useState } from "react";
import { StyleSheet, Text, View } from "react-native";

/**
 * Pacotes à venda no app (para todos ou liberados para o aluno): contratar e pagar o pacote inteiro.
 * Some sozinha quando não há pacote disponível.
 */

type PacoteVitrine = {
  id: number;
  nome: string;
  descricao: string | null;
  valor_total: number;
  vagas: number;
  plano_nome: string | null;
  modalidade_nome: string | null;
  ciclo_meses: number | null;
  liberado_para_voce: boolean;
  valida_ate: string | null;
  contrato_pendente_id: number | null;
};

const BRL = (v: number) => `R$ ${v.toFixed(2).replace(".", ",")}`;
const CICLO: Record<number, string> = { 1: "Mensal", 2: "Bimestral", 3: "Trimestral", 4: "Quadrimestral", 6: "Semestral", 12: "Anual" };
const dataBr = (v: string) => v.slice(0, 10).split("-").reverse().join("/");

type Props = {
  /** Muda para recarregar (ex.: ao voltar para a tela). */
  recarregar?: number;
  onPago?: () => void;
  onErro: (mensagem: string) => void;
};

export default function PacotesVitrine({ recarregar, onPago, onErro }: Props) {
  const [pacotes, setPacotes] = useState<PacoteVitrine[]>([]);

  const carregar = useCallback(async () => {
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) return;
      const response = await fetch(`${getApiUrlRuntime()}/mobile/pacotes/disponiveis`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const json: any = await response.json().catch(() => ({}));
      setPacotes(response.ok && Array.isArray(json?.data?.pacotes) ? json.data.pacotes : []);
    } catch {
      setPacotes([]);
    }
  }, []);

  useEffect(() => {
    void carregar();
  }, [carregar, recarregar]);

  const pagamento = usePacotePagamento({
    onPago: () => {
      void carregar();
      onPago?.();
    },
    onErro,
  });

  if (!pacotes.length) return pagamento.elemento;

  return (
    <>
      <Section title="Pacotes" aside="Um pagamento para o grupo" bare>
        <View style={styles.stack}>
          {pacotes.map((p) => {
            const meses = p.ciclo_meses ?? 1;
            return (
              <View key={p.id} style={styles.card}>
                <View style={styles.head}>
                  <View style={styles.icon}>
                    <Feather name="package" size={18} color={ds.brand} />
                  </View>
                  <View style={styles.headText}>
                    <Text style={styles.nome}>{p.nome}</Text>
                    <Text style={styles.meta}>
                      {[p.plano_nome, p.modalidade_nome, CICLO[meses] ?? `${meses} meses`].filter(Boolean).join(" · ")}
                    </Text>
                  </View>
                  {p.liberado_para_voce ? (
                    <View style={styles.badge}>
                      <Text style={styles.badgeText}>Liberado para você</Text>
                    </View>
                  ) : null}
                </View>
                {p.descricao ? <Text style={styles.descricao}>{p.descricao}</Text> : null}
                <View style={styles.linha}>
                  <View>
                    <Text style={styles.valor}>{BRL(p.valor_total)}</Text>
                    <Text style={styles.meta}>
                      {`${p.vagas} pessoas · ${BRL(p.valor_total / Math.max(1, p.vagas) / meses)} por pessoa/mês`}
                    </Text>
                  </View>
                </View>
                <Text style={styles.nota}>
                  Você paga o pacote e a academia associa os outros participantes.
                  {p.valida_ate ? ` Disponível até ${dataBr(p.valida_ate)}.` : ""}
                </Text>
                <Button
                  block
                  icon={p.contrato_pendente_id ? "credit-card" : "shopping-cart"}
                  onPress={() =>
                    pagamento.iniciar({
                      titulo: p.nome,
                      valor: p.valor_total,
                      contratoId: p.contrato_pendente_id,
                      pacoteId: p.id,
                    })
                  }
                >
                  {p.contrato_pendente_id ? "Continuar pagamento" : "Contratar pacote"}
                </Button>
              </View>
            );
          })}
        </View>
      </Section>
      {pagamento.elemento}
    </>
  );
}

const styles = StyleSheet.create({
  stack: { gap: space[3] },
  card: { backgroundColor: ds.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: ds.line, padding: space[4], gap: space[3] },
  head: { flexDirection: "row", alignItems: "center", gap: space[3] },
  icon: { width: 36, height: 36, borderRadius: 11, backgroundColor: ds.brandSoft, alignItems: "center", justifyContent: "center" },
  headText: { flex: 1, minWidth: 0 },
  nome: { fontSize: 16, lineHeight: 22, fontWeight: "800", color: ds.ink },
  meta: { ...type.caption, color: ds.inkMuted },
  badge: { backgroundColor: ds.infoSoft, borderRadius: radius.full, paddingHorizontal: space[2], paddingVertical: 2 },
  badgeText: { fontSize: 11, fontWeight: "800", color: ds.info },
  descricao: { fontSize: 14, lineHeight: 20, color: ds.ink },
  linha: { flexDirection: "row", alignItems: "flex-end", justifyContent: "space-between" },
  valor: { fontSize: 24, lineHeight: 30, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  nota: { ...type.caption, color: ds.inkMuted },
});
