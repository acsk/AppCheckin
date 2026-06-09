const appJson = require('./app.json');

/**
 * `web.output: "static"` quebra `expo start --web`: o dev server não injeta o
 * bundle JS no index.html (tela branca). Usamos "single" no dev e "static" só no export.
 */
module.exports = ({ config }) => ({
  ...appJson.expo,
  ...config,
  web: {
    ...appJson.expo.web,
    output: process.env.EXPO_STATIC_EXPORT === '1' ? 'static' : 'single',
  },
});
