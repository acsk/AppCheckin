# Script de Testes - Endpoints de Perfil

## Pré-requisitos
1. Backend rodando em http://localhost:8080
2. Usuário de teste criado (teste@exemplo.com / password123)
3. Token JWT válido

## Passo 1: Fazer Login e Obter Token
```bash
TOKEN=$(curl -s -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "teste@exemplo.com",
    "senha": "password123"
  }' | jq -r '.token')

echo "Token obtido: $TOKEN"
```

## Passo 2: Verificar Dados Atuais
```bash
curl -X GET http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" | jq
```

## Passo 3: Atualizar Nome
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Usuário Teste Atualizado"
  }' | jq
```

## Passo 4: Atualizar Email
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "teste.atualizado@exemplo.com"
  }' | jq
```

## Passo 5: Atualizar Senha
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "senha": "novaSenha123"
  }' | jq
```

## Passo 6: Testar Login com Nova Senha
```bash
curl -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "teste.atualizado@exemplo.com",
    "senha": "novaSenha123"
  }' | jq
```

## Passo 7: Criar Foto Base64 de Teste (Imagem Simples)
```bash
# Criar uma imagem PNG simples 1x1 em base64
FOTO_BASE64="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="

echo "Foto base64 criada (1x1 pixel vermelho)"
```

## Passo 8: Atualizar com Foto
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"foto_base64\": \"$FOTO_BASE64\"
  }" | jq
```

## Passo 9: Verificar Foto Atualizada
```bash
curl -X GET http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" | jq '.foto_base64'
```

## Passo 10: Obter Estatísticas do Usuário
```bash
# Assumindo que o usuário tem ID 1
curl -X GET http://localhost:8080/usuarios/1/estatisticas \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" | jq
```

## Passo 11: Testar Validações - Email Inválido
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "email-invalido"
  }' | jq
```

**Resposta esperada:**
```json
{
  "errors": ["Email inválido"]
}
```

## Passo 12: Testar Validações - Senha Curta
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "senha": "123"
  }' | jq
```

**Resposta esperada:**
```json
{
  "errors": ["Senha deve ter no mínimo 6 caracteres"]
}
```

## Passo 13: Testar Validações - Foto com Formato Inválido
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "foto_base64": "apenas-texto-sem-formato"
  }' | jq
```

**Resposta esperada:**
```json
{
  "errors": ["Formato de imagem inválido. Use data:image/[tipo];base64,[dados]"]
}
```

## Passo 14: Remover Foto
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "foto_base64": ""
  }' | jq
```

## Passo 15: Restaurar Dados Originais
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Usuario Teste",
    "email": "teste@exemplo.com",
    "senha": "password123"
  }' | jq
```

---

## Script Completo Automatizado

Salve como `test_perfil.sh`:

```bash
#!/bin/bash

echo "=== TESTE DE ENDPOINTS DE PERFIL ==="
echo ""

# 1. Login
echo "1. Fazendo login..."
TOKEN=$(curl -s -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "teste@exemplo.com",
    "senha": "password123"
  }' | jq -r '.token')

if [ "$TOKEN" = "null" ] || [ -z "$TOKEN" ]; then
  echo "❌ Erro ao obter token. Verifique as credenciais."
  exit 1
fi

echo "✅ Token obtido com sucesso"
echo ""

# 2. Verificar dados atuais
echo "2. Verificando dados atuais..."
curl -s -X GET http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" | jq
echo ""

# 3. Atualizar nome
echo "3. Atualizando nome..."
curl -s -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Usuário Teste Atualizado"
  }' | jq '.message'
echo ""

# 4. Adicionar foto de teste
echo "4. Adicionando foto de teste (1x1 pixel)..."
FOTO_BASE64="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="

curl -s -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"foto_base64\": \"$FOTO_BASE64\"
  }" | jq '.message'
echo ""

# 5. Verificar estatísticas
echo "5. Verificando estatísticas..."
curl -s -X GET http://localhost:8080/usuarios/1/estatisticas \
  -H "Authorization: Bearer $TOKEN" | jq
echo ""

# 6. Testar validação - email inválido
echo "6. Testando validação - email inválido..."
curl -s -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "email-invalido"
  }' | jq
echo ""

# 7. Testar validação - senha curta
echo "7. Testando validação - senha curta..."
curl -s -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "senha": "123"
  }' | jq
echo ""

# 8. Restaurar dados originais
echo "8. Restaurando dados originais..."
curl -s -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Usuario Teste",
    "email": "teste@exemplo.com",
    "senha": "password123",
    "foto_base64": ""
  }' | jq '.message'
echo ""

echo "=== TESTES CONCLUÍDOS ==="
```

Execute com:
```bash
chmod +x test_perfil.sh
./test_perfil.sh
```

---

## Verificação no Banco de Dados

```bash
docker exec -it appcheckin_mysql mysql -uroot -proot appcheckin -e "
  SELECT id, nome, email, 
         CASE 
           WHEN foto_base64 IS NULL THEN 'SEM FOTO'
           WHEN foto_base64 = '' THEN 'FOTO VAZIA'
           ELSE CONCAT('FOTO (', LENGTH(foto_base64), ' bytes)')
         END as foto_status,
         updated_at 
  FROM usuarios 
  WHERE email LIKE '%teste%';"
```

---

## Troubleshooting

### Erro: Token inválido
- Faça login novamente para obter um novo token
- Verifique se o token não expirou (validade: 24h)

### Erro: Email já cadastrado
- Use um email diferente ou exclua o usuário duplicado do banco

### Erro: Imagem muito grande
- Redimensione a imagem antes de converter para base64
- Recomendado: máximo 500x500px para fotos de perfil

### Erro: Formato de imagem inválido
- Certifique-se de usar o formato: `data:image/{tipo};base64,{dados}`
- Tipos válidos: jpeg, jpg, png, gif, webp
