import React, { useEffect, useState } from 'react';
import { Button, Callout, Modal, SelectField, TextField } from './index';
import { acessoHibridoService } from '../../services/acessoHibridoService';
import modalidadeService from '../../services/modalidadeService';
import { professorService } from '../../services/professorService';
import { showSuccess } from '../../utils/toast';
import GradeTurmaEditor from './GradeTurmaEditor';

const dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const diasAbreviados = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
const vazio = { nome: '', professor_id: '', modalidade_id: '', horario_inicio: '', horario_fim: '', capacidade: '12', dias_semana: [], checkin_livre: false };
const erroApi = (e) => {
  const data = e?.response?.data;
  const message = data?.message;
  const details = Object.values(data?.errors || {}).flat().join(' ');
  if (Array.isArray(data?.errors?.from) && data.errors.from.includes('validation.after')) {
    return 'A alteração deve começar amanhã ou em uma data futura para preservar as aulas anteriores. A turma continua recorrente, sem data de término.';
  }
  if (typeof message === 'string' && message !== 'The given data was invalid.' && !message.startsWith('validation.')) return message;
  return details || message || e.message;
};
const hora = (value) => String(value || '').slice(0, 5);
const minutosDoDia = (value) => {
  const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(value);
  return match ? Number(match[1]) * 60 + Number(match[2]) : null;
};
const horaNome = (value) => {
  const [horas, minutos] = hora(value).split(':');
  if (!horas || !minutos) return '';
  return `${Number(horas)}${minutos === '00' ? '' : `:${minutos}`}`;
};
const amanha = () => {
  const date = new Date();
  date.setDate(date.getDate() + 1);
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};

function nomeAutomatico(turma) {
  const diasSelecionados = turma.dias_semana.map(Number).filter((dia) => dia >= 1 && dia <= 7).sort((a, b) => a - b);
  const inicio = horaNome(turma.horario_inicio);
  const fim = horaNome(turma.horario_fim);
  if (!diasSelecionados.length || !inicio || !fim) return '';
  return `${diasSelecionados.map((dia) => diasAbreviados[dia - 1]).join('/')} ${inicio} às ${fim}h`;
}

function formTurma(turma) {
  return {
    id: Number(turma.id),
    nome: turma.nome || '',
    professor_id: String(turma.professor_id || ''),
    modalidade_id: String(turma.modalidade_id || ''),
    horario_inicio: hora(turma.horario_inicio),
    horario_fim: hora(turma.horario_fim),
    capacidade: String(turma.capacidade || '12'),
    dias_semana: Array.isArray(turma.dias_semana) ? turma.dias_semana.map(Number) : [],
    checkin_livre: !!Number(turma.checkin_livre),
  };
}

export function TurmasFixas({ onClose, onSaved, initialId }) {
  const [professores, setProfessores] = useState([]);
  const [modalidades, setModalidades] = useState([]);
  const [form, setForm] = useState(vazio);
  const [original, setOriginal] = useState(null);
  const [desde, setDesde] = useState(amanha);
  const [detalhe, setDetalhe] = useState(null);
  const [remapeamentos, setRemapeamentos] = useState({});
  const [loadingVinculos, setLoadingVinculos] = useState(false);
  const [erroVinculos, setErroVinculos] = useState('');
  const [nomePersonalizado, setNomePersonalizado] = useState(false);
  const [erro, setErro] = useState('');
  const [loading, setLoading] = useState(true);
  const [ready, setReady] = useState(false);
  const [saving, setSaving] = useState(false);
  const [reload, setReload] = useState(0);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setReady(false);
    setErro('');
    Promise.all([acessoHibridoService.listarTurmas(), professorService.listar(false), modalidadeService.listar()])
      .then(([lista, professoresLista, modalidadesLista]) => {
        if (!active) return;
        const grupos = Array.isArray(lista) ? lista : [];
        const selecionada = initialId ? grupos.find((turma) => Number(turma.id) === Number(initialId)) : null;
        if (initialId && !selecionada) throw new Error('Turma não encontrada. Feche o formulário e atualize a listagem.');
        setProfessores(Array.isArray(professoresLista) ? professoresLista : []);
        setModalidades(Array.isArray(modalidadesLista) ? modalidadesLista : []);
        const dadosForm = selecionada ? formTurma(selecionada) : vazio;
        setForm(dadosForm);
        setOriginal(selecionada ? dadosForm : null);
        setNomePersonalizado(!!selecionada && String(selecionada.nome || '').trim() !== nomeAutomatico(dadosForm));
        setReady(true);
      })
      .catch((e) => { if (active) setErro(erroApi(e)); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [reload, initialId]);

  useEffect(() => {
    if (!initialId) return undefined;
    let active = true;
    setLoadingVinculos(true);
    setErroVinculos('');
    setDetalhe(null);
    acessoHibridoService.detalheTurma(Number(initialId), desde)
      .then((response) => {
        if (!active) return;
        setDetalhe(response);
        setRemapeamentos(Object.fromEntries((response.alunos || []).map((aluno) => [
          String(aluno.matricula_id),
          { matricula_id: Number(aluno.matricula_id), dias_semana: aluno.dias_semana.map(Number) },
        ])));
      })
      .catch((e) => { if (active) setErroVinculos(erroApi(e)); })
      .finally(() => { if (active) setLoadingVinculos(false); });
    return () => { active = false; };
  }, [initialId, desde, reload]);

  const gradeAlterada = !!(form.id && original && (
    form.dias_semana.slice().sort().join(',') !== original.dias_semana.slice().sort().join(',')
    || form.horario_inicio !== original.horario_inicio
    || form.horario_fim !== original.horario_fim
    || form.professor_id !== original.professor_id
  ));

  const set = (key, value) => {
    if (key === 'nome') {
      const personalizado = !!value.trim();
      setNomePersonalizado(personalizado);
      setForm((current) => ({
        ...current,
        nome: personalizado ? value : nomeAutomatico(current),
      }));
      return;
    }
    setForm((current) => {
      const next = { ...current, [key]: value };
      if (!nomePersonalizado && ['dias_semana', 'horario_inicio', 'horario_fim'].includes(key)) {
        next.nome = nomeAutomatico(next);
      }
      return next;
    });
  };
  const atualizarDias = (novosDias) => {
    set('dias_semana', novosDias);
    setRemapeamentos((current) => Object.fromEntries(Object.entries(current).map(([id, item]) => {
      const mantidos = item.dias_semana.filter((value) => novosDias.includes(value));
      const quantidade = Math.min(item.dias_semana.length || 1, novosDias.length);
      const preenchidos = [...mantidos, ...novosDias.filter((value) => !mantidos.includes(value)).slice(0, Math.max(0, quantidade - mantidos.length))];
      return [id, { ...item, dias_semana: preenchidos }];
    })));
  };
  const toggleDia = (dia) => atualizarDias(form.dias_semana.includes(dia)
    ? form.dias_semana.filter((item) => item !== dia)
    : [...form.dias_semana, dia]);
  const inicioEmMinutos = minutosDoDia(form.horario_inicio);
  const definirDuracao = (minutos) => {
    if (inicioEmMinutos == null) return;
    const total = inicioEmMinutos + minutos;
    if (total >= 24 * 60) return;
    set('horario_fim', `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`);
  };
  const duracaoAtual = (() => {
    const fim = minutosDoDia(form.horario_fim);
    if (inicioEmMinutos == null || fim == null) return null;
    const minutos = fim - inicioEmMinutos;
    return minutos > 0 ? minutos : null;
  })();
  const alterarDiasAluno = (matriculaId, dia) => setRemapeamentos((current) => {
    const item = current[String(matriculaId)];
    const diasSelecionados = item.dias_semana.includes(dia)
      ? item.dias_semana.filter((value) => value !== dia)
      : [...item.dias_semana, dia];
    return { ...current, [String(matriculaId)]: { ...item, dias_semana: diasSelecionados } };
  });

  const salvar = async () => {
    if (saving || !ready) return;
    setSaving(true);
    setErro('');
    try {
      if (!form.dias_semana.length) throw new Error('Selecione ao menos um dia recorrente.');
      if (gradeAlterada) {
        if (!detalhe || loadingVinculos) throw new Error('Aguarde o carregamento dos vínculos da turma.');
        if (Object.values(remapeamentos).some((item) => !item.dias_semana.length || item.dias_semana.some((dia) => !form.dias_semana.includes(dia)))) {
          throw new Error('Confirme os dias de cada aluno; eles precisam pertencer à nova grade.');
        }
        await acessoHibridoService.executarAcao('grade', [form.id], {
          from: desde,
          nome: form.nome.trim() || null,
          professor_id: Number(form.professor_id),
          dias_semana: form.dias_semana.map(Number),
          horario_inicio: form.horario_inicio,
          horario_fim: form.horario_fim,
          capacidade: Number(form.capacidade),
          checkin_livre: form.checkin_livre,
          remapeamentos: (detalhe.alunos || []).map((aluno) => remapeamentos[String(aluno.matricula_id)]),
          avisar: true,
        });
      } else if (form.id) {
        await acessoHibridoService.atualizarTurma(form.id, {
          nome: form.nome.trim(),
          capacidade: Number(form.capacidade),
          checkin_livre: form.checkin_livre,
        });
      } else {
        await acessoHibridoService.criarTurma({
          nome: form.nome.trim(),
          professor_id: Number(form.professor_id),
          modalidade_id: Number(form.modalidade_id),
          horario_inicio: form.horario_inicio,
          horario_fim: form.horario_fim,
          capacidade: Number(form.capacidade),
          dias_semana: form.dias_semana.map(Number),
          checkin_livre: form.checkin_livre,
        });
      }
      showSuccess('Turma recorrente salva. Matrículas, valores e pagamentos existentes foram preservados.');
      setForm(vazio);
      setNomePersonalizado(false);
      setReload((value) => value + 1);
      onSaved?.();
    } catch (e) {
      setErro(erroApi(e));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      open
      title={initialId ? 'Editar turma' : 'Nova turma'}
      size="lg"
      dialogClassName="ck-tu-dlg"
      description={initialId ? 'Edite os dados e a grade semanal em uma única ação. Alterações na grade criam uma nova versão e preservam o histórico.' : 'Horário fixo que se repete toda semana. As aulas entram na agenda sozinhas.'}
      onClose={onClose}
      dismissable={!saving}
      footer={<>
        <Button disabled={saving} onClick={onClose}>Fechar</Button>
        <Button variant="primary" loading={saving} disabled={loading || !ready || (gradeAlterada && (loadingVinculos || !!erroVinculos))} onClick={salvar}>{initialId ? 'Salvar turma' : 'Criar turma'}</Button>
      </>}
    >
      {erro ? <Callout tone="danger" title="Não foi possível concluir">{erro}<Button onClick={() => setReload((value) => value + 1)}>Recarregar</Button></Callout> : null}
      {loading ? <p role="status">Carregando…</p> : (
        <div className="ck-tu-form">
        <fieldset className="ck-tu-fields" disabled={saving}>
          <section className="ck-pc__sec">
          <header><span className="ck-pc__secn">1</span><h3>Modalidade e nome</h3></header>
          <SelectField
            label="Modalidade"
            placeholder="Selecione a modalidade"
            value={form.modalidade_id}
            disabled={!!form.id}
            options={modalidades.map((modalidade) => ({ value: String(modalidade.id), label: modalidade.nome }))}
            onChange={(event) => set('modalidade_id', event.target.value)}
          />
          <TextField
            label="Nome (opcional)"
            hint="Sugerido automaticamente pelos dias e horários. Você pode personalizar."
            value={form.nome}
            onChange={(event) => set('nome', event.target.value)}
          />
          </section>
          <section className="ck-pc__sec">
          <header><span className="ck-pc__secn">2</span><h3>Dias e horário</h3></header>
          <div className="ck-field">
            <span className="ck-field__label">Dias recorrentes</span>
            <div className="ck-tu-presets" role="group" aria-label="Atalhos de dias recorrentes">
              <button type="button" onClick={() => atualizarDias([1, 2, 3, 4, 5])} aria-pressed={form.dias_semana.length === 5 && [1, 2, 3, 4, 5].every(dia => form.dias_semana.includes(dia))}>Seg–Sex</button>
              <button type="button" onClick={() => atualizarDias([6, 7])} aria-pressed={form.dias_semana.length === 2 && [6, 7].every(dia => form.dias_semana.includes(dia))}>Fim de semana</button>
              <button type="button" onClick={() => atualizarDias([1, 2, 3, 4, 5, 6, 7])} aria-pressed={form.dias_semana.length === 7}>Todos</button>
            </div>
            <div className="ck-tu-dayset" role="group" aria-label="Dias recorrentes">
            {dias.map((dia, index) => <button key={dia} type="button" className="ck-tu-day" aria-label={dia} aria-pressed={form.dias_semana.includes(index + 1)} onClick={() => toggleDia(index + 1)}>{dia.slice(0, 3)}</button>)}
            </div>
          </div>
          <div className="ck-al-grid">
            <TextField label="Início" type="time" value={form.horario_inicio} onChange={(event) => set('horario_inicio', event.target.value)} />
            <TextField label="Fim" type="time" value={form.horario_fim} onChange={(event) => set('horario_fim', event.target.value)} />
          </div>
          <div className="ck-tu-presets" role="group" aria-label="Duração da aula">
            <span>Duração:</span>
            {[45, 60, 90].map(minutos => <button key={minutos} type="button" disabled={inicioEmMinutos == null || inicioEmMinutos + minutos >= 24 * 60} aria-pressed={duracaoAtual === minutos} onClick={() => definirDuracao(minutos)}>{minutos} min</button>)}
          </div>
          {gradeAlterada ? <>
            <TextField label="Aplicar a partir de" type="date" min={amanha()} value={desde} onChange={(event) => setDesde(event.target.value)} />
            <p className="ck-field__hint">Esta data inicia a nova versão; a turma continua recorrente, sem data de término. Aulas anteriores e histórico ficam preservados.</p>
            {erroVinculos ? <Callout tone="danger" title="Não foi possível carregar os vínculos">{erroVinculos}<Button size="sm" onClick={() => setReload((value) => value + 1)}>Tentar novamente</Button></Callout> : null}
            {loadingVinculos ? <p role="status">Carregando alunos afetados…</p> : (detalhe?.alunos || []).map((aluno) => {
              const item = remapeamentos[String(aluno.matricula_id)] || { dias_semana: [] };
              return <div className="ck-card ck-tu-transfer" key={aluno.matricula_id}>
                <strong>{aluno.nome} · Matrícula #{aluno.matricula_id}</strong>
                <p>{aluno.plano_nome} · {aluno.checkins_semanais || 'Ilimitado'}x · Dias atuais: {aluno.dias_semana.map((value) => dias[Number(value) - 1]).join(', ')}</p>
                <div className="ck-tu-dayset" role="group" aria-label={`Dias de ${aluno.nome} na nova grade`}>
                  {dias.map((dia, index) => <button key={dia} type="button" className="ck-tu-day" disabled={!form.dias_semana.includes(index + 1)} aria-label={dia} aria-pressed={item.dias_semana.includes(index + 1)} onClick={() => alterarDiasAluno(aluno.matricula_id, index + 1)}>{dia.slice(0, 3)}</button>)}
                </div>
              </div>;
            })}
          </> : null}
          </section>
          <section className="ck-pc__sec">
          <header><span className="ck-pc__secn">3</span><h3>Professor e vagas</h3></header>
          <div className="ck-al-grid">
          <SelectField
            label="Professor"
            placeholder="Selecione o professor"
            value={form.professor_id}
          options={professores.map((professor) => ({ value: String(professor.id), label: professor.nome }))}
            onChange={(event) => set('professor_id', event.target.value)}
          />
            <TextField label="Capacidade" type="number" min="1" max="500" hint="Vagas por aula" value={form.capacidade} onChange={(event) => set('capacidade', event.target.value)} />
          </div>
          <label>
            <input type="checkbox" checked={form.checkin_livre} onChange={(event) => set('checkin_livre', event.target.checked)} />
            Permitir check-in de horário livre nas novas aulas desta turma
          </label>
          <p className="ck-field__hint">Planos, frequência e valores permanecem na matrícula de cada aluno.</p>
          </section>
        </fieldset>
        <aside className="ck-tu-side" aria-label="Prévia da turma" aria-live="polite">
          <strong>Como fica na semana</strong>
          <div className="ck-tu-week">{dias.map((dia, index) => <div key={dia} className={`ck-tu-week__d${form.dias_semana.includes(index + 1) ? ' is-on' : ''}`}><span>{dia.slice(0, 3)}</span><b>{form.dias_semana.includes(index + 1) ? form.horario_inicio || '—' : '·'}</b></div>)}</div>
          <strong className="ck-tu-side__name">{form.nome || 'Nome da turma'}</strong>
          <span className="ck-tu-side__line">{modalidades.find(item => String(item.id) === form.modalidade_id)?.nome || 'Escolha a modalidade'}</span>
          <span className="ck-tu-side__line">{form.dias_semana.map(dia => dias[dia - 1].slice(0, 3)).join(', ') || 'Escolha os dias'}{form.horario_inicio && form.horario_fim ? `, ${form.horario_inicio} às ${form.horario_fim}` : ''}</span>
          <span className="ck-tu-side__line">{professores.find(item => String(item.id) === form.professor_id)?.nome || 'Selecione o professor'}</span>
          <span className="ck-tu-side__line">{form.capacidade || '—'} vagas por aula{form.checkin_livre ? ' · check-in livre nas novas aulas' : ''}</span>
          {!form.id ? <span className="ck-tu-side__line">Aulas geradas para os próximos 90 dias, respeitando dias ativos e conflitos de agenda.</span> : null}
        </aside>
        </div>
      )}
    </Modal>
  );
}

const diaNome = (dia) => dias[Number(dia) - 1] || `Dia ${dia}`;

export function TipoAcessoMatricula({ matricula, onSaved }) {
  const agora = new Date();
  const dataHoje = `${agora.getFullYear()}-${String(agora.getMonth() + 1).padStart(2, '0')}-${String(agora.getDate()).padStart(2, '0')}`;
  const [open, setOpen] = useState(false);
  const [turmas, setTurmas] = useState([]);
  const [tipo, setTipo] = useState(matricula.tipo_acesso || 'horario_livre');
  const [gradeAtual, setGradeAtual] = useState({ grade: [], grade_revisao: false, frequencia_semanal: Number(matricula.checkins_semanais) || 0 });
  const [grade, setGrade] = useState([]);
  const [erro, setErro] = useState('');
  const [saving, setSaving] = useState(false);
  const [loadingGrade, setLoadingGrade] = useState(true);
  const [reload, setReload] = useState(0);

  useEffect(() => {
    let active = true;
    setLoadingGrade(true);
    acessoHibridoService.gradeMatricula(matricula.id)
      .then((response) => {
        if (!active) return;
        setGradeAtual({
          grade: Array.isArray(response?.grade) ? response.grade : [],
          grade_agendada: Array.isArray(response?.grade_agendada) ? response.grade_agendada.filter(item => item.vigencia_inicio > dataHoje || item.vigencia_fim) : [],
          grade_revisao: response?.grade_revisao === true || Number(response?.grade_revisao) === 1,
          frequencia_semanal: Number(response?.frequencia_semanal ?? matricula.checkins_semanais) || 0,
        });
      })
      .catch((e) => { if (active) setErro(erroApi(e)); })
      .finally(() => { if (active) setLoadingGrade(false); });
    return () => { active = false; };
  }, [matricula.id, matricula.checkins_semanais, reload]);

  useEffect(() => {
    if (!open) return;
    let active = true;
    Promise.all([
      acessoHibridoService.listarTurmas(),
      acessoHibridoService.gradeMatricula(matricula.id),
    ])
      .then(([lista, resposta]) => {
        if (!active) return;
        setTurmas(Array.isArray(lista) ? lista : []);
        const registros = Array.isArray(resposta?.grade) ? resposta.grade : [];
        setGrade(registros.map((item) => ({
          turma_fixa_id: String(item.turma_fixa_id),
          dia_semana: Number(item.dia_semana),
        })));
        setGradeAtual({
          grade: registros,
          grade_agendada: Array.isArray(resposta?.grade_agendada) ? resposta.grade_agendada.filter(item => item.vigencia_inicio > dataHoje || item.vigencia_fim) : [],
          grade_revisao: resposta?.grade_revisao === true || Number(resposta?.grade_revisao) === 1,
          frequencia_semanal: Number(resposta?.frequencia_semanal ?? matricula.checkins_semanais) || 0,
        });
        setTipo(matricula.tipo_acesso || 'horario_livre');
        setErro('');
      })
      .catch((e) => { if (active) setErro(erroApi(e)); });
    return () => { active = false; };
  }, [open, matricula.id, matricula.tipo_acesso, matricula.checkins_semanais]);

  const salvar = async () => {
    if (saving) return;
    const selecionados = grade.filter((item) => item.turma_fixa_id && item.dia_semana);
    const frequencia = Number(gradeAtual.frequencia_semanal);
    const maximo = frequencia > 0 ? frequencia : null;
    if (tipo === 'turma_fixa') {
      if (!selecionados.length || selecionados.length !== grade.length) {
        setErro('Escolha uma turma e um dia para cada entrada da grade.');
        return;
      }
      if (maximo != null && selecionados.length > maximo) {
        setErro(`O plano exige exatamente ${maximo} dia(s) e horário(s) por semana.`);
        return;
      }
      const chaves = selecionados.map((item) => `${item.turma_fixa_id}:${item.dia_semana}`);
      if (new Set(chaves).size !== chaves.length) {
        setErro('A mesma turma e o mesmo dia não podem aparecer mais de uma vez.');
        return;
      }
      const modalidadeId = Number(matricula.modalidade_id);
      if (selecionados.some((item) => {
        const turma = turmas.find((row) => String(row.id) === String(item.turma_fixa_id));
        return !turma || Number(turma.modalidade_id) !== modalidadeId || !turma.dias_semana?.map(Number).includes(Number(item.dia_semana));
      })) {
        setErro('A grade deve usar turmas da modalidade contratada e dias cadastrados nelas.');
        return;
      }
    }

    setSaving(true);
    setErro('');
    try {
      await acessoHibridoService.vincular(matricula.id, {
        tipo_acesso: tipo,
        grade: tipo === 'turma_fixa' ? selecionados.map((item) => ({
          turma_fixa_id: Number(item.turma_fixa_id),
          dia_semana: Number(item.dia_semana),
        })) : [],
      });
      setOpen(false);
      setReload((value) => value + 1);
      onSaved?.();
    } catch (e) {
      setErro(erroApi(e));
    } finally {
      setSaving(false);
    }
  };

  const registrosGrade = gradeAtual.grade || [];
  return (
    <section className="ck-card ck-mt-access">
      <header>
        <strong>Tipo de acesso: {matricula.tipo_acesso === 'turma_fixa' ? 'Turma fixa' : 'Horário livre'}</strong>
        <Button size="sm" disabled={loadingGrade || gradeAtual.grade_agendada?.length > 0} onClick={() => setOpen(true)}>Editar acesso e grade</Button>
      </header>
      {loadingGrade ? <p role="status">Carregando grade…</p> : (
        <>
          {erro && !open ? <Callout tone="danger" title="Não foi possível carregar a grade">{erro}</Callout> : null}
          {matricula.status_codigo === 'aguardando_vaga' ? <Callout tone="warning" title="Pagamento recebido, aguardando vaga">O pagamento está preservado; conclua o vínculo de turmas e dias quando houver disponibilidade.</Callout> : null}
          {matricula.tipo_acesso === 'turma_fixa' && registrosGrade.length ? (
            <div className="ck-mt-access__grade">
              <strong>Grade semanal · frequência contratada {Number(gradeAtual.frequencia_semanal) > 0 ? `${gradeAtual.frequencia_semanal}x por semana` : 'livre'}</strong>
              <ul>{registrosGrade.map((item, index) => (
                <li key={`${item.turma_fixa_id}-${item.dia_semana}-${index}`}>
                  {diaNome(item.dia_semana)} · {item.nome} · {hora(item.horario_inicio)}–{hora(item.horario_fim)}
                </li>
              ))}</ul>
            </div>
          ) : matricula.tipo_acesso === 'turma_fixa' ? <p>Nenhuma grade recorrente vinculada.</p> : null}
          {gradeAtual.grade_revisao ? <Callout tone="warning" title="Grade precisa de revisão">Confira a frequência semanal contratada e ajuste os dias vinculados à matrícula.</Callout> : null}
          {gradeAtual.grade_agendada?.length ? <Callout tone="info" title="Mudança de grade agendada">Confira a gestão de Turmas antes de editar.<ul>{gradeAtual.grade_agendada.map((item, index) => <li key={index}>{diaNome(item.dia_semana)} · {item.nome} · {hora(item.horario_inicio)}–{hora(item.horario_fim)} · {item.vigencia_fim ? `Até ${item.vigencia_fim}` : `A partir de ${item.vigencia_inicio}`}</li>)}</ul></Callout> : null}
        </>
      )}
      <Modal
        open={open}
        title="Tipo de acesso e grade da matrícula"
        dismissable={!saving}
        onClose={() => setOpen(false)}
        footer={<>
          <Button disabled={saving} onClick={() => setOpen(false)}>Cancelar</Button>
          <Button variant="primary" loading={saving} onClick={salvar}>Salvar grade</Button>
        </>}
      >
        <SelectField
          label="Tipo de acesso"
          value={tipo}
          options={[{ value: 'horario_livre', label: 'Horário livre' }, { value: 'turma_fixa', label: 'Turma fixa' }]}
          onChange={(event) => {
            setTipo(event.target.value);
            if (event.target.value === 'horario_livre') setGrade([]);
          }}
        />
        {tipo === 'turma_fixa' ? (
          <GradeTurmaEditor
            grade={grade.length ? grade : [{ turma_fixa_id: '', dia_semana: '' }]}
            onChange={setGrade}
            turmas={turmas}
            modalidadeId={matricula.modalidade_id}
            frequenciaSemanal={gradeAtual.frequencia_semanal}
            disabled={saving}
          />
        ) : <p>A modalidade, o plano e os pagamentos permanecem vinculados à matrícula.</p>}
        <p>A frequência máxima segue o plano contratado. Alterar a grade não muda valores, ciclo ou pagamentos.</p>
        {erro ? <p role="alert">{erro}</p> : null}
      </Modal>
    </section>
  );
}
