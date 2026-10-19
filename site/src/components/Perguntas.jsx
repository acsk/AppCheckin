import { useState } from 'react';
import { nomeCurto, usePlanosPrincipais } from '../planos';

const FAQS = [
  { q: 'Funciona para natação e para musculação?', a: 'Sim. Cada aula tem modalidade, professor, horário, vagas e prazo de check-in. Natação, musculação, funcional e WOD convivem na mesma agenda.' },
  { q: 'Como o aluno paga o plano?', a: 'Pelo próprio app, com PIX (QR Code ou copia e cola) ou cartão. O cartão é processado pelo provedor que a academia conectar, Mercado Pago ou Asaas, e os dados do cartão ficam com ele.' },
  { q: 'O professor precisa de outro aplicativo?', a: 'Não. O professor entra no mesmo app e vê as turmas dele, com a lista de quem agendou, presença e falta, busca de aluno e o botão para bloquear a turma.' },
  { q: 'Dá para trocar de plano no meio do ciclo?', a: 'Dá. O app calcula o crédito dos dias não usados, mostra a conta completa e o valor exato no botão antes de o aluno pagar.' },
  { q: 'Consigo ver quem está devendo?', a: 'O painel mostra o valor em atraso no topo, a lista de quem vence nos próximos 7 dias e, no relatório, a inadimplência por faixa de atraso e os maiores devedores, com “Cobrar” ao lado.' },
  { q: 'Funciona no iPhone e no Android?', a: 'Sim. O app do aluno e do professor roda nos dois sistemas, e o painel de gestão abre em qualquer navegador.' },
];

const PM_ABERTO = { transform: 'rotate(45deg)', background: '#171a21', color: '#ffffff' };
const PM_FECHADO = { transform: 'rotate(0deg)' };

function perguntaFree(free, pago) {
  const alunos = free.maxAlunos ? `Até ${free.maxAlunos} alunos ativos` : 'Sem limite de alunos';
  const prazo = free.prazo ? `por ${free.prazo} dias` : 'sem prazo para acabar';
  const depois = pago ? ` Quando quiser receber pagamentos pelo app e ter o financeiro, muda para o ${nomeCurto(pago)}.` : '';
  return {
    q: `O plano ${free.nome} é grátis mesmo?`,
    a: `É. ${alunos}, sem cartão e ${prazo}. Você usa app do aluno, check-in, modo professor e ranking.${depois}`,
  };
}

export default function Perguntas() {
  const [aberta, setAberta] = useState(0);
  const { free, pago } = usePlanosPrincipais();
  const faqs = free ? [perguntaFree(free, pago), ...FAQS] : FAQS;

  return (
    <section className="sec gray" id="perguntas">
      <div className="wrap">
        <div className="sec-head center">
          <p className="eyebrow">Perguntas</p>
          <h2 className="h2">Antes de você perguntar.</h2>
        </div>
        <div className="faq">
          {faqs.map((f, i) => {
            const isOpen = aberta === i;
            return (
              <div key={f.q}>
                <button type="button" className="faq-q" aria-expanded={isOpen} onClick={() => setAberta(isOpen ? -1 : i)}>
                  <span>{f.q}</span>
                  <span className="pm" style={isOpen ? PM_ABERTO : PM_FECHADO}>
                    <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M12 5v14M5 12h14" />
                    </svg>
                  </span>
                </button>
                {isOpen && <p className="faq-a">{f.a}</p>}
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
