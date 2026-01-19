// Polyfill DEVE ser a primeira coisa executada
require('./polyfill-toReversed');

// Agora sim, importar o Expo
const { getDefaultConfig } = require('expo/metro-config');
const { withNativeWind } = require('nativewind/metro');

const config = getDefaultConfig(__dirname);

module.exports = withNativeWind(config, { input: './global.css' });
