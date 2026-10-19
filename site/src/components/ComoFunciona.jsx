export default function ComoFunciona() {
  return (
    <section className="sec gray" id="como-funciona">
      <div className="wrap">
        <div className="sec-head center">
          <p className="eyebrow">Como funciona</p>
          <h2 className="h2">Do toque no celular ao relatório do mês.</h2>
          <p className="lead">Cada presença vira dado. Cada dado chega a quem precisa dele, sem planilha no meio.</p>
        </div>
        <div className="steps">
          <div className="step">
            <span className="step-n">01</span>
            <h3>O aluno faz check-in</h3>
            <p>Escolhe a aula na agenda do dia, vê as vagas e confirma em um toque. O plano e os pagamentos ficam no mesmo app.</p>
            <span className="step-who">
              <svg className="ic" viewBox="0 0 24 24" style={{ width: "16px", height: "16px" }} aria-hidden="true">
                <rect x="5" y="2" width="14" height="20" rx="3" />
                <path d="M11 18h2" />
              </svg>
              App do aluno
            </span>
          </div>
          <div className="step">
            <span className="step-n">02</span>
            <h3>O professor confirma</h3>
            <p>Abre a turma, marca presença ou falta, adiciona quem chegou sem agendar e bloqueia a aula quando enche.</p>
            <span className="step-who">
              <svg className="ic" viewBox="0 0 24 24" style={{ width: "16px", height: "16px" }} aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
              </svg>
              Modo professor
            </span>
          </div>
          <div className="step">
            <span className="step-n">03</span>
            <h3>A gestão acompanha</h3>
            <p>Check-ins, receita, inadimplência e horários de pico aparecem no painel, com a próxima ação já sugerida.</p>
            <span className="step-who">
              <svg className="ic" viewBox="0 0 24 24" style={{ width: "16px", height: "16px" }} aria-hidden="true">
                <rect x="2" y="4" width="20" height="14" rx="2" />
                <path d="M8 21h8M12 18v3" />
              </svg>
              Painel web
            </span>
          </div>
        </div>
      </div>
    </section>
  );
}
