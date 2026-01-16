#!/bin/bash

# Script para testar o novo endpoint de WOD Completo
# Substitua os valores conforme necessário

# Configurações
API_URL="http://localhost:8000"  # Alterar conforme ambiente
TOKEN="seu_token_aqui"           # Alterar com token válido
TENANT_ID="1"                     # Alterar conforme tenant

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Teste do Endpoint POST /admin/wods/completo ===${NC}\n"

# Teste 1: WOD Simples (3 blocos)
echo -e "${BLUE}Teste 1: Criar WOD Simples${NC}"
curl -X POST "$API_URL/admin/wods/completo" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "WOD Simples - Teste 1",
    "descricao": "Teste do endpoint unificado",
    "data": "2026-01-20",
    "status": "draft",
    "blocos": [
      {
        "ordem": 1,
        "tipo": "warmup",
        "titulo": "Aquecimento",
        "conteudo": "5 min de bicicleta\n10 push-ups\n5 pull-ups"
      },
      {
        "ordem": 2,
        "tipo": "metcon",
        "titulo": "WOD Principal",
        "conteudo": "10 min AMRAP:\n5 clean and jerk\n10 box jumps",
        "tempo_cap": "10 min"
      },
      {
        "ordem": 3,
        "tipo": "cooldown",
        "titulo": "Resfriamento",
        "conteudo": "5 min de alongamento"
      }
    ]
  }' | json_pp

echo -e "\n${BLUE}═══════════════════════════════════════${NC}\n"

# Teste 2: WOD Completo (com variações)
echo -e "${BLUE}Teste 2: Criar WOD Completo com Variações${NC}"
curl -X POST "$API_URL/admin/wods/completo" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "WOD Completo - Teste 2",
    "descricao": "WOD completo com todas as variações",
    "data": "2026-01-21",
    "status": "published",
    "blocos": [
      {
        "ordem": 1,
        "tipo": "warmup",
        "titulo": "Aquecimento",
        "conteudo": "2 rounds:\n5 cal bike\n5 shoulder pass-thru\n5 air squats"
      },
      {
        "ordem": 2,
        "tipo": "strength",
        "titulo": "Back Squat",
        "conteudo": "Build to heavy single\nThen 3x3 @ 90%",
        "tempo_cap": "15 min"
      },
      {
        "ordem": 3,
        "tipo": "metcon",
        "titulo": "20 min AMRAP",
        "conteudo": "20 min AMRAP:\n8 squat clean\n12 dumbbell rows\n15 wall balls",
        "tempo_cap": "20 min"
      },
      {
        "ordem": 4,
        "tipo": "accessory",
        "titulo": "Trabalho Auxiliar",
        "conteudo": "3 rounds:\n12 good mornings\n12 barbell bench press"
      },
      {
        "ordem": 5,
        "tipo": "cooldown",
        "titulo": "Resfriamento",
        "conteudo": "Mobilidade de ombro e alongamento geral"
      }
    ],
    "variacoes": [
      {
        "nome": "RX",
        "descricao": "65/95 lbs squat clean, 25/35 lbs dumbbell, 14/20 lbs wall ball"
      },
      {
        "nome": "Scaled",
        "descricao": "45/65 lbs squat clean, 15/25 lbs dumbbell, 10/14 lbs wall ball"
      },
      {
        "nome": "Modificado",
        "descricao": "Power clean, dumbbell rows em caixa, box jumps ao invés de wall balls"
      }
    ]
  }' | json_pp

echo -e "\n${BLUE}═══════════════════════════════════════${NC}\n"

# Teste 3: Erro de Validação (sem blocos)
echo -e "${RED}Teste 3: Erro de Validação (sem blocos)${NC}"
curl -X POST "$API_URL/admin/wods/completo" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "WOD Inválido",
    "data": "2026-01-22",
    "blocos": []
  }' | json_pp

echo -e "\n${BLUE}═══════════════════════════════════════${NC}\n"

# Teste 4: Erro de Validação (sem título)
echo -e "${RED}Teste 4: Erro de Validação (sem título)${NC}"
curl -X POST "$API_URL/admin/wods/completo" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "data": "2026-01-22",
    "blocos": [
      {
        "tipo": "warmup",
        "conteudo": "Aquecimento"
      }
    ]
  }' | json_pp

echo -e "\n${BLUE}═══════════════════════════════════════${NC}\n"

# Teste 5: WOD Completo com todos os campos
echo -e "${BLUE}Teste 5: WOD Completo com Todos os Campos${NC}"
curl -X POST "$API_URL/admin/wods/completo" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "WOD Avançado - Teste 5",
    "descricao": "WOD com toda a informação estruturada para o frontend",
    "data": "2026-01-22",
    "status": "published",
    "blocos": [
      {
        "ordem": 1,
        "tipo": "warmup",
        "titulo": "Aquecimento Dinâmico",
        "conteudo": "2 rounds:\n- 10 arm circles each direction\n- 10 leg swings each leg\n- 10 inchworms\n- 5 cal bike",
        "tempo_cap": "5 min"
      },
      {
        "ordem": 2,
        "tipo": "strength",
        "titulo": "Front Squat - Força Máxima",
        "conteudo": "10 min to find a heavy single\nThen:\n3 rounds:\n- 3 front squats @ 85%\n- 2 min rest",
        "tempo_cap": "20 min"
      },
      {
        "ordem": 3,
        "tipo": "metcon",
        "titulo": "WOD Principal - 15 min AMRAP",
        "conteudo": "Partners alternate rounds:\nRound format: 30 sec work / 30 sec transition\n- Wall balls\n- Burpees\n- Box jumps\n- Medicine ball clean",
        "tempo_cap": "15 min"
      },
      {
        "ordem": 4,
        "tipo": "accessory",
        "titulo": "Trabalho Auxiliar",
        "conteudo": "Not for time:\n- 50 Glute-ham raises\n- 50 Toes-to-bar\n- 50 Box jumps",
        "tempo_cap": "10 min"
      },
      {
        "ordem": 5,
        "tipo": "cooldown",
        "titulo": "Resfriamento - 5 min",
        "conteudo": "Light stretching:\n- Hip flexor stretch\n- Hamstring stretch\n- Shoulder stretch\nDeep breathing exercise",
        "tempo_cap": "5 min"
      }
    ],
    "variacoes": [
      {
        "nome": "RX",
        "descricao": "95/135 lbs front squat, 14/20 lbs wall ball, 20/24 inch box"
      },
      {
        "nome": "Scaled",
        "descricao": "65/95 lbs front squat, 10/14 lbs wall ball, 18/20 inch box"
      },
      {
        "nome": "Beginner",
        "descricao": "45/65 lbs front squat, 8/10 lbs wall ball, box step ups"
      },
      {
        "nome": "Modificado",
        "descricao": "Goblet squat, slap ball, jump rope ao invés de box jumps"
      }
    ]
  }' | json_pp

echo -e "\n${BLUE}═══════════════════════════════════════${NC}\n"
echo -e "${GREEN}Testes concluídos!${NC}\n"

# Outras operações úteis para comparação

echo -e "${BLUE}=== Operações Úteis para Consulta ===${NC}\n"

# Listar WODs
echo -e "${BLUE}Para listar todos os WODs:${NC}"
echo "curl -X GET \"$API_URL/admin/wods\" \\"
echo "  -H \"Authorization: Bearer \$TOKEN\""
echo ""

# Ver detalhes de um WOD
echo -e "${BLUE}Para ver detalhes de um WOD (substitua ID):${NC}"
echo "curl -X GET \"$API_URL/admin/wods/1\" \\"
echo "  -H \"Authorization: Bearer \$TOKEN\""
echo ""

# Publicar um WOD
echo -e "${BLUE}Para publicar um WOD (substitua ID):${NC}"
echo "curl -X PATCH \"$API_URL/admin/wods/1/publish\" \\"
echo "  -H \"Authorization: Bearer \$TOKEN\""
echo ""

# Arquivar um WOD
echo -e "${BLUE}Para arquivar um WOD (substitua ID):${NC}"
echo "curl -X PATCH \"$API_URL/admin/wods/1/archive\" \\"
echo "  -H \"Authorization: Bearer \$TOKEN\""
echo ""
