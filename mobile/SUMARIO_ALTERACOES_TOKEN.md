# Sumário de Alterações - Redirecionamento Automático de Token

Data: 2024-02-20

## Problema Original

- Quando token era deletado/expirava, usuários conseguiam continuar navegando na app
- Redirecionamento não era automático, dependendo de chamadas à API
- Rotas protegidas não impediam acesso sem autenticação

## Solução Implementada

### 1. Guard Global no Root Layout

**Arquivo**: `app/_layout.tsx`

```tsx
// Novo: Verificação de autenticação em tempo real na raiz
useEffect(() => {
  const checkTokenAndGuard = async () => {
    const token = await AsyncStorage.getItem("@appcheckin:token");
    const currentSegment = segments?.[0];

    // Se tentando acessar rota protegida sem token -> redireciona
    if (PROTECTED_ROUTES.includes(currentSegment) && !token) {
      router.replace("/(auth)/login");
    }
  };
  checkTokenAndGuard();
}, [segments, router]);
```

**Lógica**:

- Monitora mudanças em `segments` (rota atual)
- Bloqueia acesso a rotas protegidas se não há token
- Funciona mesmo com deep linking ou navegação direta

---

### 2. Callbacks Globais para Token Missing

**Arquivos**:

- `src/services/api.js`
- `src/api/client.ts`

**Melhorias**:

- Agora chamam `onUnauthorizedCallback()` quando detectam TOKEN_MISSING
- Logs detalhados para debug
- Callback registrado no \_layout.tsx faz `router.replace("/(auth)/login")`

```typescript
// Em client.ts - request interceptor
if (!token && !isAuthEndpoint(config.url) && !shouldSkipAuth) {
  console.warn("[Axios] ⚠️ TOKEN_MISSING - redirecionando");
  if (onUnauthorizedCallback) {
    onUnauthorizedCallback(); // ← Dispara redirecionamento global
  }
  return Promise.reject({
    /* ... */
  });
}
```

---

### 3. Hook Customizado para Rotas Protegidas

**Arquivo**: `hooks/useProtectedRoute.ts` (NOVO)

```typescript
// Usado em componentes individuais para:
// 1. Verificar token ao montar
// 2. Executar lógica de autorização
// 3. Redirecionar automaticamente se falhar

const { isLoading, isAuthorized } = useProtectedRoute({
  checkFn: async (token) => {
    const user = await authService.getCurrentUser();
    return user?.papel_id === 3 || user?.papel_id === 4; // Admin check
  },
});
```

**Benefícios**:

- Reutilizável em múltiplas rotas
- Não bloqueia UI enquanto verifica
- Permite lógica customizada de autorização

---

### 4. Estrutura de Rotas Protegidas

**Arquivo**: `app/_layout.tsx`

```typescript
const PUBLIC_ROUTES = ["(auth)", "index"];

const PROTECTED_ROUTES = [
  "(tabs)",
  "planos",
  "plano-detalhes",
  "minhas-assinaturas",
  "matricula",
  "matricula-detalhes",
  "turma-detalhes",
  "checkin",
  "checkin-detalhes",
];
```

---

## Fluxo de Autenticação (Novo)

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Usuário tenta acessar rota                              │
└─────────────────────┬───────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. Root Layout (_layout.tsx) intercepta via useSegments     │
│    - Verifica se rota está em PROTECTED_ROUTES             │
│    - Busca token em AsyncStorage                           │
└─────────────────────┬───────────────────────────────────────┘
                      ↓
        ┌─────────────────────────┐
        │ Tem token?              │
        │ E rota é pública?       │
        └────────┬────────────┬───┘
                 │ Sim        │ Não
          ┌──────▼──┐    ┌────▼─────────────────┐
          │ Renderiza│    │ Hook useProtectedRoute│
          │ normal   │    │ executa checkFn      │
          └──────────┘    └────┬──────────────┬──┘
                               │ checkFn OK?  │
                        ┌──────▼─┐    ┌──────▼────┐
                        │ Renderiza   │ Redireciona
                        │ conteúdo    │ para /login
                        └────────┘    └───────────┘
```

---

## Exemplo de Uso em Componentes

**Planos.tsx** (já atualizado):

```tsx
import { useProtectedRoute } from "@/hooks/useProtectedRoute";

export default function PlanosScreen() {
  // Verificação automática na montagem
  const { isLoading: isAuthChecking } = useProtectedRoute({
    checkFn: async (token) => {
      const user = await authService.getCurrentUser();
      return user?.papel_id === 3 || user?.papel_id === 4;
    },
  });

  // ... resto do código
}
```

**Outros componentes** (minhas-assinaturas.tsx, etc):

```tsx
// Simples - apenas verifica token
const { isLoading } = useProtectedRoute();

// Com lógica customizada
const { isLoading } = useProtectedRoute({
  checkFn: async (token) => {
    // Sua lógica aqui
    return true; // ou false
  },
});
```

---

## Logs de Debug

Toda ação agora loga mensagens para facilitar troubleshooting:

```
[RootLayout] Verificando autenticação... Segments: ["planos"]
[RootLayout] ❌ Acesso negado à rota protegida: planos - redirecionando para login
[Axios] ⚠️ TOKEN_MISSING detectado para rota protegida: /api/planos
[Axios] Chamando onUnauthorizedCallback...
[RootLayout:setOnUnauthorized] Token inválido, redirecionando...
[RootLayout:setOnUnauthorized] Executando router.replace
```

---

## Testes Recomendados

1. **Sem Token**:

   ```bash
   ./TEST_LOGOUT.sh
   # Tente navegar para /planos - deve ir para login
   ```

2. **Token Deletado em Runtime**:

   ```bash
   # No Debugger/DevTools
   await AsyncStorage.removeItem('@appcheckin:token')
   # Qualquer ação deve redirecionar
   ```

3. **Deep Linking**:

   ```bash
   # Tente abrir deep link sem token
   exp://app/planos
   # Deve redirecionar para login
   ```

4. **Permissão Admin**:
   - Login com admin: planos funciona
   - Login sem admin: useProtectedRoute bloqueia

---

## Arquivos Criados/Modificados

### Criados:

- `hooks/useProtectedRoute.ts` - Hook para verificação de autenticação
- `hooks/useNavigationGuard.ts` - Guard de navegação (opcional)
- `components/AuthGuard.tsx` - Componente wrapper (opcional)
- `TESTE_REDIRECIONAR_TOKEN.md` - Guia de testes

### Modificados:

- `app/_layout.tsx` - Guard global + callbacks + logging
- `src/services/api.js` - Callbacks + logging detalhado
- `src/api/client.ts` - Callbacks + logging detalhado
- `app/planos.tsx` - Import do useProtectedRoute + uso

---

## Diferença Antes e Depois

### ANTES:

```
Sem Token → Clica em /planos → Componente renderiza → Faz API call → JSON parse error
```

### DEPOIS:

```
Sem Token → Clica em /planos → Root Layout vê segments=["planos"] → Detecta falta de token →
router.replace("/(auth)/login") → Login renderiza imediatamente
```

---

## Possíveis Melhorias Futuras

1. Atualizar também `minhas-assinaturas.tsx`, `plano-detalhes.tsx`, etc com `useProtectedRoute`
2. Adicionar middleware de refresh de token antes de expirar
3. Implementar timeout de sessão com logout automático
4. Mostrar toast de "Sessão expirada" no redirecionamento
5. Diferenciar entre "sem token" e "sem permissão" nas mensagens
