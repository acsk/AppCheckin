import React, { useRef, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import Modal from "./ds/Modal";
import TextField from "./ds/TextField";
import TimeInput from "./ds/TimeInput";
import { ds, space } from "./ds/tokens";
import {
  fmtData, fmtDiferenca, fmtMarca, hojeIso, inputsMarca, mascaraData, melhorQue,
  payloadMarca, recordeErro, textoMelhora, valorInput,
  type Marca, type MarcaPayload, type MetricaInput, type ProvaDetalhe,
} from "../utils/recordes";

type Props = { detalhe: ProvaDetalhe; marca?: Marca; onClose: () => void; onSave: (payload: MarcaPayload) => Promise<void> };
export default function RecordeMarcaForm({ detalhe, marca, onClose, onSave }: Props) {
  const [inputs, setInputs] = useState(() => inputsMarca(detalhe.metricas, marca));
  const [data, setData] = useState(fmtData(marca?.data || hojeIso()));
  const [observacoes, setObservacoes] = useState(marca?.observacoes || "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const saving = useRef(false);
  const principal = detalhe.metricas.find(m => m.id === detalhe.definicao.metrica_principal_id);
  const melhor = detalhe.marcas.find(m => m.id === detalhe.melhor_id);
  const valor = principal ? valorInput(inputs[String(principal.id)], principal, marca?.valores[String(principal.id)]) : null;
  const novo = valor != null && (!melhor || melhorQue(valor, melhor.valor, detalhe.definicao.direcao));
  const setInput = (id: number, next: Partial<MetricaInput>) => setInputs(current => ({ ...current, [id]: { ...current[String(id)], ...next } }));
  const save = async () => {
    if (saving.current) return;
    setError("");
    try {
      const payload = payloadMarca(detalhe, inputs, data, observacoes, marca);
      saving.current = true; setBusy(true);
      await onSave(payload);
    } catch (err) {
      console.error("Erro ao salvar marca:", err);
      setError(recordeErro(err));
    } finally { saving.current = false; setBusy(false); }
  };
  return <Modal visible avoidKeyboard title={marca ? "Editar marca" : "Registrar marca"} eyebrow={detalhe.definicao.nome} icon="clock"
    onClose={() => { if (!saving.current) onClose(); }} primary={{ label: marca ? "Salvar alterações" : "Salvar marca", icon: "check", onPress: save, loading: busy }}>
    <Text style={styles.hint}>Preencha as métricas da prova. A melhor marca é comparada por {detalhe.definicao.metrica_nome.toLowerCase()}.</Text>
    {detalhe.metricas.map(m => {
      const required = m.obrigatoria || m.id === detalhe.definicao.metrica_principal_id;
      const label = `${m.nome}${required ? "" : " (opcional)"}`;
      const input = inputs[String(m.id)];
      return m.tipo_valor === "tempo_ms" ? <View key={m.id} style={styles.field}>
        <Text style={styles.label}>{label}</Text>
        <TimeInput label={m.nome} value={input.tempo} onChange={tempo => setInput(m.id, { tempo })} disabled={busy} />
      </View> : <TextField key={m.id} label={label} accessibilityLabel={m.nome}
        keyboardType={m.tipo_valor === "inteiro" ? "number-pad" : "decimal-pad"} inputMode={m.tipo_valor === "inteiro" ? "numeric" : "decimal"}
        value={input.texto} onChangeText={texto => setInput(m.id, { texto })} editable={!busy}
        hint={`${m.tipo_valor === "inteiro" ? "Número inteiro" : "Até três casas decimais"}${m.unidade ? ` · ${m.unidade}` : ""}`} />;
    })}
    {valor != null ? <View style={[styles.compare, novo && styles.better]} accessibilityLiveRegion="polite">
      <Text style={[styles.compareText, novo && { color: ds.success }]}>{!melhor ? "Sua primeira marca nesta prova." : novo ? `Novo recorde: ${fmtDiferenca(valor, melhor.valor, detalhe.definicao)} ${textoMelhora(detalhe.definicao)} que ${fmtMarca(melhor.valor, detalhe.definicao)}.` : `Seu recorde atual é ${fmtMarca(melhor.valor, detalhe.definicao)}.`}</Text>
    </View> : null}
    <TextField label="Data" accessibilityLabel="Data da marca" icon="calendar" value={data} onChangeText={value => setData(mascaraData(value))} editable={!busy} keyboardType="number-pad" inputMode="numeric" placeholder="DD/MM/AAAA" maxLength={10} />
    <TextField label="Observação (opcional)" accessibilityLabel="Observação" value={observacoes} onChangeText={setObservacoes} editable={!busy} maxLength={500} placeholder="Como foi esta marca?" hint={`${observacoes.length}/500 caracteres`} />
    {error ? <Text accessibilityRole="alert" style={styles.error}>{error}</Text> : null}
  </Modal>;
}
const styles = StyleSheet.create({
  field: { gap: space[2] },
  label: { fontSize: 12, fontWeight: "800", textTransform: "uppercase", color: ds.inkMuted, letterSpacing: 0.8 },
  hint: { color: ds.inkMuted, fontSize: 14, lineHeight: 20 },
  compare: { padding: space[3], borderRadius: 12, backgroundColor: ds.surfaceSunken },
  better: { backgroundColor: ds.successSoft },
  compareText: { color: ds.inkMuted, fontSize: 14, lineHeight: 20, fontWeight: "700" },
  error: { color: ds.danger, fontWeight: "600", fontSize: 14, lineHeight: 20 },
});
