import React from 'react';

const diasTurma = [
  { value: 1, short: 'Seg', name: 'Segunda-feira' },
  { value: 2, short: 'Ter', name: 'Terça-feira' },
  { value: 3, short: 'Qua', name: 'Quarta-feira' },
  { value: 4, short: 'Qui', name: 'Quinta-feira' },
  { value: 5, short: 'Sex', name: 'Sexta-feira' },
  { value: 6, short: 'Sáb', name: 'Sábado' },
  { value: 7, short: 'Dom', name: 'Domingo' },
];

export default function TurmasNaSemana({ tipoAcesso, onTipoAcessoChange, grade, onGradeChange, turmas, modalidadeId, plano }) {
  const frequencia = Number(plano?.checkins_semanais) || 0;
  const limite = frequencia > 0 ? frequencia : null;
  const disponiveis = turmas.filter((turma) => (
    Number(turma.modalidade_id) === Number(modalidadeId)
    && Number(turma.ativo) === 1
    && !turma.deleted_at
    && !turma.vigencia_fim
    && !['pausada', 'arquivada'].includes(turma.situacao)
  ));
  const escolhidas = grade.filter((item) => item.turma_fixa_id && item.dia_semana);
  const porDia = diasTurma.map((dia) => ({
    ...dia,
    turmas: disponiveis.filter((turma) => turma.dias_semana?.map(Number).includes(dia.value))
      .sort((a, b) => String(a.horario_inicio).localeCompare(String(b.horario_inicio))),
  })).filter((dia) => dia.turmas.length);
  const selecionar = (turma, dia) => {
    const existente = escolhidas.find((item) => Number(item.dia_semana) === dia);
    if (existente && String(existente.turma_fixa_id) === String(turma.id)) {
      onGradeChange(grade.filter((item) => !(Number(item.dia_semana) === dia && String(item.turma_fixa_id) === String(turma.id))));
      return;
    }
    const outras = escolhidas.filter((item) => Number(item.dia_semana) !== dia);
    if (limite === 1) {
      onGradeChange([{ turma_fixa_id: String(turma.id), dia_semana: dia }]);
      return;
    }
    if (limite != null && outras.length >= limite) return;
    onGradeChange([...outras, { turma_fixa_id: String(turma.id), dia_semana: dia }]);
  };

  const ultima = escolhidas.length ? disponiveis.find((turma) => String(turma.id) === String(escolhidas[escolhidas.length - 1].turma_fixa_id)) : null;
  const sugestoes = ultima && limite !== 1
    ? ultima.dias_semana.map(Number).filter((dia) => !escolhidas.some((item) => Number(item.dia_semana) === dia))
      .slice(0, limite == null ? 7 : Math.max(0, limite - escolhidas.length))
    : [];

  return (
    <div className="ck-nmt">
      <div className="ck-nmt__access" role="group" aria-label="Como o aluno frequenta">
        <button type="button" className="ck-nmt__mode" aria-pressed={tipoAcesso === 'turma_fixa'} onClick={() => onTipoAcessoChange('turma_fixa')}>
          <strong>Turma fixa</strong><span>Vaga nos dias e horários escolhidos.</span>
        </button>
        <button type="button" className="ck-nmt__mode" aria-pressed={tipoAcesso === 'horario_livre'} onClick={() => onTipoAcessoChange('horario_livre')}>
          <strong>Horário livre</strong><span>Check-in em qualquer aula permitida pelo plano.</span>
        </button>
      </div>
      {tipoAcesso === 'turma_fixa' && !plano ? (
        <p className="ck-nmt__empty">Escolha o plano para definir quantas turmas por semana o aluno pode selecionar.</p>
      ) : tipoAcesso === 'turma_fixa' ? (
        <div className="ck-nmt__selector">
          <div className="ck-nmt__head">
            <strong aria-live="polite">{limite == null ? `${escolhidas.length} horário(s)` : `${escolhidas.length} de ${limite} horário(s)`}</strong>
            {escolhidas.length ? <button type="button" className="ck-nmt__clear" onClick={() => onGradeChange([])}>Limpar</button> : null}
          </div>
          {frequencia > 0 ? <p className="ck-nmt__hint">O plano exige exatamente {frequencia} turma(s) por semana.</p> : <p className="ck-nmt__hint">Este plano não limita a quantidade de dias por semana.</p>}
          {sugestoes.length && ultima ? (
            <button type="button" className="ck-nmt__sugg" onClick={() => onGradeChange([
              ...escolhidas,
              ...sugestoes.map((dia) => ({ turma_fixa_id: String(ultima.id), dia_semana: dia })),
            ])}>
              + Adicionar {ultima.nome} também {sugestoes.map((dia) => diasTurma[dia - 1].name.toLowerCase()).join(' e ')}
            </button>
          ) : null}
          {porDia.length ? <div className="ck-nmt__days">
            {porDia.map((dia) => {
              const doDia = escolhidas.find((item) => Number(item.dia_semana) === dia.value);
              return <div key={dia.value} className={`ck-nmt__day${doDia ? ' is-taken' : ''}`} role="group" aria-label={dia.name}>
                <strong className="ck-nmt__dname">{dia.short}</strong>
                <div className="ck-nmt__opts">{dia.turmas.map((turma) => {
                  const selecionada = doDia && String(doDia.turma_fixa_id) === String(turma.id);
                  const bloqueada = !selecionada && !doDia && limite != null && limite !== 1 && escolhidas.length >= limite;
                  return <button key={turma.id} type="button" className="ck-nmt__opt" aria-pressed={!!selecionada} disabled={bloqueada} onClick={() => selecionar(turma, dia.value)}>
                    <span className="ck-nmt__check" aria-hidden="true">{selecionada ? '✓' : ''}</span>
                    <span className="ck-nmt__class">
                      <strong>{String(turma.horario_inicio || '').slice(0, 5)} · {turma.nome}</strong>
                      <small>{turma.capacidade ? `Capacidade: ${turma.capacidade} por aula` : 'Turma recorrente'}</small>
                    </span>
                  </button>;
                })}</div>
              </div>;
            })}
          </div> : <p className="ck-nmt__empty">Nenhuma turma ativa disponível para esta modalidade.</p>}
        </div>
      ) : <p className="ck-nmt__hint">{plano ? 'O plano define quantos check-ins o aluno pode fazer por semana.' : 'Escolha o plano para continuar.'}</p>}
    </div>
  );
}
