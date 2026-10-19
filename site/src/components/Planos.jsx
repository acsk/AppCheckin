import { useState } from 'react';
import { LIMITE_ALUNOS, LINKS, PRECO_POR_ALUNO, brl } from '../config';

const PRESETS = [25, 50, 75, 100];

export default function Planos() {
  const [alunos, setAlunos] = useState(60);
  const palavra = alunos === 1 ? 'aluno' : 'alunos';
  const total = alunos * PRECO_POR_ALUNO;
  const pct = (alunos / LIMITE_ALUNOS) * 100;

  return (
    <section className="sec" id="planos">
      <div className="wrap">
        <div className="sec-head center">
          <p className="eyebrow">Planos</p>
          <h2 className="h2">Um preço, tudo dentro.</h2>
          <p className="lead">App do aluno, modo professor e painel de gestão no mesmo plano. Você paga só pelos alunos ativos.</p>
        </div>
        <div className="sim" id="simulador">
          <div className="cfac cf1" style={{ opacity: ".5", width: "260px", height: "180px" }} />
          <div className="cfac cf3" style={{ opacity: ".35", width: "300px", height: "200px" }} />
          <div className="sim-l">
            <span className="sim-tag">
              <svg className="ic" viewBox="0 0 24 24" style={{ width: "14px", height: "14px" }} aria-hidden="true">
                <rect x="4" y="2" width="16" height="20" rx="2" />
                <path d="M8 6h8M8 10h2M14 10h2M8 14h2M14 14h2M8 18h2M14 18h2" />
              </svg>
              Simulador de custo
            </span>
            <h3>Quanto o AppCheckin custa para a sua academia?</h3>
            <p>Arraste até o número de alunos ativos. Na faixa de 0 a 100 alunos, você paga R$ 3,50 por aluno, por mês.</p>
            <div className="sim-count">
              <label htmlFor="alunos">Alunos ativos</label>
              <output htmlFor="alunos" className="num">{alunos}<small>{palavra}</small></output>
            </div>
            <input id="alunos" type="range" min="0" max={LIMITE_ALUNOS} step="1" value={alunos} onChange={(e) => setAlunos(Number(e.target.value))} aria-valuetext={`${alunos} ${palavra}`} style={{ background: `linear-gradient(90deg, #fdb400 0%, #ff7a00 ${pct * 0.6}%, #f2400a ${pct}%, #343a45 ${pct}%, #343a45 100%)` }} />
            <div className="sim-scale num" aria-hidden="true"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>
            <div className="sim-chips" role="group" aria-label="Atalhos de quantidade">
              {PRESETS.map((v) => (
                <button key={v} type="button" className={v === alunos ? 'sim-chip on' : 'sim-chip'} aria-pressed={v === alunos} onClick={() => setAlunos(v)}>{v} alunos</button>
              ))}
            </div>
          </div>
          <div className="sim-r" aria-live="polite">
            <p className="eyebrow">Sua mensalidade</p>
            <div className="sim-total num">{brl(total)}<small>/mês</small></div>
            <div className="sim-rows">
              <div><span>Alunos ativos</span><b className="num">{alunos}</b></div>
              <div><span>Valor por aluno</span><b className="num">{brl(PRECO_POR_ALUNO)}</b></div>
              <div><span>Custo por dia</span><b className="num">{brl(total / 30)}</b></div>
            </div>
            <a className="btn btn-glow" href={LINKS.demonstracao}>
              Agendar demonstração
              <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 12h14M12 5l7 7-7 7" />
              </svg>
            </a>
            <p className="sim-more">Mais de 100 alunos ou mais de uma unidade? <a href="#contato">Fale com vendas</a> e montamos o valor com você.</p>
          </div>
        </div>
        <div className="price">
          <div className="price-l">
            <div className="cfac cf3" style={{ opacity: ".35", width: "220px", height: "160px" }} />
            <p className="eyebrow">AppCheckin completo</p>
            <div className="price-v num">R$ 3,50 <small>por aluno ativo/mês</small></div>
            <p style={{ margin: "0", fontSize: "16px", lineHeight: "24px", color: "#c9ccd3", fontWeight: "500" }}>
              Para academias com até 100 alunos ativos. Você paga só por quem está treinando.
            </p>
            <div style={{ display: "flex", flexDirection: "column", gap: "10px", marginTop: "8px" }}>
              <a className="btn btn-glow" href={LINKS.demonstracao}>Agendar demonstração</a>
              <a className="btn" href="#contato" style={{ background: "rgba(255,255,255,.08)", color: "#ffffff" }}>Falar com vendas</a>
            </div>
          </div>
          <div className="price-r">
            <p className="eyebrow">Incluso</p>
            <div className="inc">
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                App do aluno para iPhone e Android
              </div>
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Check-in com agenda e vagas
              </div>
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Modo professor com chamada
              </div>
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Planos, ciclos e pacotes
              </div>
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                PIX e cartão no app
              </div>
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Mercado Pago e Asaas
              </div>
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Dashboard e relatório financeiro
              </div>
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Ranking mensal de frequência
              </div>
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Exportação em CSV e PDF
              </div>
              <div>
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Suporte e implantação
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
