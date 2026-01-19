// Polyfill mínimo para métodos de array ES2023 em runtime Node 18.
if (!Array.prototype.toReversed) {
  // retorna uma cópia invertida sem mutar o array original
  // eslint-disable-next-line no-extend-native
  Array.prototype.toReversed = function toReversed() {
    return [...this].reverse();
  };
}

if (!Array.prototype.toSorted) {
  // eslint-disable-next-line no-extend-native
  Array.prototype.toSorted = function toSorted(compareFn) {
    return [...this].sort(compareFn);
  };
}

if (!Array.prototype.toSpliced) {
  // eslint-disable-next-line no-extend-native
  Array.prototype.toSpliced = function toSpliced(start, deleteCount, ...items) {
    const copy = [...this];
    copy.splice(start, deleteCount, ...items);
    return copy;
  };
}

// Node 18 não possui URL.canParse; polyfill simples para Metro/Expo.
if (typeof URL !== 'undefined' && typeof URL.canParse !== 'function') {
  URL.canParse = function canParse(input, base) {
    try {
      // eslint-disable-next-line no-new
      new URL(input, base);
      return true;
    } catch {
      return false;
    }
  };
}
