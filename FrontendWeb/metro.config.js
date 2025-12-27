// Polyfill DEVE ser a primeira coisa executada
require('./polyfill-toReversed');

// Agora sim, importar o Expo
const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);

module.exports = config;
