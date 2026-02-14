# 🧪 Guia de Testes - Recuperação de Senha

## Pré-requisitos

- [ ] App compilado e rodando
- [ ] Servidor de backend funcionando
- [ ] Acesso a caixa de email de teste

---

## 📋 Casos de Teste

### TC-001: Solicitar Recuperação via Login

**Objetivo**: Verificar se o usuário consegue solicitar recuperação de senha na tela de login

**Passos**:

1. Abra a aplicação
2. Na tela de login, clique em "Esqueceu sua senha?"
3. Digite um email válido (ex: teste@example.com)
4. Clique em "Enviar Link"

**Resultado Esperado**:

- ✅ Modal se abre
- ✅ Mensagem de sucesso é exibida
- ✅ Campo para inserir token aparece
- ✅ Email é recebido com o token

---

### TC-002: Validar Token

**Objetivo**: Verificar validação do token de recuperação

**Passos**:

1. Após solicitar recuperação (TC-001)
2. Copie o token recebido por email
3. Cole no campo "Token de Recuperação"
4. Clique em "Validar Token"

**Resultado Esperado**:

- ✅ Token é aceito
- ✅ Modal avança para etapa de reset
- ✅ Campos de senha aparecem

---

### TC-003: Resetar Senha com Sucesso

**Objetivo**: Verificar reset bem-sucedido de senha

**Passos**:

1. Após validar token (TC-002)
2. Digite uma nova senha (mín 6 caracteres)
3. Confirme a mesma senha
4. Clique em "Atualizar Senha"

**Resultado Esperado**:

- ✅ Mensagem "Senha Alterada!"
- ✅ Modal fecha automaticamente
- ✅ Usuário volta para tela de login
- ✅ Login funciona com nova senha

---

### TC-004: Validar Erro - Token Expirado

**Objetivo**: Verificar comportamento com token expirado

**Passos**:

1. Solicite recuperação
2. Aguarde 15 minutos (ou use token antigo)
3. Digite o token expirado
4. Clique em "Validar Token"

**Resultado Esperado**:

- ❌ Erro: "Token inválido ou expirado"
- ❌ Usuário retorna à etapa 1
- ❌ Deve solicitar novo token

---

### TC-005: Validar Erro - Senhas Não Coincidem

**Objetivo**: Verificar validação de coincidência de senhas

**Passos**:

1. Após validar token (TC-002)
2. Digite "SenhaTest123" em "Nova Senha"
3. Digite "SenhaTest456" em "Confirmar Senha"
4. Clique em "Atualizar Senha"

**Resultado Esperado**:

- ❌ Mensagem: "As senhas não coincidem"
- ❌ Modal não avança
- ❌ Campos mantêm os valores

---

### TC-006: Validar Erro - Senha Muito Curta

**Objetivo**: Verificar validação de comprimento mínimo

**Passos**:

1. Após validar token (TC-002)
2. Digite "123" em ambos os campos
3. Clique em "Atualizar Senha"

**Resultado Esperado**:

- ❌ Mensagem: "Senha deve ter no mínimo 6 caracteres"
- ❌ Modal não avança

---

### TC-007: Alterar Senha via Conta

**Objetivo**: Verificar recuperação a partir da tela "Minha Conta"

**Passos**:

1. Faça login normalmente
2. Vá para "Minha Conta"
3. Clique em "Alterar Senha"
4. Cole token do email
5. Defina nova senha

**Resultado Esperado**:

- ✅ Modal abre direto na etapa de validação
- ✅ Processo é igual ao TC-003
- ✅ Senhas são alteradas com sucesso

---

### TC-008: Logout via Tab Bar

**Objetivo**: Verificar funcionalidade de logout

**Passos**:

1. Faça login
2. Navegue até qualquer tela
3. Clique no ícone "Sair" na tab bar (último ícone)
4. Observe redirecionamento

**Resultado Esperado**:

- ✅ Usuário é deslogado
- ✅ Redirecionamento para tela de login
- ✅ Token removido do storage
- ✅ Dados de perfil apagados

---

### TC-009: Fechar Modal sem Completar

**Objetivo**: Verificar cancelamento do fluxo

**Passos**:

1. Abra modal de recuperação
2. Clique no "X" para fechar
3. Verifique se modal fecha

**Resultado Esperado**:

- ✅ Modal fecha normalmente
- ✅ Estado é resetado
- ✅ Nenhum efeito colateral

---

### TC-010: Voltar entre Etapas

**Objetivo**: Verificar navegação no fluxo

**Passos**:

1. Abra modal de recuperação
2. Digite email e clique "Enviar Link"
3. Clique botão "Voltar"
4. Verifique volta para etapa 1

**Resultado Esperado**:

- ✅ Modal volta para etapa de email
- ✅ Campo de email está vazio
- ✅ Campos de token são resetados

---

## 🔍 Testes de Interface

### Teste de Responsividade

- [ ] Modal se ajusta em telas pequenas
- [ ] Inputs têm espaçamento adequado
- [ ] Botões são facilmente clicáveis
- [ ] Texto está legível

### Teste de Acessibilidade

- [ ] Tab order funciona corretamente
- [ ] Inputs têm labels descritivos
- [ ] Cores contrastam bem
- [ ] Ícones têm labels

### Teste de Performance

- [ ] Modal carrega rápido
- [ ] Requisições são rápidas (< 2s)
- [ ] Sem lag ao digitar
- [ ] Animações suaves

---

## 🚀 Casos de Teste Avançados

### TC-A1: Email com Domínio Especial

**Entrada**: usuario+tag@empresa.com.br
**Esperado**: ✅ Funciona normalmente

### TC-A2: Senha com Caracteres Especiais

**Entrada**: Snh@123!$%
**Esperado**: ✅ Aceita e armazena corretamente

### TC-A3: Múltiplas Solicitações

**Passos**:

1. Solicite recuperação 1ª vez
2. Solicite recuperação 2ª vez
3. Use 2º token

**Esperado**: ✅ Apenas o token mais recente funciona

### TC-A4: Timeout de Conexão

**Simulação**: Desligar internet
**Esperado**: ❌ Mensagem de erro adequada

---

## 📊 Checklist Final

- [ ] Todos os TC-001 a TC-010 passam
- [ ] Sem erros no console
- [ ] Lint passa sem novos warnings
- [ ] Performance aceitável
- [ ] UX é intuitiva
- [ ] Mensagens são claras
- [ ] Mobile e Web funcionam
- [ ] Logout limpa dados corretamente

---

## 🐛 Debugging

### Logs Úteis

```javascript
// Verificar se modal está sendo renderizado
console.log("showRecoveryModal:", showRecoveryModal);

// Verificar token armazenado
const token = await AsyncStorage.getItem("@appcheckin:token");
console.log("Token:", token);

// Verificar resposta do servidor
console.log("Response:", response);
```

### DevTools

```
1. Abra DevTools (F12 ou Cmd+I)
2. Vá para Network
3. Veja requisições para os endpoints
4. Verifique status code e resposta
```

---

**Última atualização**: 22/01/2026
**Status**: Pronto para testes
