#!/bin/bash
# ================================================================
# Script de Testes - Endpoints de Assinaturas
# ================================================================
# Use este script para testar todos os endpoints de assinaturas
# 
# Substitua:
# - TOKEN: seu JWT token
# - BASE_URL: http://localhost:8080 (development) ou https://api.appcheckin.com.br (production)

BASE_URL="http://localhost:8080"
TOKEN="seu_jwt_token_aqui"

# Cores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Testes de API - Assinaturas${NC}"
echo -e "${BLUE}========================================${NC}\n"

# ================================================================
# 1. LISTAR ASSINATURAS ATIVAS
# ================================================================
echo -e "${GREEN}1. GET /admin/assinaturas - Listar Assinaturas Ativas${NC}"
curl -X GET "${BASE_URL}/admin/assinaturas?status=ativa&limite=10" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 2. LISTAR COM FILTRO POR PLANO
# ================================================================
echo -e "${GREEN}2. GET /admin/assinaturas - Filtrar por Plano${NC}"
curl -X GET "${BASE_URL}/admin/assinaturas?status=ativa&plano_id=2&limite=10" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 3. BUSCAR ASSINATURA ESPECÍFICA
# ================================================================
echo -e "${GREEN}3. GET /admin/assinaturas/1 - Buscar Detalhes${NC}"
curl -X GET "${BASE_URL}/admin/assinaturas/1" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 4. CRIAR NOVA ASSINATURA
# ================================================================
echo -e "${GREEN}4. POST /admin/assinaturas - Criar Nova Assinatura${NC}"
curl -X POST "${BASE_URL}/admin/assinaturas" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "aluno_id": 5,
    "plano_id": 2,
    "data_inicio": "2025-01-15",
    "forma_pagamento": "cartao_credito",
    "renovacoes": 12,
    "observacoes": "Assinatura de teste via API"
  }' \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 5. ATUALIZAR ASSINATURA
# ================================================================
echo -e "${GREEN}5. PUT /admin/assinaturas/1 - Atualizar Assinatura${NC}"
curl -X PUT "${BASE_URL}/admin/assinaturas/1" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "forma_pagamento": "pix",
    "renovacoes_restantes": 5
  }' \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 6. RENOVAR ASSINATURA
# ================================================================
echo -e "${GREEN}6. POST /admin/assinaturas/1/renovar - Renovar Assinatura${NC}"
curl -X POST "${BASE_URL}/admin/assinaturas/1/renovar" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "gerar_cobranca": true,
    "observacoes": "Renovação manual"
  }' \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 7. SUSPENDER ASSINATURA
# ================================================================
echo -e "${GREEN}7. POST /admin/assinaturas/1/suspender - Suspender${NC}"
curl -X POST "${BASE_URL}/admin/assinaturas/1/suspender" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "motivo": "Pagamento pendente",
    "data_suspensao": "2025-01-20"
  }' \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 8. REATIVAR ASSINATURA
# ================================================================
echo -e "${GREEN}8. POST /admin/assinaturas/1/reativar - Reativar${NC}"
curl -X POST "${BASE_URL}/admin/assinaturas/1/reativar" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "observacoes": "Pagamento recebido"
  }' \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 9. CANCELAR ASSINATURA
# ================================================================
echo -e "${GREEN}9. POST /admin/assinaturas/1/cancelar - Cancelar${NC}"
curl -X POST "${BASE_URL}/admin/assinaturas/1/cancelar" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "motivo": "Aluno cancelou",
    "data_cancelamento": "2025-01-20",
    "gerar_reembolso": true
  }' \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 10. ASSINATURAS PRÓXIMAS DE VENCER
# ================================================================
echo -e "${GREEN}10. GET /admin/assinaturas/proximas-vencer - Próximas de Vencer${NC}"
curl -X GET "${BASE_URL}/admin/assinaturas/proximas-vencer?dias=30&limite=50" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 11. HISTÓRICO DE ASSINATURAS DO ALUNO
# ================================================================
echo -e "${GREEN}11. GET /admin/alunos/5/assinaturas - Histórico do Aluno${NC}"
curl -X GET "${BASE_URL}/admin/alunos/5/assinaturas?incluir_canceladas=true" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 12. RELATÓRIO DE ASSINATURAS
# ================================================================
echo -e "${GREEN}12. GET /admin/assinaturas/relatorio - Relatório${NC}"
curl -X GET "${BASE_URL}/admin/assinaturas/relatorio?agrupar_por=modalidade" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 13. SUPERADMIN - LISTAR TODAS ASSINATURAS
# ================================================================
echo -e "${GREEN}13. GET /superadmin/assinaturas - Listar Todas (SuperAdmin)${NC}"
curl -X GET "${BASE_URL}/superadmin/assinaturas?status=ativa&tenant_id=1&limite=20" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 14. TESTAR ERRO - SEM AUTENTICAÇÃO
# ================================================================
echo -e "${RED}14. Teste de Erro - Sem Autenticação${NC}"
curl -X GET "${BASE_URL}/admin/assinaturas" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n\n"

# ================================================================
# 15. TESTAR ERRO - ID INVÁLIDO
# ================================================================
echo -e "${RED}15. Teste de Erro - ID Inválido${NC}"
curl -X GET "${BASE_URL}/admin/assinaturas/99999" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n\n"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Testes Concluídos!${NC}"
echo -e "${BLUE}========================================${NC}\n"

# ================================================================
# EXEMPLOS ADICIONAIS - Use conforme necessário
# ================================================================

# Para testar com Postman, importe este arquivo JSON:
cat > assinaturas_postman.json << 'EOF'
{
  "info": {
    "name": "AppCheckin - Assinaturas API",
    "description": "Testes de API para gerenciamento de assinaturas",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "auth": {
    "type": "bearer",
    "bearer": [
      {
        "key": "token",
        "value": "{{TOKEN}}",
        "type": "string"
      }
    ]
  },
  "item": [
    {
      "name": "1. Listar Assinaturas",
      "request": {
        "method": "GET",
        "url": {
          "raw": "{{BASE_URL}}/admin/assinaturas?status=ativa",
          "protocol": "http",
          "host": ["localhost"],
          "port": "8080",
          "path": ["admin", "assinaturas"],
          "query": [
            {
              "key": "status",
              "value": "ativa"
            }
          ]
        }
      }
    }
  ]
}
EOF

echo -e "${GREEN}✓ Arquivo postman criado: assinaturas_postman.json${NC}"
