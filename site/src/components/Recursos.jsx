export default function Recursos() {
  return (
    <section className="sec gray" id="recursos">
      <div className="wrap">
        <div className="sec-head center">
          <p className="eyebrow">Tudo incluso</p>
          <h2 className="h2">Os detalhes que fazem o dia andar.</h2>
        </div>
        <div className="grid6">
          <div className="g6">
            <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3" y="3" width="7" height="7" rx="1" />
              <rect x="14" y="3" width="7" height="7" rx="1" />
              <rect x="3" y="14" width="7" height="7" rx="1" />
              <path d="M14 14h3v3M21 14v7h-4" />
            </svg>
            <h3>PIX com QR e copia e cola</h3>
            <p>Valor, prazo e passos claros. Confirmado o pagamento, o plano ativa sozinho.</p>
          </div>
          <div className="g6">
            <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M17 1l4 4-4 4" />
              <path d="M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4" />
              <path d="M21 13v2a4 4 0 0 1-4 4H3" />
            </svg>
            <h3>Troca de plano com crédito</h3>
            <p>Ao migrar, o app desconta os dias não usados e mostra o valor exato antes de pagar.</p>
          </div>
          <div className="g6">
            <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
              <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
            </svg>
            <h3>Vencimentos e acessos no radar</h3>
            <p>Quem vence nos próximos 7 dias e quem está com o acesso terminando, com “Lembrar” ao lado.</p>
          </div>
          <div className="g6">
            <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M3 21h18M5 21V7l7-4 7 4v14" />
              <path d="M9 21v-6h6v6" />
            </svg>
            <h3>Mais de uma unidade</h3>
            <p>O aluno escolhe a academia ao entrar e o app lembra da última. O dono troca de unidade pelo menu.</p>
          </div>
          <div className="g6">
            <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" />
            </svg>
            <h3>Exporte para a contabilidade</h3>
            <p>Lançamentos do mês com bruto, desconto, taxa e líquido, em planilha ou PDF.</p>
          </div>
          <div className="g6">
            <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z" />
            </svg>
            <h3>Tema claro e escuro</h3>
            <p>App e painel acompanham o aparelho, com contraste revisado nos dois temas.</p>
          </div>
        </div>
      </div>
    </section>
  );
}
