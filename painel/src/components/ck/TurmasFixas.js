import React, { useEffect, useState } from 'react';
import { Button, Callout, Modal, SelectField, TextField } from './index';
import { acessoHibridoService } from '../../services/acessoHibridoService';
import planoService from '../../services/planoService';
import modalidadeService from '../../services/modalidadeService';
import { professorService } from '../../services/professorService';
import { showSuccess } from '../../utils/toast';

const dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const vazio = { nome: '', professor_id: '', modalidade_id: '', horario_inicio: '', horario_fim: '', capacidade: '12', dias_semana: [], planos: [], checkin_livre: false };
const erroApi = (e) => Object.values(e?.response?.data?.errors || {}).flat().join(' ') || e?.response?.data?.message || e.message;

export function TurmasFixas({ onClose, onSaved }) {
  const [turmas, setTurmas] = useState([]);
  const [planos, setPlanos] = useState([]);
  const [professores, setProfessores] = useState([]);
  const [modalidades, setModalidades] = useState([]);
  const [form, setForm] = useState(vazio);
  const [erro, setErro] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [reload, setReload] = useState(0);
  useEffect(() => {
    let active = true;
    setLoading(true); setErro('');
    Promise.all([acessoHibridoService.listarTurmas(), planoService.listar(true), professorService.listar(false), modalidadeService.listar()])
      .then(([t, p, pr, m]) => {
        if (!active) return;
        setTurmas(t); setPlanos(Array.isArray(p) ? p : p.planos || []);
        setProfessores(pr); setModalidades(m);
      }).catch(e => { if (active) setErro(erroApi(e)); }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [reload]);
  const set = (key, value) => setForm(f => ({ ...f, [key]: value }));
  const toggle = (key, value) => set(key, form[key].includes(value) ? form[key].filter(v => v !== value) : [...form[key], value]);
  const salvar = async () => {
    if (saving) return;
    setSaving(true); setErro('');
    try {
      const dados = { ...form, professor_id: Number(form.professor_id), modalidade_id: Number(form.modalidade_id), capacidade: Number(form.capacidade) };
      form.id ? await acessoHibridoService.atualizarTurma(form.id, dados) : await acessoHibridoService.criarTurma(dados);
      showSuccess('Turma fixa salva. Valores dos contratos existentes preservados.');
      setForm(vazio); setReload(n => n + 1); onSaved?.();
    } catch (e) { setErro(erroApi(e)); } finally { setSaving(false); }
  };
  return <Modal open title="Turmas fixas" onClose={onClose} dismissable={!saving}
    footer={<><Button disabled={saving} onClick={onClose}>Fechar</Button><Button variant="primary" loading={saving} disabled={loading} onClick={salvar}>{form.id ? 'Salvar turma' : 'Criar turma'}</Button></>}>
    {erro ? <Callout tone="danger" title="Não foi possível concluir">{erro}<Button onClick={() => setReload(n => n + 1)}>Recarregar</Button></Callout> : null}
    {loading ? <p role="status">Carregando…</p> : <fieldset disabled={saving}>
      <SelectField label="Turma" value={form.id || ''} options={[{ value: '', label: 'Nova turma fixa' }, ...turmas.map(t => ({ value: t.id, label: t.nome }))]} onChange={e => {
        const t = turmas.find(t => String(t.id) === e.target.value);
        setForm(t ? { ...t, capacidade: String(t.capacidade), planos: t.planos.map(p => p.id), horario_inicio: t.horario_inicio.slice(0, 5), horario_fim: t.horario_fim.slice(0, 5), checkin_livre: !!Number(t.checkin_livre) } : vazio);
      }} />
      <TextField label="Nome" value={form.nome} onChange={e => set('nome', e.target.value)} />
      <SelectField label="Modalidade" placeholder="Selecione a modalidade" value={form.modalidade_id} disabled={!!form.id} options={modalidades.map(m => ({ value: m.id, label: m.nome }))} onChange={e => setForm(f => ({ ...f, modalidade_id: e.target.value, planos: [] }))} />
      <SelectField label="Professor" placeholder="Selecione o professor" value={form.professor_id} disabled={!!form.id} options={professores.map(p => ({ value: p.id, label: p.nome }))} onChange={e => set('professor_id', e.target.value)} />
      <div className="ck-al-grid">
        <TextField label="Início" type="time" disabled={!!form.id} value={form.horario_inicio} onChange={e => set('horario_inicio', e.target.value)} />
        <TextField label="Fim" type="time" disabled={!!form.id} value={form.horario_fim} onChange={e => set('horario_fim', e.target.value)} />
        <TextField label="Capacidade" type="number" min="1" value={form.capacidade} onChange={e => set('capacidade', e.target.value)} />
      </div>
      <fieldset disabled={!!form.id}><legend>Dias da semana</legend>{dias.map((dia, i) => <label key={dia}><input type="checkbox" checked={form.dias_semana.includes(i + 1)} onChange={() => toggle('dias_semana', i + 1)} />{dia} </label>)}</fieldset>
      <fieldset><legend>Planos disponíveis (preços do cadastro de planos)</legend>{planos.filter(p => String(p.modalidade_id) === String(form.modalidade_id) && p.ativo).map(p => <label key={p.id}><input type="checkbox" checked={form.planos.includes(p.id)} onChange={() => toggle('planos', p.id)} />{p.nome} — {Number(p.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })} </label>)}</fieldset>
      <label><input type="checkbox" checked={form.checkin_livre} onChange={e => set('checkin_livre', e.target.checked)} />Permitir horário livre nas novas aulas desta turma</label>
      <p>A secretaria pode alterar essa permissão em cada aula. Horários e dias de turmas existentes são preservados; para outra grade, crie uma turma e vincule as matrículas explicitamente.</p>
    </fieldset>}
  </Modal>;
}

export function TipoAcessoMatricula({ matricula, onSaved }) {
  const [open, setOpen] = useState(false);
  const [turmas, setTurmas] = useState([]);
  const [tipo, setTipo] = useState(matricula.tipo_acesso || 'horario_livre');
  const [turma, setTurma] = useState(String(matricula.turma_fixa_id || ''));
  const [erro, setErro] = useState('');
  const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!open) return;
    let active = true;
    acessoHibridoService.listarTurmas().then(t => { if (active) setTurmas(t); }).catch(e => { if (active) setErro(erroApi(e)); });
    return () => { active = false; };
  }, [open]);
  const salvar = async () => {
    setSaving(true); setErro('');
    try {
      await acessoHibridoService.vincular(matricula.id, { tipo_acesso: tipo, turma_fixa_id: turma ? Number(turma) : null });
      setOpen(false); onSaved();
    } catch (e) { setErro(erroApi(e)); } finally { setSaving(false); }
  };
  return <><p>Tipo de acesso: <strong>{matricula.tipo_acesso === 'turma_fixa' ? 'Turma fixa' : 'Horário livre'}</strong> <Button size="sm" onClick={() => setOpen(true)}>Alterar vínculo</Button></p>
    {matricula.status_codigo === 'aguardando_vaga' ? <Callout tone="warning" title="Pagamento recebido, aguardando vaga">Vincule a uma turma com vaga e plano compatível. O pagamento foi preservado.</Callout> : null}
    <Modal open={open} title="Tipo de acesso da matrícula" dismissable={!saving} onClose={() => setOpen(false)}
      footer={<><Button disabled={saving} onClick={() => setOpen(false)}>Cancelar</Button><Button variant="primary" loading={saving} onClick={salvar}>Salvar vínculo</Button></>}>
      <SelectField label="Tipo de acesso" value={tipo} options={[{ value: 'horario_livre', label: 'Horário livre' }, { value: 'turma_fixa', label: 'Turma fixa' }]} onChange={e => setTipo(e.target.value)} />
      {tipo === 'turma_fixa' ? <SelectField label="Turma que oferece o plano contratado" placeholder="Selecione a turma" value={turma} options={turmas.filter(t => t.ativo && t.planos.some(p => Number(p.id) === Number(matricula.plano_id))).map(t => ({ value: t.id, label: t.nome }))} onChange={e => setTurma(e.target.value)} /> : null}
      <p>O vínculo muda apenas o acesso. O valor contratado e os pagamentos não são alterados.</p>
      {erro ? <p role="alert">{erro}</p> : null}
    </Modal></>;
}
