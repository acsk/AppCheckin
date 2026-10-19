import logo from '../assets/logo.png';
import { LINKS } from '../config';

export default function Footer() {
  return (
    <footer className="foot">
      <div className="wrap foot-in">
        <img className="logo-img" style={{ height: "28px" }} src={logo} alt="AppCheckin" />
        <nav className="foot-links" aria-label="Rodapé">
          <a href="#como-funciona">Como funciona</a>
          <a href="#planos">Planos</a>
          <a href="#perguntas">Perguntas</a>
          <a href={LINKS.termos}>Termos de uso</a>
          <a href={LINKS.privacidade}>Privacidade</a>
        </nav>
        <small>© 2026 AppCheckin</small>
      </div>
    </footer>
  );
}
