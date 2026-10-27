import React, { useCallback, useEffect, useRef, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { ActionMenu, Badge, Button, Callout, Icon, PageHeader, SelectField, TextField } from '../../components/ck';
import recorrenciaService from '../../services/recorrenciaService';
import { authService } from '../../services/authService';
import RecorrenciaDetalheModal from './RecorrenciaDetalheModal';
import { STATUS, brl, dataBr, msg } from './recorrenciaUtil';

/**
 * Financeiro → Recorrências: assinaturas mensais dos planos com fidelização (Asaas). Cada mês é uma
 * cobrança separada; o contrato guarda valores e termos aceitos.
 */
const FILTRO_STATUS = [
  { value: 'vigentes', label: 'Vigentes' },
  { value: '', label: 'Todos os status' },
  ...Object.entries(STATUS).map(([value, [, label]]) => ({ value, label })),
];

export default function RecorrenciasScreen() {
  const [ind, setInd] = useState(null);
  const [filtros, setFiltros] = useState({ status: 'vigentes', busca: '', fidelizacao: '' });
  const [busca, setBusca] = useState('');
  const [pagina, setPagina] = useState(1);
  const [dados, setDados] = useState({ contratos: [], paginacao: {} });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [aberto, setAberto] = useState(null);
  const [podeAgir, setPodeAgir] = useState(false);
  const req = useRef(0);

  const carregar = useCallback(async () => {
    const id = ++req.current;
    setLoading(true); setError('');
    try {
      const [l, i] = await Promise.all([
        recorrenciaService.listar({ status: filtros.status || undefined, busca: busca || undefined, fidelizacao: filtros.fidelizacao || undefined, pagina, por_pagina: 20 }),
        recorrenciaService.indicadores(),
      ]);
      if (id !== req.current) return;
      setDados(l); setInd(i);
    } catch (e) {
      if (id === req.current) setError(msg(e, 'Não foi possível carregar as recorrências.'));
    } finally {
      if (id === req.current) setLoading(false);
    }
  }, [filtros.status, filtros.fidelizacao, busca, pagina]);

  useEffect(() => { carregar(); }, [carregar]);
  useEffect(() => { const t = setTimeout(() => { setBusca(filtros.busca.trim()); setPagina(1); }, 300); return () => clearTimeout(t); }, [filtros.busca]);
  useEffect(() => { authService.temPermissao('financeiro.baixar').then(setPodeAgir).catch(() => {}); }, []);

  const pag = dados.paginacao || {};
  const kpis = ind ? [
    ['Contratos ativos', ind.contratos_ativos, `${ind.em_fidelizacao} em fidelização`],
    ['Receita mensal recorrente', brl(ind.mrr), `prevista em 30 dias ${brl(ind.receita_prevista_30d)}`],
    ['Fidelização terminando', ind.fidelizacao_terminando_30d, 'nos próximos 30 dias'],
    ['Cancelamentos solicitados', ind.cancelamentos_solicitados, 'aguardando confirmação', ind.cancelamentos_solicitados > 0],
    ['Inadimplência', ind.inadimplentes, `${brl(ind.valor_em_atraso)} em atraso`, ind.inadimplentes > 0],
    ['Descontos concedidos', brl(ind.descontos_concedidos), 'em mensalidades pagas na fidelização'],
    ['Regularizações pendentes', brl(ind.regularizacoes_pendentes), 'a confirmar ou a receber'],
  ] : [];

  return <LayoutBase title="Recorrências" pageHeader={false}>
    <div className="ck-rec">
      <PageHeader title="Recorrências" description="Planos com fidelização: assinatura mensal com preço promocional. Cada mês é uma cobrança separada no Asaas, nunca um parcelamento." />
      {error ? <Callout tone="danger" title="Não foi possível carregar" action={<Button size="sm" onClick={carregar}>Tentar novamente</Button>}>{error}</Callout> : null}
      {ind ? <div className="ck-rec__kpis">{kpis.map(([r, v, s, alerta]) => <div key={r} className={'ck-rec__kpi' + (alerta ? ' is-warn' : '')}><span>{r}</span><strong>{v}</strong><small>{s}</small></div>)}</div> : null}

      <div className="ck-pc__panel">
        <div className="ck-rec__filtros">
          <TextField icon="search" placeholder="Aluno, e-mail ou plano" aria-label="Buscar" value={filtros.busca} onChange={(e) => setFiltros({ ...filtros, busca: e.target.value })} />
          <SelectField aria-label="Status" value={filtros.status} options={FILTRO_STATUS} onChange={(e) => { setFiltros({ ...filtros, status: e.target.value }); setPagina(1); }} />
          <SelectField aria-label="Fidelização" value={filtros.fidelizacao} onChange={(e) => { setFiltros({ ...filtros, fidelizacao: e.target.value }); setPagina(1); }}
            options={[{ value: '', label: 'Com e sem fidelização' }, { value: '1', label: 'Em fidelização' }, { value: '0', label: 'Fidelização concluída' }]} />
        </div>
        {loading && !dados.contratos.length ? <div className="ck-ml__empty" role="status"><p>Carregando…</p></div>
          : dados.contratos.length ? <div className="ck-pc__wrap"><table className="ck-pc__table ck-rec__table">
            <thead><tr>
              <th scope="col">Aluno</th><th scope="col">Plano</th><th scope="col" className="is-num">Mensalidade</th><th scope="col">Fidelização</th>
              <th scope="col">Progresso</th><th scope="col">Próxima cobrança</th><th scope="col">Status</th><th scope="col"><span className="ck-sr">Ações</span></th>
            </tr></thead>
            <tbody>{dados.contratos.map((c) => <tr key={c.id} className="is-click" tabIndex={0} aria-label={`Detalhar contrato de ${c.aluno_nome}`}
              onClick={(e) => { if (!e.target.closest('button, [role="menu"]')) setAberto(c.id); }}
              onKeyDown={(e) => { if (e.key === 'Enter' && e.target === e.currentTarget) setAberto(c.id); }}>
              <th scope="row"><strong>{c.aluno_nome}</strong><span className="ck-pc__sub">{c.aluno_email}</span></th>
              <td>{c.plano_nome}<span className="ck-pc__sub">{c.ciclo_nome}</span></td>
              <td className="is-num"><span className="ck-pc__main">{brl(c.valor_mensal_vigente)}</span><span className="ck-pc__sub">{brl(c.desconto_mensal)} de desconto</span></td>
              <td>{c.fidelizacao_meses} meses<span className="ck-pc__sub">até {dataBr(c.fim_fidelizacao_em)}</span></td>
              <td><div className="ck-rec__prog" role="img" aria-label={`${c.meses_cumpridos} de ${c.fidelizacao_meses} meses`}>
                <span style={{ width: `${Math.round((c.meses_cumpridos / Math.max(1, c.fidelizacao_meses)) * 100)}%` }} /></div>
                <span className="ck-pc__sub">{c.meses_cumpridos}/{c.fidelizacao_meses}{c.em_fidelizacao ? '' : ' · concluída'}</span></td>
              <td>{dataBr(c.proxima_cobranca_em)}</td>
              <td><Badge tone={STATUS[c.status]?.[0] || 'neutral'} dot>{c.status_nome}</Badge></td>
              <td><div className="ck-pc__acts"><ActionMenu label={`Ações para ${c.aluno_nome}`} items={[
                { label: 'Detalhar', icon: 'eye', onClick: () => setAberto(c.id) },
                podeAgir && ['ativo', 'pendente_ativacao', 'pagamento_pendente', 'inadimplente', 'cancelamento_solicitado'].includes(c.status)
                  ? { label: c.status === 'cancelamento_solicitado' ? 'Confirmar cancelamento' : 'Simular cancelamento', icon: 'calculator', onClick: () => setAberto(c.id) } : null,
              ].filter(Boolean)} /></div></td>
            </tr>)}</tbody>
          </table></div> : <div className="ck-ml__empty">
            <span className="ck-mt-empty__icon" aria-hidden><Icon name="repeat" size={20} /></span>
            <strong>Nenhuma assinatura {filtros.status === 'vigentes' ? 'vigente' : 'com esses filtros'}</strong>
            <p>Ative "Fidelização com cobrança mensal" num ciclo de 2 meses ou mais (Planos) para os alunos assinarem pelo app.</p>
          </div>}
        {pag.paginas > 1 ? <div className="ck-rec__pag">
          <span>{pag.total} contratos · página {pagina} de {pag.paginas}</span>
          <Button size="sm" icon="chevron-left" disabled={pagina <= 1} onClick={() => setPagina(pagina - 1)}>Anterior</Button>
          <Button size="sm" iconRight="chevron-right" disabled={pagina >= pag.paginas} onClick={() => setPagina(pagina + 1)}>Próxima</Button>
        </div> : null}
      </div>
    </div>
    {aberto ? <RecorrenciaDetalheModal open contratoId={aberto} canAct={podeAgir} onClose={() => setAberto(null)} onChanged={carregar} /> : null}
  </LayoutBase>;
}
