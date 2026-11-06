import React, { useEffect, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout } from '../../components/ck';
import { TurmasFixas } from '../../components/ck/TurmasFixas';
import { acessoHibridoService } from '../../services/acessoHibridoService';

const dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const hora = (value) => String(value || '').slice(0, 5);
const nomeDias = (lista) => (Array.isArray(lista) ? lista : [])
  .map(Number)
  .filter((dia) => dia >= 1 && dia <= 7)
  .map((dia) => dias[dia - 1])
  .join(', ');
const mensagemErro = (error) => error?.response?.data?.message || error?.message || 'Não foi possível carregar as turmas recorrentes.';

export default function TurmasFixasScreen() {
  const [turmas, setTurmas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [erro, setErro] = useState('');
  const [modal, setModal] = useState(null);
  const [reload, setReload] = useState(0);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setErro('');
    acessoHibridoService.listarTurmas()
      .then((lista) => { if (active) setTurmas(Array.isArray(lista) ? lista : []); })
      .catch((error) => { if (active) setErro(mensagemErro(error)); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [reload]);

  const atualizar = () => setReload((value) => value + 1);
  const salvar = () => {
    setModal(null);
    atualizar();
  };

  return (
    <LayoutBase title="Turmas" subtitle="Cadastre e gerencie grades recorrentes por modalidade, dias, horário e capacidade.">
      <div className="ck-au__statusbar">
        <Button variant="primary" icon="plus" onClick={() => setModal({ id: null })}>Cadastrar grade recorrente</Button>
        <Button icon="refresh-cw" disabled={loading} onClick={atualizar}>Atualizar</Button>
        {loading ? <span role="status">Carregando turmas…</span> : null}
      </div>
      {erro ? <Callout tone="danger" title="Não foi possível carregar as turmas" action={<Button size="sm" onClick={atualizar}>Tentar novamente</Button>}>{erro}</Callout> : null}
      {!loading && !erro && !turmas.length ? (
        <section className="ck-card">
          <h2>Nenhuma grade recorrente cadastrada</h2>
          <p>Cadastre as turmas e dias recorrentes que poderão ser vinculados às matrículas. Planos, frequência e valores permanecem na matrícula.</p>
        </section>
      ) : null}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 16 }}>
        {turmas.map((turma) => (
          <article className="ck-card" key={turma.id} style={{ display: 'grid', gap: 10 }}>
            <header style={{ display: 'flex', justifyContent: 'space-between', gap: 12, alignItems: 'flex-start' }}>
              <div>
                <h2 style={{ margin: 0 }}>{turma.nome}</h2>
                <p style={{ margin: '4px 0 0' }}>{turma.modalidade?.nome || turma.modalidade_nome || `Modalidade #${turma.modalidade_id}`}</p>
              </div>
              <Button size="sm" variant="secondary" icon="edit-2" onClick={() => setModal({ id: turma.id })}>Editar</Button>
            </header>
            <dl style={{ display: 'grid', gridTemplateColumns: 'max-content 1fr', gap: '6px 12px', margin: 0 }}>
              <dt>Dias</dt><dd style={{ margin: 0 }}>{nomeDias(turma.dias_semana) || '—'}</dd>
              <dt>Horário</dt><dd style={{ margin: 0 }}>{hora(turma.horario_inicio)}–{hora(turma.horario_fim)}</dd>
              <dt>Professor</dt><dd style={{ margin: 0 }}>{turma.professor?.nome || turma.professor_nome || '—'}</dd>
              <dt>Capacidade</dt><dd style={{ margin: 0 }}>{turma.capacidade}</dd>
              <dt>Check-in livre</dt><dd style={{ margin: 0 }}>{Number(turma.checkin_livre) ? 'Permitido nas novas aulas' : 'Não permitido'}</dd>
            </dl>
          </article>
        ))}
      </div>
      {modal ? <TurmasFixas initialId={modal.id} onClose={() => setModal(null)} onSaved={salvar} /> : null}
    </LayoutBase>
  );
}
