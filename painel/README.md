# Frontend Web - React Native com Expo

## 📱 Sobre

Frontend web/mobile desenvolvido com React Native e Expo para o sistema AppCheckin. Substitui o antigo frontend Angular.

## 🚀 Tecnologias

- React Native 0.81.5
- Expo SDK ~54
- React Navigation 6.x
- Axios (requisições HTTP)
- AsyncStorage (armazenamento local)

## 📋 Pré-requisitos

- Node.js 20+ (atualmente usando 18.16.1 com warnings)
- npm ou yarn
- Backend rodando em http://localhost:8080

## 🔧 Instalação

```bash
# Instalar dependências
cd FrontendWeb
npm install

# Iniciar o servidor de desenvolvimento
npm start
```

## 📱 Executar no dispositivo

### iOS

```bash
npm run ios
```

### Android

```bash
npm run android
```

### Web

```bash
npm run web
```

## 🏗️ Estrutura do Projeto

```
FrontendWeb/
├── src/
│   ├── screens/          # Telas da aplicação
│   │   ├── LoginScreen.js
│   │   ├── SuperAdminHomeScreen.js
│   │   └── CadastrarAcademiaScreen.js
│   └── services/         # Serviços de API
│       ├── api.js               # Cliente Axios configurado
│       ├── authService.js       # Autenticação
│       └── superAdminService.js # SuperAdmin operations
├── App.js               # Navegação principal
└── package.json
```

## 🔑 Credenciais de Teste

### SuperAdmin
- **Email:** superadmin@appcheckin.com
- **Senha:** SuperAdmin@2025

### Admin (Academia Fitness Pro)
- **Email:** carlos@fitnesspro.com
- **Senha:** Admin@123

## 📚 Funcionalidades Implementadas

### ✅ SuperAdmin
- [x] Login
- [x] Listar academias
- [x] Cadastrar nova academia
- [x] Associar academia a plano/contrato
- [ ] Editar academia
- [ ] Desativar/ativar academia
- [ ] Criar admin para academia

### ⏳ Admin
- [ ] Dashboard
- [ ] Gestão de alunos
- [ ] Gestão de turmas
- [ ] Contas a receber

### ⏳ Aluno
- [ ] Check-in
- [ ] Ver horários
- [ ] Ver histórico

## 🔌 API Endpoints Utilizados

### Autenticação
- `POST /auth/login` - Login com email e senha

### SuperAdmin
- `GET /superadmin/academias` - Listar todas as academias
- `POST /superadmin/academias` - Criar nova academia
- `POST /superadmin/academias/{id}/admin` - Criar admin para academia

## 🐛 Problemas Conhecidos

### Node Version Warning
O projeto está sendo executado com Node.js 18.16.1, mas algumas dependências requerem Node.js 20+. Apesar dos warnings, a aplicação funciona corretamente. Para resolver definitivamente:

```bash
# Atualizar Node.js para versão 20+
# macOS (com nvm)
nvm install 20
nvm use 20
```

## 🔄 Migrações Necessárias

### Backend - Migration 016
Antes de usar o cadastro de academias, aplique a migration que adiciona o campo `plano_id` à tabela `tenants`:

```bash
cd Backend/database/migrations
chmod +x apply_016.sh
./apply_016.sh
```

Ou execute manualmente:

```sql
ALTER TABLE tenants 
ADD COLUMN plano_id INT NULL AFTER endereco,
ADD COLUMN data_inicio_plano DATE NULL AFTER plano_id,
ADD COLUMN data_fim_plano DATE NULL AFTER data_inicio_plano;
```

## 📝 Próximos Passos

1. Implementar telas de Admin
2. Implementar telas de Aluno
3. Adicionar testes unitários
4. Melhorar tratamento de erros
5. Adicionar validações de formulário
6. Implementar refresh tokens
7. Adicionar suporte offline
8. Implementar push notifications

## 🤝 Contribuindo

1. Faça um fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este projeto está sob a licença MIT.

## 👥 Autores

- Equipe AppCheckin

## 📞 Suporte

Para suporte, envie um email para suporte@appcheckin.com
