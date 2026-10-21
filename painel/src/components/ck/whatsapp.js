/* Contato manual pelo WhatsApp Web (Prospecção → Recuperação de Alunos e afins).
   Estilos: src/theme/ck-whatsapp.css. Regras de telefone em ../../utils/whatsapp.
   Abre a conversa numa nova aba e mantém o painel aberto; nunca envia mensagem nem a registra como enviada. */
import React from 'react';
import { Icon } from './index';
import { maskPhone } from './pessoas';
import { abrirConversaWhatsapp, normalizarTelefoneWhatsapp, ORIENTACAO_TELEFONE } from '../../utils/whatsapp';
import { showWarning } from '../../utils/toast';

const TIP = 'Conversar pelo WhatsApp';

function exibicao(telefone, tel) {
  if (tel.ok) return tel.nacional ? maskPhone(tel.nacional) : `+${tel.numero}`;
  return telefone ? maskPhone(telefone) || String(telefone) : '';
}

/**
 * @param {object} p
 * @param {string} p.telefone - Telefone cadastrado (com ou sem máscara)
 * @param {string} [p.mensagem] - Texto para deixar preenchido no WhatsApp (opcional)
 * @param {'inline'|'button'|'icon'} [p.variant] - inline: número + ícone (tabela); button: "Conversar no WhatsApp" (ficha); icon: ação rápida
 * @param {boolean|string} [p.bloqueado] - Aluno pediu para não receber mensagens (string = motivo exibido)
 * @param {(info: { url: string, numero: string }) => void} [p.onAbrir] - Conversa aberta (não significa mensagem enviada)
 * @param {string} [p.nome] - Nome do aluno, para leitores de tela
 */
export function WhatsappContato({ telefone, mensagem, variant = 'inline', bloqueado, onAbrir, nome, className }) {
  const tel = normalizarTelefoneWhatsapp(telefone);
  const texto = exibicao(telefone, tel);
  const motivoBloqueio = bloqueado ? (typeof bloqueado === 'string' ? bloqueado : 'Aluno pediu para não receber mensagens.') : '';
  const disabled = tel.motivo === 'vazio' || !!motivoBloqueio;
  const invalido = !tel.ok && tel.motivo === 'invalido';
  const tip = motivoBloqueio || (tel.motivo === 'vazio' ? 'Aluno sem telefone cadastrado' : invalido ? `Telefone inválido. ${ORIENTACAO_TELEFONE}` : TIP);
  const label = `${TIP}${nome ? ` com ${nome}` : ''}`;

  const abrir = (event) => {
    event.stopPropagation();
    if (disabled) return;
    const r = abrirConversaWhatsapp(telefone, mensagem);
    if (!r.ok) { showWarning(r.mensagem); return; }
    if (onAbrir) onAbrir({ url: r.url, numero: tel.numero });
  };

  const cls = ['ck-wa', `ck-wa--${variant}`, invalido && 'is-invalid', disabled && 'is-disabled', className].filter(Boolean).join(' ');
  const icone = <Icon name={invalido ? 'alert-triangle' : 'whatsapp'} glyph={!invalido} size={variant === 'button' ? 18 : 16} className="ck-wa__icon" />;

  if (variant === 'inline' && tel.motivo === 'vazio') return <span className="ck-wa ck-wa--inline is-empty">Sem telefone</span>;

  return <button type="button" className={cls} onClick={abrir} disabled={disabled} title={tip}
    aria-label={variant === 'icon' ? label : variant === 'inline' ? `${label}: ${texto}` : undefined}>
    {variant === 'inline' ? <><span className="ck-wa__num">{texto}</span>{icone}</> : null}
    {variant === 'button' ? <>{icone}<span>Conversar no WhatsApp</span></> : null}
    {variant === 'icon' ? icone : null}
  </button>;
}

export default WhatsappContato;
