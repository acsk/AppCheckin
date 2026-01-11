const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);

// Configurar porta customizada (9091) para evitar conflitos com Docker
config.server = {
  ...config.server,
  port: 9091,
};

module.exports = config;
