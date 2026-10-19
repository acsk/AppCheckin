export default function Professor() {
  return (
    <section className="sec gray" id="professor">
      <div className="wrap split">
        <div className="visual" style={{ background: "#ffffff", border: "1px solid #e4e6ea" }}>
          <div className="ring" style={{ width: "520px", height: "520px" }} />
          <div className="phone float" role="img" aria-label="Modo professor: presença da turma das 16:00">
            <div className="screen">
              <div className="ah" style={{ paddingBottom: "14px" }}>
                <div className="facet f1" />
                <div className="facet f2" />
                <div className="facet f3" />
                <div className="ah-row">
                  <div>
                    <small>Natação · Tiago Renan</small>
                    <h6>Turma das 16:00</h6>
                  </div>
                </div>
                <div className="infochips">
                  <span>
                    <svg className="ic" viewBox="0 0 24 24" style={{ width: "12px", height: "12px" }} aria-hidden="true">
                      <circle cx="12" cy="12" r="9" />
                      <path d="M12 7v5l3 2" />
                    </svg>
                    16:00 – 17:00
                  </span>
                  <span className="num">7/15</span>
                  <span className="lock">
                    <svg className="ic" viewBox="0 0 24 24" style={{ width: "12px", height: "12px" }} aria-hidden="true">
                      <rect x="3" y="11" width="18" height="11" rx="2" />
                      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    Bloquear
                  </span>
                </div>
              </div>
              <div className="pbody" style={{ gap: "10px" }}>
                <div className="roster">
                  <div style={{ background: "#e7f6ec", color: "#15703a" }}><b className="num">4</b>Presentes</div>
                  <div style={{ background: "#fdecec", color: "#b91c1c" }}><b className="num">1</b>Faltas</div>
                  <div style={{ background: "#fff4db", color: "#9a5b00" }}><b className="num">2</b>Pendentes</div>
                </div>
                <div>
                  <p className="plbl">Alunos na turma</p>
                  <div className="pcard" style={{ padding: "2px 12px" }}>
                    <div className="att">
                      <div className="ava" style={{ width: "26px", height: "26px", fontSize: "10px", boxShadow: "none", background: "#e09a5e", color: "#171a21" }}>HV</div>
                      <span className="nm">HELLOÁ VICTÓRIA</span>
                      <div className="tog"><span className="p">Presente</span><span>Falta</span></div>
                    </div>
                    <div className="att">
                      <div className="ava" style={{ width: "26px", height: "26px", fontSize: "10px", boxShadow: "none", background: "#1d5fd1" }}>DF</div>
                      <span className="nm">DALVAN FERREIRA</span>
                      <div className="tog"><span className="p">Presente</span><span>Falta</span></div>
                    </div>
                    <div className="att">
                      <div className="ava" style={{ width: "26px", height: "26px", fontSize: "10px", boxShadow: "none", background: "#8b929e" }}>EM</div>
                      <span className="nm">ELIANE MIDIAN</span>
                      <div className="tog"><span>Presente</span><span className="f">Falta</span></div>
                    </div>
                    <div className="att">
                      <div className="ava" style={{ width: "26px", height: "26px", fontSize: "10px", boxShadow: "none", background: "#15703a" }}>JV</div>
                      <span className="nm">JEAN VITOR</span>
                      <div className="tog"><span>Presente</span><span>Falta</span></div>
                    </div>
                    <div className="att">
                      <div className="ava" style={{ width: "26px", height: "26px", fontSize: "10px", boxShadow: "none", background: "#c2410c" }}>MN</div>
                      <span className="nm">MANUELLY NUNES</span>
                      <div className="tog"><span>Presente</span><span>Falta</span></div>
                    </div>
                  </div>
                </div>
                <div className="pbtn dark" style={{ marginTop: "0" }}>
                  <svg className="ic" viewBox="0 0 24 24" style={{ width: "16px", height: "16px" }} aria-hidden="true">
                    <path d="M20 6 9 17l-5-5" />
                  </svg>
                  Confirmar presenças
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
        </div>
        <div>
          <div className="sec-head">
            <p className="eyebrow">Para o professor</p>
            <h2 className="h2">Chamada feita antes do aquecimento.</h2>
            <p className="lead">A turma aparece pronta, com quem agendou. O professor só confirma.</p>
          </div>
          <div className="feat-list">
            <div className="feat">
              <div className="feat-ico">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m9 11 3 3L22 4" />
                  <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                </svg>
              </div>
              <div>
                <h3>Presente ou falta, aluno por aluno</h3>
                <p>Resumo no topo com presentes, faltas e pendentes, e “Marcar todos presentes” para fechar a chamada de uma vez.</p>
              </div>
            </div>
            <div className="feat">
              <div className="feat-ico">
                <svg className="ic" viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="11" cy="11" r="7" />
                  <path d="m21 21-4.3-4.3" />
                </svg>
              </div>
              <div>
                <h3>Adicione quem chegou sem agendar</h3>
                <p>Busca por nome ou e-mail e o aluno entra na turma na hora, com o resumo financeiro dele à vista.</p>
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
                <h3>Bloqueie a turma quando encher</h3>
                <p>Horário, prazo de check-in e vagas ficam no cabeçalho. Um toque fecha a aula para novos check-ins.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
