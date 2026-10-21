/* Conversa manual pelo WhatsApp Web: normaliza o telefone cadastrado e monta o link wa.me/web.
   Não envia nada — só abre a conversa (com texto opcional) para o atendente enviar à mão. */

export const WHATSAPP_WEB_URL = 'https://web.whatsapp.com/send';
export const PAIS_BRASIL = '55';

// DDDs em uso no Brasil (Anatel)
export const DDDS = new Set([
  11, 12, 13, 14, 15, 16, 17, 18, 19, 21, 22, 24, 27, 28, 31, 32, 33, 34, 35, 37, 38,
  41, 42, 43, 44, 45, 46, 47, 48, 49, 51, 53, 54, 55, 61, 62, 63, 64, 65, 66, 67, 68, 69,
  71, 73, 74, 75, 77, 79, 81, 82, 83, 84, 85, 86, 87, 88, 89, 91, 92, 93, 94, 95, 96, 97, 98, 99,
].map(String));

export const ORIENTACAO_TELEFONE = 'Corrija o cadastro com DDD + número, ex.: (82) 99999-9999.';

// Celular: DDD + 9 + 8 dígitos. Fixo: DDD + [2-5] + 7 dígitos. Contas antigas de celular
// com 8 dígitos (DDD + [6-9] + 7) ainda existem no cadastro e o WhatsApp as reconhece.
function nacionalValido(d) {
  if (!DDDS.has(d.slice(0, 2))) return false;
  if (d.length === 11) return d[2] === '9';
  return d.length === 10 && /[2-9]/.test(d[2]);
}

/**
 * Normaliza um telefone para o formato internacional usado pelo WhatsApp (só dígitos, com país).
 * @param {string|number|null} telefone - Valor cadastrado, com ou sem máscara/+55
 * @returns {{ ok: true, numero: string, nacional: string|null } | { ok: false, motivo: 'vazio'|'invalido', mensagem: string }}
 */
export function normalizarTelefoneWhatsapp(telefone) {
  const bruto = telefone == null ? '' : String(telefone).trim();
  let d = bruto.replace(/\D/g, '');
  const invalido = { ok: false, motivo: 'invalido', mensagem: `Telefone inválido. ${ORIENTACAO_TELEFONE}` };
  if (!d) return /[a-z]/i.test(bruto) ? invalido : { ok: false, motivo: 'vazio', mensagem: 'Aluno sem telefone cadastrado.' };

  // Discagem internacional (00 / +): país explícito
  const internacional = bruto.startsWith('+') || d.startsWith('00');
  const comZero = d.startsWith('0');
  d = d.replace(/^0+/, '');
  // Discagem nacional com código de operadora: 0 + XX + DDD + número
  if (comZero && !internacional && (d.length === 12 || d.length === 13) && nacionalValido(d.slice(2))) {
    d = d.slice(2);
  }
  if (internacional && !d.startsWith(PAIS_BRASIL)) {
    return d.length >= 8 && d.length <= 15 ? { ok: true, numero: d, nacional: null } : invalido;
  }
  // Já vem com 55 (sem duplicar). DDD 55 sozinho tem 10/11 dígitos, então não se confunde.
  if ((d.length === 12 || d.length === 13) && d.startsWith(PAIS_BRASIL) && nacionalValido(d.slice(2))) {
    d = d.slice(2);
  }
  return nacionalValido(d) ? { ok: true, numero: PAIS_BRASIL + d, nacional: d } : invalido;
}

export const telefoneWhatsappValido = (telefone) => normalizarTelefoneWhatsapp(telefone).ok;

// Prefere o campo whatsapp do aluno; cai para o telefone geral.
export const telefoneDoAluno = (aluno) => (aluno && (aluno.whatsapp || aluno.telefone || aluno.phone)) || '';

/**
 * @param {string} numero - Já normalizado (normalizarTelefoneWhatsapp().numero)
 * @param {string} [texto] - Mensagem para deixar preenchida (opcional)
 */
export function urlConversaWhatsapp(numero, texto) {
  const msg = texto && String(texto).trim();
  return `${WHATSAPP_WEB_URL}?phone=${numero}${msg ? `&text=${encodeURIComponent(msg)}` : ''}`;
}

/**
 * Abre a conversa numa nova aba. Deve ser chamada direto no handler de clique (evita bloqueio de pop-up).
 * Abrir a conversa NÃO significa que a mensagem foi enviada.
 * @returns {{ ok: boolean, url?: string, mensagem?: string }}
 */
export function abrirConversaWhatsapp(telefone, texto) {
  const tel = normalizarTelefoneWhatsapp(telefone);
  if (!tel.ok) return { ok: false, mensagem: tel.mensagem };
  const url = urlConversaWhatsapp(tel.numero, texto);
  window.open(url, '_blank', 'noopener,noreferrer');
  return { ok: true, url };
}
