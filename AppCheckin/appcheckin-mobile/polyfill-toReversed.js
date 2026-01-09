/**
 * Polyfill para Array.prototype.toReversed() e toSorted()
 * Necessário para Node.js < 20.6.0
 * 
 * Estes polyfills implementam métodos ES2023 que retornam novas arrays
 * sem modificar a original.
 */

if (!Array.prototype.toReversed) {
  Array.prototype.toReversed = function() {
    return this.slice().reverse();
  };
}

if (!Array.prototype.toSorted) {
  Array.prototype.toSorted = function(compareFn) {
    return this.slice().sort(compareFn);
  };
}
