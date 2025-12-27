# 🚀 Guia Rápido - Frontend React Native

## ✅ O que foi implementado

### 1. **Remoção do Angular**
- ✅ Pasta `FrontEnd/` (Angular) foi completamente removida
- ✅ Criado novo projeto `FrontendWeb/` com React Native + Expo

### 2. **Estrutura do Projeto**
```
FrontendWeb/
├── src/
│   ├── screens/
│   │   ├── LoginScreen.js              # Tela de login
│   │   ├── SuperAdminHomeScreen.js     # Dashboard do SuperAdmin
│   │   └── CadastrarAcademiaScreen.js  # Cadastro de academias
│   └── services/
│       ├── api.js                      # Cliente Axios
│       ├── authService.js              # Autenticação
│       └── superAdminService.js        # SuperAdmin operations
└── App.js                              # Navegação
```

### 3. **Funcionalidades SuperAdmin**
- ✅ Login com email e senha
- ✅ Dashboard com lista de academias
- ✅ Cadastro de academia **com associação a plano**
- ✅ Visualização de estatísticas (total de academias, ativas)
- ✅ Logout

### 4. **Backend Atualizado**
- ✅ `SuperAdminController.php` modificado para aceitar `plano_id`
- ✅ Migration 016 criada (`016_add_plano_to_tenants.sql`)
- ✅ Campos adicionados: `plano_id`, `data_inicio_plano`, `data_fim_plano`

## 🔧 Como usar

### 1. Aplicar a Migration (IMPORTANTE)

```bash
# Entrar no container MySQL
docker exec -it appcheckin_mysql bash

# Executar no MySQL
mysql -u root -prootpass appcheckin

# Colar o SQL
ALTER TABLE tenants 
ADD COLUMN plano_id INT NULL AFTER endereco,
ADD COLUMN data_inicio_plano DATE NULL AFTER plano_id,
ADD COLUMN data_fim_plano DATE NULL AFTER data_inicio_plano;

# Verificar
DESCRIBE tenants;

# Sair
exit
exit
```

### 2. Iniciar o Frontend

```bash
cd FrontendWeb

# Instalar dependências (se ainda não instalou)
npm install

# Iniciar Expo
npx expo start
```

### 3. Testar a Aplicação

#### Opção 1: Web
```bash
npx expo start --web
```

#### Opção 2: iOS Simulator
```bash
npx expo start
# Pressione 'i' para abrir no iOS simulator
```

#### Opção 3: Android Emulator
```bash
npx expo start
# Pressione 'a' para abrir no Android emulator
```

#### Opção 4: Dispositivo Físico
1. Instale o app **Expo Go** no seu celular
2. Execute `npx expo start`
3. Escaneie o QR code com o Expo Go

## 🎯 Fluxo de Teste

### 1. Login como SuperAdmin
```
Email: superadmin@appcheckin.com
Senha: SuperAdmin@2025
```

### 2. Ver Lista de Academias
- Ao fazer login, você verá a tela com:
  - Total de academias cadastradas
  - Academias ativas
  - Lista completa de academias

### 3. Cadastrar Nova Academia
1. Clique no botão **"+ Nova"**
2. Preencha o formulário:
   - Nome: `Academia Teste`
   - Email: `teste@academia.com`
   - Telefone: `(11) 99999-9999` (opcional)
   - Endereço: `Rua Teste, 123` (opcional)
   - **Plano: Selecione um plano** (obrigatório)
3. Clique em **"Cadastrar Academia"**
4. A academia será criada e você voltará para a lista

### 4. Ver Detalhes da Academia
- Cada card mostra:
  - Nome
  - Email
  - Telefone
  - Status (Ativo/Inativo)
  - Slug/ID

## 📋 Planos Disponíveis

Os planos estão hardcoded no formulário por enquanto:

1. **Básico** - R$ 99,90/mês
2. **Profissional** - R$ 199,90/mês
3. **Premium** - R$ 299,90/mês

## 🐛 Solução de Problemas

### Erro: Port 8081 occupied
```bash
# Use outra porta
npx expo start --port 8082

# Ou mate o processo na porta 8081
lsof -ti:8081 | xargs kill -9
```

### Erro: Cannot find module
```bash
# Reinstale as dependências
cd FrontendWeb
rm -rf node_modules package-lock.json
npm install
```

### Erro: Network request failed
- Verifique se o backend está rodando: `http://localhost:8080`
- Se estiver usando dispositivo físico, use o IP da sua máquina:
  ```javascript
  // src/services/api.js
  baseURL: 'http://SEU_IP:8080'
  ```

### Warning: Node version
- O projeto funciona com Node 18.16.1, mas recomenda-se Node 20+
- Para atualizar:
  ```bash
  nvm install 20
  nvm use 20
  ```

## 🎨 Personalização

### Mudar cores do tema
Edite os arquivos de screen em `src/screens/`:
- **Azul principal:** `#007AFF` → Substitua pela cor desejada
- **Verde sucesso:** `#34C759`
- **Vermelho erro:** `#FF3B30`

### Adicionar novos planos
Edite [CadastrarAcademiaScreen.js](src/screens/CadastrarAcademiaScreen.js):
```javascript
setPlanos([
  { id: 1, nome: 'Básico', valor: 'R$ 99,90/mês' },
  { id: 2, nome: 'Pro', valor: 'R$ 199,90/mês' },
  { id: 3, nome: 'Premium', valor: 'R$ 299,90/mês' },
  { id: 4, nome: 'SEU_PLANO', valor: 'R$ XXX,XX/mês' }, // NOVO
]);
```

## 📱 Próximas Implementações

### Fase 2: Admin
- [ ] Tela de dashboard do Admin
- [ ] Gestão de alunos
- [ ] Gestão de turmas
- [ ] Contas a receber

### Fase 3: Aluno
- [ ] Tela de check-in
- [ ] Ver horários disponíveis
- [ ] Histórico de check-ins

### Melhorias Gerais
- [ ] Criar endpoint `/superadmin/planos` para listar planos do banco
- [ ] Adicionar paginação na lista de academias
- [ ] Implementar busca/filtro de academias
- [ ] Adicionar foto/logo da academia
- [ ] Implementar edição de academia
- [ ] Implementar desativação de academia
- [ ] Adicionar tela de criar admin para academia
- [ ] Push notifications
- [ ] Modo offline

## 📞 Dúvidas?

Consulte a documentação completa em [README.md](README.md)
