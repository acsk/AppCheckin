import React from 'react';
import { Button, SelectField } from './index';

const dias = [
  { value: 1, label: 'Segunda-feira' },
  { value: 2, label: 'Terça-feira' },
  { value: 3, label: 'Quarta-feira' },
  { value: 4, label: 'Quinta-feira' },
  { value: 5, label: 'Sexta-feira' },
  { value: 6, label: 'Sábado' },
  { value: 7, label: 'Domingo' },
];

const horario = (value) => String(value || '').slice(0, 5);
const diasDaTurma = (turma) => Array.isArray(turma?.dias_semana)
  ? turma.dias_semana.map(Number).filter((dia) => dia >= 1 && dia <= 7)
  : [];

export default function GradeTurmaEditor({
  grade = [],
  onChange,
  turmas = [],
  modalidadeId,
  frequenciaSemanal,
  disabled = false,
  onGroupChange,
  dataVigencia,
}) {
  const frequencia = Number(frequenciaSemanal);
  const maximo = Number.isFinite(frequencia) && frequencia > 0 ? frequencia : null;
  const selecionados = Array.isArray(grade) ? grade : [];

  const atualizar = (indice, valores) => {
    onChange(selecionados.map((item, index) => index === indice ? { ...item, ...valores } : item));
  };
  const gruposDisponiveis = (item) => turmas.filter((turma) => (
    (!modalidadeId || Number(turma.modalidade_id) === Number(modalidadeId))
    && ((dataVigencia ? (!turma.vigencia_inicio || turma.vigencia_inicio <= dataVigencia)
      && (!turma.vigencia_fim || turma.vigencia_fim >= dataVigencia) : turma.disponivel_matricula !== false)
      || String(turma.id) === String(item.turma_fixa_id))
    && (turma.ativo == null || Number(turma.ativo) === 1 || String(turma.id) === String(item.turma_fixa_id))
  ));

  return (
    <section className="ck-grade-editor" aria-label="Grade recorrente da matrícula">
      <header>
        <strong>Grade semanal</strong>
        {frequencia > 0 ? <span>Frequência do plano: {frequencia}x por semana</span> : null}
      </header>
      {selecionados.map((item, indice) => {
        const turma = turmas.find((row) => String(row.id) === String(item.turma_fixa_id));
        const diasDisponiveis = diasDaTurma(turma);
        const slotsOcupados = new Set(selecionados
          .filter((_, index) => index !== indice)
          .map((row) => `${row.turma_fixa_id}:${row.dia_semana}`));
        return (
          <div className="ck-grade-editor__row" key={`${indice}-${item.turma_fixa_id || 'vazia'}`}>
            <SelectField
              label={`Turma ${indice + 1}`}
              placeholder="Selecione a turma"
              value={String(item.turma_fixa_id || '')}
              disabled={disabled}
              options={gruposDisponiveis(item).map((grupo) => ({
                value: String(grupo.id),
                label: `${grupo.nome} · ${horario(grupo.horario_inicio)}–${horario(grupo.horario_fim)}`,
              }))}
              onChange={(event) => {
                const next = turmas.find((row) => String(row.id) === event.target.value);
                const diasDoGrupo = diasDaTurma(next);
                const diaAtual = Number(item.dia_semana);
                onGroupChange?.(next);
                atualizar(indice, {
                  turma_fixa_id: event.target.value,
                  dia_semana: diasDoGrupo.includes(diaAtual) ? diaAtual : (diasDoGrupo[0] || ''),
                });
              }}
            />
            <SelectField
              label="Dia"
              placeholder={turma ? 'Selecione o dia' : 'Selecione uma turma primeiro'}
              value={item.dia_semana ? String(item.dia_semana) : ''}
              disabled={disabled || !turma}
              options={dias.filter((dia) => (
                diasDisponiveis.includes(dia.value)
                && (!slotsOcupados.has(`${item.turma_fixa_id}:${dia.value}`) || Number(item.dia_semana) === dia.value)
              )).map((dia) => ({
                value: String(dia.value),
                label: dia.label,
              }))}
              onChange={(event) => atualizar(indice, { dia_semana: Number(event.target.value) })}
            />
            <Button
              size="sm"
              variant="ghost"
              disabled={disabled || selecionados.length === 1}
              onClick={() => onChange(selecionados.filter((_, index) => index !== indice))}
            >
              Remover
            </Button>
          </div>
        );
      })}
      <Button
        size="sm"
        variant="secondary"
        disabled={disabled || (maximo != null && selecionados.length >= maximo)}
        onClick={() => onChange([...selecionados, { turma_fixa_id: '', dia_semana: '' }])}
      >
        Adicionar dia ou turma
      </Button>
      {maximo != null ? <p>A grade permite até {maximo} dia(s) por semana.</p> : null}
      {!turmas.length ? <p>Nenhuma turma recorrente ativa foi cadastrada.</p> : null}
    </section>
  );
}
