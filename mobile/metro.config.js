const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);
config.watchFolders = [require('path').resolve(__dirname, '../packages')];
config.resolver.nodeModulesPaths = [require('path').resolve(__dirname, 'node_modules')];
config.resolver.resolveRequest = (context, moduleName, platform) => {
  if (moduleName === 'react' || moduleName.startsWith('react/')) {
    return { type: 'sourceFile', filePath: require.resolve(moduleName, { paths: [__dirname] }) };
  }
  return context.resolveRequest(context, moduleName, platform);
};

// Configurar porta customizada (9091) para evitar conflitos com Docker
config.server = {
  ...config.server,
  port: 9091,
};

module.exports = config;
