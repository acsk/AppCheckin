// Polyfill para Node 18 - Array ES2023 methods
// Este arquivo deve ser carregado ANTES de qualquer outro código

if (!Array.prototype.toReversed) {
  Array.prototype.toReversed = function() {
    return [...this].reverse();
  };
}

if (!Array.prototype.toSorted) {
  Array.prototype.toSorted = function(compareFn) {
    return [...this].sort(compareFn);
  };
}

if (!Array.prototype.toSpliced) {
  Array.prototype.toSpliced = function(start, deleteCount, ...items) {
    const copy = [...this];
    copy.splice(start, deleteCount, ...items);
    return copy;
  };
}

if (!Array.prototype.with) {
  Array.prototype.with = function(index, value) {
    const copy = [...this];
    copy[index] = value;
    return copy;
  };
}

// Polyfill para URL.canParse (Node 18.17+)
if (!URL.canParse) {
  URL.canParse = function(url, base) {
    try {
      new URL(url, base);
      return true;
    } catch {
      return false;
    }
  };
}

console.log('✅ Polyfill Node 18 carregado');
