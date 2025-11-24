# 🚀 Guia de Instalação - App Check-in

## 📋 Pré-requisitos

- Docker e Docker Compose instalados
- Node.js 18+ e npm instalados
- Composer (opcional, pode usar via Docker)

---

## 🔧 Instalação

### 1️⃣ Backend (PHP + MySQL)

```bash
# Navegar para a raiz do projeto
cd /Users/andrecabral/Projetos/AppCheckin

# Copiar arquivo de ambiente
cd Backend
cp .env.example .env

# Voltar para raiz e subir containers
cd ..
docker-compose up -d

# Aguardar containers iniciarem (30 segundos)
sleep 30

# Instalar dependências PHP
docker-compose exec php composer install

# Criar tabelas do banco
docker-compose exec mysql mysql -uroot -proot appcheckin < Backend/database/migrations/001_create_tables.sql

# Popular dados de teste
docker-compose exec mysql mysql -uroot -proot appcheckin < Backend/database/seeds/seed_data.sql
```

**Verificar se API está funcionando:**
```bash
curl http://localhost:8080
```

Deve retornar:
```json
{"message":"API Check-in - funcionando!","version":"1.0.0"}
```

---

### 2️⃣ Frontend (Angular)

```bash
# Navegar para pasta do frontend
cd FrontEnd

# Instalar dependências
npm install

# Executar aplicação em modo desenvolvimento
npm start
```

**Acessar aplicação:** http://localhost:4200

---

## 🧪 Testar a Aplicação

### Credenciais de teste já criadas:
- **Email:** teste@exemplo.com
- **Senha:** password123

### Ou criar nova conta:
1. Acesse http://localhost:4200/register
2. Preencha os dados e clique em "Cadastrar"
3. Você será redirecionado automaticamente

---

## 🔄 Comandos Úteis

### Backend (Docker)

```bash
# Ver logs do PHP
docker-compose logs -f php

# Ver logs do MySQL
docker-compose logs -f mysql

# Reiniciar containers
docker-compose restart

# Parar containers
docker-compose down

# Acessar container PHP
docker-compose exec php bash

# Acessar MySQL diretamente
docker-compose exec mysql mysql -uroot -proot appcheckin
```

### Frontend (Angular)

```bash
# Executar em modo desenvolvimento
npm start

# Build de produção
npm run build

# Executar testes
npm test
```

---

## 📚 Estrutura de Pastas

```
AppCheckin/
├── Backend/                    # API PHP
│   ├── app/
│   │   ├── Controllers/       # Controladores da API
│   │   ├── Models/            # Modelos de dados
│   │   ├── Middlewares/       # Middlewares (autenticação)
│   │   └── Services/          # Serviços (JWT)
│   ├── config/                # Configurações
│   ├── database/
│   │   ├── migrations/        # Migrações SQL
│   │   └── seeds/             # Dados iniciais
│   ├── public/                # Ponto de entrada
│   ├── routes/                # Definição de rotas
│   └── composer.json          # Dependências PHP
│
├── FrontEnd/                   # Aplicação Angular
│   ├── src/
│   │   ├── app/
│   │   │   ├── components/    # Componentes da UI
│   │   │   ├── services/      # Serviços (API calls)
│   │   │   ├── guards/        # Guards de rota
│   │   │   ├── interceptors/  # Interceptors HTTP
│   │   │   └── models/        # Interfaces TypeScript
│   │   └── environments/      # Configurações de ambiente
│   └── package.json           # Dependências Node
│
└── docker-compose.yml          # Orquestração Docker
```

---

## 🌐 Endpoints da API

### Públicos
- `POST /auth/register` - Registro de usuário
- `POST /auth/login` - Login

### Protegidos (requer token)
- `GET /me` - Dados do usuário
- `PUT /me` - Atualizar perfil
- `GET /dias` - Listar dias disponíveis
- `GET /dias/{id}/horarios` - Horários de um dia
- `POST /checkin` - Realizar check-in
- `GET /me/checkins` - Meus check-ins
- `DELETE /checkin/{id}` - Cancelar check-in

---

## 🎯 Fluxo de Uso

1. **Registro/Login**
   - Usuário cria conta ou faz login
   - Recebe token JWT

2. **Dashboard**
   - Visualiza estatísticas
   - Acesso rápido às funcionalidades

3. **Fazer Check-in**
   - Seleciona um dia disponível
   - Escolhe um horário com vagas
   - Confirma o check-in

4. **Histórico**
   - Visualiza todos os check-ins
   - Pode cancelar check-ins futuros

5. **Perfil**
   - Atualiza informações pessoais
   - Altera senha

---

## 🐛 Solução de Problemas

### Container MySQL não inicia
```bash
# Remover volumes e recriar
docker-compose down -v
docker-compose up -d
```

### Erro de permissão no PHP
```bash
# Dentro do container, ajustar permissões
docker-compose exec php chown -R www-data:www-data /var/www/html
```

### Frontend não conecta na API
- Verificar se a API está rodando em http://localhost:8080
- Verificar CORS no arquivo `Backend/public/index.php`
- Verificar `FrontEnd/src/environments/environment.ts`

### Dependências do Angular não instalam
```bash
# Limpar cache e reinstalar
cd FrontEnd
rm -rf node_modules package-lock.json
npm cache clean --force
npm install
```

---

## 🎨 Customizações

### Alterar porta do Backend
Edite `docker-compose.yml`:
```yaml
php:
  ports:
    - "8081:80"  # Mude 8080 para 8081
```

E atualize `FrontEnd/src/environments/environment.ts`:
```typescript
apiUrl: 'http://localhost:8081'
```

### Alterar tema do Angular
Edite `FrontEnd/src/styles.scss`:
```scss
@import '@angular/material/prebuilt-themes/purple-green.css';
```

Opções: `indigo-pink`, `deeppurple-amber`, `pink-bluegrey`, `purple-green`

---

## 📝 Próximos Passos (Futuras Implementações)

- [ ] Painel Admin para gerenciar dias e horários
- [ ] Notificações por email
- [ ] Relatórios de presença
- [ ] Integração com Google Calendar
- [ ] Aplicativo mobile (React Native/Flutter)
- [ ] Autenticação com redes sociais
- [ ] Sistema de pontos/gamificação

---

## 📄 Licença

Projeto de código aberto para fins educacionais.

---

## 👨‍💻 Suporte

Para dúvidas ou problemas:
1. Verifique os logs: `docker-compose logs`
2. Consulte o README.md principal
3. Revise a documentação da API

Bom desenvolvimento! 🚀
