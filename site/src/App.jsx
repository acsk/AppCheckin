import Nav from './components/Nav';
import Hero from './components/Hero';
import ComoFunciona from './components/ComoFunciona';
import Aluno from './components/Aluno';
import Professor from './components/Professor';
import Gestao from './components/Gestao';
import Recorrencia from './components/Recorrencia';
import Recursos from './components/Recursos';
import Planos from './components/Planos';
import Perguntas from './components/Perguntas';
import Contato from './components/Contato';
import Footer from './components/Footer';

export default function App() {
  return (
    <div className="lp">
      <Nav />
      <main>
        <Hero />
        <ComoFunciona />
        <Aluno />
        <Professor />
        <Gestao />
        <Recorrencia />
        <Recursos />
        <Planos />
        <Perguntas />
        <Contato />
      </main>
      <Footer />
    </div>
  );
}
