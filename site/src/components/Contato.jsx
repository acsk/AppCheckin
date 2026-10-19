import logoBranco from '../assets/logo-branco.png';
import { LINKS } from '../config';
import { assinarHref, usePlanosPrincipais } from '../planos';

export default function Contato() {
  const { free, pago } = usePlanosPrincipais();

  return (
    <section style={{ padding: "128px 0 0" }} id="contato">
      <div className="wrap">
        <div className="cta">
          <div className="cfac cf1" />
          <div className="cfac cf2" />
          <div className="cfac cf3" />
          <div className="cfac cf4" />
          <img className="cta-logo" src={logoBranco} alt="AppCheckin" />
          <h2 className="h2">Sua academia no ritmo do check-in.</h2>
          <p className="lead">Mostramos o app e o painel funcionando com os planos e as turmas da sua academia.</p>
          <div className="hero-ctas" style={{ marginTop: "8px", animation: "none" }}>
            <a className="btn btn-glow" href={assinarHref(free || pago)}>
              {free ? 'Começar grátis' : 'Assinar agora'}
              <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 12h14M12 5l7 7-7 7" />
              </svg>
            </a>
            <a className="btn" href={LINKS.whatsapp} style={{ background: "rgba(255,255,255,.08)", color: "#ffffff", boxShadow: "inset 0 0 0 1px rgba(255,255,255,.18)" }}>
              Falar no WhatsApp
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}
