import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../src/utils/whatsapp.js', import.meta.url), 'utf8');
const { normalizarTelefoneWhatsapp: norm, telefoneWhatsappValido, telefoneDoAluno, urlConversaWhatsapp, abrirConversaWhatsapp } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const numero = value => norm(value).numero;
test('remove máscara e adiciona +55 a celulares e fixos brasileiros', () => {
  for (const value of ['(82) 99999-9999', '82 99999 9999', '82999999999', '82.99999.9999', ' (82)99999-9999 ', 82999999999]) assert.equal(numero(value), '5582999999999');
  assert.equal(numero('(82) 3333-4444'), '558233334444');
  assert.equal(norm('(82) 99999-9999').nacional, '82999999999');
});
test('não duplica o código do país', () => {
  for (const value of ['+55 82 99999-9999', '5582999999999', '+5582999999999', '0055 82 99999-9999', '55 (82) 99999-9999']) assert.equal(numero(value), '5582999999999');
  assert.equal(numero('558233334444'), '558233334444');
});
test('DDD 55 (RS) não é confundido com código do país', () => {
  assert.equal(numero('(55) 99999-8888'), '5555999998888');
  assert.equal(numero('5555999998888'), '5555999998888');
  assert.equal(numero('(55) 3222-1111'), '555532221111');
});
test('remove zero de discagem e código de operadora', () => {
  assert.equal(numero('082 99999-9999'), '5582999999999');
  assert.equal(numero('0 15 82 99999-9999'), '5582999999999');
  assert.equal(numero('0xx21 98888-7777'), '5521988887777');
});
test('aceita número estrangeiro só com + explícito', () => {
  assert.deepEqual(norm('+1 (415) 555-2671'), { ok: true, numero: '14155552671', nacional: null });
  assert.equal(norm('+1 23').ok, false);
});
test('rejeita telefone vazio e formatos inválidos com orientação', () => {
  for (const value of [null, undefined, '', '   ', '()-']) assert.equal(norm(value).motivo, 'vazio');
  for (const value of ['9999-9999', '82 9999', '(20) 99999-9999', '(10) 3333-4444', '(82) 89999-9999', '(82) 1333-4444', '(82) 99999-99999', '(82) 99999-9999 ramal 2', '(82) 00000-0000', '(82) 99999-9999 / (82) 98888-7777', 'abc']) {
    const r = norm(value);
    assert.equal(r.ok, false, String(value)); assert.equal(r.motivo, 'invalido'); assert.match(r.mensagem, /DDD/);
  }
  assert.equal(telefoneWhatsappValido('(82) 99999-9999'), true); assert.equal(telefoneWhatsappValido('123'), false);
});
test('usa o WhatsApp do aluno antes do telefone geral', () => {
  assert.equal(telefoneDoAluno({ telefone: '8233334444', whatsapp: '82999999999' }), '82999999999');
  assert.equal(telefoneDoAluno({ telefone: '8233334444', whatsapp: null }), '8233334444');
  assert.equal(telefoneDoAluno(null), '');
});
test('monta a URL do WhatsApp Web com texto codificado opcional', () => {
  assert.equal(urlConversaWhatsapp('5582999999999'), 'https://web.whatsapp.com/send?phone=5582999999999');
  assert.equal(urlConversaWhatsapp('5582999999999', '  '), 'https://web.whatsapp.com/send?phone=5582999999999');
  const url = new URL(urlConversaWhatsapp('5582999999999', 'Olá & até já? 😊\nLinha 2'));
  assert.equal(url.searchParams.get('phone'), '5582999999999'); assert.equal(url.searchParams.get('text'), 'Olá & até já? 😊\nLinha 2');
});
test('abre em nova aba sem opener e não abre nada para telefone inválido', () => {
  const calls = []; globalThis.window = { open: (...args) => calls.push(args) };
  assert.deepEqual(abrirConversaWhatsapp('(82) 99999-9999', 'Oi'), { ok: true, url: 'https://web.whatsapp.com/send?phone=5582999999999&text=Oi' });
  assert.deepEqual(calls, [['https://web.whatsapp.com/send?phone=5582999999999&text=Oi', '_blank', 'noopener,noreferrer']]);
  assert.equal(abrirConversaWhatsapp('123').ok, false); assert.equal(abrirConversaWhatsapp('').ok, false);
  assert.equal(calls.length, 1);
  delete globalThis.window;
});

test('monta link do aplicativo preservando telefone e mensagem codificada', () => {
  assert.equal(urlConversaWhatsapp('5582999999999', '', 'app'), 'whatsapp://send?phone=5582999999999');
  const mensagem = 'Olá & até já? 😊\nLinha 2';
  const url = new URL(urlConversaWhatsapp('5582999999999', mensagem, 'app'));
  assert.equal(url.protocol, 'whatsapp:');
  assert.equal(url.searchParams.get('phone'), '5582999999999');
  assert.equal(url.searchParams.get('text'), mensagem);
});

test('a escolha aplicativo aciona o protocolo sem abrir o WhatsApp Web', () => {
  const apps = [], abas = [];
  globalThis.window = { location: { assign: (url) => apps.push(url) }, open: (...args) => abas.push(args) };
  try {
    const r = abrirConversaWhatsapp('(82) 99999-9999', 'Oi', 'app');
    assert.equal(r.ok, true);
    assert.deepEqual(apps, ['whatsapp://send?phone=5582999999999&text=Oi']);
    assert.equal(abas.length, 0);
    assert.equal(abrirConversaWhatsapp('123', 'Oi', 'app').ok, false);
    assert.equal(apps.length, 1);
  } finally { delete globalThis.window; }
});

test('a escolha web continua abrindo somente o navegador', () => {
  const apps = [], abas = [];
  globalThis.window = { location: { assign: (url) => apps.push(url) }, open: (...args) => abas.push(args) };
  try {
    abrirConversaWhatsapp('+1 (415) 555-2671', 'Hello', 'web');
    assert.equal(apps.length, 0);
    assert.deepEqual(abas, [['https://web.whatsapp.com/send?phone=14155552671&text=Hello', '_blank', 'noopener,noreferrer']]);
  } finally { delete globalThis.window; }
});
