import React, { useEffect, useState } from 'react';
import { Button, Callout, Modal, SelectField, TextField } from './index';
import { acessoHibridoService } from '../../services/acessoHibridoService';
import modalidadeService from '../../services/modalidadeService';
import { professorService } from '../../services/professorService';
import { showSuccess } from '../../utils/toast';
import GradeTurmaEditor from './GradeTurmaEditor';

const dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const vazio = { nome: '', professor_id: '', modalidade_id: '', horario_inicio: '', horario_fim: '', capacidade: '12', dias_semana: [], checkin_livre: false };
const erroApi = (e) => Object.values(e?.response?.data?.errors || {}).flat().join(' ') || e?.response?.data?.message || e.message;
const hora = (value) => String(value || '').slice(0, 5);

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
        setForm(selecionada ? formTurma(selecionada) : vazio);
        setReady(true);
      })
      .catch((e) => { if (active) setErro(erroApi(e)); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [reload, initialId]);

  const set = (key, value) => setForm((current) => ({ ...current, [key]: value }));
  const toggleDia = (dia) => set('dias_semana', form.dias_semana.includes(dia)
    ? form.dias_semana.filter((item) => item !== dia)
    : [...form.dias_semana, dia]);

  const salvar = async () => {
    if (saving || !ready) return;
    setSaving(true);
    setErro('');
    try {
      const dados = {
        nome: form.nome.trim(),
        professor_id: Number(form.professor_id),
        modalidade_id: Number(form.modalidade_id),
        horario_inicio: form.horario_inicio,
        horario_fim: form.horario_fim,
        capacidade: Number(form.capacidade),
        dias_semana: form.dias_semana.map(Number),
        checkin_livre: form.checkin_livre,
      };
      if (form.id) await acessoHibridoService.atualizarTurma(form.id, dados);
      else await acessoHibridoService.criarTurma(dados);
      showSuccess('Turma recorrente salva. Matrículas, valores e pagamentos existentes foram preservados.');
      setForm(vazio);
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
      title={initialId ? 'Editar turma' : 'Criar turma'}
      onClose={onClose}
      dismissable={!saving}
      footer={<>
        <Button disabled={saving} onClick={onClose}>Fechar</Button>
        <Button variant="primary" loading={saving} disabled={loading || !ready} onClick={salvar}>{initialId ? 'Salvar turma' : 'Criar turma'}</Button>
      </>}
    >
      {erro ? <Callout tone="danger" title="Não foi possível concluir">{erro}<Button onClick={() => setReload((value) => value + 1)}>Recarregar</Button></Callout> : null}
      {loading ? <p role="status">Carregando…</p> : (
        <fieldset disabled={saving || !ready}>
          <TextField label="Nome" value={form.nome} onChange={(event) => set('nome', event.target.value)} />
          <SelectField
            label="Modalidade"
            placeholder="Selecione a modalidade"
            value={form.modalidade_id}
            disabled={!!form.id}
            options={modalidades.map((modalidade) => ({ value: String(modalidade.id), label: modalidade.nome }))}
            onChange={(event) => set('modalidade_id', event.target.value)}
          />
          <SelectField
            label="Professor"
            placeholder="Selecione o professor"
            value={form.professor_id}
            disabled={!!form.id}
            options={professores.map((professor) => ({ value: String(professor.id), label: professor.nome }))}
            onChange={(event) => set('professor_id', event.target.value)}
          />
          <div className="ck-al-grid">
            <TextField label="Início" type="time" disabled={!!form.id} value={form.horario_inicio} onChange={(event) => set('horario_inicio', event.target.value)} />
            <TextField label="Fim" type="time" disabled={!!form.id} value={form.horario_fim} onChange={(event) => set('horario_fim', event.target.value)} />
            <TextField label="Capacidade" type="number" min="1" value={form.capacidade} onChange={(event) => set('capacidade', event.target.value)} />
          </div>
          <fieldset disabled={!!form.id}>
            <legend>Dias recorrentes</legend>
            {dias.map((dia, index) => (
              <label key={dia}>
                <input type="checkbox" checked={form.dias_semana.includes(index + 1)} onChange={() => toggleDia(index + 1)} />
                {dia}{' '}
              </label>
            ))}
          </fieldset>
          <label>
            <input type="checkbox" checked={form.checkin_livre} onChange={(event) => set('checkin_livre', event.target.checked)} />
            Permitir check-in de horário livre nas novas aulas desta turma
          </label>
          <p>As turmas recorrentes organizam a grade; a modalidade e a frequência contratadas pertencem à matrícula. Para alterar os dias ou horários de uma turma existente, crie outra turma.</p>
        </fieldset>
      )}
    </Modal>
  );
}

const diaNome = (dia) => dias[Number(dia) - 1] || `Dia ${dia}`;

export function TipoAcessoMatricula({ matricula, onSaved }) {
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
        setErro(`A frequência do plano permite até ${maximo} dia(s) por semana.`);
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
        <Button size="sm" onClick={() => setOpen(true)}>Editar acesso e grade</Button>
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
