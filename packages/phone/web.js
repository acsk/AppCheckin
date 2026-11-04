import React, { useEffect, useId, useRef, useState } from 'react';
import { changePhoneCountry, countries, DEFAULT_COUNTRY, parsePhone, phoneEditor, PHONE_ERROR, validPhone } from './index.js';

const h = React.createElement;

export default function PhoneField({ value = '', onChange, label, error, hint, labelAside, disabled, readOnly, required, id, className, onBlur, ...props }) {
  const uid = useId();
  const inputId = id || uid;
  const initial = parsePhone(value);
  const [country, setCountry] = useState(initial?.country ?? DEFAULT_COUNTRY);
  const [display, setDisplay] = useState(initial ? initial.formatNational() : String(value ?? ''));
  const [touched, setTouched] = useState(false);
  const lastEmitted = useRef(value);
  useEffect(() => {
    if (value === lastEmitted.current) return;
    const phone = parsePhone(value);
    if (phone?.country) setCountry(phone.country);
    setDisplay(phone ? phone.formatNational() : String(value ?? ''));
    lastEmitted.current = value;
  }, [value]);
  function emit(next) {
    lastEmitted.current = next;
    onChange?.({ target: { value: next }, currentTarget: { value: next } });
  }
  const message = error || (touched && value && !validPhone(value) ? PHONE_ERROR : null);
  return h('div', { className: `ck-field ${className || ''}` },
    label ? h('label', { className: 'ck-field__label', htmlFor: inputId }, label, labelAside ? h('span', null, ' · ', labelAside) : null, required ? ' *' : '') : null,
    h('div', { style: { display: 'flex', gap: 8, minWidth: 0 } },
      h('select', {
        'aria-label': `País de ${label || 'telefone'}`, disabled: disabled || readOnly,
        value: country, className: 'ck-control__input',
        style: { width: '42%', minWidth: 100, border: '1px solid #cbd5e1', borderRadius: 8, padding: 8, color: 'inherit', background: 'transparent' },
        onChange: (event) => {
          const next = event.target.value;
          setCountry(next);
          const state = changePhoneCountry(display, next);
          setDisplay(state.display);
          emit(state.value);
        },
      }, countries.map((item) => h('option', { key: item.country, value: item.country }, item.label))),
      h('input', {
        id: inputId, type: 'tel', inputMode: 'tel', autoComplete: 'tel-national', disabled, readOnly, required,
        className: 'ck-control__input', style: { minWidth: 0, flex: 1, border: '1px solid #cbd5e1', borderRadius: 8, padding: 10, color: 'inherit', background: 'transparent' },
        value: display, 'aria-invalid': !!message, 'aria-describedby': message || hint ? `${inputId}-hint` : undefined,
        placeholder: props.placeholder?.startsWith('(') ? 'Número de telefone' : props.placeholder || 'Número de telefone',
        onChange: (event) => {
          const state = phoneEditor(event.target.value, country);
          setCountry(state.country);
          setDisplay(state.display);
          emit(state.value);
        },
        onBlur: (event) => { setTouched(true); onBlur?.(event); },
      })),
    message || hint ? h('span', { id: `${inputId}-hint`, role: message ? 'alert' : undefined, style: { fontSize: 12, color: message ? '#b91c1c' : 'inherit' } }, message || hint) : null);
}
