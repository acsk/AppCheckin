import React, { useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, PageHeader, SegmentedControl } from '../../components/ck';
import { Panel, StatTile } from '../../components/ck/pessoas';
import { ColumnChart, deltaObj } from '../../components/ck/graficos';
import { fdate, money, moneyShort } from '../../components/ck/financeiro';
import { buscarBiGlobal } from '../../services/biService';
import { BotaoExportar, ComoCalculamos, Empty, PERIODOS, mesCurto, mesLongo, num, textoVs, useBiCarga } from './biComum';

// Fórmulas exibidas na tela; a fonte é App\Services\Bi\BiGlobalService
const COMO_CALCULAMOS = [
  ['Por academia', 'Os mesmos números do BI de cada academia (alunos ativos, novos, saídas, receita, check-ins). Os totais são a soma. O tenant do sistema fica de fora.'],
  ['Receita das academias', 'O que os alunos pagaram às academias no período, pela data do pagamento (inclui parcelas depois estornadas).'],
  ['Receita da plataforma', 'Mensalidades das academias para o Check-in pagas no período. Vencido: em aberto com vencimento no período, antes de hoje.'],
  ['Academias com alunos', 'Academias com pelo menos um aluno ativo no fim do período.'],
  ['Histórico', 'Alunos ativos no último dia de cada mês; receita e check-ins somados no mês.'],
];

const ORDENS = [
  { value: 'alunos_ativos', label: 'Alunos' },
  { value: 'receita_recebida', label: 'Receita' },
  { value: 'checkins', label: 'Check-ins' },
];

export default function BiGlobalScreen() {
  const [periodo, setPeriodo] = useState('mes');
  const [ordem, setOrdem] = useState('alunos_ativos');
  const { dados, erro, loading, recarregar } = useBiCarga(buscarBiGlobal, periodo);

  const ind = dados?.indicadores || {};
  const per = dados?.periodo;
  const vs = textoVs(per);
  const v = (x) => (loading && !dados ? '—' : x);
  const par = (k) => ind[k] || {};
  const carregando = loading && !dados;
  const carregandoTxt = <p className="ck-fin-empty" role="status">Carregando…</p>;

  const academias = [...(dados?.academias || [])].sort((a, b) => b[ordem] - a[ordem] || a.nome.localeCompare(b.nome, 'pt-BR'));
  const mensal = dados?.mensal || [];
  const serie = (k) => mensal.map((m) => ({ label: mesCurto(m.mes), tip: mesLongo(m.mes), value: m[k] }));

  return (
    <LayoutBase title="BI Global" pageHeader={false}>
      <div className="ck-db">
        <PageHeader
          title="BI Global"
          description={per ? `De ${fdate(per.inicio)} a ${fdate(per.fim)}. Todas as academias somadas e a receita da plataforma.` : 'Todas as academias somadas e a receita da plataforma.'}
          actions={<>
            <SegmentedControl label="Período" value={periodo} onChange={setPeriodo} options={PERIODOS} />
            <BotaoExportar alvo={{ tela: 'global' }} periodo={periodo}>Exportar academias</BotaoExportar>
          </>}
        />

        {erro ? (
          <Callout tone="warning" title="Os indicadores não carregaram" action={<Button size="sm" icon="refresh-cw" onClick={recarregar}>Tentar de novo</Button>}>
            {erro}
          </Callout>
        ) : null}

        <div className="ck-db__kpis" aria-busy={loading}>
          <StatTile label="Academias com alunos" icon="building-2" value={v(num(par('academias_com_alunos').atual))}
            delta={deltaObj(par('academias_com_alunos').atual, par('academias_com_alunos').anterior, vs, true)}
            note={dados ? `${num(par('academias_ativas').atual)} academias ativas` : null} />
          <StatTile label="Alunos ativos" icon="users" value={v(num(par('alunos_ativos').atual))}
            delta={deltaObj(par('alunos_ativos').atual, par('alunos_ativos').anterior, vs, true)}
            note={dados ? `${num(par('novos_alunos').atual)} novos, ${num(par('saidas').atual)} saídas` : null} />
          <StatTile label="Receita das academias" icon="wallet" value={v(money(par('receita_academias').atual))} note={dados ? 'Pago pelos alunos' : null} />
          <StatTile label="Check-ins" icon="activity" value={v(num(par('checkins').atual))} />
        </div>

        <div className="ck-db__kpis">
          <StatTile label="Receita da plataforma" icon="banknote" value={v(money(par('receita_plataforma').atual))}
            delta={deltaObj(par('receita_plataforma').atual, par('receita_plataforma').anterior, vs, true)}
            note={dados ? `${num(par('academias_pagantes').atual)} ${par('academias_pagantes').atual === 1 ? 'academia pagou' : 'academias pagaram'}` : null} />
          <StatTile label="Plataforma: vencido" icon="alert-triangle" value={v(money(par('plataforma_vencido').atual))} note={dados ? 'Mensalidades das academias em atraso' : null} />
        </div>

        <div className="ck-db__row ck-db__row--1-1">
          <Panel title="Alunos ativos por mês" description="Somando as academias, no último dia de cada mês.">
            {carregando ? carregandoTxt : mensal.some((m) => m.alunos_ativos) ? <ColumnChart data={serie('alunos_ativos')} highlightLast height={180} caption="Alunos ativos por mês" />
              : <Empty icon="users" title="Nenhum aluno ativo nos últimos 12 meses" />}
          </Panel>
          <Panel title="Receita das academias por mês" description="Pago pelos alunos, pela data do pagamento.">
            {carregando ? carregandoTxt : mensal.some((m) => m.receita) ? <ColumnChart data={serie('receita')} highlightLast height={180} format={money} formatAxis={moneyShort} caption="Receita das academias por mês" />
              : <Empty icon="wallet" title="Nenhum pagamento nos últimos 12 meses" />}
          </Panel>
        </div>

        <Panel title="Academias" description="Números do período, iguais aos do BI de cada academia."
          actions={<SegmentedControl label="Ordenar por" value={ordem} onChange={setOrdem} options={ORDENS} />}>
          {carregando ? carregandoTxt : academias.length ? (
            <div className="ck-mt-table-wrap">
              <table className="ck-mt-table">
                <thead>
                  <tr>
                    <th scope="col">Academia</th>
                    <th scope="col" className="is-num">Alunos ativos</th>
                    <th scope="col" className="is-num">Novos</th>
                    <th scope="col" className="is-num">Saídas</th>
                    <th scope="col" className="is-num">Check-ins</th>
                    <th scope="col" className="is-num">Receita</th>
                    <th scope="col" className="is-num">Vencido</th>
                  </tr>
                </thead>
                <tbody>
                  {academias.map((a) => {
                    const dif = a.alunos_ativos - a.alunos_ativos_anterior;
                    return (
                      <tr key={a.tenant_id}>
                        <th scope="row">
                          <span className="ck-mt-cell__main">{a.nome}</span>
                          {!a.ativa ? <Badge tone="neutral">Inativa</Badge> : null}
                        </th>
                        <td className="is-num">
                          <span className="ck-mt-cell__main">{num(a.alunos_ativos)}</span>
                          {dif ? <span className="ck-mt-cell__sub">{`${dif > 0 ? '+' : '−'}${num(Math.abs(dif))} ${vs}`}</span> : null}
                        </td>
                        <td className="is-num">{num(a.novos_alunos)}</td>
                        <td className="is-num">{num(a.saidas)}</td>
                        <td className="is-num">{num(a.checkins)}</td>
                        <td className="is-num">
                          <span className="ck-mt-cell__main">{money(a.receita_recebida)}</span>
                          {a.estornos ? <span className="ck-mt-cell__sub">{`Estornos ${money(a.estornos)}`}</span> : null}
                        </td>
                        <td className="is-num">{money(a.valor_vencido)}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          ) : <Empty icon="building-2" title="Nenhuma academia cadastrada" />}
        </Panel>

        <ComoCalculamos itens={COMO_CALCULAMOS} />
      </div>
    </LayoutBase>
  );
}
