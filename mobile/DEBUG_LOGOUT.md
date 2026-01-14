# 🔍 Debug do Botão "Sair"

## Passos para Debugar

### 1. Abra o Console do Navegador
```
F12 → Console
```

### 2. Clique no Botão "🔴 DEBUG"
Você verá logs como:
```
🔴 [DEBUG] Verificando localStorage...
🔴 [DEBUG] localStorage disponível
🔴 [DEBUG] Chaves no localStorage: ['@appcheckin:token', '@appcheckin:user', ...]
🔴 [DEBUG] @appcheckin:token: eyJ0eXAiOi...
```

### 3. Agora Clique em "Sair"
Confirme no Alert.

### 4. Verifique os Logs
Procure por estes logs em ordem:
```
🔴 [LOGOUT] Alert.alert chamado
🟡 [LOGOUT] Iniciando logout...
🟡 [LOGOUT] AsyncStorage type: object
🟡 [LOGOUT] removeItem type: function
🟡 [LOGOUT] Token antes de remover: EXISTE
🟡 [LOGOUT] Removendo token...
✅ [LOGOUT] Token removido - resultado: undefined
✅ [LOGOUT] Token após remover: FOI REMOVIDO
✅ [LOGOUT] Usuário removido - resultado: undefined
✅ [LOGOUT] Tenant removido - resultado: undefined
✅ [LOGOUT] Estado local limpo
🟡 [LOGOUT] Redirecionando para login...
✅ [LOGOUT] Replace chamado
🟢 [LOGOUT] Logout completo!
```

---

## O que Procurar

### ✅ Se tudo estiver funcionando:
- Todos os logs aparecem
- Você é redirecionado para login
- localStorage fica vazio após logout

### ❌ Se não funcionar:
1. **Se parar em "Alert.alert chamado"**
   - O clique não está sendo registrado
   - Problema no React Native

2. **Se parar em "Iniciando logout..."**
   - AsyncStorage não está funcionando
   - Verificar storage.ts

3. **Se parar em "Removendo token..."**
   - AsyncStorage.removeItem está falhando
   - Verificar erro abaixo dos logs

4. **Se parar em "Redirecionando para login..."**
   - Router.replace não está funcionando
   - Problema no expo-router

---

## Logs Esperados no localStorage

### ANTES do logout:
```
@appcheckin:token: eyJ0eXAiOiJKV1QiLCJhbGc...
@appcheckin:user: {"id":1,"nome":"João"...}
@appcheckin:tenant: {"id":1,"nome":"Academia"}
```

### DEPOIS do logout:
```
(vazio - sem chaves)
```

---

## Próximas Ações

Após debugar, cole os logs aqui e vou identificar onde está travando!
