#!/bin/bash

# ============================================================
# Script para popular usuários de teste
# ============================================================

set -e

USUARIO_TESTE="teste@example.com"
SENHA_TESTE="senha123"

# Hash bcrypt para "senha123" (gerado com password_hash em PHP)
# Use o comando: php -r "echo password_hash('senha123', PASSWORD_BCRYPT);"
HASH_BCRYPT='$2y$10$ZIb/CnBLtVQ6sR8Qx4yKJO7v0xZqxZqxZqxZqxZqxZqxZqxZqxZqx2'

echo "🔧 Populando usuários de teste..."

# Conectar ao MySQL e executar INSERT
docker-compose exec -T mysql mysql -u root -proot appcheckin << EOF

-- Limpar usuários de teste existentes
DELETE FROM usuarios WHERE email = '$USUARIO_TESTE';

-- Inserir usuário de teste (Aluno)
INSERT INTO usuarios (
  nome, 
  email, 
  email_global,
  senha_hash,
  role_id,
  tenant_id,
  ativo,
  created_at,
  updated_at
) VALUES (
  'Usuário Teste',
  '$USUARIO_TESTE',
  '$USUARIO_TESTE',
  '$HASH_BCRYPT',
  1,
  1,
  1,
  NOW(),
  NOW()
);

-- Inserir Super Admin de teste
INSERT INTO usuarios (
  nome, 
  email, 
  email_global,
  senha_hash,
  role_id,
  tenant_id,
  ativo,
  created_at,
  updated_at
) VALUES (
  'Super Admin Teste',
  'admin@example.com',
  'admin@example.com',
  '$HASH_BCRYPT',
  3,
  1,
  1,
  NOW(),
  NOW()
);

-- Inserir Tenant Admin de teste
INSERT INTO usuarios (
  nome, 
  email, 
  email_global,
  senha_hash,
  role_id,
  tenant_id,
  ativo,
  created_at,
  updated_at
) VALUES (
  'Gerenciador Academia',
  'gerenciador@example.com',
  'gerenciador@example.com',
  '$HASH_BCRYPT',
  2,
  1,
  1,
  NOW(),
  NOW()
);

SELECT 'Usuários criados com sucesso!' as resultado;

EOF

echo "✅ Usuários de teste inseridos!"
echo ""
echo "Credenciais de teste:"
echo "  Email: $USUARIO_TESTE"
echo "  Senha: $SENHA_TESTE"
echo ""
