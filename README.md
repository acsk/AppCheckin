# 🎯 App Check-in

Sistema completo de check-in com autenticação JWT, desenvolvido com PHP (Slim Framework) e Angular.

## 📸 Screenshot

```
┌─────────────────────────────────────────┐
│  🔐 Login → 📊 Dashboard → ✅ Check-in  │
│             ↓                           │
│      📜 Histórico ← 👤 Perfil          │
└─────────────────────────────────────────┘
```

## ⚡ Quick Start

### Interfaces atuais (painel, mobile e site)

O painel e o mobile usam React/Expo; o site usa React/Vite. Os comandos Angular
abaixo pertencem à interface legada. A API ativa para gravações é `apiV2` (Laravel).

O `postinstall` das interfaces instala o pacote local compartilhado de telefones,
que fornece metadados completos do libphonenumber. Se a instalação usar
`--ignore-scripts`, execute também, a partir da raiz:

```bash
npm ci --prefix packages/phone
# Depois, na interface desejada:
npm ci --prefix site
# painel/mobile: respeite as opções de instalação já usadas nesses projetos.
```

O pacote `@appcheckin/phone` fornece o seletor de países, máscara, validação,
normalização E.164 e links de WhatsApp para as três interfaces. Não há limite fixo
de 11 dígitos. Campos legados não editados são omitidos dos payloads de atualização,
permitindo editar outros dados sem reinterpretar o país do telefone.

Auditoria, migração e cuidados de publicação: [API v2](apiV2/README.md#telefones-internacionais).

```bash
# Instalação automática
./install.sh

# Ou manual
docker-compose up -d
cd FrontEnd && npm install && npm start
```

**URLs:**
- 🌐 Frontend: http://localhost:4200
- 🔌 API: http://localhost:8080

**Credenciais de teste:**
- 📧 Email: `teste@exemplo.com`
- 🔑 Senha: `password123`

## 🚀 Tecnologias

### Backend
- PHP 8.2
- Slim Framework 4
- Firebase JWT
- MySQL 8.0
- Docker

### Frontend
- Angular 17+
- Angular Material
- RxJS

## 📋 Pré-requisitos

- Docker e Docker Compose
- Node.js 18+ (para o frontend)
- Composer (ou usar via Docker)

## 🔧 Instalação e Execução

### Backend

1. **Subir containers Docker:**
```bash
docker-compose up -d
```

2. **Verificar se os containers estão rodando:**
```bash
docker-compose ps
```

3. **Instalar dependências PHP (se necessário):**
```bash
docker-compose exec php composer install
```

4. **Executar migrations (criar tabelas):**
```bash
docker-compose exec mysql mysql -uroot -proot appcheckin < database/migrations/001_create_tables.sql
```

5. **Executar seeds (dados de teste):**
```bash
docker-compose exec mysql mysql -uroot -proot appcheckin < database/seeds/seed_data.sql
```

6. **Testar API:**
```bash
curl http://localhost:8080
```

### Frontend

1. **Navegar para pasta do frontend:**
```bash
cd FrontEnd
```

2. **Instalar dependências:**
```bash
npm install
```

3. **Executar aplicação:**
```bash
ng serve
```

4. **Acessar:** http://localhost:4200

## 🗄️ Estrutura do Banco de Dados

### Tabelas

- **usuarios**: Armazena dados dos usuários
- **dias**: Dias disponíveis para check-in
- **horarios**: Horários disponíveis por dia
- **checkins**: Registro de check-ins realizados

## 📚 Endpoints da API

### Autenticação (Públicos)

```http
POST /auth/register
Content-Type: application/json

{
  "nome": "João Silva",
  "email": "joao@exemplo.com",
  "senha": "senha123"
}
```

```http
POST /auth/login
Content-Type: application/json

{
  "email": "joao@exemplo.com",
  "senha": "senha123"
}
```

### Usuário (Autenticado)

```http
GET /me
Authorization: Bearer {token}
```

```http
PUT /me
Authorization: Bearer {token}
Content-Type: application/json

{
  "nome": "João Silva Atualizado",
  "email": "novoemail@exemplo.com"
}
```

### Dias e Horários (Autenticado)

```http
GET /dias
Authorization: Bearer {token}
```

```http
GET /dias/{id}/horarios
Authorization: Bearer {token}
```

### Check-ins (Autenticado)

```http
POST /checkin
Authorization: Bearer {token}
Content-Type: application/json

{
  "horario_id": 1
}
```

```http
GET /me/checkins
Authorization: Bearer {token}
```

```http
DELETE /checkin/{id}
Authorization: Bearer {token}
```

## 🔑 Credenciais de Teste

**Email:** teste@exemplo.com  
**Senha:** password123

## 🐳 Comandos Docker Úteis

```bash
# Parar containers
docker-compose down

# Ver logs
docker-compose logs -f php
docker-compose logs -f mysql

# Reiniciar containers
docker-compose restart

# Acessar container PHP
docker-compose exec php bash

# Acessar MySQL
docker-compose exec mysql mysql -uroot -proot appcheckin
```

## 📁 Estrutura de Pastas

```
AppCheckin/
├── Backend/
│   ├── app/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Middlewares/
│   │   └── Services/
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeds/
│   ├── public/
│   ├── routes/
│   ├── vendor/
│   ├── .env.example
│   ├── .gitignore
│   ├── composer.json
│   └── Dockerfile
├── FrontEnd/
│   └── (Angular app)
└── docker-compose.yml
```

## 🛠️ Desenvolvimento

### Adicionar nova rota

1. Criar método no Controller
2. Adicionar rota em `routes/api.php`
3. Testar endpoint

### Modificar banco de dados

1. Criar nova migration em `database/migrations/`
2. Executar migration no container MySQL

## 📝 Licença

Este projeto é de código aberto.
