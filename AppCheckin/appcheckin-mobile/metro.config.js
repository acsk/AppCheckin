/**
 * Metro Config - Carrega polyfill antes de tudo
 */

// Aplicar polyfill globalmente antes do metro inicializar
require('./polyfill-toReversed');

const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);

module.exports = config;
