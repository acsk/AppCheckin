import { AsYouType, getCountries, getCountryCallingCode, parsePhoneNumberFromString } from 'libphonenumber-js/max';
import countryNames from './country-names.js';

export const DEFAULT_COUNTRY = 'BR';
export const PHONE_ERROR = 'Telefone inválido. Selecione o país e informe o número completo.';

export function phoneInput(value, country = DEFAULT_COUNTRY) {
  const raw = String(value ?? '').trim().replace(/^0xx(?=[\d\s().-]+$)/i, '');
  if (raw.startsWith('00')) return `+${raw.slice(2)}`;
  const digits = raw.replace(/\D/g, '');
  // Older Brazilian clients also stored the full DDI without a plus.
  return country === 'BR' && /^[\d\s().-]+$/.test(raw) && /^55\d{10,11}$/.test(digits) ? `+${digits}` : raw;
}

export function parsePhone(value, country = DEFAULT_COUNTRY) {
  const raw = phoneInput(value, country);
  if (!raw || !/^[+\d\s().-]+$/.test(raw)) return undefined;
  const phone = parsePhoneNumberFromString(raw, { defaultCountry: country, extract: false });
  return phone?.isValid() && !phone.ext ? phone : undefined;
}

export function normalizePhone(value, country = DEFAULT_COUNTRY) {
  return parsePhone(value, country)?.number ?? null;
}

export function validPhone(value, country = DEFAULT_COUNTRY) {
  return normalizePhone(value, country) !== null;
}

export function formatPhone(value) {
  const phone = parsePhone(value);
  return phone ? phone.formatInternational() : String(value ?? '');
}

export function phoneForApi(value, original) {
  if (original !== undefined && value === original) return undefined;
  if (!String(value ?? '').trim()) return null;
  const normalized = normalizePhone(value);
  return normalized ?? value;
}

export function editablePhoneValid(value, original) {
  return !value || validPhone(value) || (original !== undefined && value === original);
}

export function countryFlag(country) {
  return String.fromCodePoint(...country.split('').map((letter) => 127397 + letter.charCodeAt(0)));
}

export const countries = getCountries().map((country) => ({
  country,
  callingCode: getCountryCallingCode(country),
  label: `${countryFlag(country)} ${countryNames[country]} (+${getCountryCallingCode(country)})`,
})).sort((a, b) => a.country === DEFAULT_COUNTRY ? -1 : b.country === DEFAULT_COUNTRY ? 1 : a.label.localeCompare(b.label, 'pt-BR'));

export function phoneEditor(value, country = DEFAULT_COUNTRY) {
  const raw = phoneInput(value, country);
  if (raw && !/^[+\d\s().-]+$/.test(raw)) return { country, display: raw, value: raw };
  const formatter = new AsYouType(country);
  const formatted = formatter.input(raw);
  const detected = raw.startsWith('+') ? formatter.getCountry() : undefined;
  const parsed = formatter.getNumber();
  const callingCode = formatter.getCallingCode();
  const possible = raw.startsWith('+') && callingCode
    ? countries.filter((item) => item.callingCode === callingCode) : [];
  const selected = !raw.startsWith('+') ? country : detected
    ?? (possible.some((item) => item.country === country) ? country : possible.length === 1 ? possible[0].country : callingCode === '1' ? 'US' : country);
  const national = parsed?.isValid() ? parsed.formatNational() : formatted;
  return {
    country: selected,
    display: national,
    value: parsed?.number ?? (raw.startsWith('+') ? raw.replace(/[^\d+]/g, '') : raw ? `+${getCountryCallingCode(selected)}${raw.replace(/\D/g, '')}` : ''),
  };
}

export function nationalPhone(value, country) {
  const parsed = parsePhone(value);
  if (parsed) return parsed.formatNational();
  return phoneEditor(value, country).display;
}

export function changePhoneCountry(value, country) {
  const raw = phoneInput(value, country);
  if (!raw.startsWith('+')) return phoneEditor(raw, country);
  const formatter = new AsYouType();
  formatter.input(raw);
  return phoneEditor(formatter.getNumber()?.nationalNumber ?? '', country);
}

export function whatsappUrl(value, text = '') {
  const number = normalizePhone(value);
  if (!number) return null;
  return `https://wa.me/${number.slice(1)}${text.trim() ? `?text=${encodeURIComponent(text.trim())}` : ''}`;
}
