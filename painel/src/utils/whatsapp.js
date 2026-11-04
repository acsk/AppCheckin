/* Conversa manual pelo WhatsApp: normaliza o telefone e abre a versão web ou o aplicativo.
   Não envia nada — só abre a conversa (com texto opcional) para o atendente enviar à mão. */
import { parsePhone } from '@appcheckin/phone';

export const WHATSAPP_WEB_URL = 'https://wa.me';
export const WHATSAPP_APP_URL = 'whatsapp://send';
export const PAIS_BRASIL = '55';

// DDDs em uso no Brasil (Anatel)
export const DDDS = new Set([
  11, 12, 13, 14, 15, 16, 17, 18, 19, 21, 22, 24, 27, 28, 31, 32, 33, 34, 35, 37, 38,
  41, 42, 43, 44, 45, 46, 47, 48, 49, 51, 53, 54, 55, 61, 62, 63, 64, 65, 66, 67, 68, 69,
  71, 73, 74, 75, 77, 79, 81, 82, 83, 84, 85, 86, 87, 88, 89, 91, 92, 93, 94, 95, 96, 97, 98, 99,
].map(String));

export const ORIENTACAO_TELEFONE = 'Corrija o cadastro selecionando o país e o DDI, ex.: +55 (82) 99999-9999.';

/**
 * Normaliza um telefone para o formato internacional usado pelo WhatsApp (só dígitos, com país).
 * @param {string|number|null} telefone - Valor cadastrado, com ou sem máscara/+55
 * @returns {{ ok: true, numero: string, nacional: string|null } | { ok: false, motivo: 'vazio'|'invalido', mensagem: string }}
 */
export function normalizarTelefoneWhatsapp(telefone) {
  const bruto = telefone == null ? '' : String(telefone).trim();
  const invalido = { ok: false, motivo: 'invalido', mensagem: `Telefone inválido. ${ORIENTACAO_TELEFONE}` };
  if (!bruto.replace(/\D/g, '')) return /[a-z]/i.test(bruto) ? invalido : { ok: false, motivo: 'vazio', mensagem: 'Aluno sem telefone cadastrado.' };
  const phone = parsePhone(bruto);
  return phone ? { ok: true, numero: phone.number.slice(1), nacional: phone.country === 'BR' ? phone.nationalNumber : null } : invalido;
}

export const telefoneWhatsappValido = (telefone) => normalizarTelefoneWhatsapp(telefone).ok;

// Prefere o campo whatsapp do aluno; cai para o telefone geral.
export const telefoneDoAluno = (aluno) => (aluno && (aluno.whatsapp || aluno.telefone || aluno.phone)) || '';

/**
 * @param {string} numero - Já normalizado (normalizarTelefoneWhatsapp().numero)
 * @param {string} [texto] - Mensagem para deixar preenchida (opcional)
 * @param {'web'|'app'} [destino='web'] - Onde abrir a conversa
 */
export function urlConversaWhatsapp(numero, texto, destino = 'web') {
  const msg = texto && String(texto).trim();
  if (destino === 'app') return `${WHATSAPP_APP_URL}?phone=${numero}${msg ? `&text=${encodeURIComponent(msg)}` : ''}`;
  return `${WHATSAPP_WEB_URL}/${numero}${msg ? `?text=${encodeURIComponent(msg)}` : ''}`;
}

/**
 * Abre a versão web em nova aba ou aciona o aplicativo instalado. Deve ser chamada direto no handler de clique (evita bloqueio de pop-up).
 * Abrir a conversa NÃO significa que a mensagem foi enviada.
 * @returns {{ ok: boolean, url?: string, mensagem?: string }}
 */
export function abrirConversaWhatsapp(telefone, texto, destino = 'web') {
  const tel = normalizarTelefoneWhatsapp(telefone);
  if (!tel.ok) return { ok: false, mensagem: tel.mensagem };
  const url = urlConversaWhatsapp(tel.numero, texto, destino);
  if (destino === 'app') window.location.assign(url);
  else window.open(url, '_blank', 'noopener,noreferrer');
  return { ok: true, url };
}
