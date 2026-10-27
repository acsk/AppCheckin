import TextField from "@/src/components/ds/TextField";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { Feather } from "@expo/vector-icons";
import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";

/** Perguntas da enquete com as escolhas do aluno (tela da enquete e aviso em destaque). */

export type Pergunta = {
  id: number;
  pergunta: string;
  tipo_resposta: "unica" | "multipla" | "sim_nao" | "avaliacao" | "aberta";
  obrigatoria: boolean;
  alternativas: { id: number; texto: string }[];
};
export type Resposta = { alternativas: number[]; valor: string | number | null };
export type Respostas = Record<number, Resposta>;

/** Respostas da API (minhas_respostas) → estado. */
export function respostasIniciais(minhas?: Record<string, Resposta>): Respostas {
  const out: Respostas = {};
  Object.entries(minhas || {}).forEach(([k, v]) => { out[Number(k)] = v; });
  return out;
}

/** Perguntas obrigatórias ainda sem resposta (números 1…n). */
export function perguntasFaltando(perguntas: Pergunta[], respostas: Respostas): number[] {
  return perguntas.flatMap((p, i) => {
    if (!p.obrigatoria) return [];
    const r = respostas[p.id];
    const vazia = ["unica", "multipla"].includes(p.tipo_resposta) ? !r?.alternativas?.length : r?.valor == null || String(r.valor).trim() === "";
    return vazia ? [i + 1] : [];
  });
}

/** Corpo do POST /responder. */
export const corpoRespostas = (perguntas: Pergunta[], respostas: Respostas) => ({
  respostas: perguntas.map((p) => ({ pergunta_id: p.id, alternativas: respostas[p.id]?.alternativas || [], valor: respostas[p.id]?.valor ?? null })),
});

type Props = {
  perguntas: Pergunta[];
  respostas: Respostas;
  disabled?: boolean;
  onChange: (r: Respostas) => void;
  /** Cada pergunta num cartão (tela) ou em lista corrida (aviso). */
  cartoes?: boolean;
};

export default function EnquetePerguntas({ perguntas, respostas, disabled = false, onChange, cartoes = true }: Props) {
  const set = (p: Pergunta, r: Partial<Resposta>) => onChange({ ...respostas, [p.id]: { alternativas: [], valor: null, ...respostas[p.id], ...r } });

  return (
    <View style={{ gap: space[3] }}>
      {perguntas.map((p, i) => (
        <View key={p.id} style={cartoes ? styles.card : styles.bloco}>
          <Text style={styles.pergunta}>{i + 1}. {p.pergunta}{p.obrigatoria ? " *" : ""}</Text>
          <PerguntaInput p={p} r={respostas[p.id]} disabled={disabled} onChange={(r) => set(p, r)} />
        </View>
      ))}
    </View>
  );
}

function PerguntaInput({ p, r, disabled, onChange }: { p: Pergunta; r?: Resposta; disabled: boolean; onChange: (r: Partial<Resposta>) => void }) {
  const marcadas = r?.alternativas || [];
  if (p.tipo_resposta === "unica" || p.tipo_resposta === "multipla") {
    const multipla = p.tipo_resposta === "multipla";
    return (
      <View style={{ gap: space[2] }} accessibilityRole={multipla ? undefined : "radiogroup"}>
        <Text style={styles.dica}>{multipla ? "Escolha uma ou mais opções" : "Escolha uma opção"}</Text>
        {p.alternativas.map((a) => {
          const on = marcadas.includes(a.id);
          return (
            <Pressable key={a.id} disabled={disabled} style={[styles.opcao, on && styles.opcaoOn]}
              accessibilityRole={multipla ? "checkbox" : "radio"} accessibilityState={{ checked: on, disabled }}
              onPress={() => onChange({ alternativas: multipla ? (on ? marcadas.filter((x) => x !== a.id) : [...marcadas, a.id]) : [a.id] })}>
              <Feather name={multipla ? (on ? "check-square" : "square") : on ? "check-circle" : "circle"} size={20} color={on ? ds.brandInk : ds.inkMuted} />
              <Text style={styles.opcaoTexto}>{a.texto}</Text>
            </Pressable>
          );
        })}
      </View>
    );
  }
  if (p.tipo_resposta === "sim_nao") {
    return (
      <View style={styles.linha} accessibilityRole="radiogroup">
        {[["sim", "Sim"], ["nao", "Não"]].map(([v, l]) => (
          <Pressable key={v} disabled={disabled} style={[styles.opcao, styles.opcaoMeia, r?.valor === v && styles.opcaoOn]}
            accessibilityRole="radio" accessibilityState={{ checked: r?.valor === v, disabled }} onPress={() => onChange({ valor: v })}>
            <Text style={[styles.opcaoTexto, { textAlign: "center" }]}>{l}</Text>
          </Pressable>
        ))}
      </View>
    );
  }
  if (p.tipo_resposta === "avaliacao") {
    return (
      <View style={styles.linha} accessibilityRole="radiogroup" accessibilityLabel="Nota de 1 a 5">
        {[1, 2, 3, 4, 5].map((n) => (
          <Pressable key={n} disabled={disabled} style={styles.estrela} accessibilityRole="radio" accessibilityLabel={`Nota ${n}`}
            accessibilityState={{ checked: Number(r?.valor) === n, disabled }} onPress={() => onChange({ valor: n })}>
            <Feather name="star" size={30} color={Number(r?.valor) >= n ? ds.brand : ds.lineStrong} />
          </Pressable>
        ))}
      </View>
    );
  }
  return <TextField multiline editable={!disabled} value={String(r?.valor ?? "")} onChangeText={(t) => onChange({ valor: t })} placeholder="Escreva sua resposta" maxLength={2000} style={{ minHeight: 88 }} />;
}

const styles = StyleSheet.create({
  card: { backgroundColor: ds.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: ds.line, padding: space[4], gap: space[3] },
  bloco: { gap: space[2] },
  pergunta: { ...type.heading, color: ds.ink },
  dica: { ...type.caption, color: ds.inkMuted },
  opcao: { flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 48, paddingHorizontal: space[3], borderRadius: radius.md, borderWidth: 1, borderColor: ds.line, backgroundColor: ds.surface },
  opcaoOn: { borderColor: ds.brand, backgroundColor: ds.brandSoft },
  opcaoMeia: { flex: 1, justifyContent: "center" },
  opcaoTexto: { flex: 1, ...type.body, color: ds.ink },
  linha: { flexDirection: "row", gap: space[2] },
  estrela: { width: 48, height: 48, alignItems: "center", justifyContent: "center" },
});
