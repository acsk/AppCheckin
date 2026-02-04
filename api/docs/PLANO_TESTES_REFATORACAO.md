# Plano de Testes - Refatoração usuario_tenant → tenant_usuario_papel

**Data:** 04 de Fevereiro de 2026  
**Versão:** 1.0  
**Status:** ✅ Migrations Executadas

---

## 🎯 Objetivo dos Testes

Validar que a refatoração que eliminou a tabela `usuario_tenant` não causou regressões e que todas as funcionalidades relacionadas a vínculos usuário-tenant continuam funcionando corretamente usando apenas `tenant_usuario_papel`.

---

## 📋 Checklist Geral

- [ ] Testes de Integração - API Endpoints
- [ ] Testes de Banco de Dados
- [ ] Testes de Login e Autenticação
- [ ] Testes de Matrícula
- [ ] Testes de Aluno
- [ ] Testes de Professor
- [ ] Testes de Check-in
- [ ] Testes de Performance
- [ ] Monitoramento de Logs

---

## 1️⃣ Testes de Banco de Dados

### 1.1 Verificar Estado das Tabelas

```bash
# Executar via Docker
docker exec -i appcheckin_mysql mysql -u root -proot appcheckin << EOF
-- Verificar que usuario_tenant não existe mais
SHOW TABLES LIKE 'usuario_tenant';

-- Verificar que o backup existe
SHOW TABLES LIKE 'usuario_tenant_backup';

-- Contar registros
SELECT COUNT(*) as backup_count FROM usuario_tenant_backup;
SELECT COUNT(*) as tenant_usuario_papel_count FROM tenant_usuario_papel;

-- Verificar índices
SHOW INDEX FROM tenant_usuario_papel WHERE Key_name LIKE 'idx_tenant_usuario_papel%';
EOF
```

**Resultado Esperado:**
- ✅ `usuario_tenant` não deve existir
- ✅ `usuario_tenant_backup` deve existir
- ✅ 3 novos índices devem estar criados

---

### 1.2 Verificar Função get_tenant_id_from_usuario

```bash
docker exec -i appcheckin_mysql mysql -u root -proot appcheckin << EOF
-- Verificar função
SELECT ROUTINE_NAME, CREATED, LAST_ALTERED 
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_NAME = 'get_tenant_id_from_usuario';

-- Testar função (assumindo que usuario_id 1 existe)
SELECT get_tenant_id_from_usuario(1) as tenant_id;
EOF
```

**Resultado Esperado:**
- ✅ Função deve estar atualizada (LAST_ALTERED = 2026-02-04)
- ✅ Deve retornar um tenant_id válido

---

### 1.3 Verificar Integridade dos Dados

```bash
docker exec -i appcheckin_mysql mysql -u root -proot appcheckin << EOF
-- Verificar se todos os usuários ativos têm vínculo
SELECT 
    u.id,
    u.nome,
    u.email,
    COUNT(tup.id) as vinculos_ativos
FROM usuarios u
LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
WHERE u.ativo = 1
GROUP BY u.id, u.nome, u.email
HAVING vinculos_ativos = 0;

-- Se retornar registros, há usuários sem vínculo ativo
EOF
```

**Resultado Esperado:**
- ✅ Nenhum registro deve ser retornado (todos os usuários ativos devem ter vínculo)

---

## 2️⃣ Testes de Login e Autenticação

### 2.1 Login de Aluno

```bash
# Substitua pelos dados reais de um aluno de teste
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "aluno@teste.com",
    "senha": "senha123"
  }' | jq
```

**Resultado Esperado:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLC...",
  "user": {
    "id": 1,
    "nome": "ALUNO TESTE",
    "email": "aluno@teste.com",
    "tenant_id": 1,
    "papel_id": 1
  }
}
```

**Validações:**
- ✅ Status 200
- ✅ Token JWT retornado
- ✅ `tenant_id` presente
- ✅ `papel_id` = 1 (aluno)

---

### 2.2 Login de Professor

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "professor@teste.com",
    "senha": "senha123"
  }' | jq
```

**Resultado Esperado:**
- ✅ Status 200
- ✅ `papel_id` = 2 (professor)

---

### 2.3 Login de Admin

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@teste.com",
    "senha": "senha123"
  }' | jq
```

**Resultado Esperado:**
- ✅ Status 200
- ✅ `papel_id` = 3 (admin)

---

## 3️⃣ Testes de Matrícula (CRÍTICO)

### 3.1 Criar Nova Matrícula

```bash
# Primeiro, fazer login como admin para obter token
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@teste.com","senha":"senha123"}' | jq -r '.token')

# Criar matrícula
curl -X POST http://localhost:8080/api/matriculas \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "aluno_id": 1,
    "plano_id": 1,
    "data_inicio": "2026-02-04",
    "observacoes": "Teste de refatoração"
  }' | jq
```

**Resultado Esperado:**
```json
{
  "message": "Matrícula realizada com sucesso",
  "matricula": {
    "id": 999,
    "aluno_id": 1,
    "plano_id": 1,
    "status_id": 5
  },
  "pagamento_criado": true
}
```

**Validações:**
- ✅ Status 201
- ✅ Matrícula criada
- ✅ Vínculo em `tenant_usuario_papel` criado automaticamente
- ✅ Primeiro pagamento criado

---

### 3.2 Verificar Vínculo Criado no Banco

```bash
docker exec -i appcheckin_mysql mysql -u root -proot appcheckin << EOF
-- Buscar a última matrícula criada
SELECT 
    m.id as matricula_id,
    m.aluno_id,
    a.usuario_id,
    tup.tenant_id,
    tup.papel_id,
    tup.ativo
FROM matriculas m
INNER JOIN alunos a ON a.id = m.aluno_id
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id AND tup.papel_id = 1
ORDER BY m.id DESC
LIMIT 1;
EOF
```

**Resultado Esperado:**
- ✅ Matrícula deve estar vinculada corretamente
- ✅ `papel_id` = 1 (aluno)
- ✅ `ativo` = 1

---

### 3.3 Listar Matrículas

```bash
curl -X GET http://localhost:8080/api/matriculas \
  -H "Authorization: Bearer $TOKEN" | jq
```

**Resultado Esperado:**
- ✅ Status 200
- ✅ Lista de matrículas retornada
- ✅ Dados completos com informações do aluno e plano

---

## 4️⃣ Testes de Aluno

### 4.1 Listar Alunos

```bash
curl -X GET http://localhost:8080/api/alunos \
  -H "Authorization: Bearer $TOKEN" | jq
```

**Resultado Esperado:**
- ✅ Status 200
- ✅ Lista de alunos retornada
- ✅ Cada aluno deve ter informações de vínculo

---

### 4.2 Criar Novo Aluno

```bash
curl -X POST http://localhost:8080/api/alunos \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "nome": "TESTE REFATORACAO",
    "email": "teste.refatoracao@teste.com",
    "senha": "senha123",
    "telefone": "11999999999",
    "cpf": "12345678901"
  }' | jq
```

**Resultado Esperado:**
- ✅ Status 201
- ✅ Aluno criado
- ✅ Vínculo criado em `tenant_usuario_papel` automaticamente

---

### 4.3 Verificar Vínculo do Novo Aluno

```bash
docker exec -i appcheckin_mysql mysql -u root -proot appcheckin << EOF
SELECT 
    u.id as usuario_id,
    u.nome,
    u.email,
    tup.tenant_id,
    tup.papel_id,
    tup.ativo
FROM usuarios u
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
WHERE u.email = 'teste.refatoracao@teste.com';
EOF
```

**Resultado Esperado:**
- ✅ Vínculo existe
- ✅ `papel_id` = 1
- ✅ `ativo` = 1

---

### 4.4 Associar Aluno Existente

```bash
# Buscar um usuário que ainda não está associado ao tenant
curl -X POST http://localhost:8080/api/alunos/associar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "cpf": "12345678901"
  }' | jq
```

**Resultado Esperado:**
- ✅ Status 200 ou 201
- ✅ Aluno associado ao tenant
- ✅ Registro criado em `tenant_usuario_papel`

---

### 4.5 Desativar Aluno

```bash
curl -X PUT http://localhost:8080/api/alunos/1/desativar \
  -H "Authorization: Bearer $TOKEN" | jq
```

**Resultado Esperado:**
- ✅ Status 200
- ✅ Campo `ativo` = 0 em `tenant_usuario_papel`

---

### 4.6 Reativar Aluno

```bash
curl -X PUT http://localhost:8080/api/alunos/1/reativar \
  -H "Authorization: Bearer $TOKEN" | jq
```

**Resultado Esperado:**
- ✅ Status 200
- ✅ Campo `ativo` = 1 em `tenant_usuario_papel`

---

## 5️⃣ Testes de Check-in

### 5.1 Realizar Check-in

```bash
# Login como aluno
TOKEN_ALUNO=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"aluno@teste.com","senha":"senha123"}' | jq -r '.token')

# Realizar check-in
curl -X POST http://localhost:8080/api/checkins \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN_ALUNO" \
  -d '{
    "turma_id": 1
  }' | jq
```

**Resultado Esperado:**
- ✅ Status 201
- ✅ Check-in registrado
- ✅ `tenant_id` preenchido automaticamente pelo trigger

---

### 5.2 Verificar tenant_id do Check-in

```bash
docker exec -i appcheckin_mysql mysql -u root -proot appcheckin << EOF
SELECT 
    c.id,
    c.aluno_id,
    c.tenant_id,
    c.turma_id,
    c.created_at
FROM checkins c
ORDER BY c.id DESC
LIMIT 1;
EOF
```

**Resultado Esperado:**
- ✅ `tenant_id` deve estar preenchido (não NULL)
- ✅ `tenant_id` deve corresponder ao tenant do aluno

---

## 6️⃣ Testes de Professor

### 6.1 Listar Professores

```bash
curl -X GET http://localhost:8080/api/professores \
  -H "Authorization: Bearer $TOKEN" | jq
```

**Resultado Esperado:**
- ✅ Status 200
- ✅ Lista de professores retornada

---

### 6.2 Criar Professor

```bash
curl -X POST http://localhost:8080/api/professores \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "nome": "PROFESSOR TESTE",
    "email": "prof.teste@teste.com",
    "senha": "senha123",
    "telefone": "11999999999"
  }' | jq
```

**Resultado Esperado:**
- ✅ Status 201
- ✅ Professor criado
- ✅ Vínculo criado com `papel_id` = 2

---

## 7️⃣ Testes de Performance

### 7.1 Benchmark de Queries

```bash
docker exec -i appcheckin_mysql mysql -u root -proot appcheckin << EOF
-- Habilitar profiling
SET profiling = 1;

-- Query 1: Buscar usuários de um tenant
SELECT u.* 
FROM usuarios u
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
WHERE tup.tenant_id = 1 AND tup.ativo = 1;

-- Query 2: Buscar alunos com vínculo
SELECT a.*, tup.papel_id, tup.ativo
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.tenant_id = 1 AND tup.papel_id = 1;

-- Mostrar profile
SHOW PROFILES;

-- Analisar query específica (substitua X pelo número da query)
SHOW PROFILE FOR QUERY 1;
SHOW PROFILE FOR QUERY 2;
EOF
```

**Resultado Esperado:**
- ✅ Queries devem executar em < 0.01s
- ✅ Índices devem ser utilizados (verificar com EXPLAIN)

---

### 7.2 Verificar Uso de Índices

```bash
docker exec -i appcheckin_mysql mysql -u root -proot appcheckin << EOF
EXPLAIN SELECT u.* 
FROM usuarios u
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
WHERE tup.tenant_id = 1 AND tup.ativo = 1;
EOF
```

**Resultado Esperado:**
- ✅ `type` = "ref" ou "index"
- ✅ `key` deve mostrar uso de índice (não NULL)

---

## 8️⃣ Monitoramento de Logs

### 8.1 Logs de Erro do PHP

```bash
# Monitorar logs em tempo real
docker logs appcheckin_php -f --tail 50
```

**Durante os testes, verificar:**
- ❌ Não deve haver erros relacionados a `usuario_tenant`
- ❌ Não deve haver "Table doesn't exist"
- ❌ Não deve haver "Column not found"

---

### 8.2 Logs de Erro do MySQL

```bash
docker exec -it appcheckin_mysql tail -f /var/log/mysql/error.log
```

**Resultado Esperado:**
- ❌ Sem erros de SQL syntax
- ❌ Sem erros de foreign key

---

### 8.3 Buscar Erros Específicos

```bash
# Buscar por referências à tabela antiga
docker logs appcheckin_php 2>&1 | grep -i "usuario_tenant" | grep -v "usuario_tenant_backup"

# Se retornar algo, há código ainda usando a tabela antiga
```

**Resultado Esperado:**
- ✅ Nenhuma referência à tabela `usuario_tenant` (apenas `usuario_tenant_backup` em logs de migração)

---

## 9️⃣ Testes de Regressão

### 9.1 Fluxo Completo de Matrícula

**Cenário:** Aluno novo → Matrícula → Pagamento → Check-in

```bash
# 1. Criar aluno
NOVO_ALUNO=$(curl -s -X POST http://localhost:8080/api/alunos \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "nome": "FLUXO COMPLETO",
    "email": "fluxo.completo@teste.com",
    "senha": "senha123",
    "telefone": "11999999999"
  }' | jq -r '.aluno.id')

echo "Aluno criado: $NOVO_ALUNO"

# 2. Criar matrícula
NOVA_MATRICULA=$(curl -s -X POST http://localhost:8080/api/matriculas \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{
    \"aluno_id\": $NOVO_ALUNO,
    \"plano_id\": 1,
    \"data_inicio\": \"2026-02-04\"
  }" | jq -r '.matricula.id')

echo "Matrícula criada: $NOVA_MATRICULA"

# 3. Login como o novo aluno
TOKEN_NOVO=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"fluxo.completo@teste.com","senha":"senha123"}' | jq -r '.token')

echo "Token obtido: ${TOKEN_NOVO:0:20}..."

# 4. Realizar check-in
curl -X POST http://localhost:8080/api/checkins \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN_NOVO" \
  -d '{"turma_id": 1}' | jq
```

**Resultado Esperado:**
- ✅ Aluno criado com sucesso
- ✅ Matrícula criada com sucesso
- ✅ Login funcionando
- ✅ Check-in registrado

---

### 9.2 Múltiplos Papéis

**Cenário:** Usuário que é aluno E professor

```bash
docker exec -i appcheckin_mysql mysql -u root -proot appcheckin << EOF
-- Criar usuário com múltiplos papéis
INSERT INTO tenant_usuario_papel (usuario_id, tenant_id, papel_id, ativo, created_at, updated_at)
VALUES 
  (999, 1, 1, 1, NOW(), NOW()),  -- Aluno
  (999, 1, 2, 1, NOW(), NOW());  -- Professor

-- Verificar
SELECT * FROM tenant_usuario_papel WHERE usuario_id = 999;

-- Testar função get_tenant_id_from_usuario (deve priorizar aluno)
SELECT get_tenant_id_from_usuario(999) as tenant_id;
EOF
```

**Resultado Esperado:**
- ✅ Ambos os vínculos criados
- ✅ Função retorna tenant_id correto (prioriza papel de aluno)

---

## 🔟 Testes de Stress (Opcional)

### 10.1 Carga de Consultas

```bash
# Instalar apache-bench se não tiver
# brew install httpd (macOS)

# 100 requisições com 10 concorrentes
ab -n 100 -c 10 -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/api/alunos
```

**Resultado Esperado:**
- ✅ Todas as requisições devem retornar 200
- ✅ Tempo médio < 100ms

---

## 📊 Relatório de Testes

Preencha conforme executa os testes:

| Categoria | Teste | Status | Observações |
|-----------|-------|--------|-------------|
| **Banco de Dados** | Estado das tabelas | ⬜ | |
| | Função atualizada | ⬜ | |
| | Integridade de dados | ⬜ | |
| **Login** | Login de aluno | ⬜ | |
| | Login de professor | ⬜ | |
| | Login de admin | ⬜ | |
| **Matrícula** | Criar matrícula | ⬜ | |
| | Vínculo criado | ⬜ | |
| | Listar matrículas | ⬜ | |
| **Aluno** | Listar alunos | ⬜ | |
| | Criar aluno | ⬜ | |
| | Associar aluno | ⬜ | |
| | Desativar aluno | ⬜ | |
| | Reativar aluno | ⬜ | |
| **Check-in** | Realizar check-in | ⬜ | |
| | tenant_id preenchido | ⬜ | |
| **Professor** | Listar professores | ⬜ | |
| | Criar professor | ⬜ | |
| **Performance** | Benchmark queries | ⬜ | |
| | Uso de índices | ⬜ | |
| **Logs** | Logs PHP | ⬜ | |
| | Logs MySQL | ⬜ | |
| **Regressão** | Fluxo completo | ⬜ | |
| | Múltiplos papéis | ⬜ | |

---

## 🚨 Critérios de Falha

**❌ Bloqueadores (impedem deploy para produção):**
- Erro 500 em qualquer endpoint crítico
- Dados inconsistentes no banco
- Impossibilidade de login
- Impossibilidade de criar matrícula
- tenant_id NULL em check-ins

**⚠️ Atenção (monitorar mas não bloqueiam):**
- Performance degradada (> 200ms em queries simples)
- Logs de warning relacionados à refatoração
- Índices não sendo utilizados

---

## ✅ Critérios de Sucesso

**Para aprovar a refatoração:**
- [ ] Todos os testes de Banco de Dados passando
- [ ] Todos os testes de Login passando
- [ ] Todos os testes de Matrícula passando
- [ ] Todos os testes de Aluno passando
- [ ] Todos os testes de Check-in passando
- [ ] Performance mantida ou melhorada
- [ ] Zero erros nos logs após 48h de monitoramento
- [ ] Fluxo completo de ponta a ponta funcionando

---

## 📝 Script de Teste Automatizado

Criei um script que executa os principais testes. Salve como `test_refatoracao.sh`:

```bash
#!/bin/bash

echo "======================================"
echo "TESTE DE REFATORAÇÃO - usuario_tenant"
echo "======================================"
echo ""

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Contadores
PASSED=0
FAILED=0

# Função para testar
test_endpoint() {
    local name=$1
    local url=$2
    local method=$3
    local data=$4
    local headers=$5
    
    echo -n "Testando $name... "
    
    if [ -z "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X $method "$url" $headers)
    else
        response=$(curl -s -w "\n%{http_code}" -X $method "$url" -H "Content-Type: application/json" $headers -d "$data")
    fi
    
    http_code=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" == "200" ] || [ "$http_code" == "201" ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $http_code)"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗ FAIL${NC} (HTTP $http_code)"
        echo "Response: $body"
        ((FAILED++))
        return 1
    fi
}

# 1. Testes de Banco
echo "1. TESTES DE BANCO DE DADOS"
echo "----------------------------"

docker exec -i appcheckin_mysql mysql -u root -proot appcheckin -e "SHOW TABLES LIKE 'usuario_tenant'" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    count=$(docker exec -i appcheckin_mysql mysql -u root -proot appcheckin -e "SHOW TABLES LIKE 'usuario_tenant'" 2>/dev/null | wc -l)
    if [ $count -le 1 ]; then
        echo -e "${GREEN}✓ usuario_tenant não existe${NC}"
        ((PASSED++))
    else
        echo -e "${RED}✗ usuario_tenant ainda existe!${NC}"
        ((FAILED++))
    fi
fi

docker exec -i appcheckin_mysql mysql -u root -proot appcheckin -e "SHOW TABLES LIKE 'usuario_tenant_backup'" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ usuario_tenant_backup existe${NC}"
    ((PASSED++))
else
    echo -e "${RED}✗ usuario_tenant_backup não existe${NC}"
    ((FAILED++))
fi

echo ""

# 2. Teste de Login
echo "2. TESTE DE LOGIN"
echo "-----------------"
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@teste.com","senha":"admin123"}' | jq -r '.token' 2>/dev/null)

if [ ! -z "$TOKEN" ] && [ "$TOKEN" != "null" ]; then
    echo -e "${GREEN}✓ Login funcionando${NC}"
    ((PASSED++))
else
    echo -e "${RED}✗ Login falhou${NC}"
    ((FAILED++))
fi

echo ""

# 3. Teste de Endpoints
echo "3. TESTES DE ENDPOINTS"
echo "----------------------"
test_endpoint "Listar Alunos" "http://localhost:8080/api/alunos" "GET" "" "-H 'Authorization: Bearer $TOKEN'"
test_endpoint "Listar Matrículas" "http://localhost:8080/api/matriculas" "GET" "" "-H 'Authorization: Bearer $TOKEN'"

echo ""
echo "======================================"
echo "RESUMO"
echo "======================================"
echo -e "Testes que passaram: ${GREEN}$PASSED${NC}"
echo -e "Testes que falharam: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ TODOS OS TESTES PASSARAM!${NC}"
    exit 0
else
    echo -e "${RED}✗ ALGUNS TESTES FALHARAM!${NC}"
    exit 1
fi
```

**Para executar:**
```bash
chmod +x test_refatoracao.sh
./test_refatoracao.sh
```

---

## 📅 Cronograma de Monitoramento

| Período | Ação | Responsável |
|---------|------|-------------|
| **Dia 0 (Hoje)** | Executar todos os testes acima | Desenvolvedor |
| **Dia 1** | Monitorar logs de erro | Desenvolvedor |
| **Dia 2** | Verificar performance | Desenvolvedor |
| **Dia 7** | Revisar métricas semanais | Tech Lead |
| **Dia 30** | Avaliar remoção do backup | Tech Lead |

---

**Documentação criada por:** GitHub Copilot  
**Data:** 04/02/2026  
**Versão:** 1.0
