import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Icon, PageHeader, SegmentedControl, SelectField, TextField } from '../../components/ck';
import PainelRecuperacaoResumo from '../../components/ck/PainelRecuperacaoResumo';
import { DiagnosticoProspeccao, IniciarProspeccaoModal } from '../../components/ck/prospeccao';
import prospeccaoService from '../../services/prospeccaoService';
import { showError, showSuccess } from '../../utils/toast';
import { useProspeccaoContexto } from './useProspeccao';
import { dataBR, erroApi } from '../../utils/prospeccao';

const DEFINICOES = [
  ['Contagens', 'São de alunos (não de matrículas). Aluno com várias matrículas canceladas conta uma vez, pelo cancelamento mais recente.'],
  ['Período', 'Considera os alunos cujo cancelamento caiu no período escolhido. O status é o de hoje.'],
  ['Cancelados', 'Sem diárias avulsas e sem quem continua com outra matrícula em andamento.'],
  ['Elegíveis', 'Cancelados menos os que pediram para não ser contatados.'],
  ['Taxa de conversão', 'Recuperados ÷ elegíveis do mesmo período.'],
  ['Receita mensal recuperada', 'Valor mensal das matrículas de retorno que continuam ativas hoje.'],
  ['Recuperações por mês', 'Pela data em que o aluno voltou a ter matrícula ativa.'],
];

// Padrão: últimos 12 meses, do dia 1º (mesmo critério da API)
function periodoPadrao(preset = '12m') {
  const hoje = new Date();
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  return { de: iso(new Date(hoje.getFullYear(), preset === 'ano' ? 0 : hoje.getMonth() - (preset === '3m' ? 2 : 11), 1)), ate: iso(hoje) };
}

export default function PainelRecuperacaoScreen() {
  const router = useRouter();
  const [filtro, setFiltro] = useState(() => ({ ...periodoPadrao(), modalidade_id: '' }));
  const [preset, setPreset] = useState('12m');
  const [fila, setFila] = useState([]);
  const [erroFila, setErroFila] = useState('');
  const [carregando, setCarregando] = useState(true);
  const requestId = useRef(0);
  const [d, setD] = useState(null);
  const [rel, setRel] = useState(null);
  const [erro, setErro] = useState('');
  const [diagnostico, setDiagnostico] = useState(null);
  const [iniciar, setIniciar] = useState(false);
  const [sincronizando, setSincronizando] = useState(false);
  const { ctx } = useProspeccaoContexto();

  const carregar = useCallback(async () => {
    const request = ++requestId.current;
    setErro(''); setCarregando(true); setFila([]); setErroFila('');
    const params = Object.fromEntries(Object.entries(filtro).filter(([, v]) => v));
    try {
      const [dash, relatorio] = await Promise.all([prospeccaoService.dashboard(params), prospeccaoService.relatorio(params)]);
      if (request !== requestId.current) return;
      setD(dash); setRel(relatorio);
      if (!dash.indicadores.contatados && dash.indicadores.nao_contatados > 0) {
        prospeccaoService.listar({ status: 'nao_contatado', sem_contato: 1, ordem: 'score', direcao: 'desc', por_pagina: 5, cancelamento_de: filtro.de, cancelamento_ate: filtro.ate, modalidade_id: filtro.modalidade_id || undefined })
          .then((r) => { if (request === requestId.current) { setFila(r.oportunidades || []); if (!r.oportunidades?.length) setErroFila('Nenhum aluno disponível para contato neste filtro.'); } })
          .catch(() => { if (request === requestId.current) setErroFila('Não foi possível carregar as sugestões. Use Ver alunos para continuar.'); });
      }
      if (!dash.indicadores.cancelados) prospeccaoService.diagnostico().then((r) => { if (request === requestId.current) setDiagnostico(r.diagnostico); }).catch(() => {});
    } catch (e) { if (request === requestId.current) setErro(erroApi(e, 'Não foi possível carregar os indicadores.')); } finally { if (request === requestId.current) setCarregando(false); }
  }, [filtro.de, filtro.ate, filtro.modalidade_id]);
  useEffect(() => { carregar(); return () => { requestId.current += 1; }; }, [carregar]);

  const k = d?.indicadores;
  const set = (c, v) => setFiltro((f) => ({ ...f, [c]: v }));

  return <LayoutBase title="Painel de recuperação" pageHeader={false}>
    <div className="ck-rp ck-pr">
      <PageHeader title="Recuperação de alunos" description="Quem cancelou, quem já foi abordado e quem voltou."
        actions={<>
          <Button icon="calendar" onClick={() => router.push('/prospeccao/contatos')}>Contatos pendentes<span className={`ck-rp__pill${d?.agenda?.hoje || d?.agenda?.atrasados ? ' is-on' : ''}`}>{d?.agenda ? d.agenda.hoje + d.agenda.atrasados : '—'}</span></Button>
          <Button variant="primary" icon="users" onClick={() => router.push('/prospeccao/recuperacao')}>Ver alunos</Button>
        </>} />
      <div className="ck-rp__filters">
        <SegmentedControl label="Período" value={preset} onChange={(value) => { setPreset(value); if (value !== 'datas') setFiltro((f) => ({ ...f, ...periodoPadrao(value) })); }}
          options={[{ value: '12m', label: 'Últimos 12 meses' }, { value: 'ano', label: 'Este ano' }, { value: '3m', label: 'Últimos 3 meses' }, { value: 'datas', label: 'Escolher datas' }]} />
        {preset === 'datas' ? <div className="ck-rp__dates">
          <TextField label="De" type="date" value={filtro.de} max={filtro.ate} onChange={(e) => set('de', e.target.value)} />
          <TextField label="Até" type="date" value={filtro.ate} min={filtro.de} onChange={(e) => set('ate', e.target.value)} />
        </div> : null}
        <SelectField aria-label="Modalidade" value={filtro.modalidade_id} onChange={(e) => set('modalidade_id', e.target.value)}
          options={[{ value: '', label: 'Todas as modalidades' }, ...(d?.filtros?.modalidades || []).map((m) => ({ value: String(m.id), label: m.nome }))]} />
        <span className="ck-rp__range"><Icon name="calendar" size={15} />Cancelamentos de <b>{dataBR(filtro.de)} a {dataBR(filtro.ate)}</b></span>
      </div>
      {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}
      {!carregando && !erro && d?.agenda?.atrasados ? <Callout tone="warning" title={`${d.agenda.atrasados} retorno${d.agenda.atrasados > 1 ? 's' : ''} atrasado${d.agenda.atrasados > 1 ? 's' : ''}`}
        action={<Button size="sm" onClick={() => router.push('/prospeccao/contatos')}>Ver agenda</Button>}>Alunos esperando o contato combinado.</Callout> : null}
      {carregando && !erro ? <p role="status" className="ck-enrollment-state">Carregando indicadores…</p> : null}

      {k && !carregando && !erro && !k.cancelados ? <section className="ck-card" aria-label="Por que não há alunos no período">
        <DiagnosticoProspeccao diagnostico={diagnostico} admin={ctx?.admin} sincronizando={sincronizando} onIniciar={() => setIniciar(true)}
          onSincronizar={async () => {
            setSincronizando(true);
            try { const r = await prospeccaoService.sincronizar(!!ctx?.admin); setDiagnostico(r.diagnostico); showSuccess(r.message); carregar(); }
            catch (e) { showError(erroApi(e, 'Não foi possível sincronizar.')); } finally { setSincronizando(false); }
          }} />
      </section> : null}
      {k && !carregando && !erro ? <>
        <PainelRecuperacaoResumo dashboard={d} relatorio={rel} fila={fila} erroFila={erroFila}
          onAbrir={(aluno) => router.push(`/prospeccao/recuperacao/${aluno.id}`)}
          onModelos={() => router.push('/prospeccao/modelos')} onEquipe={ctx?.admin ? () => router.push('/prospeccao/configuracoes') : undefined} />

        <details className="ck-card ck-pr-def">
          <summary>Como os indicadores são calculados</summary>
          <dl>{DEFINICOES.map(([t, txt]) => <React.Fragment key={t}><dt>{t}</dt><dd>{txt}</dd></React.Fragment>)}</dl>
        </details>
      </> : null}
    </div>
    <IniciarProspeccaoModal open={iniciar} buscar={prospeccaoService.alunosCancelados} iniciar={prospeccaoService.iniciar}
      onClose={() => setIniciar(false)} onAbrir={(id) => { setIniciar(false); router.push(`/prospeccao/recuperacao/${id}`); }} />
  </LayoutBase>;
}
