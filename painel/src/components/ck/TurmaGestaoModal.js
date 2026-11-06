import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import { Button, Callout, Modal, SelectField, TextField } from './index';
import GradeTurmaEditor from './GradeTurmaEditor';
import { acessoHibridoService } from '../../services/acessoHibridoService';
import { showSuccess } from '../../utils/toast';

const dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const titulos = { detalhe: 'Detalhe da turma', duplicar: 'Duplicar turma', professor: 'Trocar professor', grade: 'Alterar dias e horário', capacidade: 'Ajustar vagas', pausar: 'Pausar turmas', retomar: 'Retomar turmas', arquivar: 'Arquivar turmas', reativar: 'Reativar turmas', excluir: 'Excluir turma' };
const dataLocal = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const hoje = () => dataLocal(new Date());
const amanha = () => { const date = new Date(); date.setDate(date.getDate() + 1); return dataLocal(date); };
const erroApi = error => Object.values(error?.response?.data?.errors || {}).flat().join(' ') || error?.response?.data?.message || error?.message || 'Não foi possível concluir a operação.';

export default function TurmaGestaoModal({ acao, turmas, todas, professores, onClose, onSaved, onEdit, onAction }) {
  const router = useRouter();
  const inicial = turmas[0];
  const [form, setForm] = useState({
    from: acao === 'pausar' ? hoje() : amanha(), to: '', motivo: '', professor_id: String(inicial.professor_id || ''),
    nome: acao === 'duplicar' ? `${inicial.nome} (cópia)` : inicial.nome,
    dias_semana: inicial.dias_semana.map(Number), horario_inicio: String(inicial.horario_inicio).slice(0, 5),
    horario_fim: String(inicial.horario_fim).slice(0, 5), capacidade: String(inicial.capacidade),
    avisar: true,
  });
  const [detalhes, setDetalhes] = useState([]);
  const [remapeamentos, setRemapeamentos] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [erro, setErro] = useState('');
  const [reload, setReload] = useState(0);
  const [tab, setTab] = useState('alunos');
  const comVigencia = ['grade', 'professor', 'arquivar'].includes(acao);
  const ids = turmas.map(turma => Number(turma.id));
  const set = (key, value) => setForm(current => ({ ...current, [key]: value }));

  useEffect(() => {
    let active = true;
    setLoading(true);
    setDetalhes([]);
    setErro('');
    Promise.all(ids.map(id => acessoHibridoService.detalheTurma(id, comVigencia ? form.from : undefined)))
      .then(lista => {
        if (!active) return;
        setDetalhes(lista);
        setRemapeamentos(Object.fromEntries(lista.flatMap(item => item.alunos.map(aluno => [`${item.turma.id}-${aluno.matricula_id}`, {
          matricula_id: Number(aluno.matricula_id), dias_semana: [], grade: [], retirar: false,
        }]))));
      })
      .catch(error => { if (active) setErro(erroApi(error)); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [acao, turmas, comVigencia, form.from, reload]);

  const modificarRemapeamento = (id, valores) => setRemapeamentos(current => ({ ...current, [id]: { ...current[id], ...valores } }));
  const salvar = async () => {
    if (loading || saving) return;
    setErro('');
    if (['grade', 'duplicar'].includes(acao) && !form.dias_semana.length) { setErro('Selecione os dias da turma.'); return; }
    if (acao === 'pausar' && (!form.to || form.to < form.from)) { setErro('Informe o período de pausa.'); return; }
    if (acao === 'grade' && Object.values(remapeamentos).some(item => !item.dias_semana.length || item.dias_semana.some(dia => !form.dias_semana.includes(dia)))) {
      setErro('Confirme os novos dias de cada aluno; eles devem pertencer à grade da turma.'); return;
    }
    if (acao === 'arquivar' && Object.values(remapeamentos).some(item => !item.retirar && (!item.grade.length || item.grade.some(slot => !slot.turma_fixa_id || !slot.dia_semana)))) {
      setErro('Escolha horários de destino ou confirme explicitamente a retirada de cada vínculo.'); return;
    }
    setSaving(true);
    try {
      const porTurma = detalhes.map(detalhe => ({
        id: detalhe.turma.id,
        dados: { ...form, professor_id: Number(form.professor_id), capacidade: Number(form.capacidade),
          remapeamentos: detalhe.alunos.map(aluno => ({
            ...remapeamentos[`${detalhe.turma.id}-${aluno.matricula_id}`],
            grade: (remapeamentos[`${detalhe.turma.id}-${aluno.matricula_id}`]?.grade || []).map(slot => ({ turma_fixa_id: Number(slot.turma_fixa_id), dia_semana: Number(slot.dia_semana) })),
          })) },
      }));
      const dados = acao === 'arquivar' && ids.length > 1 ? { por_turma: porTurma } : porTurma[0]?.dados || form;
      const response = await acessoHibridoService.executarAcao(acao, ids, dados);
      showSuccess(response.message || 'Operação concluída.');
      onSaved();
    } catch (error) {
      setErro(erroApi(error));
    } finally {
      setSaving(false);
    }
  };

  const alunos = detalhes.flatMap(detalhe => detalhe.alunos.map(aluno => ({ ...aluno, turma: detalhe.turma })));
  return <Modal open size="lg" dialogClassName="ck-tu-gestao" title={titulos[acao]} dismissable={!saving} onClose={onClose}
    description={turmas.map(turma => turma.nome).join(', ')}
    footer={<><Button disabled={saving} onClick={onClose}>Fechar</Button>{acao !== 'detalhe' ? <Button variant={['arquivar', 'excluir'].includes(acao) ? 'danger' : 'primary'} loading={saving} disabled={loading || !detalhes.length} onClick={salvar}>Confirmar</Button> : <><Button onClick={() => router.push('/matriculas/novo')}>Nova matrícula</Button><Button disabled={!!detalhes[0]?.turma.substituida_por_id} onClick={() => onEdit(inicial)}>Editar turma</Button></>}</>}>
    {erro ? <Callout tone="danger" title="Não foi possível concluir">{erro}<Button size="sm" onClick={() => setReload(value => value + 1)}>Recarregar dados</Button></Callout> : null}
    {loading ? <p role="status">Carregando vínculos e aulas…</p> : acao === 'detalhe' ? <>
      <div className="ck-tu-bar2" role="group" aria-label="Detalhes da turma">{['alunos', 'aulas', 'historico'].map(value => <Button key={value} variant={tab === value ? 'primary' : 'ghost'} size="sm" onClick={() => setTab(value)}>{value === 'alunos' ? 'Alunos' : value === 'aulas' ? 'Aulas e ocupação' : 'Histórico'}</Button>)}</div>
      {detalhes[0]?.turma.vigencia_fim ? <Callout tone="warning" title="Encerramento agendado">Válida até {detalhes[0].turma.vigencia_fim}.{detalhes[0].turma.substituida_por_id ? ` Nova versão: turma #${detalhes[0].turma.substituida_por_id}.` : ''}</Callout> : null}
      {detalhes[0]?.pausas.filter(pausa => pausa.fim >= hoje()).map(pausa => <Callout key={pausa.id} tone="warning" title="Pausa programada">{pausa.inicio} a {pausa.fim}: {pausa.motivo || 'Sem motivo informado'}. Cobrança e vigência permanecem iguais.</Callout>)}
      {tab === 'alunos' ? <ul className="ck-tu-studs">{alunos.length ? alunos.map(aluno => <li key={aluno.matricula_id}><div><strong>{aluno.nome}</strong><span>{aluno.plano_nome} · {aluno.status_codigo} · {aluno.dias_semana.map(dia => dias[Number(dia) - 1]).join(', ')}</span></div><Button size="sm" onClick={() => router.push(`/matriculas/detalhe?id=${aluno.matricula_id}`)}>Ver matrícula</Button></li>) : <li>Nenhum vínculo vigente.</li>}</ul> : null}
      {tab === 'aulas' ? <ul className="ck-tu-studs">{detalhes[0]?.aulas.map(aula => <li key={aula.id}><div><strong>{aula.dia_data} · {String(aula.horario_inicio).slice(0, 5)}–{String(aula.horario_fim).slice(0, 5)}</strong><span>{Number(aula.ativo) ? 'Ativa' : 'Cancelada/inativa'} · {aula.vagas.vagas_fixas} fixos · {aula.vagas.reposicoes_confirmadas} reposições · {aula.vagas.vagas_livres_ocupadas} livres · {aula.vagas.vagas_livres} vagas livres</span></div><Button size="sm" onClick={() => router.push(`/turmas?data=${aula.dia_data}`)}>Ver na agenda</Button></li>)}</ul> : null}
      {tab === 'historico' ? <ul className="ck-tu-studs">{detalhes[0]?.historico.length ? detalhes[0].historico.map(evento => <li key={evento.id}><div><strong>{evento.acao} · {evento.created_at}</strong><span>Operador #{evento.usuario_id || 'sistema'}{evento.detalhes?.resultado?.vigencia_inicio ? ` · Vigência ${evento.detalhes.resultado.vigencia_inicio}` : ''}</span></div></li>) : <li>Sem ações registradas nesta nova gestão.</li>}</ul> : null}
      {detalhes[0]?.turma.situacao !== 'arquivada' && !detalhes[0]?.turma.vigencia_fim ? <div className="ck-tu-bar2"><Button onClick={() => onAction('professor', [inicial])}>Trocar professor</Button><Button onClick={() => onAction('grade', [inicial])}>Alterar dias e horário</Button><Button onClick={() => onAction('pausar', [inicial])}>Pausar</Button></div> : null}
    </> : <fieldset className="ck-tu-fields" disabled={saving}>
      {comVigencia ? <><TextField label="A partir de" type="date" min={amanha()} value={form.from} onChange={event => set('from', event.target.value)} /><p className="ck-field__hint">A partir de amanhã ou data futura. Aulas anteriores, presenças e grade histórica não são reescritas.</p></> : null}
      {['professor', 'grade', 'duplicar'].includes(acao) ? <SelectField label="Professor" value={form.professor_id} options={professores.map(prof => ({ value: String(prof.id), label: prof.nome }))} onChange={event => set('professor_id', event.target.value)} /> : null}
      {['grade', 'duplicar'].includes(acao) ? <>
        <TextField label="Nome" value={form.nome} onChange={event => set('nome', event.target.value)} />
        <div role="group" aria-label="Dias da turma" className="ck-tu-dayset">{dias.map((dia, index) => <button key={dia} type="button" className="ck-tu-day" aria-label={dia} aria-pressed={form.dias_semana.includes(index + 1)} onClick={() => set('dias_semana', form.dias_semana.includes(index + 1) ? form.dias_semana.filter(item => item !== index + 1) : [...form.dias_semana, index + 1])}>{dia.slice(0, 3)}</button>)}</div>
        <div className="ck-al-grid"><TextField label="Início" type="time" value={form.horario_inicio} onChange={event => set('horario_inicio', event.target.value)} /><TextField label="Fim" type="time" value={form.horario_fim} onChange={event => set('horario_fim', event.target.value)} /></div>
        {acao === 'duplicar' ? <Callout tone="info" title="Nova turma sem vínculos">Alunos, grade individual, check-ins e reposições não serão copiados. Ajuste horário/dias para não conflitar com o professor.</Callout> : null}
      </> : null}
      {acao === 'capacidade' ? <TextField label="Vagas por aula" type="number" min="1" max="500" value={form.capacidade} onChange={event => set('capacidade', event.target.value)} /> : null}
      {acao === 'pausar' ? <><div className="ck-al-grid"><TextField label="De" type="date" min={hoje()} value={form.from} onChange={event => set('from', event.target.value)} /><TextField label="Até" type="date" min={form.from} value={form.to} onChange={event => set('to', event.target.value)} /></div><TextField label="Motivo" value={form.motivo} onChange={event => set('motivo', event.target.value)} /><Callout tone="warning" title="Somente as aulas do período">A grade continua reservada. Mensalidades e vigência não mudam. A turma retorna automaticamente após o fim pelo agendamento da API.</Callout></> : null}
      {['grade', 'arquivar'].includes(acao) ? <section aria-label="Conferência dos vínculos"><h3>{acao === 'grade' ? 'Remapear dias de cada aluno' : 'Transferir ou retirar cada vínculo'}</h3>{alunos.length ? alunos.map(aluno => {
        const key = `${aluno.turma.id}-${aluno.matricula_id}`;
        const item = remapeamentos[key] || { dias_semana: [], grade: [], retirar: false };
        return <section className="ck-card ck-tu-transfer" key={`${aluno.turma.id}-${aluno.matricula_id}`}><strong>{aluno.nome} · Matrícula #{aluno.matricula_id}</strong><p>{aluno.plano_nome} · {aluno.checkins_semanais || 'Ilimitado'}x · Dias atuais: {aluno.dias_semana.map(dia => dias[Number(dia) - 1]).join(', ')}</p>
          {acao === 'grade' ? <div className="ck-tu-dayset" role="group" aria-label={`Novos dias de ${aluno.nome}`}>{dias.map((dia, index) => <button key={dia} type="button" className="ck-tu-day" disabled={!form.dias_semana.includes(index + 1)} aria-label={dia} aria-pressed={item.dias_semana.includes(index + 1)} onClick={() => modificarRemapeamento(key, { dias_semana: item.dias_semana.includes(index + 1) ? item.dias_semana.filter(value => value !== index + 1) : [...item.dias_semana, index + 1] })}>{dia.slice(0, 3)}</button>)}</div> : <>
            <label><input type="checkbox" checked={item.retirar} onChange={event => modificarRemapeamento(key, { retirar: event.target.checked, grade: [] })} />Confirmo retirar os horários desta turma sem transferir; matrícula e cobrança continuam.</label>
            {!item.retirar ? <GradeTurmaEditor grade={item.grade} onChange={grade => modificarRemapeamento(key, { grade })} turmas={todas.filter(turma => !ids.includes(Number(turma.id)))} dataVigencia={form.from} modalidadeId={aluno.turma.modalidade_id} frequenciaSemanal={aluno.checkins_semanais} /> : null}
          </>}
        </section>;
      }) : <p>Nenhuma matrícula vigente afetada na data escolhida.</p>}</section> : null}
      {['retomar', 'reativar'].includes(acao) ? <Callout tone="info" title="Histórico preservado">{acao === 'retomar' ? 'Cancela pausas futuras e reabre apenas aulas suspensas por esta gestão. Cancelamentos manuais continuam.' : 'Reabre a geração de aulas. Vínculos retirados no arquivamento não voltam automaticamente.'}</Callout> : null}
      {acao === 'excluir' ? <Callout tone="danger" title="Excluir sem apagar histórico">Só é permitido quando não existe vínculo de grade, check-in ou reposição. A turma sai do cadastro, mas ocorrências e auditoria ficam preservadas. Para as demais, use arquivar.</Callout> : null}
      {['grade', 'professor', 'pausar', 'retomar', 'arquivar'].includes(acao) ? <label><input type="checkbox" checked={form.avisar} onChange={event => set('avisar', event.target.checked)} />Publicar aviso aos alunos na Central de Comunicação do app</label> : null}
    </fieldset>}
  </Modal>;
}
