import { LINKS } from '../config';
import { assinarHref, resumoFree, usePlanosPrincipais } from '../planos';

export default function Hero() {
  const { free, pago } = usePlanosPrincipais();
  const nota = free ? `${resumoFree(free)} · iPhone e Android` : 'Para academias, boxes e escolas de natação · iPhone e Android';

  return (
    <section className="hero" id="topo">
      <div className="wrap">
        <div className="hero-badge"><span>NOVO</span> App do aluno + painel da academia, num só sistema</div>
        <h1 className="h1">Check-in em <em>um toque.</em> Sua academia inteira em uma tela.</h1>
        <p className="lead">O aluno marca presença, paga o plano e acompanha a semana pelo celular. Você vê alunos, turmas e financeiro em tempo real no painel.</p>
        <div className="hero-ctas">
          <a className="btn btn-glow" href={assinarHref(free || pago)}>
            {free ? 'Começar grátis' : 'Assinar agora'}
            <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14M12 5l7 7-7 7" />
            </svg>
          </a>
          <a className="btn btn-line" href={LINKS.demonstracao}>Agendar demonstração</a>
        </div>
        <p className="hero-note">{nota}</p>
      </div>
      <div className="stage">
        <div className="chip chip-a float">
          <div className="chip-ico" style={{ background: "#e7f6ec", color: "#15703a" }}>
            <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M20 6 9 17l-5-5" />
            </svg>
          </div>
          <div><b>Check-in feito</b><small>Natação · 06:00</small></div>
        </div>
        <div className="chip chip-b float-2">
          <div className="chip-ico" style={{ background: "#32bcad", color: "#0b2b28" }}>
            <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3" y="3" width="7" height="7" rx="1" />
              <rect x="14" y="3" width="7" height="7" rx="1" />
              <rect x="3" y="14" width="7" height="7" rx="1" />
              <path d="M14 14h3v3M21 14v7h-4" />
            </svg>
          </div>
          <div><b className="num">PIX recebido · R$ 150,00</b><small>Plano 3x por semana</small></div>
        </div>
        {/* dashboard */}
        <div className="browser" aria-label="Painel AppCheckin: dashboard" role="img">
          <div className="bbar">
            <i />
            <i />
            <i />
            <div className="addr">Painel AppCheckin · Dashboard</div>
          </div>
          <div className="dash">
            <div className="side">
              <div className="side-brand">
                <i>
                  <svg className="ic" viewBox="0 0 24 24" style={{ width: "16px", height: "16px" }} aria-hidden="true">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                  </svg>
                </i>
                Check-in
              </div>
              <div className="side-lbl">Geral</div>
              <div className="side-it on">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3" y="3" width="7" height="9" rx="1" />
                  <rect x="14" y="3" width="7" height="5" rx="1" />
                  <rect x="14" y="12" width="7" height="9" rx="1" />
                  <rect x="3" y="16" width="7" height="5" rx="1" />
                </svg>
                Dashboard
              </div>
              <div className="side-lbl">Gestão</div>
              <div className="side-it">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                </svg>
                Alunos
              </div>
              <div className="side-it">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                  <path d="M14 2v6h6" />
                </svg>
                Matrículas
              </div>
              <div className="side-it">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z" />
                </svg>
                Planos
              </div>
              <div className="side-it">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M3 7a2 2 0 0 1 2-2h14v4" />
                  <path d="M3 7v12a2 2 0 0 0 2 2h16V9H5a2 2 0 0 1-2-2z" />
                </svg>
                Financeiro
              </div>
              <div className="side-it">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="2" y="5" width="20" height="14" rx="2" />
                  <path d="M2 10h20" />
                </svg>
                Pagamentos
              </div>
              <div className="side-lbl">Sistema</div>
              <div className="side-it">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="12" r="3" />
                  <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" />
                </svg>
                Configurações
              </div>
            </div>
            <div className="dmain">
              <div className="dtitle">
                <div>
                  <h4>Bom dia, Andre</h4>
                  <p>Sábado, 17 de outubro. Até agora, 64 check-ins e R$ 1.240,00 recebidos hoje.</p>
                </div>
                <div className="dbtn">Nova matrícula</div>
              </div>
              <div className="kpis">
                <div className="kpi"><small>Alunos ativos</small><b className="num">241</b><span className="up">↑ 12 desde setembro</span></div>
                <div className="kpi"><small>Recebido no mês</small><b className="num">R$ 11.438</b><span className="up">↑ 8% desde setembro</span></div>
                <div className="kpi"><small>Check-ins hoje</small><b className="num">64</b><span className="up">↑ média de 58</span></div>
                <div className="kpi"><small>Em atraso</small><b className="num">R$ 642</b><span className="up">↓ 29% · 7 alunos</span></div>
              </div>
              <div className="panels">
                <div className="panel">
                  <h5>Check-ins nos últimos 30 dias</h5>
                  <p>58 por dia em média. Sábados rendem 40% menos que dias úteis.</p>
                  <div className="cols">
                    <div className="avg" />
                    <i style={{ height: "66%" }} />
                    <i style={{ height: "72%" }} />
                    <i style={{ height: "60%" }} />
                    <i style={{ height: "34%" }} />
                    <i style={{ height: "12%" }} />
                    <i style={{ height: "70%" }} />
                    <i style={{ height: "76%" }} />
                    <i style={{ height: "68%" }} />
                    <i style={{ height: "74%" }} />
                    <i style={{ height: "62%" }} />
                    <i style={{ height: "38%" }} />
                    <i style={{ height: "11%" }} />
                    <i style={{ height: "78%" }} />
                    <i style={{ height: "71%" }} />
                    <i style={{ height: "66%" }} />
                    <i style={{ height: "80%" }} />
                    <i style={{ height: "64%" }} />
                    <i style={{ height: "36%" }} />
                    <i style={{ height: "13%" }} />
                    <i style={{ height: "73%" }} />
                    <i style={{ height: "69%" }} />
                    <i style={{ height: "75%" }} />
                    <i style={{ height: "70%" }} />
                    <i style={{ height: "67%" }} />
                    <i style={{ height: "40%" }} />
                    <i style={{ height: "12%" }} />
                    <i style={{ height: "77%" }} />
                    <i style={{ height: "72%" }} />
                    <i style={{ height: "79%" }} />
                    <i className="hot" style={{ height: "46%" }} />
                  </div>
                </div>
                <div className="panel">
                  <h5>Alunos por plano</h5>
                  <p>19 novos e 7 saídas em outubro.</p>
                  <div className="hbar">
                    <div className="hrow">
                      <span>2x por semana</span>
                      <div className="track"><i style={{ width: "100%" }}></i></div>
                      <span className="num">86</span>
                    </div>
                    <div className="hrow">
                      <span>1x por semana</span>
                      <div className="track"><i style={{ width: "74%" }}></i></div>
                      <span className="num">64</span>
                    </div>
                    <div className="hrow">
                      <span>3x por semana</span>
                      <div className="track"><i style={{ width: "48%" }}></i></div>
                      <span className="num">41</span>
                    </div>
                    <div className="hrow">
                      <span>4x por semana</span>
                      <div className="track"><i style={{ width: "31%" }}></i></div>
                      <span className="num">27</span>
                    </div>
                    <div className="hrow">
                      <span>Musculação</span>
                      <div className="track"><i style={{ width: "22%" }}></i></div>
                      <span className="num">19</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        {/* phone: home */}
        <div className="stage-phone">
          <div className="phone float" role="img" aria-label="App do aluno: tela inicial com a próxima aula e a semana">
            <div className="screen">
              <div className="ah">
                <div className="facet f1" />
                <div className="facet f2" />
                <div className="facet f3" />
                <div className="ah-row">
                  <div>
                    <small>Cia da Natação</small>
                    <h6>Olá, André</h6>
                  </div>
                  <div className="ava">AC</div>
                </div>
              </div>
              <div className="pbody">
                <div>
                  <p className="plbl">Próxima aula</p>
                  <div className="pcard">
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
                      <div>
                        <div className="ptime num">16:00</div>
                        <div className="pmeta">Natação · Tiago Renan</div>
                      </div>
                      <span className="badge" style={{ background: "#e8f0fe", color: "#1d5fd1" }}>Hoje</span>
                    </div>
                    <div className="pmeta" style={{ marginTop: "6px" }}>9/15 vagas · check-in até 15:30</div>
                    <div className="pbtn">
                      <svg className="ic" viewBox="0 0 24 24" style={{ width: "16px", height: "16px" }} aria-hidden="true">
                        <path d="m9 11 3 3L22 4" />
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                      </svg>
                      Fazer check-in
                    </div>
                  </div>
                </div>
                <div>
                  <p className="plbl">Minha semana</p>
                  <div className="week">
                    <div className="wd ok">SEG<b>13</b></div>
                    <div className="wd">TER<b>14</b></div>
                    <div className="wd ok">QUA<b>15</b></div>
                    <div className="wd">QUI<b>16</b></div>
                    <div className="wd today">SEX<b>17</b></div>
                    <div className="wd">SÁB<b>18</b></div>
                    <div className="wd">DOM<b>19</b></div>
                  </div>
                </div>
                <div>
                  <p className="plbl">Ranking de outubro</p>
                  <div className="pcard" style={{ padding: "4px 12px" }}>
                    <div className="rank">
                      <div className="medal" style={{ background: "#f2b705" }}>1</div>
                      <b>WILLEVER SILVA</b>
                      <span className="num">18</span>
                    </div>
                    <div className="rank" style={{ background: "#fff3e6", margin: "0 -12px", padding: "8px 12px", borderRadius: "0 0 14px 14px" }}>
                      <div className="medal" style={{ background: "#171a21", color: "#ffffff" }}>4</div>
                      <b>Você</b>
                      <span className="num" style={{ color: "#c2410c" }}>14</span>
                    </div>
                  </div>
                </div>
              </div>
              <div className="bn" aria-hidden="true">
                <i>
                  <svg className="ic" viewBox="0 0 24 24">
                    <path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                  </svg>
                </i>
                <i>
                  <svg className="ic" viewBox="0 0 24 24">
                    <path d="M6 7v10M18 7v10M3 9v6M21 9v6M6 12h12" />
                  </svg>
                </i>
                <i className="on">
                  <svg className="ic" viewBox="0 0 24 24">
                    <path d="m9 11 3 3L22 4" />
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                  </svg>
                </i>
                <i>
                  <svg className="ic" viewBox="0 0 24 24">
                    <path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z" />
                  </svg>
                </i>
                <i>
                  <svg className="ic" viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 21a8 8 0 0 1 16 0" />
                  </svg>
                </i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
