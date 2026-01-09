# 📱 Guia Completo - Testando Minha Conta com Dados Completos

## ✅ Verificação Prévia (Ambiente)

### 1. Verificar se Backend está rodando
```bash
curl -s http://localhost:8080/health | jq '.'
```
Esperado: Deve retornar status 200 (ou similar)

### 2. Verificar Banco de Dados
```bash
docker ps | grep mysql
docker exec -it appcheckin_mysql mysql -u root -proot appcheckin -e "SELECT id, nome, email, cpf, cep FROM usuarios WHERE email='teste@exemplo.com';"
```

Esperado:
```
| id | nome              | email                 | cpf         | cep       |
| 14 | Usuário Teste    | teste@exemplo.com     | 12345678901 | 01310-100 |
```

### 3. Testar Endpoint /me
```bash
bash /Users/andrecabral/Projetos/AppCheckin/test_me_endpoint.sh
```

Esperado: Retorna `✅ Teste concluído com sucesso!` com todos os dados do usuário

---

## 🚀 Iniciando a App Mobile

### Passo 1: Abrir Terminal
```bash
cd /Users/andrecabral/Projetos/AppCheckin/AppCheckin/appcheckin-mobile
```

### Passo 2: Instalar dependências (se necessário)
```bash
npm install
```

### Passo 3: Iniciar App
```bash
npm start
```

Você verá:
```
Expo project loaded at
https://127.0.0.1:9200

Press 'w' to open web, 'i' for iOS simulator, 'a' for Android Emulator or '?' to see all options.
```

### Passo 4: Abrir em Web (mais fácil para debug)
Pressione `w` para abrir em navegador

---

## 🧪 Testando Login

Na tela de Login, entre com:
- **Email**: `teste@exemplo.com`
- **Senha**: `password123`

Esperado:
- Deve fazer login com sucesso
- Ir para a tela de Perfil
- Mostrar nome: "Usuário Teste"

---

## 🎯 Testando Minha Conta

### 1. Na tela de Perfil, clique em "Minha Conta"

Você verá:
- Debug box amarelo no topo mostrando:
  - `baseUrl: http://localhost:8080`
  - `usuario.id: 14 | usuario.nome: Usuário Teste`
  - `cpf: 12345678901 | cep: 01310-100`
  - Mensagem para clicar em 🔄

### 2. Abrir Console do Navegador
- **Chrome/Firefox/Safari**: F12 ou Cmd+Option+I
- **Expo Web**: Pressione `Cmd+Shift+M`

### 3. Ver os logs enquanto acontecem

Procure por:
```
🎯 MinhaConta montado, baseUrl: http://localhost:8080
👤 Usuario foi atualizado: {id: 14, nome: "Usuário Teste", cpf: "12345678901", ...}
📥 Iniciando carregamento de dados...
🔑 Token encontrado: eyJ0eXAi...
📍 Buscando dados em: http://localhost:8080/me
📊 Status da resposta: 200 OK
✅ Dados recebidos do /me: {id: 14, nome: "Usuário Teste", cpf: "12345678901", ...}
💾 Dados salvos no AsyncStorage - ID: 14 CPF: 12345678901 CEP: 01310-100
✅ Dados carregados no MinhaConta: {id: 14, cpf: "12345678901", cep: "01310-100", ...}
```

### 4. Clique no botão 🔄 (refresh) para recarregar

Você deve ver os logs acima sendo repetidos

### 5. Verifique se os dados aparecem na tela

Esperado:
- **CPF**: 12345678901
- **CEP**: 01310-100
- **Telefone**: (11) 98765-4321
- **Logradouro**: Avenida Paulista
- **Número**: 1000
- **Complemento**: Apto 501
- **Bairro**: Bela Vista
- **Cidade**: São Paulo
- **Estado**: SP

---

## 🔴 Se não funcionar

### Problema 1: Dados ainda mostram "-"

1. **Verificar console** para mensagens de erro (começam com ❌)
2. **Procurar por**: `⚠️ Erro ao buscar dados completos:`
3. **Copiar a mensagem de erro** e verificar:
   - É um erro de token?
   - É um erro de conexão?
   - É um erro 401 (unauthorized)?

### Problema 2: Erro 401 (Unauthorized)

1. Fazer logout (clique no menu hamburger)
2. Fazer login novamente
3. Tentar novamente

### Problema 3: Erro de conexão

1. Verificar se backend está rodando:
   ```bash
   curl http://localhost:8080/auth/login -X POST -H "Content-Type: application/json" -d '{"email":"teste@exemplo.com","senha":"password123"}' | jq '.token'
   ```

2. Se não funcionar, backend não está rodando

3. Se funcionou, o problema está na app ou no baseUrl

### Problema 4: baseUrl incorreto

Se o debug box mostra `baseUrl: undefined` ou algo errado:
1. Verificar se `Perfil` está passando `baseUrl` para `MinhaConta`
2. Verificar se `Tabs` está passando `baseUrl` para `Perfil`
3. Verificar se `App.js` definiu `baseUrl` corretamente

---

## 📋 Checklist de Debug

- [ ] Backend rodando (curl test passa)
- [ ] MySQL rodando com dados (docker exec test passa)
- [ ] Endpoint `/me` funciona (test_me_endpoint.sh passa)
- [ ] App inicia sem erros
- [ ] Login funciona
- [ ] Debug box mostra informações corretas
- [ ] Console mostra logs 🔑 Token encontrado
- [ ] Console mostra logs 📍 Buscando dados em: http://localhost:8080/me
- [ ] Console mostra logs 📊 Status da resposta: 200 OK
- [ ] Console mostra logs ✅ Dados recebidos do /me
- [ ] Campos de CPF e CEP mostram valores, não "-"

---

## 🎉 Teste Alternativo (carolina.ferreira@tenant4.com)

Se quiser testar com outro usuário que já tem dados:
- **Email**: `carolina.ferreira@tenant4.com`
- **Senha**: `123456`

Este usuário também tem todos os dados preenchidos no banco

---

## 💾 Dados Atualizados no Banco

Tanto `teste@exemplo.com` quanto `carolina.ferreira@tenant4.com` têm:
- ✅ CPF
- ✅ CEP
- ✅ Telefone
- ✅ Logradouro (Endereço)
- ✅ Número
- ✅ Complemento (Apto)
- ✅ Bairro
- ✅ Cidade
- ✅ Estado

---

## 🛠️ Troubleshooting Avançado

### Se mesmo com tudo correto os dados não aparecem

1. **Limpar AsyncStorage da app:**
   - No console do navegador (Web), execute:
   ```javascript
   // Abrir Developer Tools (F12)
   // Go to Application > Local Storage
   // Delete tudo
   // Reload page
   ```

2. **Testar diretamente a chamada `/me` no console:**
   ```javascript
   const token = 'YOUR_TOKEN_HERE'; // Copie do login
   fetch('http://localhost:8080/me', {
     method: 'GET',
     headers: {
       'Content-Type': 'application/json',
       'Authorization': `Bearer ${token}`
     }
   })
   .then(r => r.json())
   .then(d => console.log('Resposta:', d))
   .catch(e => console.error('Erro:', e));
   ```

3. **Verificar se o token é válido:**
   - Vá para https://jwt.io/
   - Cole o token que vem do login
   - Verifique se `user_id` e `tenant_id` estão presentes

---

**Data**: 09/01/2026  
**Última Atualização**: Após testes de endpoint /me com sucesso  
**Status do Backend**: ✅ Verificado e funcionando corretamente
