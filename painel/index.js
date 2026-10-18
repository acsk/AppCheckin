// Polyfill DEVE ser a primeira coisa
import './polyfill-toReversed';
import './global.css';
// Design system do painel (Sidebar, AppHeader, telas de configuração): variáveis + componentes
import './src/theme/ck-tokens.css';
import './src/theme/ck.css';
import './src/theme/ck-enrollments.css';
import './src/theme/ck-painel.css';
import './src/theme/ck-planos.css';
import './src/theme/ck-troca-plano.css';
import './src/theme/ck-financeiro.css';

import 'expo-router/entry';
