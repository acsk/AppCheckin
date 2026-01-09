# Expo Router + Drawer Navigation

## 📱 Nova Estrutura do Projeto

```
app/
├── _layout_root.js          # Layout raiz (autenticação)
├── login.js                 # Tela de login
└── (drawer)/
    ├── _layout.js           # Drawer layout principal
    └── (tabs)/
        ├── _layout.js       # Layout com abas
        ├── index.js         # Home
        ├── perfil.js        # Perfil
        ├── minha-conta.js   # Minha Conta
        └── planos.js        # Planos
```

## 🎯 Fluxo de Navegação

```
App.js (Expo Router)
    ↓
_layout_root.js (Stack com autenticação)
    ├─ login.js (Tela de login)
    └─ (drawer)/_layout.js (Drawer Navigator)
       └─ (tabs)/_layout.js (Tabs Navigator)
          ├─ index.js (Home)
          ├─ perfil.js
          ├─ minha-conta.js
          └─ planos.js
```

## ✨ Funcionalidades Implementadas

### 1. **Drawer Navigator (Navegação de Gaveta)**
- Menu deslizante com usuário no topo
- Seções organizadas: NAVEGAÇÃO e MINHA CONTA
- Logout com confirmação
- Avatar dinâmico baseado no usuário

### 2. **Tab Navigation (Abas)**
- 4 abas na parte inferior
- Início, Perfil, Minha Conta, Planos
- Ícones Feather Icons
- Cores personalizadas (#FF9A3D para ativo)

### 3. **Autenticação**
- Verificação automática de token
- Redirecionamento para login se sem token
- Redirecionamento para drawer se autenticado
- AsyncStorage para persistência

### 4. **Deep Linking**
- Suporte a navegação profunda
- URLs como: `appcheckin://home`, `appcheckin://perfil`
- Linking via Expo Linking

## 🔧 Dependências Adicionadas

```json
{
  "@react-navigation/drawer": "^6.8.7",
  "@react-navigation/native": "^6.1.18",
  "@react-navigation/native-stack": "^6.11.7",
  "expo-router": "^3.5.0",
  "react-native-gesture-handler": "^2.14.1",
  "react-native-reanimated": "^3.6.0"
}
```

## 🚀 Como Usar

### Instalação de Dependências
```bash
cd AppCheckin/appcheckin-mobile
npm install
```

### Executar o App
```bash
npm start
```

### Navegar com Código
```javascript
import { useRouter } from 'expo-router';

const router = useRouter();

// Navegar para perfil
router.push('/(drawer)/(tabs)/perfil');

// Voltar
router.back();

// Substituir rota (sem histórico)
router.replace('/(drawer)/(tabs)/home');
```

## 🎨 Customizações do Drawer

**Cores:**
- Background: `#1a1d24`
- Texto: `#e5e5e5`
- Ativo: `#FF9A3D`
- Ativo BG: `rgba(255,154,61,0.1)`

**Dimensões:**
- Largura: 280px
- Avatar: 50x50px
- Icons: 24px

## 📂 Estrutura de Grupos de Rota

- `(drawer)` - Grupo drawer (não mostra na URL)
- `(tabs)` - Grupo de abas (não mostra na URL)
- Permite organizar sem afetar a navegação

## 🔐 Fluxo de Autenticação

1. App inicia → Splash (1.8s)
2. Verifica token em AsyncStorage
3. Token existe? → Vai para (drawer)
4. Token não existe? → Vai para login
5. Login com sucesso → Salva token → Vai para (drawer)
6. Logout → Remove token → Vai para login

## 📖 Documentação Oficial

- [Expo Router](https://docs.expo.dev/router/introduction/)
- [Drawer Navigation](https://docs.expo.dev/router/advanced/drawer/)
- [React Navigation](https://reactnavigation.org/docs/drawer-navigation/)

## ✅ Próximos Passos

1. Testar navegação em Android/iOS
2. Adicionar mais telas conforme necessário
3. Implementar animações customizadas
4. Adicionar notificações/badges nas abas
5. Melhorar deep linking com dados dinâmicos

---

**Status:** ✅ Pronto para desenvolvimento!
