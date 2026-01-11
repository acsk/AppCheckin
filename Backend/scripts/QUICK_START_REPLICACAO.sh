#!/usr/bin/env bash
# Quick Start: Endpoint de Replicação de Turmas

# ============================================================================
# REPLICAR TURMAS PARA DIAS DA SEMANA
# ============================================================================

# Você tem turmas agendadas em 2026-01-09 (quinta-feira) e quer replicar
# para todas as outras quintas do mês?

# 1. Encontre o dia_id (pode fazer uma chamada a GET /admin/turmas)
DIA_ID=17

# 2. Defina os dias da semana (1=dom, 2=seg, 3=ter, 4=qua, 5=qui, 6=sex, 7=sab)
DIAS_SEMANA='[5]'  # Apenas quintas

# 3. Defina o mês (opcional, padrão é o mês atual)
MES='2026-01'

# 4. Chamar o endpoint (obtenha seu token JWT primeiro)
TOKEN='seu_token_jwt_aqui'

curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{
    \"dia_id\": $DIA_ID,
    \"dias_semana\": $DIAS_SEMANA,
    \"mes\": \"$MES\"
  }"

# ============================================================================
# EXEMPLOS
# ============================================================================

# Exemplo 1: Replicar para múltiplos dias da semana
# Replicar turmas de segunda-feira (2026-01-05) para seg/qua/sex de janeiro
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "dia_id": 16,
    "dias_semana": [2, 4, 6],
    "mes": "2026-01"
  }'

# Exemplo 2: Replicar apenas para o próximo mês
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "dia_id": 17,
    "dias_semana": [5],
    "mes": "2026-02"
  }'

# Exemplo 3: Replicar para o mês atual (omitir "mes")
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "dia_id": 17,
    "dias_semana": [5]
  }'

# ============================================================================
# INTERPRETANDO A RESPOSTA
# ============================================================================

# Sucesso: HTTP 201
# {
#   "type": "success",
#   "message": "Replicação concluída com sucesso",
#   "summary": {
#     "total_solicitadas": 3,    // 3 turmas do dia origem
#     "total_criadas": 9,         // 9 turmas criadas (3 turmas × 3 dias)
#     "total_puladas": 0,         // 0 conflitos
#     "dias_destino": 3           // 3 dias encontrados
#   }
# }

# ============================================================================
# DOCUMENTAÇÃO COMPLETA
# ============================================================================

# Para documentação técnica completa:
# cat REPLICAR_TURMAS_API.md

# Para exemplos práticos e cenários:
# cat EXEMPLO_REPLICACAO_TURMAS.md

# Para resumo da implementação:
# cat RESUMO_REPLICACAO_TURMAS.md

# ============================================================================
# DIAS DA SEMANA (DAYOFWEEK no MySQL)
# ============================================================================

# 1 = Domingo
# 2 = Segunda-feira
# 3 = Terça-feira
# 4 = Quarta-feira
# 5 = Quinta-feira
# 6 = Sexta-feira
# 7 = Sábado

# ============================================================================
# COMO OBTER O DIA_ID
# ============================================================================

# Chamada GET para listar todos os dias:
curl http://localhost:8080/admin/dias \
  -H "Authorization: Bearer $TOKEN" | jq '.dias[] | {id, data}'

# Ou para um dia específico:
# curl "http://localhost:8080/admin/dias?data=2026-01-09" \
#   -H "Authorization: Bearer $TOKEN"

# ============================================================================
# DICAS & BOAS PRÁTICAS
# ============================================================================

# ✅ REPLICAR TURMAS DE UM DIA PARA MÚLTIPLOS DIAS
# Use caso: "Temos aulas de CrossFit seg/qua/sex, cria tudo em 1 request"
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "dia_id": 16,
    "dias_semana": [2, 4, 6],
    "mes": "2026-03"
  }'

# ✅ REPLICAR PARA O PRÓXIMO MÊS
# Use caso: "Vou replicar as turmas de janeiro para fevereiro"
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "dia_id": 17,
    "dias_semana": [5],
    "mes": "2026-02"
  }'

# ⚠️ CONFLITOS SÃO AUTOMATICAMENTE EVITADOS
# Se uma turma já existe no horário, ela é pulada.
# Mas as outras turmas continuam sendo criadas normalmente!

# ============================================================================
# TROUBLESHOOTING
# ============================================================================

# Erro: "dia_id e dias_semana são obrigatórios"
# → Verifique se está enviando dia_id e dias_semana no JSON

# Erro: "dias_semana deve conter valores entre 1 e 7"
# → Os valores em dias_semana devem estar entre 1 (dom) e 7 (sab)

# Erro: "Token inválido ou expirado"
# → Faça login primeiro para obter um token JWT válido

# Nenhuma turma criada?
# → Verifique se o dia_id tem turmas (GET /admin/turmas/dia/{diaId})
# → Verifique se há dias no mês especificado com o dia da semana desejado

# ============================================================================
# PRÓXIMOS PASSOS
# ============================================================================

# 1. Testar com seus dados reais
# 2. Integrá a UI frontend (painel admin)
# 3. Automação: usar em scripts/cron jobs
# 4. Monitorar logs de API para erros

# ============================================================================
