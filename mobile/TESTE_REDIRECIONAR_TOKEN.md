# Teste de Redirecionamento Automático de Token

## O que foi implementado

Implementei uma estratégia **em camadas** para forçar redirecionamento automático quando o token está ausente:

### 1. **Guard Global no Root Layout** (`app/_layout.tsx`)

- O layout raiz agora verifica o token ANTES de renderizar rotas
- Se alguém tenta acessar uma rota protegida sem token, é redirecionado para `/login` imediatamente
- Usa `useSegments()` para interceptar mudanças de navegação

### 2. **Callbacks de Token Missing** (`src/services/api.js` e `src/api/client.ts`)

- Quando uma API detecta TOKEN_MISSING, chama `onUnauthorizedCallback()`
- Este callback foi registrado no \_layout.tsx para fazer `router.replace("/(auth)/login")`
- Agora com logs detalhados para debug

### 3. **Hook Customizado** (`hooks/useProtectedRoute.ts`)

- Cada rota protegida pode usar `useProtectedRoute()` para:
  - Verificar token ao montar
  - Executar lógica de autorização customizada
  - Redirecionar automaticamente se falhar
- Usado em `planos.tsx` para verificar se usuário é admin

### 4. **Estrutura de Rotas**

```
PUBLIC_ROUTES = ["(auth)", "index"]
PROTECTED_ROUTES = ["(tabs)", "planos", "plano-detalhes", ...]
```

## Como Testar

### Cenário 1: Remover token e tentar acessar rota protegida

```bash
# Via script shell para simular logout
./TEST_LOGOUT.sh

# Agora tente:
# 1. Navegar para /planos
# 2. Navegar para /(tabs)
# 3. Qualquer outra rota protegida

# Resultado esperado: Redirecionamento automático para /(auth)/login
```

### Cenário 2: Token expira durante sessão

```bash
# 1. Login normalmente
# 2. Abra DevTools/Debugger
# 3. Execute:
await AsyncStorage.removeItem('@appcheckin:token')

# 4. Tente fazer qualquer ação que chame API em rota protegida
# Resultado esperado: Callback acionado -> redirecionamento para login
```

### Cenário 3: Deep linking

```bash
# Tente abrir deep link de rota protegida sem token:
expo://app/planos
# ou
exp://192.168.x.x:8081/--/planos

# Resultado esperado: Redirecionamento para login
```

## Logs para Debug

O código agora imprime logs detalhados em vários pontos:

### No \_layout.tsx:

```
[RootLayout] Verificando autenticação... Segments: ...
[RootLayout] ❌ Acesso negado à rota protegida: planos - redirecionando para login
[RootLayout:setOnUnauthorized] Token inválido, redirecionando...
[RootLayout:setOnUnauthorized] Executando router.replace
```

### No src/api/client.ts:

```
[Axios] ⚠️ TOKEN_MISSING detectado para rota protegida: /api/...
[Axios] Chamando onUnauthorizedCallback...
```

### No src/services/api.js:

```
[API] Chamando onUnauthorizedCallback...
```

### No hooks/useProtectedRoute.ts:

```
[useProtectedRoute] Token não encontrado, redirecionando
[useProtectedRoute] Usuário autorizado
```

## Checklist de Funcionamento

- [ ] Sem token na app: usuário vai para /(auth)/login ao abrir
- [ ] Com token, acesso a /planos: funciona se for admin
- [ ] Sem token, tenta /planos: redireciona para login
- [ ] Token é deletado manualmente: próxima ação de API redireciona
- [ ] Logout remove token: próxima navegação redireciona
- [ ] Deep link para rota protegida: verifica token antes de renderizar
- [ ] Admin consegue ver planos: permissão verificada via `papel_id`
- [ ] Não-admin tenta /planos: será testado quando página retornar false em checkFn

## Próximos Passos Possíveis

1. **Bloquear UI não-admin em planos**: Adicionar verificação de permissão que mostra mensagem de acesso negado em vez de apenas deixar empty
2. **Persiste redirecionamento**: Garantir que se router.replace falhar, tenta novamente
3. **Timeout de sessão**: Adicionar verificação de expiração de token no clock app
4. **Refresh token**: Implementar refresh automático quando token está próximo de expirar

## Arquivos Modificados

- `app/_layout.tsx` - Guard global de autenticação
- `src/services/api.js` - Logs e callback de token missing
- `src/api/client.ts` - Logs e callback de token missing
- `app/planos.tsx` - Uso de useProtectedRoute
- `hooks/useProtectedRoute.ts` (novo) - Hook customizado
- `hooks/useNavigationGuard.ts` (novo) - Guard de navegação
- `components/AuthGuard.tsx` (novo) - Componente wrapper (opcional)
