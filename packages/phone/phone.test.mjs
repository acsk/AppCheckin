import test from 'node:test';
import assert from 'node:assert/strict';
import { changePhoneCountry, countries, editablePhoneValid, formatPhone, normalizePhone, phoneEditor, phoneForApi, validPhone, whatsappUrl } from './index.js';

const cases = [
  ['(82) 9 9999-9999', 'BR', '+5582999999999'],
  ['+49 162 3329708', 'DE', '+491623329708'],
  ['0162 3329708', 'DE', '+491623329708'],
  ['(415) 555-2671', 'US', '+14155552671'],
  ['912 345 678', 'PT', '+351912345678'],
  ['0055 82 99999-9999', 'BR', '+5582999999999'],
  ['5582999999999', 'BR', '+5582999999999'],
  ['(55) 99999-8888', 'BR', '+5555999998888'],
];
for (const [value, country, expected] of cases) {
  test(`normalizes ${country}: ${value}`, () => {
    assert.equal(normalizePhone(value, country), expected);
    assert.equal(normalizePhone(expected), expected);
  });
}
test('invalid inputs never become valid through truncation or extraction', () => {
  for (const value of ['abc', '+49 12', '123', '(20) 99999-9999', '(82) 99999-999999', '+999123456789', '+49 162 3329708 ramal 2', 'call +49 162 3329708']) {
    assert.equal(validPhone(value), false, value);
  }
});
test('paste detects country and emits full E.164', () => {
  for (const [value, country, expected] of cases.filter(([value]) => value.startsWith('+'))) {
    const state = phoneEditor(value);
    assert.equal(state.country, country);
    assert.equal(state.value, expected);
  }
  assert.equal(phoneEditor('+351 912 345 678').country, 'PT');
  assert.equal(phoneEditor('+1 415 555 2671').country, 'US');
  assert.equal(phoneEditor('5582999999999').value, '+5582999999999');
  assert.equal(phoneEditor('(55) 99999-8888').value, '+5555999998888');
  assert.equal(phoneEditor('+4').value, '+4');
  assert.equal(phoneEditor('+').value, '+');
  assert.equal(phoneEditor('').value, '');
});
test('country selection formats national input without fixing length to Brazil', () => {
  assert.deepEqual(phoneEditor('82999999999'), { country: 'BR', display: '(82) 99999-9999', value: '+5582999999999' });
  assert.equal(phoneEditor('01623329708', 'DE').display, '0162 3329708');
  assert.equal(phoneEditor('912345678', 'PT').value, '+351912345678');
  assert.equal(phoneEditor('4155552671', 'US').value, '+14155552671');
  for (const code of ['BR', 'DE', 'US', 'PT']) assert.ok(countries.find((item) => item.country === code)?.label.includes('+'));
});
test('switching country preserves national digits from incomplete international input', () => {
  assert.equal(changePhoneCountry('+49 162', 'PT').value, '+351162');
  assert.equal(changePhoneCountry('+4', 'DE').value, '');
  assert.equal(changePhoneCountry('912345678', 'PT').value, '+351912345678');
});
test('shared calling codes preserve the selected country until detection is possible', () => {
  assert.equal(phoneEditor('4165552671', 'CA').country, 'CA');
  assert.equal(phoneEditor('+1', 'CA').country, 'CA');
  assert.equal(phoneEditor('+1', 'BR').country, 'US');
  assert.equal(phoneEditor('+1 416 555 2671', 'BR').country, 'CA');
});
test('all supported countries have bundled names on native runtimes', async () => {
  const original = Intl.DisplayNames;
  try {
    Intl.DisplayNames = undefined;
    const native = await import('./index.js?native');
    assert.ok(native.countries.every((item) => !item.label.includes('undefined')));
    assert.ok(native.countries.find((item) => item.country === 'DE').label.includes('Alemanha'));
  } finally {
    Intl.DisplayNames = original;
  }
});
test('unchanged legacy values are neither lost nor silently assigned a country', () => {
  assert.equal(formatPhone('unknown legacy'), 'unknown legacy');
  assert.equal(phoneForApi('unknown legacy', 'unknown legacy'), undefined);
  assert.equal(phoneForApi('82999999999', '82999999999'), undefined);
  assert.equal(editablePhoneValid('unknown legacy', 'unknown legacy'), true);
  assert.equal(editablePhoneValid('bad edit', 'unknown legacy'), false);
  assert.equal(phoneForApi('', '82999999999'), null);
});
test('duplicates normalize to the same identity and WhatsApp preserves DDI', () => {
  assert.equal(normalizePhone('(82) 99999-9999'), normalizePhone('+55 82 99999 9999'));
  assert.notEqual(normalizePhone('+49 162 3329708'), normalizePhone('+351 912 345 678'));
  assert.equal(whatsappUrl('+49 162 3329708', 'Olá & até já'), 'https://wa.me/491623329708?text=Ol%C3%A1%20%26%20at%C3%A9%20j%C3%A1');
  assert.equal(whatsappUrl('invalid'), null);
});
