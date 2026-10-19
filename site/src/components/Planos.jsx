import { useState } from 'react';
import { LINKS, brl } from '../config';
import { cicloLabel, limitesTexto, precoTexto, usePlanos } from '../planos';

const INCLUSO = [
  'App do aluno para iPhone e Android',
  'Check-in com agenda e vagas',
  'Modo professor com chamada',
  'Planos, ciclos e pacotes',
  'PIX e cartão no app',
  'Mercado Pago e Asaas',
  'Dashboard e relatório financeiro',
  'Ranking mensal de frequência',
  'Exportação em CSV e PDF',
  'Suporte e implantação',
];
const LIMITE_SEM_TETO = 200;

function Check() {
  return (
    <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M20 6 9 17l-5-5" />
    </svg>
  );
}

function presetsAte(max) {
  const passo = max / 4;
  return [1, 2, 3, 4].map((i) => Math.round(passo * i));
}

function Simulador({ plano }) {
  const max = plano.maxAlunos || LIMITE_SEM_TETO;
  const [escolhidos, setAlunos] = useState(() => Math.round(max * 0.6));
  const alunos = Math.min(escolhidos, max);

  const porAluno = plano.tipo === 'por_aluno';
  const ciclo = cicloLabel(plano.ciclo);
  const palavra = alunos === 1 ? 'aluno' : 'alunos';
  const total = porAluno ? alunos * plano.porAluno : plano.valor;
  const pct = (alunos / max) * 100;
  const presets = presetsAte(max);
  const faixa = plano.maxAlunos ? `de 0 a ${plano.maxAlunos} alunos` : 'qualquer número de alunos';

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
          Simulador de custo
        </span>
        <h3>Quanto o AppCheckin custa para a sua academia?</h3>
        <p>
          {porAluno
            ? `Arraste até o número de alunos ativos. Na faixa ${faixa}, você paga ${brl(plano.porAluno)} por aluno, por ${ciclo}.`
            : `Arraste até o número de alunos ativos. Na faixa ${faixa}, o valor é fixo: ${brl(plano.valor)} por ${ciclo}. Quanto mais alunos, menor o custo de cada um.`}
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
        <a className="btn btn-glow" href={LINKS.demonstracao}>
          Agendar demonstração
          <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M5 12h14M12 5l7 7-7 7" />
          </svg>
        </a>
        <p className="sim-more">
          {plano.maxAlunos ? `Mais de ${plano.maxAlunos} alunos ou mais de uma unidade?` : 'Mais de uma unidade?'} <a href="#contato">Fale com vendas</a> e montamos o valor com você.
        </p>
      </div>
    </div>
  );
}

function OutroPlano({ plano }) {
  const preco = precoTexto(plano);
  const gratis = plano.tipo === 'gratuito';
  return (
    <article className="plan-alt">
      <p className="eyebrow">{plano.nome}</p>
      <div className="plan-alt-v num">{preco.valor} <small>{preco.sufixo}</small></div>
      {plano.descricao ? <p className="plan-alt-d">{plano.descricao}</p> : null}
      <ul>
        {limitesTexto(plano).map((t) => <li key={t}><Check />{t}</li>)}
      </ul>
      <a className="btn" href={LINKS.demonstracao}>{gratis ? 'Começar grátis' : 'Quero este plano'}</a>
    </article>
  );
}

export default function Planos() {
  const planos = usePlanos();
  const principal = planos.find((p) => p.tipo !== 'gratuito') || planos[0];
  const outros = planos.filter((p) => p !== principal);
  if (!principal) return null;

  const preco = precoTexto(principal);
  const paga = principal.tipo !== 'gratuito';
  const limites = limitesTexto(principal);
  const gratis = outros.find((p) => p.tipo === 'gratuito');
  const lead = principal.tipo === 'por_aluno'
    ? 'App do aluno, modo professor e painel de gestão no mesmo plano. Você paga só pelos alunos ativos.'
    : 'App do aluno, modo professor e painel de gestão no mesmo plano, por um valor que cabe na sua academia.';

  return (
    <section className="sec" id="planos">
      <div className="wrap">
        <div className="sec-head center">
          <p className="eyebrow">Planos</p>
          <h2 className="h2">Um preço, tudo dentro.</h2>
          <p className="lead">
            {lead}
            {gratis ? ` Teste grátis${gratis.prazo ? ` por ${gratis.prazo} dias` : ''} antes de decidir.` : ''}
          </p>
        </div>
        {paga ? <Simulador plano={principal} /> : null}
        <div className="price">
          <div className="price-l">
            <div className="cfac cf3" style={{ opacity: '.35', width: '220px', height: '160px' }} />
            <p className="eyebrow">{principal.nome}</p>
            <div className="price-v num">{preco.valor} <small>{preco.sufixo}</small></div>
            <p style={{ margin: '0', fontSize: '16px', lineHeight: '24px', color: '#c9ccd3', fontWeight: '500' }}>
              {principal.descricao || `${limites.join(', ')}.`}
            </p>
            {principal.descricao ? <p className="price-lim">{limites.join(' · ')}</p> : null}
            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '8px' }}>
              <a className="btn btn-glow" href={LINKS.demonstracao}>Agendar demonstração</a>
              <a className="btn" href="#contato" style={{ background: 'rgba(255,255,255,.08)', color: '#ffffff' }}>Falar com vendas</a>
            </div>
          </div>
          <div className="price-r">
            <p className="eyebrow">Incluso</p>
            <div className="inc">
              {INCLUSO.map((t) => <div key={t}><Check />{t}</div>)}
            </div>
          </div>
        </div>
        {outros.length ? (
          <div className="plans-alt">
            {outros.map((p) => <OutroPlano key={p.id} plano={p} />)}
          </div>
        ) : null}
      </div>
    </section>
  );
}
