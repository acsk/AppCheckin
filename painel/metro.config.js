// Polyfill DEVE ser a primeira coisa executada
require('./polyfill-toReversed');

// Agora sim, importar o Expo
const { getDefaultConfig } = require('expo/metro-config');
const { withNativeWind } = require('nativewind/metro');

const config = getDefaultConfig(__dirname);
config.watchFolders = [require('path').resolve(__dirname, '../packages')];
config.resolver.nodeModulesPaths = [require('path').resolve(__dirname, 'node_modules')];
config.resolver.resolveRequest = (context, moduleName, platform) => {
  if (moduleName === 'react' || moduleName.startsWith('react/')) {
    return { type: 'sourceFile', filePath: require.resolve(moduleName, { paths: [__dirname] }) };
  }
  return context.resolveRequest(context, moduleName, platform);
};

module.exports = withNativeWind(config, { input: './global.css' });
