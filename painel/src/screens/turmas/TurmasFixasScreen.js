import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Avatar, Badge, Button, Callout, Icon, PageHeader, SegmentedControl, SelectField } from '../../components/ck';
import { TurmasFixas } from '../../components/ck/TurmasFixas';
import { acessoHibridoService } from '../../services/acessoHibridoService';
import modalidadeService from '../../services/modalidadeService';
import { professorService } from '../../services/professorService';

const dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const abreviacoes = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
const hora = (value) => String(value || '').slice(0, 5);
const normalizar = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const mensagemErro = (error) => error?.response?.data?.message || error?.message || 'Não foi possível carregar as turmas recorrentes.';

function DiasTurma({ lista }) {
  return <span className="ck-tu-days" aria-label={lista.map(dia => dias[dia - 1]).join(', ')}>
    {dias.map((dia, index) => <i key={dia} className={lista.includes(index + 1) ? 'is-on' : ''} aria-hidden="true">{abreviacoes[index].slice(0, 1)}</i>)}
  </span>;
}

export default function TurmasFixasScreen() {
  const router = useRouter();
  const [turmas, setTurmas] = useState([]);
  const [modalidades, setModalidades] = useState([]);
  const [professores, setProfessores] = useState([]);
  const [loading, setLoading] = useState(true);
  const [erro, setErro] = useState('');
  const [modal, setModal] = useState(null);
  const [reload, setReload] = useState(0);
  const [query, setQuery] = useState('');
  const [modalidade, setModalidade] = useState('');
  const [professor, setProfessor] = useState('');
  const [diasFiltro, setDiasFiltro] = useState([]);
  const [situacao, setSituacao] = useState('ativas');
  const [view, setView] = useState('lista');

  useEffect(() => {
    let active = true;
    setLoading(true);
    setErro('');
    Promise.all([acessoHibridoService.listarTurmas(), modalidadeService.listar(), professorService.listar(false)])
      .then(([lista, mods, profs]) => {
        if (!active) return;
        setTurmas(lista);
        setModalidades(mods);
        setProfessores(profs);
      })
      .catch((error) => { if (active) setErro(mensagemErro(error)); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [reload]);

  const atualizar = () => setReload(value => value + 1);
  const salvar = () => { setModal(null); atualizar(); };
  const limpar = () => { setQuery(''); setModalidade(''); setProfessor(''); setDiasFiltro([]); };
  const filtrado = !!(query || modalidade || professor || diasFiltro.length);
  const linhas = turmas.map(turma => ({
    ...turma,
    dias: Array.isArray(turma.dias_semana) ? turma.dias_semana.map(Number) : [],
    modalidade: modalidades.find(item => Number(item.id) === Number(turma.modalidade_id)),
    professor: professores.find(item => Number(item.id) === Number(turma.professor_id)),
  }));
  const ativas = linhas.filter(turma => Number(turma.ativo) === 1);
  const rows = linhas.filter(turma =>
    (situacao === 'ativas' ? Number(turma.ativo) === 1 : Number(turma.ativo) !== 1)
    && (!modalidade || String(turma.modalidade_id) === modalidade)
    && (!professor || String(turma.professor_id) === professor)
    && diasFiltro.every(dia => turma.dias.includes(dia))
    && normalizar(`${turma.nome} ${turma.modalidade?.nome || ''} ${turma.professor?.nome || ''}`).includes(normalizar(query.trim())),
  ).sort((a, b) => hora(a.horario_inicio).localeCompare(hora(b.horario_inicio)) || a.nome.localeCompare(b.nome));
  const editar = turma => setModal({ id: turma.id });

  return <LayoutBase title="Turmas" pageHeader={false}>
    <div className="ck-tu">
      <PageHeader title="Turmas" description="Horários fixos da semana. Cada turma gera as aulas da agenda; presenças e cancelamentos de um dia ficam em Aulas."
        actions={<><Button icon="calendar" onClick={() => router.push('/turmas')}>Ver agenda</Button><Button variant="primary" icon="plus" onClick={() => setModal({ id: null })}>Nova turma</Button></>} />
      <div className="ck-tu-stats" aria-label="Resumo das turmas">
        <div><span>Turmas ativas</span><strong>{loading || erro ? '—' : ativas.length}</strong><small>Grades recorrentes</small></div>
        <div><span>Aulas por semana</span><strong>{loading || erro ? '—' : ativas.reduce((total, turma) => total + turma.dias.length, 0)}</strong><small>Dias das turmas ativas</small></div>
        <div><span>Capacidade semanal</span><strong>{loading || erro ? '—' : ativas.reduce((total, turma) => total + Number(turma.capacidade) * turma.dias.length, 0)}</strong><small>Vagas por aula × dias, não saldo livre</small></div>
        <div><span>Check-in livre</span><strong>{loading || erro ? '—' : ativas.filter(turma => Number(turma.checkin_livre)).length}</strong><small>Turmas habilitadas nas novas aulas</small></div>
      </div>
      {erro ? <Callout tone="danger" title="Não foi possível carregar as turmas" action={<Button size="sm" onClick={atualizar}>Tentar novamente</Button>}>{erro}</Callout> : null}
      <section className="ck-pc__panel" aria-label="Lista de turmas">
        <div className="ck-tu-bar">
          <SegmentedControl label="Situação" value={situacao} onChange={setSituacao} options={[{ value: 'ativas', label: `Ativas ${ativas.length}` }, { value: 'inativas', label: `Inativas ${linhas.length - ativas.length}` }]} />
          <div className="ck-control ck-tu-search"><Icon name="search" size={16} className="ck-control__icon" /><input type="search" className="ck-control__input" aria-label="Buscar turma ou professor" placeholder="Buscar turma ou professor" value={query} onChange={event => setQuery(event.target.value)} /></div>
          <SelectField label="Modalidade" value={modalidade} options={[{ value: '', label: 'Todas as modalidades' }, ...modalidades.map(item => ({ value: String(item.id), label: item.nome }))]} onChange={event => setModalidade(event.target.value)} />
          <SelectField label="Professor" value={professor} options={[{ value: '', label: 'Todos os professores' }, ...professores.map(item => ({ value: String(item.id), label: item.nome }))]} onChange={event => setProfessor(event.target.value)} />
        </div>
        <div className="ck-tu-bar2">
          <div className="ck-tu-dayf" role="group" aria-label="Filtrar por dia"><span>Dias:</span>{dias.map((dia, index) => <button key={dia} type="button" className="ck-tu-day is-sm" aria-label={dia} aria-pressed={diasFiltro.includes(index + 1)} onClick={() => setDiasFiltro(current => current.includes(index + 1) ? current.filter(value => value !== index + 1) : [...current, index + 1])}>{abreviacoes[index]}</button>)}</div>
          <span className="ck-tu-count">{rows.length} {rows.length === 1 ? 'turma' : 'turmas'}{filtrado ? ' com esses filtros' : ''}</span>
          {filtrado ? <Button size="sm" variant="ghost" onClick={limpar}>Limpar filtros</Button> : null}
          <SegmentedControl label="Visualização" value={view} onChange={setView} options={[{ value: 'lista', label: 'Lista', icon: 'layers' }, { value: 'grade', label: 'Grade', icon: 'calendar' }]} />
          <Button size="sm" icon="refresh-cw" disabled={loading} onClick={atualizar}>Atualizar</Button>
        </div>
        {loading ? <p className="ck-tu-empty" role="status">Carregando turmas…</p> : !erro && !rows.length ? <div className="ck-tu-empty"><Icon name={filtrado ? 'search' : 'calendar'} size={32} /><h2>{filtrado ? 'Nenhuma turma com esses filtros' : 'Nenhuma turma cadastrada'}</h2><p>{filtrado ? 'Tire algum filtro para ver mais.' : 'Crie a primeira turma com os dias e o horário fixos; as aulas entram na agenda sozinhas.'}</p>{filtrado ? <Button onClick={limpar}>Limpar filtros</Button> : <Button variant="primary" icon="plus" onClick={() => setModal({ id: null })}>Nova turma</Button>}</div> : !erro && view === 'grade' ? (
          <div className="ck-tu-grid">{dias.map((dia, index) => {
            const turmasDia = rows.filter(turma => turma.dias.includes(index + 1));
            return <div className="ck-tu-col" key={dia}><div className="ck-tu-col__h"><strong>{dia}</strong><span>{turmasDia.length} turmas</span></div>{turmasDia.length ? turmasDia.map(turma => <button key={turma.id} type="button" className="ck-tu-card" onClick={() => editar(turma)} style={{ '--mod': turma.modalidade?.cor || 'var(--info)' }}><span className="ck-tu-card__time">{hora(turma.horario_inicio)}–{hora(turma.horario_fim)}</span><strong>{turma.nome}</strong><span className="ck-tu-card__meta">{turma.modalidade?.nome || `Modalidade #${turma.modalidade_id}`}</span><span className="ck-tu-card__meta">{turma.professor?.nome || `Professor #${turma.professor_id}`}</span><span className="ck-tu-card__meta">{turma.capacidade} vagas por aula</span></button>) : <span className="ck-tu-col__empty">Sem turmas</span>}</div>;
          })}</div>
        ) : !erro ? (
          <div className="ck-pc__wrap"><table className="ck-pc__table ck-tu-table"><thead><tr>{['Turma', 'Dias', 'Horário', 'Professor', 'Capacidade', 'Ações'].map(label => <th scope="col" key={label}>{label}</th>)}</tr></thead><tbody>{rows.map(turma => <tr key={turma.id}>
            <th scope="row"><div className="ck-pc__name"><span className="ck-pc__icon"><Icon name="calendar" size={18} /></span><div><strong>{turma.nome}</strong><span>{turma.modalidade?.nome || `Modalidade #${turma.modalidade_id}`}</span>{Number(turma.checkin_livre) ? <Badge tone="info">Check-in livre</Badge> : null}</div></div></th>
            <td><DiasTurma lista={turma.dias} /><span className="ck-pc__sub">{turma.dias.map(dia => abreviacoes[dia - 1]).join(', ')}</span></td>
            <td><span className="ck-pc__main">{hora(turma.horario_inicio)}–{hora(turma.horario_fim)}</span></td>
            <td><span className="ck-pc__person"><Avatar name={turma.professor?.nome || `Professor ${turma.professor_id}`} size="sm" decorative />{turma.professor?.nome || `Professor #${turma.professor_id}`}</span></td>
            <td><span className="ck-pc__main">{turma.capacidade}</span><span className="ck-pc__sub">vagas por aula</span></td>
            <td><Button size="sm" variant="ghost" icon="edit-2" onClick={() => editar(turma)}>Editar</Button></td>
          </tr>)}</tbody></table></div>
        ) : null}
      </section>
    </div>
    {modal ? <TurmasFixas key={modal.id || 'nova'} initialId={modal.id} onClose={() => setModal(null)} onSaved={salvar} /> : null}
  </LayoutBase>;
}
