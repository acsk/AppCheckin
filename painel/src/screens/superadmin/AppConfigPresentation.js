import React, { useEffect, useId, useState } from 'react';
import { Icon } from '../../components/ck';

const MODULO_ICONES = { Aulas: 'calendar', Planos: 'layers', Financeiro: 'wallet', Início: 'layout-dashboard', Treino: 'trophy', Menu: 'users', Sistema: 'settings' };

export function ConfigSection({ title, description, icon, children }) {
  return (
    <section className="ck-card ck-pm-sec">
      <header className="ck-pm-sechead">
        <span className="ck-pm-secicon" aria-hidden="true"><Icon name={icon || MODULO_ICONES[title] || 'settings'} size={18} /></span>
        <div><h2>{title}</h2>{description ? <p>{description}</p> : null}</div>
      </header>
      {children}
    </section>
  );
}

export function ExcecoesButton({ def, estado, onClick }) {
  const total = Object.values(estado.rascunho.tenants || {}).filter((valores) => valores[def.chave] !== undefined).length;
  return (
    <button type="button" className={`ck-cf-ovrbtn${total ? ' is-set' : ''}`} onClick={onClick} aria-label={`${def.nome}: exceções por academia`}>
      <Icon name="building-2" size={15} />
      {total ? `${total} exceç${total === 1 ? 'ão' : 'ões'}` : 'Por academia'}
    </button>
  );
}

export function ExcecoesNote({ def, estado, academias }) {
  const entradas = Object.entries(estado.rascunho.tenants || {}).filter(([, valores]) => valores[def.chave] !== undefined);
  if (!entradas.length) return null;
  return (
    <span className="ck-cf-ovrnote">
      <Icon name="building-2" size={13} />
      <span>{entradas.map(([id, valores]) => {
        const nome = academias.find((a) => String(a.id) === id)?.nome || estado.academias?.[id] || `Academia ${id}`;
        const valor = valores[def.chave];
        return `${nome}: ${def.tipo === 'booleano' ? (valor ? 'ligado' : 'desligado') : valor === '' ? 'vazio' : valor}`;
      }).join(', ')}</span>
    </span>
  );
}

// A edição continua salvando no rascunho ao sair do campo, como no editor original.
export function ConfigValueInput({ def, valor, onSalvar, unit, multiline = false }) {
  const id = useId();
  const [texto, setTexto] = useState(String(valor ?? ''));
  const [erro, setErro] = useState('');
  useEffect(() => { setTexto(String(valor ?? '')); setErro(''); }, [valor]);
  const numero = def.tipo === 'inteiro';
  const validar = (v) => numero && (v === '' || Number(v) < def.min || Number(v) > def.max)
    ? `Use um valor entre ${def.min} e ${def.max}.` : '';
  const salvar = async () => {
    const mensagem = validar(texto);
    setErro(mensagem);
    if (mensagem || texto === String(valor ?? '')) return;
    try { await onSalvar(numero ? Number(texto) : texto); } catch (e) { setErro(e.message); }
  };
  const props = {
    id, value: texto, 'aria-label': def.nome, 'aria-invalid': !!erro,
    'aria-describedby': erro || multiline ? `${id}-hint` : undefined,
    onChange: (e) => { setTexto(numero ? e.target.value.replace(/\D/g, '').slice(0, 6) : e.target.value); setErro(''); },
    onBlur: salvar,
  };
  return (
    <div className={multiline ? 'ck-cf-text' : 'ck-pm-num'}>
      {multiline ? <textarea {...props} className="ck-cf-ta" rows={2} maxLength={def.max} placeholder="Escreva a mensagem que aparecerá no app" />
        : <div className={`ck-control ck-pm-num__ctl${erro ? ' is-invalid' : ''}`}>
          <input {...props} className="ck-control__input" inputMode={numero ? 'numeric' : undefined}
            onKeyDown={(e) => { if (e.key === 'Enter') e.currentTarget.blur(); }} />
          {unit ? <span className="ck-pm-num__unit">{unit}</span> : null}
        </div>}
      {erro ? <span id={`${id}-hint`} className="ck-pm-err" role="alert">{erro}</span>
        : multiline ? <span id={`${id}-hint`} className="ck-pm-row__desc">{texto.length}/{def.max}{!texto ? '. Vazio: sem aviso.' : ''}</span> : null}
      {multiline && texto ? <div className="ck-cf-banner"><Icon name="info" size={14} /><span>{texto}</span></div> : null}
    </div>
  );
}
