import logo from '../assets/logo.png';
import { LINKS } from '../config';

export default function Nav() {
  return (
    <header className="nav">
      <div className="wrap nav-in">
        <a href="#topo" aria-label="AppCheckin, início">
          <img className="logo-img" src={logo} alt="AppCheckin" />
        </a>
        <nav className="nav-links" aria-label="Seções">
          <a href="#como-funciona">Como funciona</a>
          <a href="#aluno">Para o aluno</a>
          <a href="#professor">Para o professor</a>
          <a href="#gestao">Para a gestão</a>
          <a href="#recorrencia">Recorrência</a>
          <a href="#simulador">Simulador</a>
          <a href="#perguntas">Perguntas</a>
        </nav>
        <div className="nav-cta">
          <a className="btn btn-line btn-sm" href={LINKS.entrar}>Entrar</a>
          <a className="btn btn-dark btn-sm" href="#contato">Agendar demonstração</a>
        </div>
      </div>
    </header>
  );
}
