export default function Aluno() {
  return (
    <section className="sec" id="aluno">
      <div className="wrap split">
        <div>
          <div className="sec-head">
            <p className="eyebrow">Para o aluno</p>
            <h2 className="h2">A semana inteira no bolso. Sem fila no balcão.</h2>
            <p className="lead">Um app pensado para ser usado com uma mão, entre o vestiário e a aula.</p>
          </div>
          <div className="feat-list">
            <div className="feat">
              <div className="feat-ico">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="18" rx="2" />
                  <path d="M16 2v4M8 2v4M3 10h18" />
                </svg>
              </div>
              <div>
                <h3>Agenda com vagas em tempo real</h3>
                <p>Linha do tempo do dia com o marcador “Agora”, vagas restantes e quem já vai. Check-in direto da aula.</p>
              </div>
            </div>
            <div className="feat">
              <div className="feat-ico">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3" y="3" width="7" height="7" rx="1" />
                  <rect x="14" y="3" width="7" height="7" rx="1" />
                  <rect x="3" y="14" width="7" height="7" rx="1" />
                  <path d="M14 14h3v3M21 14v7h-4" />
                </svg>
              </div>
              <div>
                <h3>Planos e pagamento no app</h3>
                <p>O aluno escolhe ou troca de plano e paga com PIX ou cartão. A conta aparece antes do botão, sem surpresa.</p>
              </div>
            </div>
            <div className="feat">
              <div className="feat-ico">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4z" />
                  <path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3" />
                </svg>
              </div>
              <div>
                <h3>Ranking que dá vontade de voltar</h3>
                <p>Frequência do mês vira posição no ranking. Quem treina mais aparece, e quem está perto quer passar.</p>
              </div>
            </div>
          </div>
        </div>
        <div className="visual warm">
          <div className="ring" style={{ width: "560px", height: "560px" }} />
          <div className="ring" style={{ width: "380px", height: "380px" }} />
          <div className="phone float" role="img" aria-label="App do aluno: agenda de check-in do dia">
            <div className="screen">
              <div className="ah" style={{ paddingBottom: "14px" }}>
                <div className="facet f1" />
                <div className="facet f2" />
                <div className="facet f3" />
                <div className="ah-row">
                  <div>
                    <small>Outubro · semana 42</small>
                    <h6>Check-in</h6>
                  </div>
                </div>
                <div className="days">
                  <div className="day">SEG<b>13</b></div>
                  <div className="day">TER<b>14</b></div>
                  <div className="day">QUA<b>15</b></div>
                  <div className="day">QUI<b>16</b></div>
                  <div className="day sel now">SEX<b>17</b></div>
                  <div className="day">SÁB<b>18</b></div>
                  <div className="day">DOM<b>19</b></div>
                </div>
              </div>
              <div className="pbody" style={{ gap: "10px" }}>
                <div className="seg"><span className="on">Todas</span><span>Disponíveis</span><span>Minhas</span></div>
                <div className="tl">
                  <div className="slot">
                    <div className="t past num">06:00</div>
                    <div className="pcard" style={{ padding: "10px 12px", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                      <span className="pmeta">Natação · 3 aulas</span>
                      <span className="badge" style={{ background: "#fdecec", color: "#b91c1c" }}>Encerrado</span>
                    </div>
                  </div>
                  <div className="now-line">AGORA · 15:12</div>
                  <div className="slot">
                    <div className="t num">16:00</div>
                    <div className="pcard">
                      <div style={{ display: "flex", justifyContent: "space-between" }}>
                        <b style={{ fontSize: "14px" }}>Natação</b>
                        <span className="pmeta">9/15 vagas</span>
                      </div>
                      <div className="pmeta">Tiago Renan · até 15:30</div>
                      <div className="vagas"><i style={{ width: "60%" }}></i></div>
                      <div className="faces">
                        <i style={{ background: "#e09a5e" }} />
                        <i style={{ background: "#8b929e" }} />
                        <i style={{ background: "#1d5fd1" }} />
                        <i style={{ background: "#15703a" }} />
                        <i style={{ background: "#c2410c" }} />
                      </div>
                      <div className="pbtn" style={{ height: "36px", marginTop: "8px" }}>Fazer check-in</div>
                    </div>
                  </div>
                  <div className="slot">
                    <div className="t num">17:00</div>
                    <div className="pcard" style={{ padding: "10px 12px" }}>
                      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                        <b style={{ fontSize: "14px" }}>Funcional</b>
                        <span className="badge" style={{ background: "#e7f6ec", color: "#15703a" }}>
                          <svg className="ic" viewBox="0 0 24 24" style={{ width: "12px", height: "12px" }} aria-hidden="true">
                            <path d="M20 6 9 17l-5-5" />
                          </svg>
                          Check-in feito
                        </span>
                      </div>
                      <div className="pmeta">Carla Mendes · 12/20 vagas</div>
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
          <div className="pix float-2" role="img" aria-label="Pagamento via PIX de R$ 150,00" style={{ position: "absolute", right: "24px", bottom: "40px" }}>
            <div className="pix-top">
              <span className="eyebrow" style={{ fontSize: "11px" }}>Pagamento via PIX</span>
              <span className="badge" style={{ background: "#fff4db", color: "#9a5b00" }}>Vence em 30 min</span>
            </div>
            <div className="pix-amt num">R$ 150,00</div>
            <div className="qr-row">
              <svg viewBox="0 0 21 21" width="96" height="96" aria-hidden="true" style={{ background: "#ffffff", border: "1px solid #e4e6ea", borderRadius: "10px", padding: "6px" }} shapeRendering="crispEdges">
                <path fill="#171a21" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2zM8 0h2v2H8zM11 1h2v3h-2zM8 3h2v2H8zM9 6h3v2H9zM8 9h2v2H8zM12 8h2v3h-2zM0 8h2v2H0zM3 9h3v2H3zM15 8h2v2h-2zM18 9h3v2h-3zM9 12h3v2H9zM13 12h3v2h-3zM17 12h2v3h-2zM8 15h2v3H8zM11 16h3v2h-3zM15 15h2v2h-2zM19 16h2v2h-2zM12 19h2v2h-2zM16 18h2v3h-2zM9 19h2v2H9z" />
              </svg>
              <ol>
                <li>Abra o app do banco</li>
                <li>Escolha pagar com PIX</li>
                <li>Escaneie o código</li>
              </ol>
            </div>
            <div className="pbtn" style={{ marginTop: "14px", height: "44px" }}>Já paguei</div>
          </div>
        </div>
      </div>
    </section>
  );
}
