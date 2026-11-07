import { Feather } from "@expo/vector-icons";
import React, { useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import Button from "./ds/Button";
import Section from "./ds/Section";
import TextField from "./ds/TextField";
import { ds, radius, space, type } from "./ds/tokens";

export type PeriodoGratuito = { encerrado: boolean; fim: string | null; plano_destino: { id: number; descricao: string } | null };
export type DependentePreMatricula = {
  usuario_id: number;
  nome: string;
  disponivel: boolean;
  status: "pendente" | "aprovada" | "reprovada" | null;
  matricula_id?: number | null;
  periodo_gratuito?: PeriodoGratuito | null;
  motivo?: string | null;
  tem_planos: boolean;
};

export type PreMatricula = {
  tenant_id: number;
  disponivel: boolean;
  obrigatoria: boolean;
  status: "pendente" | "aprovada" | "reprovada" | null;
  matricula_id?: number | null;
  periodo_gratuito?: PeriodoGratuito | null;
  /** Onde contratar: neste perfil, no perfil do dependente ou só o responsável (login por PIN) */
  contratar?: "aqui" | "perfil_dependente" | "responsavel";
  /** Só no perfil do responsável: situação de cada dependente */
  dependentes?: DependentePreMatricula[];
  observacao: string | null;
  motivo?: string | null;
  planos: { id: number; descricao: string | null; checkins_semanais: number; modalidade_id: number; modalidade_nome: string; dias_disponiveis?: number }[];
  turmas: { id: number; nome: string; modalidade_id: number; dias_semana: number[]; horario_inicio: string; horario_fim: string }[];
};

type Slot = { turma_fixa_id: number; dia_semana: number };
type Props = {
  dados: PreMatricula;
  planoId: number | null;
  grade: Slot[];
  vencimento: string;
  disabled: boolean;
  onPlanoChange: (id: number) => void;
  onGradeChange: (grade: Slot[]) => void;
  onVencimentoChange: (value: string) => void;
};

const DIAS = ["Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado", "Domingo"];
const ABREV = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];
const hora = (value: string) => value.slice(0, 5);

export default function PreMatriculaForm({ dados, planoId, grade, vencimento, disabled, onPlanoChange, onGradeChange, onVencimentoChange }: Props) {
  const [diaEscolhido, setDiaEscolhido] = useState<number | null>(null);
  const plano = dados.planos.find(item => item.id === planoId);
  const limite = Number(plano?.checkins_semanais) || 0;
  const turmas = dados.turmas.filter(turma => turma.modalidade_id === plano?.modalidade_id);
  const diasDisponiveis = DIAS.map((_, index) => index + 1).filter(dia => turmas.some(turma => turma.dias_semana.includes(dia)));
  const diaAtual = diaEscolhido && diasDisponiveis.includes(diaEscolhido) ? diaEscolhido : diasDisponiveis[0];
  const opcoes = turmas.filter(turma => turma.dias_semana.includes(diaAtual)).sort((a, b) => a.horario_inicio.localeCompare(b.horario_inicio));
  const escolhida = grade.find(slot => slot.dia_semana === diaAtual);
  const vencimentoInvalido = !!vencimento && (!/^\d{1,2}$/.test(vencimento) || Number(vencimento) < 1 || Number(vencimento) > 31);

  return <>
    <Section title="1. ESCOLHA O PLANO" padded>
      <View style={styles.fields}>
        <View style={styles.plans} accessibilityRole="radiogroup" accessibilityLabel="Planos disponíveis">
          {dados.planos.map(item => {
            const selecionado = item.id === planoId;
            const descricao = String(item.descricao || "").replace(/\bfree\b/gi, "").trim();
            return <Pressable key={item.id} disabled={disabled} onPress={() => { if (!selecionado) { onPlanoChange(item.id); setDiaEscolhido(null); } }} accessibilityRole="radio" accessibilityState={{ checked: selecionado, disabled }} style={[styles.plan, selecionado && styles.selected]}>
              <View style={styles.planHead}>
                <Text style={[styles.planTitle, selecionado && styles.onSelected]}>{item.checkins_semanais}x por semana</Text>
                <Feather name={selecionado ? "check-circle" : "circle"} size={20} color={selecionado ? ds.onInverse : ds.inkMuted} />
              </View>
              <Text style={[styles.caption, selecionado && styles.onSelected]}>{item.modalidade_nome}</Text>
              {descricao ? <Text style={[styles.caption, selecionado && styles.onSelected]}>{descricao}</Text> : null}
            </Pressable>;
          })}
        </View>
        {!dados.planos.length ? <Text style={styles.body}>Não há planos disponíveis. Fale com a academia.</Text> : null}
        {plano?.dias_disponiveis !== undefined && plano.dias_disponiveis < limite ? <Text style={styles.warning}>Este plano precisa de {limite} dias, mas há horários em {plano.dias_disponiveis}. Peça à academia para disponibilizar mais dias.</Text> : null}
      </View>
    </Section>

    <Section title="2. DIAS E HORÁRIOS" padded>
      <View style={styles.fields}>
        {!plano ? <Text style={styles.body}>Escolha o plano para ver os horários disponíveis.</Text> : <>
          <View style={styles.progress} accessibilityLiveRegion="polite">
            <View style={styles.progressHead}>
              <Text style={styles.heading}>{grade.length} de {limite} selecionados</Text>
              {grade.length ? <Button variant="ghost" size="sm" disabled={disabled} onPress={() => onGradeChange([])}>Limpar</Button> : null}
            </View>
            <Text style={styles.caption}>Escolha um horário em cada dia, até completar os {limite} do plano.</Text>
          </View>
          {diasDisponiveis.length ? <>
            <View style={styles.days} accessibilityRole="tablist" accessibilityLabel="Dias disponíveis">
              {diasDisponiveis.map(dia => {
                const ativo = diaAtual === dia;
                const temHorario = grade.some(slot => slot.dia_semana === dia);
                return <Pressable key={dia} disabled={disabled} onPress={() => setDiaEscolhido(dia)} accessibilityRole="tab" accessibilityLabel={`${DIAS[dia - 1]}${temHorario ? ', com horário escolhido' : ''}`} accessibilityState={{ selected: ativo, disabled }} style={[styles.day, ativo && styles.selected]}>
                  <Text style={[styles.dayText, ativo && styles.onSelected]}>{ABREV[dia - 1]}</Text>
                  {temHorario ? <Feather name="check" size={12} color={ativo ? ds.onInverse : ds.ink} /> : null}
                </Pressable>;
              })}
            </View>
            <View style={styles.fields} accessibilityRole="radiogroup" accessibilityLabel={`Horários de ${DIAS[diaAtual - 1]}`}>
              {opcoes.map(turma => {
                const marcada = escolhida?.turma_fixa_id === turma.id;
                const bloqueada = disabled || (!escolhida && grade.length >= limite);
                return <Pressable key={turma.id} disabled={bloqueada} onPress={() => {
                  const outras = grade.filter(slot => slot.dia_semana !== diaAtual);
                  onGradeChange(marcada ? outras : [...outras, { turma_fixa_id: turma.id, dia_semana: diaAtual }]);
                }} accessibilityRole="radio" accessibilityState={{ checked: marcada, disabled: bloqueada }} style={[styles.slot, marcada && styles.selected, bloqueada && !marcada && styles.unavailable]}>
                  <Feather name={marcada ? "check-circle" : "clock"} size={20} color={marcada ? ds.onInverse : ds.inkMuted} />
                  <View style={styles.slotText}>
                    <Text style={[styles.heading, marcada && styles.onSelected]}>{hora(turma.horario_inicio)}–{hora(turma.horario_fim)}</Text>
                    <Text style={[styles.caption, marcada && styles.onSelected]}>{turma.nome}</Text>
                  </View>
                </Pressable>;
              })}
            </View>
          </> : <Text style={styles.body}>Nenhum horário disponível para este plano. Fale com a academia.</Text>}
          {grade.length ? <View style={styles.summary} accessibilityLiveRegion="polite">
            <Text style={styles.label}>SEUS HORÁRIOS</Text>
            {[...grade].sort((a, b) => a.dia_semana - b.dia_semana).map(slot => {
              const turma = dados.turmas.find(item => item.id === slot.turma_fixa_id);
              return <View key={slot.dia_semana} style={styles.summaryRow}>
                <Feather name="check" size={16} color={ds.success} />
                <Text style={styles.summaryText}>{DIAS[slot.dia_semana - 1]} · {turma ? `${hora(turma.horario_inicio)}–${hora(turma.horario_fim)}` : 'Horário selecionado'}</Text>
              </View>;
            })}
          </View> : null}
          {grade.length >= limite ? <Text style={styles.caption}>Para escolher outro dia, remova um horário ou toque em Limpar.</Text> : null}
        </>}
      </View>
    </Section>

    <Section title="3. DIA DE VENCIMENTO" aside="Opcional" padded>
      <TextField label="Dia do mês" icon="calendar" value={vencimento} onChangeText={onVencimentoChange} editable={!disabled} keyboardType="number-pad" maxLength={2} placeholder="De 1 a 31" error={vencimentoInvalido ? "Informe um dia de 1 a 31." : null} hint="Dia do mês em que você prefere pagar." />
    </Section>
    <View style={styles.note}>
      <Feather name="info" size={18} color={ds.inkMuted} />
      <Text style={styles.noteText}>A academia analisa o pedido antes de confirmar a matrícula e a vaga.</Text>
    </View>
    <View style={styles.note}>
      <Feather name="clock" size={18} color={ds.inkMuted} />
      <Text style={styles.noteText}>O acesso inicial é gratuito por tempo limitado. Para continuar depois, será preciso contratar um plano.</Text>
    </View>
  </>;
}

const styles = StyleSheet.create({
  fields: { gap: space[3] },
  plans: { flexDirection: "row", flexWrap: "wrap", gap: space[2] },
  plan: { flexGrow: 1, flexBasis: "45%", minWidth: 0, gap: space[2], padding: space[3], borderRadius: radius.md, borderWidth: 1.5, borderColor: ds.lineStrong, backgroundColor: ds.surface },
  planHead: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: space[2] },
  planTitle: { ...type.body, fontWeight: "800", color: ds.ink, flexShrink: 1 },
  selected: { backgroundColor: ds.inverse, borderColor: ds.inverse },
  onSelected: { color: ds.onInverse },
  body: { ...type.body, color: ds.inkMuted },
  caption: { ...type.caption, color: ds.inkMuted },
  heading: { ...type.heading, color: ds.ink },
  label: { ...type.label, color: ds.inkMuted },
  warning: { ...type.caption, color: ds.warning },
  progress: { gap: space[1] },
  progressHead: { flexDirection: "row", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: space[2] },
  days: { flexDirection: "row", flexWrap: "wrap", gap: space[2] },
  day: { minWidth: 48, minHeight: 48, paddingHorizontal: space[2], paddingVertical: space[2], flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 4, borderRadius: radius.md, borderWidth: 1.5, borderColor: ds.lineStrong, backgroundColor: ds.surface },
  dayText: { ...type.body, fontWeight: "700", color: ds.ink },
  slot: { minHeight: 64, flexDirection: "row", alignItems: "center", gap: space[3], padding: space[3], borderRadius: radius.md, borderWidth: 1.5, borderColor: ds.lineStrong, backgroundColor: ds.surface },
  slotText: { flex: 1, minWidth: 0, gap: 2 },
  unavailable: { backgroundColor: ds.surfaceSunken, borderColor: ds.line },
  summary: { gap: space[2], padding: space[3], borderRadius: radius.md, backgroundColor: ds.surfaceSunken },
  summaryRow: { flexDirection: "row", alignItems: "center", gap: space[2] },
  summaryText: { ...type.caption, color: ds.ink, flex: 1 },
  note: { flexDirection: "row", alignItems: "flex-start", gap: space[2], paddingHorizontal: space[1] },
  noteText: { ...type.caption, color: ds.inkMuted, flex: 1 },
});
