import { useEffect, useState } from 'react';
import logo from '../assets/logo.png';
import { API_URL, LINKS, brl } from '../config';
import { cicloLabel, gratis, nomeCurto, useCatalogo, valorCiclo } from '../planos';
import '../styles/checkout.css';

const METODOS = [
  { id: 'CREDIT_CARD', titulo: 'Cartão de crédito', desc: 'Pague na página segura do Asaas', nome: 'Cartão de crédito' },
  { id: 'PIX', titulo: 'PIX', desc: 'QR Code na hora, todo mês', nome: 'PIX' },
  { id: 'BOLETO', titulo: 'Boleto', desc: 'Compensação em até 3 dias úteis', nome: 'Boleto' },
];

const ENDERECO = ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'];
const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
const VAZIO = {
  tipo_pessoa: 'pj', academia_nome: '', cnpj: '', razao_social: '', nome_fantasia: '',
  ...Object.fromEntries(ENDERECO.map((c) => [c, ''])),
  responsavel_nome: '', responsavel_cpf: '', responsavel_nascimento: '', email: '', telefone: '',
  endereco_responsavel_igual: true, ...Object.fromEntries(ENDERECO.map((c) => [`responsavel_${c}`, ''])),
  senha: '', senha_confirmacao: '',
};

const pad = (n) => String(n).padStart(2, '0');
const dataBR = (d) => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
const isoBR = (iso) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : null);
const digitos = (v) => v.replace(/\D/g, '');
const semCampo = (obj, campo) => Object.fromEntries(Object.entries(obj).filter(([k]) => k !== campo));

function somarDias(data, dias) {
  const d = new Date(data);
  d.setDate(d.getDate() + dias);
  return d;
}

const mascaraCpf = (v) => digitos(v).slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
const mascaraCnpj = (v) => digitos(v).slice(0, 14).replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
const mascaraCep = (v) => digitos(v).slice(0, 8).replace(/^(\d{5})(\d)/, '$1-$2');
const mascaraData = (v) => digitos(v).slice(0, 8).replace(/^(\d{2})(\d)/, '$1/$2').replace(/^(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');

/** DD/MM/AAAA → AAAA-MM-DD (null se não for uma data de verdade). */
function brParaIso(v) {
  const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(v || '');
  if (!m) return null;
  const d = new Date(+m[3], +m[2] - 1, +m[1]);
  return d.getDate() === +m[1] && d.getMonth() === +m[2] - 1 ? `${m[3]}-${m[2]}-${m[1]}` : null;
}

function idade(iso) {
  const [a, m, d] = iso.split('-').map(Number);
  const h = new Date();
  return h.getFullYear() - a - (h.getMonth() + 1 < m || (h.getMonth() + 1 === m && h.getDate() < d) ? 1 : 0);
}

function cpfOk(v) {
  const d = digitos(v);
  if (d.length !== 11 || /^(\d)\1+$/.test(d)) return false;
  for (let t = 9; t < 11; t++) {
    let s = 0;
    for (let i = 0; i < t; i++) s += +d[i] * (t + 1 - i);
    if (((s * 10) % 11) % 10 !== +d[t]) return false;
  }
  return true;
}

function cnpjOk(v) {
  const d = digitos(v);
  if (d.length !== 14 || /^(\d)\1+$/.test(d)) return false;
  for (const t of [12, 13]) {
    let s = 0;
    let peso = t - 7;
    for (let i = 0; i < t; i++) {
      s += +d[i] * peso;
      peso = peso === 2 ? 9 : peso - 1;
    }
    if ((s % 11 < 2 ? 0 : 11 - (s % 11)) !== +d[t]) return false;
  }
  return true;
}

function erroEndereco(f, p) {
  const e = {};
  if (digitos(f[`${p}cep`]).length !== 8) e[`${p}cep`] = 'O CEP tem 8 números.';
  if (f[`${p}logradouro`].trim().length < 3) e[`${p}logradouro`] = 'Informe a rua.';
  if (!f[`${p}numero`].trim()) e[`${p}numero`] = 'Informe o número (ou s/n).';
  if (f[`${p}bairro`].trim().length < 2) e[`${p}bairro`] = 'Informe o bairro.';
  if (f[`${p}cidade`].trim().length < 2) e[`${p}cidade`] = 'Informe a cidade.';
  if (!UFS.includes(f[`${p}estado`])) e[`${p}estado`] = 'Escolha o estado.';
  return e;
}

/** Mesmas regras da API (CheckoutService::validar). */
function validarForm(f) {
  const e = {};
  if (f.academia_nome.trim().length < 2) e.academia_nome = 'Informe o nome da academia.';
  if (f.tipo_pessoa === 'pj') {
    if (!cnpjOk(f.cnpj)) e.cnpj = digitos(f.cnpj).length !== 14 ? 'O CNPJ tem 14 números.' : 'CNPJ inválido. Confira os números.';
    if (f.razao_social.trim().length < 3) e.razao_social = 'Informe a razão social, como está no cartão do CNPJ.';
  }
  Object.assign(e, erroEndereco(f, ''));
  if (!/^\p{L}[\p{L}'. -]*\s+\p{L}/u.test(f.responsavel_nome.trim())) e.responsavel_nome = 'Informe nome e sobrenome.';
  if (!cpfOk(f.responsavel_cpf)) e.responsavel_cpf = digitos(f.responsavel_cpf).length !== 11 ? 'O CPF tem 11 números.' : 'CPF inválido. Confira os números.';
  const nasc = brParaIso(f.responsavel_nascimento);
  if (!nasc || nasc > new Date().toISOString().slice(0, 10) || idade(nasc) > 110) e.responsavel_nascimento = 'Informe a data de nascimento (DD/MM/AAAA).';
  else if (idade(nasc) < 18) e.responsavel_nascimento = 'O responsável precisa ter 18 anos ou mais.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.email.trim())) e.email = 'Confira o e-mail.';
  if (!/^\d{2}9\d{8}$/.test(digitos(f.telefone))) e.telefone = 'Informe o WhatsApp com DDD: (00) 90000-0000.';
  if (!f.endereco_responsavel_igual) Object.assign(e, erroEndereco(f, 'responsavel_'));
  if (f.senha.length < 8 || !/\p{L}/u.test(f.senha) || !/\d/.test(f.senha)) e.senha = 'Use pelo menos 8 caracteres, com letras e números.';
  else if (f.senha !== f.senha_confirmacao) e.senha_confirmacao = 'As senhas não são iguais.';
  return e;
}

/** Busca o endereço pelo CEP (API pública do Check-in); null se não achar. */
async function buscarCep(cep) {
  try {
    const r = await fetch(`${API_URL}/cep/${digitos(cep)}`);
    const b = await r.json();
    const d = b?.data;
    return b?.type === 'success' && d && (d.logradouro || d.cidade) ? d : null;
  } catch {
    return null;
  }
}

function mascaraTelefone(v) {
  const d = digitos(v).slice(0, 11);
  if (d.length <= 2) return d ? `(${d}` : '';
  const meio = d.length === 11 ? 7 : 6;
  return `(${d.slice(0, 2)}) ${d.slice(2, meio)}${d.length > meio ? `-${d.slice(meio)}` : ''}`;
}

function parametros() {
  const q = new URLSearchParams(window.location.search);
  return { plano: Number(q.get('plano')) || null, alunos: Number(q.get('alunos')) || null };
}

function Ic({ d, children, ...rest }) {
  return (
    <svg className="ic" viewBox="0 0 24 24" aria-hidden="true" {...rest}>
      {d ? <path d={d} /> : children}
    </svg>
  );
}

const Check = () => <Ic d="M20 6 9 17l-5-5" />;
const Info = () => <Ic><circle cx="12" cy="12" r="9" /><path d="M12 16v-4M12 8h.01" /></Ic>;
const Cadeado = () => <Ic><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></Ic>;
const Seta = () => <Ic d="M5 12h14M12 5l7 7-7 7" />;

function IconeMetodo({ id }) {
  if (id === 'PIX') return <Ic><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><path d="M14 14h3v3M21 14v7h-4" /></Ic>;
  if (id === 'BOLETO') return <Ic d="M3 5v14M7 5v14M10 5v14M14 5v14M17 5v14M21 5v14" />;
  return <Ic><rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20" /></Ic>;
}

function Campo({ id, label, erro, full, dica, children }) {
  return (
    <div className={`f${full ? ' full' : ''}${erro ? ' err' : ''}`}>
      <label htmlFor={id}>{label}</label>
      {children}
      {erro ? <p className="err-msg" id={`${id}-erro`}>{erro}</p> : dica ? <small>{dica}</small> : null}
    </div>
  );
}

function precoPlano(p) {
  if (gratis(p)) return { valor: 'R$ 0', unidade: `/${cicloLabel(p.ciclo)}` };
  if (p.tipo === 'por_aluno') return { valor: brl(p.porAluno), unidade: `por aluno/${cicloLabel(p.ciclo)}` };
  return { valor: brl(p.valor), unidade: `/${cicloLabel(p.ciclo)}` };
}

function descPlano(p) {
  if (gratis(p)) {
    const alunos = p.maxAlunos ? `Até ${p.maxAlunos} alunos` : 'Sem limite de alunos';
    return p.prazo ? `${alunos}, por ${p.prazo} dias, sem cartão` : `${alunos}, sem cartão`;
  }
  return 'Pagamentos, recorrência e financeiro';
}

function Concluido({ res, plano, alunos, metodo, hoje }) {
  const [copiado, setCopiado] = useState(false);
  const pago = !gratis(plano);
  const c = res.cobranca;
  const proxima = somarDias(hoje, plano.ciclo);
  const linkPagamento = c ? (c.billing_type === 'BOLETO' ? c.boleto_url || c.url : c.url) : null;

  const copiar = () => {
    navigator.clipboard?.writeText(c.pix_payload).then(() => setCopiado(true));
  };

  let texto = `Entre no painel com ${res.email} e a senha que você criou. Comece cadastrando planos e turmas, e convide seus alunos para baixar o app.`;
  if (pago && c?.billing_type === 'PIX') texto = `Pague o PIX abaixo para liberar o ${nomeCurto(plano)}. ${texto}`;
  else if (pago && c?.billing_type === 'BOLETO') texto = `O painel é liberado assim que o boleto compensar, em até 3 dias úteis. ${texto}`;
  else if (pago && c) texto = `Informe o cartão na página segura do Asaas para liberar o ${nomeCurto(plano)}. ${texto}`;

  return (
    <div className="done" role="status">
      <div className="done-ico"><Check /></div>
      <h1>{pago ? 'Assinatura criada' : 'Conta grátis criada'}</h1>
      <p>{texto}</p>
      {res.aviso ? <p className="alert"><Info />{res.aviso}</p> : null}
      {c?.billing_type === 'PIX' && c.pix_payload ? (
        <div className="pay-qr">
          {c.pix_qr_code ? <img src={c.pix_qr_code.startsWith('data:') ? c.pix_qr_code : `data:image/png;base64,${c.pix_qr_code}`} alt="QR Code do PIX" /> : null}
          <code>{c.pix_payload}</code>
          <button type="button" className="btn btn-line" onClick={copiar}>{copiado ? 'Código copiado' : 'Copiar código PIX'}</button>
        </div>
      ) : null}
      <div className="done-rows">
        <div><span>Plano</span><b>{plano.nome}</b></div>
        <div><span>Alunos ativos</span><b className="num">{pago && plano.tipo === 'por_aluno' ? alunos : plano.maxAlunos ? `Até ${plano.maxAlunos}` : 'Sem limite'}</b></div>
        <div><span>Mensalidade</span><b className="num">{brl(Number(res.valor) || 0)}</b></div>
        <div><span>Forma de pagamento</span><b>{pago ? metodo?.nome : 'Sem cartão'}</b></div>
        {pago
          ? <div><span>Próxima cobrança</span><b className="num">{dataBR(proxima)}</b></div>
          : <div><span>Válido até</span><b className="num">{isoBR(res.contrato?.data_fim) || 'Sem prazo'}</b></div>}
      </div>
      <div className="done-actions">
        {linkPagamento ? (
          <a className="btn btn-dark" href={linkPagamento} target="_blank" rel="noreferrer">
            {c.billing_type === 'BOLETO' ? 'Abrir boleto' : c.billing_type === 'PIX' ? 'Abrir fatura' : 'Pagar com cartão'}
            <Seta />
          </a>
        ) : null}
        <a className={linkPagamento ? 'btn btn-line' : 'btn btn-dark'} href={LINKS.entrar}>Acessar o painel</a>
        <a className="btn btn-line" href="/">Voltar ao site</a>
      </div>
    </div>
  );
}

export default function Checkout() {
  const { planos, formas, carregado } = useCatalogo();
  const [inicio] = useState(parametros);
  const [escolhido, setEscolhido] = useState(inicio.plano);
  const [qtd, setQtd] = useState(inicio.alunos);
  const [metodoEscolhido, setMetodo] = useState(null);
  const [form, setForm] = useState(VAZIO);
  const [verSenha, setVerSenha] = useState(false);
  const [tocados, setTocados] = useState({});
  const [tentou, setTentou] = useState(false);
  const [cepStatus, setCepStatus] = useState({});
  const [aceite, setAceite] = useState(false);
  const [erros, setErros] = useState({});
  const [enviando, setEnviando] = useState(false);
  const [resultado, setResultado] = useState(null);
  const [hoje] = useState(() => new Date());

  useEffect(() => {
    document.title = 'AppCheckin — Assinar o plano';
  }, []);

  const plano = planos.find((p) => p.id === escolhido) || planos.find((p) => !gratis(p)) || planos[0];
  const pago = plano ? !gratis(plano) : false;
  const porAluno = plano?.tipo === 'por_aluno';
  const max = plano?.maxAlunos || 200;
  const alunos = Math.min(Math.max(qtd || Math.round(max * 0.6), 1), max);

  const disponiveis = METODOS.filter((m) => formas === null || formas.includes(m.id));
  const metodo = disponiveis.find((m) => m.id === metodoEscolhido) || disponiveis[0] || null;
  const semGateway = pago && carregado && disponiveis.length === 0;

  const total = plano ? valorCiclo(plano, alunos) : 0;
  const proxima = somarDias(hoje, plano?.ciclo || 30);
  const ciclo = plano ? cicloLabel(plano.ciclo) : 'mês';

  // Erro do campo: validação local (depois de sair do campo ou de tentar enviar) ou o que a API devolveu
  const locais = validarForm(form);
  const erroDe = (campo) => erros[campo] || ((tentou || tocados[campo]) ? locais[campo] : null);

  const set = (campo, mascara) => (e) => {
    const valor = mascara ? mascara(e.target.value) : e.target.value;
    setForm((f) => ({ ...f, [campo]: valor }));
    if (erros[campo]) setErros((x) => semCampo(x, campo));
  };

  const prop = (campo) => ({
    'aria-invalid': erroDe(campo) ? true : undefined,
    'aria-describedby': erroDe(campo) ? `${campo}-erro` : undefined,
    onBlur: () => setTocados((t) => ({ ...t, [campo]: true })),
  });

  // CEP completo: preenche rua, bairro, cidade e UF (sem apagar o que a pessoa já digitou se não achar)
  const onCep = (prefixo) => async (e) => {
    const campo = `${prefixo}cep`;
    const valor = mascaraCep(e.target.value);
    setForm((f) => ({ ...f, [campo]: valor }));
    setErros((x) => semCampo(x, campo));
    if (digitos(valor).length !== 8) { setCepStatus((c) => ({ ...c, [prefixo]: null })); return; }
    setCepStatus((c) => ({ ...c, [prefixo]: 'buscando' }));
    const d = await buscarCep(valor);
    setCepStatus((c) => ({ ...c, [prefixo]: d ? 'ok' : 'nao' }));
    if (d) {
      setForm((f) => ({
        ...f,
        [`${prefixo}logradouro`]: d.logradouro || f[`${prefixo}logradouro`],
        [`${prefixo}bairro`]: d.bairro || f[`${prefixo}bairro`],
        [`${prefixo}cidade`]: d.cidade || f[`${prefixo}cidade`],
        [`${prefixo}estado`]: d.estado || f[`${prefixo}estado`],
      }));
      setErros((x) => ['logradouro', 'bairro', 'cidade', 'estado'].reduce((acc, c) => semCampo(acc, `${prefixo}${c}`), x));
      document.getElementById(`${prefixo}numero`)?.focus();
    }
  };

  const enderecoCampos = (prefixo) => (
    <>
      <Campo id={`${prefixo}cep`} label="CEP" erro={erroDe(`${prefixo}cep`)}
        dica={cepStatus[prefixo] === 'buscando' ? 'Buscando o endereço…' : cepStatus[prefixo] === 'ok' ? 'Endereço preenchido pelo CEP.' : cepStatus[prefixo] === 'nao' ? 'CEP não encontrado. Preencha à mão.' : 'Preenche rua, bairro e cidade.'}>
        <input id={`${prefixo}cep`} type="text" inputMode="numeric" placeholder="00000-000" autoComplete="postal-code" value={form[`${prefixo}cep`]} onChange={onCep(prefixo)} {...prop(`${prefixo}cep`)} />
      </Campo>
      <Campo id={`${prefixo}logradouro`} label="Rua" erro={erroDe(`${prefixo}logradouro`)}>
        <input id={`${prefixo}logradouro`} type="text" placeholder="Rua, avenida, travessa" autoComplete="address-line1" value={form[`${prefixo}logradouro`]} onChange={set(`${prefixo}logradouro`)} {...prop(`${prefixo}logradouro`)} />
      </Campo>
      <Campo id={`${prefixo}numero`} label="Número" erro={erroDe(`${prefixo}numero`)}>
        <input id={`${prefixo}numero`} type="text" placeholder="Nº ou s/n" value={form[`${prefixo}numero`]} onChange={set(`${prefixo}numero`)} {...prop(`${prefixo}numero`)} />
      </Campo>
      <Campo id={`${prefixo}complemento`} label="Complemento" erro={erroDe(`${prefixo}complemento`)} dica="Opcional.">
        <input id={`${prefixo}complemento`} type="text" placeholder="Sala, bloco, referência" autoComplete="address-line2" value={form[`${prefixo}complemento`]} onChange={set(`${prefixo}complemento`)} {...prop(`${prefixo}complemento`)} />
      </Campo>
      <Campo id={`${prefixo}bairro`} label="Bairro" erro={erroDe(`${prefixo}bairro`)}>
        <input id={`${prefixo}bairro`} type="text" value={form[`${prefixo}bairro`]} onChange={set(`${prefixo}bairro`)} {...prop(`${prefixo}bairro`)} />
      </Campo>
      <Campo id={`${prefixo}cidade`} label="Cidade" erro={erroDe(`${prefixo}cidade`)}>
        <input id={`${prefixo}cidade`} type="text" autoComplete="address-level2" value={form[`${prefixo}cidade`]} onChange={set(`${prefixo}cidade`)} {...prop(`${prefixo}cidade`)} />
      </Campo>
      <Campo id={`${prefixo}estado`} label="Estado" erro={erroDe(`${prefixo}estado`)}>
        <select id={`${prefixo}estado`} autoComplete="address-level1" value={form[`${prefixo}estado`]} onChange={set(`${prefixo}estado`)} {...prop(`${prefixo}estado`)}>
          <option value="">Escolha</option>
          {UFS.map((uf) => <option key={uf} value={uf}>{uf}</option>)}
        </select>
      </Campo>
    </>
  );

  async function enviar(e) {
    e?.preventDefault();
    if (!plano || enviando || !aceite || semGateway) {
      if (!aceite) setErros((x) => ({ ...x, aceite_termos: 'Aceite os termos para continuar.' }));
      return;
    }
    setTentou(true);
    if (Object.keys(locais).length) {
      const primeiro = Object.keys(locais).find((k) => document.getElementById(k));
      if (primeiro) document.getElementById(primeiro).focus();
      return;
    }
    setEnviando(true);
    setErros({});
    try {
      const r = await fetch(`${API_URL}/site/assinar`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          ...form,
          responsavel_nascimento: brParaIso(form.responsavel_nascimento),
          ...(form.tipo_pessoa === 'pf' ? { cnpj: '', razao_social: '', nome_fantasia: '' } : {}),
          plano_id: plano.id,
          forma_pagamento: pago ? metodo?.id : null,
          alunos: pago ? alunos : null,
          aceite_termos: aceite,
        }),
      });
      const body = await r.json().catch(() => ({}));
      if (r.ok) {
        setResultado(body);
        window.scrollTo(0, 0);
        return;
      }
      const campos = body.errors && typeof body.errors === 'object' ? body.errors : {};
      setErros({ ...campos, geral: campos.geral || body.message || 'Não foi possível concluir agora. Tente de novo.' });
      const primeiro = Object.keys(campos).find((k) => document.getElementById(k));
      if (primeiro) document.getElementById(primeiro).focus();
    } catch {
      setErros({ geral: 'Sem conexão com o servidor. Confira a internet e tente de novo.' });
    } finally {
      setEnviando(false);
    }
  }

  const dia = pad(Math.min(hoje.getDate(), 28));
  let botao = 'Criar conta grátis';
  if (pago) {
    const verbo = metodo?.id === 'PIX' ? 'Assinar e gerar PIX de ' : metodo?.id === 'BOLETO' ? 'Assinar e gerar boleto de ' : 'Assinar e pagar ';
    botao = verbo + brl(total);
  }
  if (enviando) botao = pago ? 'Gerando a cobrança…' : 'Criando a conta…';
  const bloqueado = !aceite || enviando || semGateway;

  return (
    <div className="co">
      <header className="top">
        <div className="wrap top-in">
          <a className="back" href="/"><Ic d="M19 12H5M12 19l-7-7 7-7" />Voltar</a>
          <img src={logo} alt="AppCheckin" />
          <span className="secure"><Cadeado /><span>Pagamento seguro</span></span>
        </div>
      </header>

      {resultado && plano ? (
        <Concluido res={resultado} plano={plano} alunos={alunos} metodo={metodo} hoje={hoje} />
      ) : !plano ? (
        <p className="co-load">Carregando os planos…</p>
      ) : (
        <div className="wrap">
          <div className="head">
            <p className="eyebrow">Assinar o AppCheckin</p>
            <h1>Falta pouco para sua academia entrar no ritmo.</h1>
            <p>
              Conte sobre a academia e escolha o plano.
              {planos.some(gratis) ? ` O ${planos.find(gratis).nome} começa agora, sem cartão.` : ''}
              {planos.some((p) => p.tipo === 'por_aluno') ? ` No ${nomeCurto(planos.find((p) => p.tipo === 'por_aluno'))}, você paga só pelos alunos ativos, todo mês.` : ''}
            </p>
          </div>

          <div className="grid">
            <form className="form" onSubmit={enviar} noValidate>
              <section className="box" aria-labelledby="s1">
                <div className="box-h"><span className="co-step">1</span><div><h2 id="s1">Sua academia</h2><p>Usamos esses dados no contrato, na nota fiscal e para criar o seu painel.</p></div></div>
                <div className="fields">
                  <div className="f full">
                    <span className="f-label" id="tipo-label">A academia é</span>
                    <div className="tipo" role="radiogroup" aria-labelledby="tipo-label">
                      {[['pj', 'Empresa', 'Tem CNPJ'], ['pf', 'Pessoa física', 'No CPF do responsável']].map(([v, t, d]) => (
                        <button key={v} type="button" role="radio" aria-checked={form.tipo_pessoa === v} className={form.tipo_pessoa === v ? 'on' : undefined}
                          onClick={() => setForm((f) => ({ ...f, tipo_pessoa: v }))}>
                          <b>{t}</b><small>{d}</small>
                        </button>
                      ))}
                    </div>
                  </div>
                  <Campo id="academia_nome" label="Nome da academia" erro={erroDe('academia_nome')} full dica="Como os alunos vão ver no app.">
                    <input id="academia_nome" type="text" placeholder="Ex.: Cia da Natação" autoComplete="organization" value={form.academia_nome} onChange={set('academia_nome')} {...prop('academia_nome')} />
                  </Campo>
                  {form.tipo_pessoa === 'pj' ? (
                    <>
                      <Campo id="cnpj" label="CNPJ" erro={erroDe('cnpj')} full>
                        <input id="cnpj" type="text" inputMode="numeric" placeholder="00.000.000/0000-00" value={form.cnpj} onChange={set('cnpj', mascaraCnpj)} {...prop('cnpj')} />
                      </Campo>
                      <Campo id="razao_social" label="Razão social" erro={erroDe('razao_social')} dica="Como está no cartão do CNPJ.">
                        <input id="razao_social" type="text" placeholder="Ex.: Cia da Natação Ltda" value={form.razao_social} onChange={set('razao_social')} {...prop('razao_social')} />
                      </Campo>
                      <Campo id="nome_fantasia" label="Nome fantasia" erro={erroDe('nome_fantasia')} dica="Opcional. Se ficar em branco, usamos o nome da academia.">
                        <input id="nome_fantasia" type="text" value={form.nome_fantasia} onChange={set('nome_fantasia')} {...prop('nome_fantasia')} />
                      </Campo>
                    </>
                  ) : null}
                  <p className="f-sub full">Endereço da academia</p>
                  {enderecoCampos('')}
                </div>
              </section>

              <section className="box" aria-labelledby="s1b">
                <div className="box-h"><span className="co-step">2</span><div><h2 id="s1b">Responsável</h2><p>{form.tipo_pessoa === 'pf' ? 'A academia fica no CPF desta pessoa, que também administra o painel.' : 'Quem responde pela academia e administra o painel.'}</p></div></div>
                <div className="fields">
                  <Campo id="responsavel_nome" label="Nome completo" erro={erroDe('responsavel_nome')} full>
                    <input id="responsavel_nome" type="text" placeholder="Nome e sobrenome" autoComplete="name" value={form.responsavel_nome} onChange={set('responsavel_nome')} {...prop('responsavel_nome')} />
                  </Campo>
                  <Campo id="responsavel_cpf" label="CPF" erro={erroDe('responsavel_cpf')}>
                    <input id="responsavel_cpf" type="text" inputMode="numeric" placeholder="000.000.000-00" value={form.responsavel_cpf} onChange={set('responsavel_cpf', mascaraCpf)} {...prop('responsavel_cpf')} />
                  </Campo>
                  <Campo id="responsavel_nascimento" label="Data de nascimento" erro={erroDe('responsavel_nascimento')}>
                    <input id="responsavel_nascimento" type="text" inputMode="numeric" placeholder="DD/MM/AAAA" autoComplete="bday" value={form.responsavel_nascimento} onChange={set('responsavel_nascimento', mascaraData)} {...prop('responsavel_nascimento')} />
                  </Campo>
                  <Campo id="email" label="E-mail" erro={erroDe('email')} dica="É o seu login no painel.">
                    <input id="email" type="email" placeholder="voce@academia.com.br" autoComplete="email" value={form.email} onChange={set('email')} {...prop('email')} />
                  </Campo>
                  <Campo id="telefone" label="WhatsApp" erro={erroDe('telefone')} dica="Celular com DDD.">
                    <input id="telefone" type="tel" placeholder="(82) 90000-0000" autoComplete="tel" value={form.telefone} onChange={set('telefone', mascaraTelefone)} {...prop('telefone')} />
                  </Campo>
                  <label className="f-check full">
                    <input type="checkbox" checked={form.endereco_responsavel_igual} onChange={(e) => setForm((f) => ({ ...f, endereco_responsavel_igual: e.target.checked }))} />
                    <span>Meu endereço é o mesmo da academia</span>
                  </label>
                  {!form.endereco_responsavel_igual ? (
                    <>
                      <p className="f-sub full">Seu endereço</p>
                      {enderecoCampos('responsavel_')}
                    </>
                  ) : null}
                  <Campo id="senha" label="Crie uma senha" erro={erroDe('senha')} dica="Pelo menos 8 caracteres, com letras e números.">
                    <div className="f-pass">
                      <input id="senha" type={verSenha ? 'text' : 'password'} autoComplete="new-password" value={form.senha} onChange={set('senha')} {...prop('senha')} />
                      <button type="button" onClick={() => setVerSenha((v) => !v)} aria-pressed={verSenha}>{verSenha ? 'Ocultar' : 'Mostrar'}</button>
                    </div>
                  </Campo>
                  <Campo id="senha_confirmacao" label="Confirme a senha" erro={erroDe('senha_confirmacao')}
                    dica={form.senha_confirmacao && form.senha === form.senha_confirmacao ? 'As senhas conferem.' : 'Digite a mesma senha de novo.'}>
                    <input id="senha_confirmacao" type={verSenha ? 'text' : 'password'} autoComplete="new-password" value={form.senha_confirmacao} onChange={set('senha_confirmacao')} onPaste={(e) => e.preventDefault()} {...prop('senha_confirmacao')} />
                  </Campo>
                </div>
              </section>

              <section className="box" aria-labelledby="s2">
                <div className="box-h"><span className="co-step">3</span><div><h2 id="s2">Seu plano</h2><p>{planos.some(gratis) ? 'Comece grátis ou libere os pagamentos pelo app.' : 'Escolha o plano da sua academia.'}</p></div></div>
                <div className="plans-pick" role="radiogroup" aria-label="Plano">
                  {planos.map((p) => {
                    const on = p.id === plano.id;
                    const preco = precoPlano(p);
                    return (
                      <button key={p.id} type="button" role="radio" className={on ? 'm on' : 'm'} aria-checked={on} onClick={() => setEscolhido(p.id)}>
                        <span className="m-dot" aria-hidden="true" />
                        <b>{nomeCurto(p)}</b>
                        <span className="pp-price num">{preco.valor}<small>{preco.unidade}</small></span>
                        <small>{descPlano(p)}</small>
                      </button>
                    );
                  })}
                </div>
                {erros.plano_id ? <p className="box-err">{erros.plano_id}</p> : null}
                {!pago ? (
                  <>
                    <div className="free-list">
                      <div><Check />{plano.maxAlunos ? `Até ${plano.maxAlunos} alunos ativos` : 'Alunos ilimitados'}</div>
                      <div><Check />App do aluno e check-in</div>
                      <div><Check />Modo professor com chamada</div>
                      <div><Check />Ranking mensal de frequência</div>
                    </div>
                    <p className="hint"><Info />Pagamentos no app, recorrência com Asaas e relatório financeiro ficam nos planos pagos. Você muda de plano pelo painel quando quiser.</p>
                  </>
                ) : porAluno ? (
                  <>
                    <div className="qty">
                      <div className="qty-top">
                        <label htmlFor="co-alunos">Alunos ativos</label>
                        <output htmlFor="co-alunos" className="num">{alunos}<small>{alunos === 1 ? 'aluno' : 'alunos'}</small></output>
                      </div>
                      <input id="co-alunos" type="range" min="1" max={max} step="1" value={alunos} onChange={(e) => setQtd(Number(e.target.value))} aria-valuetext={`${alunos} ${alunos === 1 ? 'aluno' : 'alunos'}`} style={{ background: `linear-gradient(90deg, #ff7a00 0%, #ff7a00 ${((alunos - 1) / Math.max(max - 1, 1)) * 100}%, #dcdfe4 ${((alunos - 1) / Math.max(max - 1, 1)) * 100}%, #dcdfe4 100%)` }} />
                      <div className="scale num" aria-hidden="true"><span>1</span>{[1, 2, 3, 4].map((i) => <span key={i}>{Math.round((max / 4) * i)}</span>)}</div>
                    </div>
                    <p className="hint"><Info /><span>O valor acompanha a academia: se o número de alunos ativos mudar, a mensalidade seguinte é ajustada.{plano.maxAlunos ? <> Mais de {plano.maxAlunos} alunos? <a href="/#contato">Fale com vendas</a>.</> : null}</span></p>
                  </>
                ) : (
                  <p className="hint"><Info /><span>Valor fixo de {brl(plano.valor)} por {ciclo}{plano.maxAlunos ? `, até ${plano.maxAlunos} alunos ativos` : ''}.{plano.maxAlunos ? <> Precisa de mais? <a href="/#contato">Fale com vendas</a>.</> : null}</span></p>
                )}
              </section>

              {pago ? (
                <section className="box" aria-labelledby="s3">
                  <div className="box-h"><span className="co-step">4</span><div><h2 id="s3">Pagamento</h2><p>Cobrança mensal pelo Asaas.</p></div></div>
                  {semGateway ? (
                    <p className="alert"><Info /><span>A assinatura paga está indisponível no momento. Comece pelo plano grátis ou <a href="/#contato">fale com a gente</a>.</span></p>
                  ) : (
                    <>
                      <div className="methods" role="radiogroup" aria-label="Forma de pagamento" style={{ gridTemplateColumns: `repeat(${Math.max(disponiveis.length, 1)}, minmax(0, 1fr))` }}>
                        {disponiveis.map((m) => {
                          const on = m.id === metodo?.id;
                          return (
                            <button key={m.id} type="button" role="radio" className={`m${m.id === 'PIX' ? ' pix' : ''}${on ? ' on' : ''}`} aria-checked={on} onClick={() => setMetodo(m.id)}>
                              <span className="m-dot" aria-hidden="true" />
                              <span className="m-ico" aria-hidden="true"><IconeMetodo id={m.id} /></span>
                              <b>{m.titulo}</b>
                              <small>{m.desc}</small>
                            </button>
                          );
                        })}
                      </div>
                      {erros.forma_pagamento ? <p className="box-err">{erros.forma_pagamento}</p> : null}
                      <div className="panel-in" key={metodo?.id}>
                        {metodo?.id === 'CREDIT_CARD' ? (
                          <p className="note"><Cadeado />Ao assinar, abrimos a página segura do Asaas para você informar o cartão. Os dados do cartão ficam com o Asaas, não com o AppCheckin. As próximas mensalidades chegam por e-mail antes do dia {dia}.</p>
                        ) : metodo?.id === 'PIX' ? (
                          <p className="note"><Check />Depois de assinar, você recebe o primeiro PIX na hora, com QR Code e copia e cola. Nos meses seguintes, o PIX chega por e-mail antes do dia {dia}.</p>
                        ) : (
                          <p className="note"><Check />O primeiro boleto é gerado ao assinar e o painel é liberado após a compensação, em até 3 dias úteis. Os próximos chegam por e-mail antes do dia {dia}.</p>
                        )}
                      </div>
                    </>
                  )}
                </section>
              ) : null}
            </form>

            <aside className="sum" aria-label="Resumo da assinatura">
              <div className="fac fa" /><div className="fac fb" />
              <div><p className="eyebrow">Resumo</p><h3>{plano.nome}</h3></div>
              <div className="sum-rows">
                <div><span>Alunos ativos</span><b className="num">{pago && porAluno ? alunos : plano.maxAlunos ? `Até ${plano.maxAlunos}` : 'Sem limite'}</b></div>
                {porAluno ? <div><span>Valor por aluno</span><b className="num">{brl(plano.porAluno)}</b></div> : null}
                <div><span>Forma de pagamento</span><b>{pago ? metodo?.nome || '—' : 'Sem cartão'}</b></div>
                <div><span>Cobrança</span><b>{pago ? `Mensal, todo dia ${dia}` : 'Sem cobrança'}</b></div>
              </div>
              <div className="sum-total" aria-live="polite"><span>Total por {ciclo}</span><b className="num">{brl(total)}<small>/{ciclo}</small></b></div>
              <p className="sum-when">
                {pago
                  ? `Hoje você paga ${brl(total)}. Próxima cobrança em ${dataBR(proxima)}. Cancele quando quiser, sem multa.`
                  : `Nada a pagar hoje${plano.prazo ? `. A conta grátis vale por ${plano.prazo} dias` : ' nem depois'}. Quando quiser receber pelo app, mude de plano no painel.`}
              </p>
              {erros.geral ? <p className="alert" role="alert"><Info />{erros.geral}</p> : null}
              <label className="terms">
                <input type="checkbox" checked={aceite} onChange={(e) => { setAceite(e.target.checked); setErros((x) => semCampo(x, 'aceite_termos')); }} />
                <span>Li e aceito os <a href={LINKS.termos}>Termos de uso</a> e a <a href={LINKS.privacidade}>Política de privacidade</a>.</span>
              </label>
              {erros.aceite_termos ? <p className="box-err" style={{ color: '#ffb27a' }}>{erros.aceite_termos}</p> : null}
              <button type="button" className={bloqueado ? 'pay off' : 'pay'} aria-disabled={bloqueado} aria-busy={enviando} onClick={enviar}>
                {botao}
                <Seta />
              </button>
              <div className="trust">
                {pago ? <div><Cadeado />Pagamento processado pelo Asaas</div> : <div><Check />Sem cartão e sem cobrança</div>}
                {porAluno ? <div><Ic><path d="M21 12a9 9 0 1 1-3-6.7L21 8" /><path d="M21 3v5h-5" /></Ic>Valor ajustado aos alunos ativos de cada mês</div> : null}
                <div><Ic d="M18 6 6 18M6 6l12 12" />Sem fidelidade e sem taxa de cancelamento</div>
              </div>
            </aside>
          </div>
        </div>
      )}
    </div>
  );
}
