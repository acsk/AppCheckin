export default function Recorrencia() {
  return (
    <section className="sec warm" id="recorrencia" style={{ background: "#fff8f0" }}>
      <div className="wrap split">
        <div>
          <div className="sec-head">
            <p className="eyebrow">Planos recorrentes com Asaas</p>
            <h2 className="h2">A mensalidade se cobra sozinha.</h2>
            <p className="lead">
              Conecte sua conta Asaas ao AppCheckin e transforme cada plano em assinatura. O aluno autoriza uma vez e a cobrança acontece a cada ciclo, sem você correr atrás.
            </p>
          </div>
          <div className="feat-list">
            <div className="feat">
              <div className="feat-ico">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M21 12a9 9 0 1 1-3-6.7L21 8" />
                  <path d="M21 3v5h-5" />
                </svg>
              </div>
              <div>
                <h3>Cobrança automática por ciclo</h3>
                <p>Mensal, trimestral, semestral ou anual, como o plano estiver configurado. No vencimento, o Asaas cobra e o AppCheckin registra.</p>
              </div>
            </div>
            <div className="feat">
              <div className="feat-ico">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="2" y="5" width="20" height="14" rx="2" />
                  <path d="M2 10h20" />
                </svg>
              </div>
              <div>
                <h3>Cartão, PIX ou boleto</h3>
                <p>Cartão de crédito cobrado direto na data. Para PIX e boleto, a cobrança é gerada antes do vencimento e chega ao aluno.</p>
              </div>
            </div>
            <div className="feat">
              <div className="feat-ico">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
              </div>
              <div>
                <h3>Baixa e acesso sem trabalho manual</h3>
                <p>Pagamento confirmado vira fatura paga no painel e mantém o acesso do aluno ativo. Atrasos aparecem no dashboard com “Cobrar” ao lado.</p>
              </div>
            </div>
            <div className="feat">
              <div className="feat-ico">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3" y="11" width="18" height="11" rx="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
              </div>
              <div>
                <h3>Teste em Sandbox, ative em Produção</h3>
                <p>Valide a integração sem cobrar ninguém. As chaves ficam mascaradas e nunca voltam para a tela.</p>
              </div>
            </div>
          </div>
        </div>
        <div className="visual" style={{ background: "#ffffff", border: "1px solid #f1e2d2" }}>
          <div className="ring" style={{ width: "560px", height: "560px" }} />
          <div className="ring" style={{ width: "400px", height: "400px" }} />
          <div className="rec-asaas float-2"><i></i>Asaas conectado · Produção</div>
          <div className="rec-card float" role="img" aria-label="Assinatura recorrente do plano 3x por semana, cobrada todo dia 14 no cartão">
            <div className="rec-top">
              <div><b>Plano 3x por semana</b><small>Natação · assinatura mensal</small></div>
              <span className="badge" style={{ background: "#e7f6ec", color: "#15703a" }}>
                <svg className="ic" viewBox="0 0 24 24" style={{ width: "12px", height: "12px" }} aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Ativa
              </span>
            </div>
            <div className="rec-amt num">R$ 150,00 <small>/mês</small></div>
            <div className="rec-meta">
              <div>Forma<b>Cartão final 4242</b></div>
              <div>Próxima cobrança<b className="num">14/11/2026</b></div>
            </div>
            <div className="rec-tl">
              <div className="rec-it">
                <div className="rec-dot" style={{ background: "#e7f6ec", color: "#15703a" }}>
                  <svg className="ic" viewBox="0 0 24 24" style={{ width: "14px", height: "14px" }} aria-hidden="true">
                    <path d="M20 6 9 17l-5-5" />
                  </svg>
                </div>
                <div><b className="num">14/08/2026</b><small>Cobrado no cartão</small></div>
                <span className="badge" style={{ background: "#e7f6ec", color: "#15703a" }}>Pago</span>
              </div>
              <div className="rec-it">
                <div className="rec-dot" style={{ background: "#e7f6ec", color: "#15703a" }}>
                  <svg className="ic" viewBox="0 0 24 24" style={{ width: "14px", height: "14px" }} aria-hidden="true">
                    <path d="M20 6 9 17l-5-5" />
                  </svg>
                </div>
                <div><b className="num">14/09/2026</b><small>Cobrado no cartão</small></div>
                <span className="badge" style={{ background: "#e7f6ec", color: "#15703a" }}>Pago</span>
              </div>
              <div className="rec-it">
                <div className="rec-dot" style={{ background: "#e7f6ec", color: "#15703a" }}>
                  <svg className="ic" viewBox="0 0 24 24" style={{ width: "14px", height: "14px" }} aria-hidden="true">
                    <path d="M20 6 9 17l-5-5" />
                  </svg>
                </div>
                <div><b className="num">14/10/2026</b><small>Cobrado no cartão</small></div>
                <span className="badge" style={{ background: "#e7f6ec", color: "#15703a" }}>Pago</span>
              </div>
              <div className="rec-it">
                <div className="rec-dot" style={{ background: "#fff3e6", color: "#c2410c" }}>
                  <svg className="ic" viewBox="0 0 24 24" style={{ width: "14px", height: "14px" }} aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                  </svg>
                </div>
                <div><b className="num">14/11/2026</b><small>Cobrança automática</small></div>
                <span className="badge" style={{ background: "#fff3e6", color: "#c2410c" }}>Agendada</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
