# Arquitetura Compartilhada - Web e Mobile

## Visão Geral

Este projeto usa uma arquitetura compartilhada onde componentes, serviços e hooks são reutilizados entre:
- **FrontendWeb** (React Web)
- **AppCheckin/appcheckin-mobile** (React Native)

## Estrutura de Pastas

```
AppCheckin/
├── shared/                 # Código compartilhado entre Web e Mobile
│   ├── services/
│   │   └── authService.js  # Serviço de autenticação universal
│   └── hooks/
│       └── usePerfil.js    # Hook para gerenciar dados de perfil
│
├── FrontendWeb/            # Versão Web
│   └── src/
│       ├── screens/
│       │   └── Perfil/
│       │       ├── Perfil.js     # Componente de Perfil (Web)
│       │       └── Perfil.css    # Estilos Web
│       └── App.js
│
└── AppCheckin/
    └── appcheckin-mobile/  # Versão Mobile (React Native)
        └── src/
            ├── screens/
            │   └── MinhaConta/
            │       └── index.js  # Componente de MinhaConta (Mobile)
            └── App.js
```

## Serviços Compartilhados

### authService

Gerencia autenticação com suporte a diferentes tipos de storage:

```javascript
// Inicializar no Web
import authService from '../../shared/services/authService';
authService.setStorage(localStorage);

// Inicializar no Mobile
import authService from '../../shared/services/authService';
import AsyncStorage from '@react-native-async-storage/async-storage';
authService.setStorage(AsyncStorage);

// Usar
const { token, user } = await authService.login(email, senha, baseUrl);
await authService.logout();
```

### usePerfil Hook

Hook compartilhado para gerenciar dados de perfil:

```javascript
import usePerfil from '../../hooks/usePerfil';

function Perfil() {
  const {
    usuario,
    carregando,
    editando,
    dadosEditados,
    setEditando,
    setDadosEditados,
    salvarPerfil,
    logout,
  } = usePerfil(baseUrl);

  // ... usar no componente
}
```

## Fluxo de Desenvolvimento

### 1️⃣ Desenvolver na Web (Mais Fácil)

```bash
cd FrontendWeb
npm install
npm start
```

**Vantagens:**
- F12 para debug
- HMR (Hot Module Reload)
- Console JavaScript
- Ferramentas de browser mais poderosas

### 2️⃣ Testar no Mobile

Após validar na web:

```bash
cd AppCheckin/appcheckin-mobile
npm install
npm start
```

## Componentes

### Tela de Perfil Web (`FrontendWeb/src/screens/Perfil/Perfil.js`)

```javascript
<Perfil 
  baseUrl="http://localhost:8080"
  onLogout={() => { /* redirecionar para login */ }}
/>
```

**Features:**
- ✅ Carregar dados completos do usuário
- ✅ Editar perfil
- ✅ Logout com confirmação
- ✅ Indicadores visuais de carregamento
- ✅ Mensagens de erro
- ✅ Dados em cache (localStorage)

### Tela de Perfil Mobile (Em desenvolvimento)

Será criada seguindo o mesmo padrão, mas usando React Native em vez de React Web.

## Fluxo de Autenticação

```
1. Login
   └─> authService.login(email, senha)
       └─> Salva token + usuário no storage
       └─> Retorna { token, user, tenants }

2. Carregar Perfil
   └─> usePerfil() hook
       └─> authService.fetchCompleteUser()
           └─> Faz request GET /me com token
           └─> Salva dados completos no storage

3. Editar Perfil
   └─> salvarPerfil()
       └─> authService.updateProfile()
           └─> Faz request PUT /me com dados
           └─> Atualiza storage

4. Logout
   └─> logout()
       └─> authService.logout()
           └─> Limpa storage
           └─> Redireciona para login
```

## Armazenamento

### Web (localStorage)
```javascript
localStorage.setItem('@appcheckin:token', 'token-value')
localStorage.setItem('@appcheckin:user', JSON.stringify(userData))
```

### Mobile (AsyncStorage)
```javascript
await AsyncStorage.setItem('@appcheckin:token', 'token-value')
await AsyncStorage.setItem('@appcheckin:user', JSON.stringify(userData))
```

## Variáveis de Ambiente

### Web (.env)
```
REACT_APP_API_BASE_URL=http://localhost:8080
```

### Mobile (eas.json ou .env)
```
EXPO_PUBLIC_API_BASE_URL=http://localhost:8080
```

## Debug

### No Browser Web (F12)
```javascript
// Console
authService.getToken()
authService.getUser()
localStorage.getItem('@appcheckin:token')
```

### No Simulator Mobile
```javascript
// Console
adb logcat | grep -i "AppCheckin"
// ou no código
console.log('Debug:', usuario)
```

## Próximos Passos

1. ✅ Criar `shared/services/authService.js` ← Done
2. ✅ Criar `shared/hooks/usePerfil.js` ← Done
3. ✅ Criar tela Web de Perfil ← Done
4. 🔄 Adaptar tela Mobile usando mesmos hooks
5. 🔄 Criar tela de Login compartilhada
6. 🔄 Criar tela de Dashboard compartilhada

## Testes

### Teste Manual Web

1. Abrir http://localhost:3000
2. Entrar em Perfil
3. Clique em 🔄 para recarregar
4. Clique em ✏️ para editar
5. Modifique algum campo
6. Clique em "Salvar"
7. Clique em "Sair da Conta"

### Teste Manual Mobile

Mesmo fluxo, mas no app Expo.

## Troubleshooting

### "Storage não configurado"
Verifique se você chamou `authService.setStorage()` no início do app.

### "Dados não aparecem"
1. Abra o DevTools (Web) ou Console (Mobile)
2. Procure pelos logs: `✅ Dados do usuário carregados`
3. Se não aparecer, clique em 🔄

### "Token inválido (401)"
1. Verifique se o backend está rodando
2. Faça login novamente
3. Procure no storage: `localStorage.getItem('@appcheckin:token')`

