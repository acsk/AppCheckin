#!/bin/bash

echo "🚀 Iniciando instalação do App Check-in..."
echo ""

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar se Docker está instalado
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker não está instalado${NC}"
    exit 1
fi

# Verificar se Docker Compose está instalado
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}❌ Docker Compose não está instalado${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Docker e Docker Compose encontrados${NC}"
echo ""

# Copiar arquivo .env
echo -e "${YELLOW}📝 Configurando ambiente...${NC}"
cd Backend
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${GREEN}✅ Arquivo .env criado${NC}"
else
    echo -e "${YELLOW}⚠️  Arquivo .env já existe${NC}"
fi
cd ..

# Subir containers
echo ""
echo -e "${YELLOW}🐳 Iniciando containers Docker...${NC}"
docker-compose up -d

# Aguardar MySQL iniciar
echo -e "${YELLOW}⏳ Aguardando MySQL inicializar (30 segundos)...${NC}"
sleep 30

# Instalar dependências PHP
echo ""
echo -e "${YELLOW}📦 Instalando dependências PHP...${NC}"
docker-compose exec -T php composer install

# Criar tabelas
echo ""
echo -e "${YELLOW}🗄️  Criando tabelas do banco de dados...${NC}"
docker-compose exec -T mysql mysql -uroot -proot appcheckin < Backend/database/migrations/001_create_tables.sql

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Tabelas criadas com sucesso${NC}"
else
    echo -e "${RED}❌ Erro ao criar tabelas${NC}"
    exit 1
fi

# Popular dados
echo ""
echo -e "${YELLOW}🌱 Populando dados iniciais...${NC}"
docker-compose exec -T mysql mysql -uroot -proot appcheckin < Backend/database/seeds/seed_data.sql

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Dados iniciais inseridos${NC}"
else
    echo -e "${RED}❌ Erro ao inserir dados${NC}"
    exit 1
fi

# Testar API
echo ""
echo -e "${YELLOW}🧪 Testando API...${NC}"
sleep 5
API_RESPONSE=$(curl -s http://localhost:8080)

if [[ $API_RESPONSE == *"API Check-in"* ]]; then
    echo -e "${GREEN}✅ API funcionando corretamente${NC}"
else
    echo -e "${RED}❌ API não está respondendo${NC}"
    exit 1
fi

# Instalar dependências do Frontend
echo ""
echo -e "${YELLOW}📦 Instalando dependências do Frontend...${NC}"
cd FrontEnd

if ! command -v npm &> /dev/null; then
    echo -e "${RED}❌ npm não está instalado${NC}"
    echo -e "${YELLOW}⚠️  Instale Node.js e npm antes de continuar${NC}"
    exit 1
fi

npm install

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Dependências do Frontend instaladas${NC}"
else
    echo -e "${RED}❌ Erro ao instalar dependências do Frontend${NC}"
    exit 1
fi

cd ..

# Finalização
echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}✅ Instalação concluída com sucesso!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo -e "${YELLOW}📋 Informações importantes:${NC}"
echo ""
echo -e "🔹 Backend (API): ${GREEN}http://localhost:8080${NC}"
echo -e "🔹 Frontend: Execute ${YELLOW}cd FrontEnd && npm start${NC}"
echo -e "   Depois acesse: ${GREEN}http://localhost:4200${NC}"
echo ""
echo -e "👤 Credenciais de teste:"
echo -e "   Email: ${GREEN}teste@exemplo.com${NC}"
echo -e "   Senha: ${GREEN}password123${NC}"
echo ""
echo -e "${YELLOW}📚 Comandos úteis:${NC}"
echo -e "   Ver logs: ${GREEN}docker-compose logs -f${NC}"
echo -e "   Parar: ${GREEN}docker-compose down${NC}"
echo -e "   Reiniciar: ${GREEN}docker-compose restart${NC}"
echo ""
echo -e "${GREEN}Bom desenvolvimento! 🚀${NC}"
