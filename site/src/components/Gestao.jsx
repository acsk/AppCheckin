export default function Gestao() {
  return (
    <section className="sec" id="gestao">
      <div className="wrap">
        <div className="sec-head center">
          <p className="eyebrow">Para a gestão</p>
          <h2 className="h2">Do bruto ao líquido, sem abrir planilha.</h2>
          <p className="lead">O painel mostra o que está acontecendo hoje, como está o mês e o que precisa de ação agora.</p>
        </div>
        <div className="browser full" style={{ marginTop: "56px" }} role="img" aria-label="Painel AppCheckin: relatório financeiro de outubro">
          <div className="bbar">
            <i />
            <i />
            <i />
            <div className="addr">Painel AppCheckin · Relatório financeiro</div>
          </div>
          <div className="dash" style={{ minHeight: "0" }}>
            <div className="side">
              <div className="side-brand">
                <i>
                  <svg className="ic" viewBox="0 0 24 24" style={{ width: "16px", height: "16px" }} aria-hidden="true">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                  </svg>
                </i>
                Check-in
              </div>
              <div className="side-it">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3" y="3" width="7" height="9" rx="1" />
                  <rect x="14" y="3" width="7" height="5" rx="1" />
                  <rect x="14" y="12" width="7" height="9" rx="1" />
                  <rect x="3" y="16" width="7" height="5" rx="1" />
                </svg>
                Dashboard
              </div>
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
              <div className="side-it on">
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
            </div>
            <div className="dmain">
              <div className="dtitle">
                <div>
                  <h4>Relatório financeiro</h4>
                  <p>Outubro de 2026 · por data de pagamento</p>
                </div>
                <div className="dbtn" style={{ background: "#ffffff", color: "#15181d", boxShadow: "inset 0 0 0 1px #cfd4da" }}>Exportar CSV ou PDF</div>
              </div>
              <div className="kpis">
                <div className="kpi"><small>Receita bruta</small><b className="num">R$ 12.480</b><span className="up">↑ 9% desde setembro</span></div>
                <div className="kpi dark">
                  <small>Receita líquida</small>
                  <b className="num">R$ 11.438</b>
                  <span style={{ fontSize: "12px", fontWeight: "700", color: "#a3abb7" }}>91,7% do bruto</span>
                </div>
                <div className="kpi"><small>Ticket médio</small><b className="num">R$ 47,46</b><span className="up">↑ R$ 1,20</span></div>
                <div className="kpi"><small>Inadimplência</small><b className="num">5,1%</b><span className="up">↓ 2,3 p.p.</span></div>
              </div>
              <div className="fin">
                <div className="panel">
                  <h5>Do bruto ao líquido</h5>
                  <p>Descontos, taxas de pagamento e estornos, cada um com o peso no mês.</p>
                  <div className="wf">
                    <div className="wf-row">
                      <span>Receita bruta</span>
                      <div className="wf-bar"><i style={{ left: "0", width: "100%", background: "#8a93a0" }}></i></div>
                      <b className="num">R$ 12.480</b>
                    </div>
                    <div className="wf-row">
                      <span>Descontos</span>
                      <div className="wf-bar"><i style={{ left: "95%", width: "5%", background: "#c3c8d0" }}></i></div>
                      <b className="num">− R$ 620</b>
                    </div>
                    <div className="wf-row">
                      <span>Taxas</span>
                      <div className="wf-bar"><i style={{ left: "92.5%", width: "2.5%", background: "#c3c8d0" }}></i></div>
                      <b className="num">− R$ 312</b>
                    </div>
                    <div className="wf-row">
                      <span>Estornos</span>
                      <div className="wf-bar"><i style={{ left: "91.6%", width: "0.9%", background: "#c3c8d0" }}></i></div>
                      <b className="num">− R$ 110</b>
                    </div>
                    <div className="wf-row">
                      <span style={{ color: "#15181d", fontWeight: "800" }}>Líquido</span>
                      <div className="wf-bar"><i style={{ left: "0", width: "91.6%", background: "#c9430c" }}></i></div>
                      <b className="num">R$ 11.438</b>
                    </div>
                  </div>
                </div>
                <div className="panel">
                  <h5>Previsto x recebido</h5>
                  <p>R$ 13.200 previstos para outubro.</p>
                  <div style={{ display: "flex", height: "22px", borderRadius: "6px", overflow: "hidden", gap: "2px" }}>
                    <i style={{ display: "block", width: "86.6%", background: "#127a3a" }} />
                    <i style={{ display: "block", width: "8.5%", background: "#c3c8d0" }} />
                    <i style={{ display: "block", width: "4.9%", background: "#c4291c" }} />
                  </div>
                  <div className="hbar" style={{ marginTop: "16px" }}>
                    <div className="hrow" style={{ gridTemplateColumns: "12px minmax(0,1fr) 88px" }}>
                      <i style={{ width: "10px", height: "10px", borderRadius: "3px", background: "#127a3a", display: "block" }} />
                      <span>Recebido</span>
                      <span className="num">R$ 11.438</span>
                    </div>
                    <div className="hrow" style={{ gridTemplateColumns: "12px minmax(0,1fr) 88px" }}>
                      <i style={{ width: "10px", height: "10px", borderRadius: "3px", background: "#c3c8d0", display: "block" }} />
                      <span>A vencer</span>
                      <span className="num">R$ 1.120</span>
                    </div>
                    <div className="hrow" style={{ gridTemplateColumns: "12px minmax(0,1fr) 88px" }}>
                      <i style={{ width: "10px", height: "10px", borderRadius: "3px", background: "#c4291c", display: "block" }} />
                      <span>Em atraso</span>
                      <span className="num">R$ 642</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="gest-grid">
          <div className="gcard">
            <div className="feat-ico">
              <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="3" width="7" height="9" rx="1" />
                <rect x="14" y="3" width="7" height="5" rx="1" />
                <rect x="14" y="12" width="7" height="9" rx="1" />
                <rect x="3" y="16" width="7" height="5" rx="1" />
              </svg>
            </div>
            <h3>Dashboard do dia</h3>
            <p>Alunos ativos, recebido, check-ins de hoje e atrasos, comparados com o mês anterior. Mapa de calor dos horários de pico.</p>
          </div>
          <div className="gcard">
            <div className="feat-ico">
              <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <path d="M14 2v6h6" />
              </svg>
            </div>
            <h3>Matrículas e pacotes</h3>
            <p>Plano, ciclo, faturas, descontos e pacotes familiares numa tela só. “Dar baixa” sempre à vista quando há cobrança.</p>
          </div>
          <div className="gcard">
            <div className="feat-ico">
              <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 3v18h18" />
                <path d="M7 15v2M12 10v7M17 6v11" />
              </svg>
            </div>
            <h3>Relatório financeiro</h3>
            <p>Por pagamento ou por vencimento, com inadimplência por faixa de atraso, maiores devedores e lançamentos para exportar.</p>
          </div>
          <div className="gcard">
            <div className="feat-ico">
              <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="2" y="5" width="20" height="14" rx="2" />
                <path d="M2 10h20" />
              </svg>
            </div>
            <h3>Pagamentos integrados</h3>
            <p>Conecte Mercado Pago ou Asaas, teste em Sandbox e ative em Produção. Chaves salvas nunca voltam para a tela.</p>
          </div>
        </div>
      </div>
    </section>
  );
}
