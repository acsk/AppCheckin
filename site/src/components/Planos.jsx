import { useState } from 'react';
import { brl } from '../config';
import { assinarHref, cicloLabel, gratis, limitesTexto, nomeCurto, precoTexto, usePlanosPrincipais, valorCiclo } from '../planos';

const FREE_INCLUI = [
  'App do aluno para iPhone e Android',
  'Check-in com agenda e vagas',
  'Modo professor com chamada',
  'Cadastro de alunos, turmas e planos',
  'Ranking mensal de frequência',
];
const FREE_NAO_INCLUI = ['Pagamentos no app e recorrência', 'Dashboard e relatório financeiro'];
const PAGO_INCLUI = [
  'PIX e cartão no app',
  'Planos recorrentes com Asaas',
  'Mercado Pago e Asaas',
  'Dashboard e relatório financeiro',
  'Matrículas, pacotes e descontos',
  'Exportação em CSV e PDF',
];
const LIMITE_SEM_TETO = 200;

function Check() {
  return (
    <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M20 6 9 17l-5-5" />
    </svg>
  );
}

function Traco() {
  return (
    <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M5 12h14" />
    </svg>
  );
}

function Seta() {
  return (
    <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M5 12h14M12 5l7 7-7 7" />
    </svg>
  );
}

function alunosTexto(p) {
  return p.maxAlunos ? `até ${p.maxAlunos} alunos ativos` : 'sem limite de alunos';
}

function presetsAte(max) {
  const passo = max / 4;
  return [1, 2, 3, 4].map((i) => Math.round(passo * i));
}

function CartaoFree({ plano }) {
  const ciclo = cicloLabel(plano.ciclo);
  const tempo = plano.prazo ? `Por ${plano.prazo} dias` : 'Para sempre';
  return (
    <div className="plan">
      <div className="plan-top">
        <p className="eyebrow">{plano.nome}</p>
        <span className="badge plan-badge" style={{ background: '#e7f6ec', color: '#15703a' }}>Sem cartão</span>
      </div>
      <div className="plan-price num">R$ 0<small>/{ciclo}</small></div>
      <p className="plan-desc">{tempo}, {alunosTexto(plano)}. Ideal para tirar a chamada do papel e colocar a agenda no celular.</p>
      <a className="btn btn-dark" href={assinarHref(plano)}>Começar grátis</a>
      <div className="plan-list">
        {FREE_INCLUI.map((t) => <div key={t}><Check />{t}</div>)}
        {FREE_NAO_INCLUI.map((t) => <div key={t} className="no"><Traco />{t}</div>)}
      </div>
    </div>
  );
}

function CartaoPago({ plano, free, destaque }) {
  const preco = precoTexto(plano);
  const limite = plano.maxAlunos ? `Até ${plano.maxAlunos} alunos ativos; acima disso, fale com vendas.` : 'Sem limite de alunos.';
  const lista = destaque ? PAGO_INCLUI : limitesTexto(plano);
  return (
    <div className={destaque ? 'plan dark' : 'plan'}>
      {destaque ? <div className="cfac cf3" style={{ opacity: '.35', width: '240px', height: '170px' }} /> : null}
      <div className="plan-top">
        <p className="eyebrow" style={destaque ? { color: '#a3aab6' } : undefined}>{nomeCurto(plano)}</p>
        {destaque ? <span className="badge plan-badge" style={{ background: '#ff7a00', color: '#171a21' }}>Mais escolhido</span> : null}
      </div>
      <div className="plan-price num">{preco.valor}<small>{preco.sufixo}</small></div>
      <p className="plan-desc">{free ? `Tudo do ${free.nome}, mais a academia recebendo pelo app.` : 'A academia recebendo pelo app.'} {limite}</p>
      <a className={destaque ? 'btn btn-glow' : 'btn btn-dark'} href={assinarHref(plano)}>Assinar o {nomeCurto(plano)}</a>
      <div className="plan-list">
        {destaque && free ? <div><Check />Tudo do {free.nome}</div> : null}
        {lista.map((t) => <div key={t}><Check />{t}</div>)}
      </div>
    </div>
  );
}

function Simulador({ plano, free }) {
  const max = plano.maxAlunos || LIMITE_SEM_TETO;
  const [escolhidos, setAlunos] = useState(() => Math.round(max * 0.6));
  const alunos = Math.min(escolhidos, max);

  const porAluno = plano.tipo === 'por_aluno';
  const ciclo = cicloLabel(plano.ciclo);
  const nome = nomeCurto(plano);
  const palavra = alunos === 1 ? 'aluno' : 'alunos';
  const total = valorCiclo(plano, alunos);
  const pct = (alunos / max) * 100;
  const presets = presetsAte(max);
  const teto = plano.maxAlunos ? `, até ${plano.maxAlunos} alunos` : '';
  const semPagar = free ? ` Sem receber pelo app, o ${free.nome} continua R$ 0.` : '';

  return (
    <div className="sim" id="simulador">
      <div className="cfac cf1" style={{ opacity: '.5', width: '260px', height: '180px' }} />
      <div className="cfac cf3" style={{ opacity: '.35', width: '300px', height: '200px' }} />
      <div className="sim-l">
        <span className="sim-tag">
          <svg className="ic" viewBox="0 0 24 24" style={{ width: '14px', height: '14px' }} aria-hidden="true">
            <rect x="4" y="2" width="16" height="20" rx="2" />
            <path d="M8 6h8M8 10h2M14 10h2M8 14h2M14 14h2M8 18h2M14 18h2" />
          </svg>
          Simulador do plano {nome}
        </span>
        <h3>Quanto o {nome} custa para a sua academia?</h3>
        <p>
          {porAluno
            ? `Arraste até o número de alunos ativos. No ${nome}, você paga ${brl(plano.porAluno)} por aluno, por ${ciclo}${teto}.${semPagar}`
            : `Arraste até o número de alunos ativos. No ${nome}, o valor é fixo: ${brl(plano.valor)} por ${ciclo}${teto}. Quanto mais alunos, menor o custo de cada um.${semPagar}`}
        </p>
        <div className="sim-count">
          <label htmlFor="alunos">Alunos ativos</label>
          <output htmlFor="alunos" className="num">{alunos}<small>{palavra}</small></output>
        </div>
        <input id="alunos" type="range" min="0" max={max} step="1" value={alunos} onChange={(e) => setAlunos(Number(e.target.value))} aria-valuetext={`${alunos} ${palavra}`} style={{ background: `linear-gradient(90deg, #fdb400 0%, #ff7a00 ${pct * 0.6}%, #f2400a ${pct}%, #343a45 ${pct}%, #343a45 100%)` }} />
        <div className="sim-scale num" aria-hidden="true">
          <span>0</span>
          {presets.map((v) => <span key={v}>{v}</span>)}
        </div>
        <div className="sim-chips" role="group" aria-label="Atalhos de quantidade">
          {presets.map((v) => (
            <button key={v} type="button" className={v === alunos ? 'sim-chip on' : 'sim-chip'} aria-pressed={v === alunos} onClick={() => setAlunos(v)}>{v} alunos</button>
          ))}
        </div>
      </div>
      <div className="sim-r" aria-live="polite">
        <p className="eyebrow">Sua mensalidade</p>
        <div className="sim-total num">{brl(total)}<small>/{ciclo}</small></div>
        <div className="sim-rows">
          <div><span>Alunos ativos</span><b className="num">{alunos}</b></div>
          {porAluno
            ? <div><span>Valor por aluno</span><b className="num">{brl(plano.porAluno)}</b></div>
            : <div><span>Custo por aluno</span><b className="num">{alunos ? brl(total / alunos) : '—'}</b></div>}
          <div><span>Custo por dia</span><b className="num">{brl(total / plano.ciclo)}</b></div>
        </div>
        <a className="btn btn-glow" href={`${assinarHref(plano)}&alunos=${alunos}`}>
          Assinar o {nome}
          <Seta />
        </a>
        {free ? <a className="btn btn-line" href={assinarHref(free)}>Começar grátis</a> : null}
        <p className="sim-more">
          {plano.maxAlunos ? `Mais de ${plano.maxAlunos} alunos ou mais de uma unidade?` : 'Mais de uma unidade?'} <a href="#contato">Fale com vendas</a> e montamos o valor com você.
        </p>
      </div>
    </div>
  );
}

export default function Planos() {
  const { planos, free, pago } = usePlanosPrincipais();
  if (!planos.length) return null;

  const lead = [
    free ? `O ${free.nome} coloca sua academia no app sem custo.` : null,
    pago
      ? `O ${nomeCurto(pago)} libera pagamentos, recorrência e financeiro${pago.tipo === 'por_aluno' ? ', e você paga só pelos alunos ativos.' : ` por ${brl(pago.valor)} por ${cicloLabel(pago.ciclo)}.`}`
      : null,
  ].filter(Boolean).join(' ');

  return (
    <section className="sec" id="planos">
      <div className="wrap">
        <div className="sec-head center">
          <p className="eyebrow">Planos</p>
          <h2 className="h2">{free ? 'Comece grátis. Cresça quando quiser.' : 'Um preço, tudo dentro.'}</h2>
          <p className="lead">{lead}</p>
        </div>
        <div className="plans">
          {planos.map((p) => (gratis(p)
            ? <CartaoFree key={p.id} plano={p} />
            : <CartaoPago key={p.id} plano={p} free={free} destaque={p === pago} />))}
        </div>
        {pago ? <Simulador plano={pago} free={free} /> : null}
      </div>
    </section>
  );
}
