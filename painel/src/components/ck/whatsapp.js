/* Contato manual pelo WhatsApp (Prospecção → Recuperação de Alunos e afins).
   Estilos: src/theme/ck-whatsapp.css. Regras de telefone em ../../utils/whatsapp.
   Permite escolher entre navegador e aplicativo; nunca envia mensagem nem a registra como enviada. */
import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { Button, Icon, Modal } from './index';
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
  const [escolhendo, setEscolhendo] = useState(false);
  const tel = normalizarTelefoneWhatsapp(telefone);
  const texto = exibicao(telefone, tel);
  const motivoBloqueio = bloqueado ? (typeof bloqueado === 'string' ? bloqueado : 'Aluno pediu para não receber mensagens.') : '';
  const disabled = tel.motivo === 'vazio' || !!motivoBloqueio;
  const invalido = !tel.ok && tel.motivo === 'invalido';
  const tip = motivoBloqueio || (tel.motivo === 'vazio' ? 'Aluno sem telefone cadastrado' : invalido ? `Telefone inválido. ${ORIENTACAO_TELEFONE}` : TIP);
  const label = `${TIP}${nome ? ` com ${nome}` : ''}`;

  const escolher = (event) => {
    event.stopPropagation();
    if (disabled) return;
    if (!tel.ok) { showWarning(tel.mensagem); return; }
    setEscolhendo(true);
  };
  const abrir = (destino) => {
    if (disabled) return;
    const r = abrirConversaWhatsapp(telefone, mensagem, destino);
    if (!r.ok) { showWarning(r.mensagem); return; }
    setEscolhendo(false);
    if (onAbrir) onAbrir({ url: r.url, numero: tel.numero, destino });
  };

  const cls = ['ck-wa', `ck-wa--${variant}`, invalido && 'is-invalid', disabled && 'is-disabled', className].filter(Boolean).join(' ');
  const icone = <Icon name={invalido ? 'alert-triangle' : 'whatsapp'} glyph={!invalido} size={variant === 'button' ? 18 : 16} className="ck-wa__icon" />;

  if (variant === 'inline' && tel.motivo === 'vazio') return <span className="ck-wa ck-wa--inline is-empty">Sem telefone</span>;

  return <><button type="button" className={cls} onClick={escolher} disabled={disabled} title={tip} aria-haspopup="dialog" aria-expanded={escolhendo}
    aria-label={variant === 'icon' ? label : variant === 'inline' ? `${label}: ${texto}` : undefined}>
    {variant === 'inline' ? <><span className="ck-wa__num">{texto}</span>{icone}</> : null}
    {variant === 'button' ? <>{icone}<span>Conversar no WhatsApp</span></> : null}
    {variant === 'icon' ? icone : null}
  </button>
    {escolhendo && typeof document !== 'undefined' ? createPortal(<div onClick={(event) => event.stopPropagation()}>
      <Modal open onClose={() => setEscolhendo(false)} title="Onde abrir o WhatsApp?" size="sm" autoFocus={false}
        description={`${nome ? `${nome} · ` : ''}${texto}`} footer={<Button variant="ghost" onClick={() => setEscolhendo(false)}>Cancelar</Button>}>
        <div className="ck-wa-destinos">
          <button type="button" className="ck-wa-destino" onClick={() => abrir('web')} disabled={disabled}>
            <Icon name="external-link" size={20} /><span><strong>WhatsApp Web</strong><small>Abrir no navegador.</small></span><Icon name="chevron-right" size={16} />
          </button>
          <button type="button" className="ck-wa-destino" onClick={() => abrir('app')} disabled={disabled}>
            <Icon name="phone" size={20} /><span><strong>Aplicativo do WhatsApp</strong><small>Abrir o aplicativo instalado neste dispositivo.</small></span><Icon name="chevron-right" size={16} />
          </button>
        </div>
      </Modal>
    </div>, document.body) : null}
  </>;
}

export default WhatsappContato;
