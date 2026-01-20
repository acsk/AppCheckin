# Limpeza e Reset do Banco de Dados

## 📋 Visão Geral

Este documento descreve as 3 formas principais de limpar o banco de dados mantendo:
- **SuperAdmin** (role_id = 3)
- **PlanosSistema** (tabela de configuração)
- **FormasPagamento** (tabela de configuração)
- **Tenant padrão** (tenant_id = 1)

## ⚠️ AVISOS CRÍTICOS

- **DESTRUIÇÃO DE DADOS**: Todos os dados serão **permanentemente apagados**
- **SEM PRODUÇÃO**: Essas operações devem ser executadas **APENAS em desenvolvimento**
- **BACKUP RECOMENDADO**: Faça backup antes de executar qualquer limpeza
- **APENAS SUPERADMIN**: Todas as operações requerem credenciais de SuperAdmin

---

## Método 1: Endpoint API (Recomendado para Produção Dev)

### Endpoint
```
POST /superadmin/cleanup-database
```

### Headers Requeridos
```bash
Authorization: Bearer {JWT_TOKEN_SUPERADMIN}
Content-Type: application/json
```

### Resposta de Sucesso (200)
```json
{
  "status": "success",
  "message": "Banco de dados limpo com sucesso",
  "tables_cleaned": 15,
  "environment": "development",
  "timestamp": "2026-01-19 15:30:45",
  "warning": "Dados foram permanentemente apagados! Backup recomendado.",
  "maintained": [
    "SuperAdmin",
    "Planos do Sistema",
    "Formas de Pagamento",
    "Tenant padrão"
  ]
}
```

### Exemplo com cURL
```bash
# 1. Fazer login e pegar token
TOKEN=$(curl -X POST https://api.appcheckin.com.br/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@app.com", "password": "senha123"}' \
  | jq -r '.token')

# 2. Executar limpeza
curl -X POST https://api.appcheckin.com.br/superadmin/cleanup-database \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json"
```

### Características de Segurança
- ✅ Bloqueia execução em `APP_ENV=production`
- ✅ Requer autenticação via JWT
- ✅ Requer role_id = 3 (SuperAdmin)
- ✅ Retorna warning quando executado
- ✅ Mantém dados essenciais automaticamente

---

## Método 2: Script PHP Interativo (Recomendado para Dev Local)

### Localização
```
database/cleanup.php
```

### Executar
```bash
php database/cleanup.php
```

### Fluxo Interativo
```
╔════════════════════════════════════════════════════╗
║   LIMPEZA DE BANCO DE DADOS - AppCheckin API      ║
╚════════════════════════════════════════════════════╝

⚠️  AVISO: Esta operação é IRREVERSÍVEL!

Tabelas que serão limpas:
  • sessions
  • checkins
  • presenqas
  • matriculas
  • ... (16 tabelas)

Dados que serão mantidos:
  ✓ SuperAdmin (role_id = 3)
  ✓ PlanosSistema
  ✓ FormasPagamento
  ✓ Tenant padrão (id = 1)

Deseja continuar? (SIM/NÃO): SIM

[Processando...]
✓ Limpeza concluída com sucesso!
```

### Características
- ✅ Terminal com cores e formatação
- ✅ Confirmação obrigatória do usuário
- ✅ Desabilita FK checks durante execução
- ✅ Oferece opção de rollback
- ✅ Registra cada tabela processada
- ✅ Bloqueia produção automaticamente

### Código Exemplo
```php
// Seu script PHP pode usar a mesma lógica:
include 'database/cleanup.php';

// Ou para produção customizada:
$cleanup = new DatabaseCleanup($_ENV['DB_HOST'], $_ENV['DB_USER']);
$cleanup->execute();
```

---

## Método 3: SQL Direto (Para Automação/CI-CD)

### Localização do Script
```
database/migrations/999_LIMPAR_BANCO_DADOS.sql
```

### Executar via MySQL CLI
```bash
# Local
mysql -u root -p < database/migrations/999_LIMPAR_BANCO_DADOS.sql

# Servidor Hostinger (remoto)
mysql -h u304177849_api.mysql.db -u u304177849_api -p < database/migrations/999_LIMPAR_BANCO_DADOS.sql

# Ou inserir senha direto (não recomendado em produção)
mysql -h u304177849_api.mysql.db -u u304177849_api -pSENHA < database/migrations/999_LIMPAR_BANCO_DADOS.sql
```

### Executar via PHP
```php
<?php
$db = new PDO('mysql:host=localhost;dbname=appcheckin', 'root', '');
$sql = file_get_contents('database/migrations/999_LIMPAR_BANCO_DADOS.sql');
$db->exec($sql);
?>
```

### Conteúdo do SQL
O script:
1. Desabilita verificação de chaves estrangeiras
2. Limpa 16 tabelas em ordem segura
3. Deleta usuários que NÃO são SuperAdmin
4. Remove tenants alternativos
5. Limpa dados de planos de tenants removidos
6. Reabilita verificação de chaves estrangeiras

---

## Comparação dos Métodos

| Aspecto | Endpoint API | PHP Script | SQL Direto |
|---------|-------------|-----------|-----------|
| Segurança | ⭐⭐⭐ Alta | ⭐⭐ Média | ⭐ Baixa |
| Confirmação | Automática | Interativa | Nenhuma |
| Produção Safe | ✅ Sim | ✅ Sim | ❌ Não |
| Flexibilidade | Média | Alta | Baixa |
| Auditoria | ✅ Logs API | ✅ Output | ❌ Nenhuma |
| Recomendado para | Produção Dev | Dev Local | CI/CD Automatizado |

---

## ✅ Checklist Antes de Limpar

- [ ] Backup do banco criado
- [ ] Confirmado ambiente = development
- [ ] Confirmado rol do usuário = SuperAdmin
- [ ] Todos os usuários notificados
- [ ] Dados essenciais exportados se necessário
- [ ] Acesso ao servidor confirmado

---

## 🔄 Recuperação em Caso de Erro

Se algo der errado:

```bash
# 1. Restaurar do backup
mysql -u root -p < database/backup_before_migrations_20260106_120013.sql

# 2. Ou reexecutar migrations
php artisan migrate

# 3. Recriar SuperAdmin se necessário
php database/seeds/create_superadmin.php
```

---

## 📝 Logging e Auditoria

### Via Endpoint API
Cria log automático em:
```
storage/logs/cleanup-YYYY-MM-DD.log
```

### Via PHP Script
Output interativo com timestamp:
```
[2026-01-19 15:30:45] ✓ Tabela 'sessions' limpa (45 registros)
[2026-01-19 15:30:46] ✓ Tabela 'checkins' limpa (102 registros)
```

### Via SQL Direto
Sem logging automático - execute em terminal para capturar output:
```bash
mysql ... < script.sql > cleanup_log.txt 2>&1
```

---

## 🚀 Próximos Passos Após Limpeza

1. **Verificar Integridade**
   ```bash
   curl https://api.appcheckin.com.br/health
   ```

2. **Recriar SuperAdmin se Necessário**
   ```bash
   POST /auth/register
   {
     "email": "admin@app.com",
     "password": "SenhaForte123!",
     "nome": "Super Admin",
     "role_id": 3
   }
   ```

3. **Seeder de Dados Essenciais**
   ```bash
   php database/seeders/PlanosSistema.php
   php database/seeders/FormasPagamento.php
   ```

4. **Verificar Dados Mantidos**
   ```bash
   # Via endpoint
   GET /superadmin/usuarios
   GET /superadmin/planos-sistema
   GET /superadmin/formas-pagamento
   ```

---

## ⚡ Troubleshooting

### Erro: "Bloqueado em produção"
```
APP_ENV deve ser "development", nunca "production"
```

### Erro: "Apenas SuperAdmin"
```
Seu usuário não tem role_id = 3
Execute: UPDATE usuarios SET role_id = 3 WHERE id = sua_id;
```

### Erro: "Constraint Error"
```
O script SQL desabilita FK checks automaticamente
Se ainda falhar, restaure do backup
```

### Erro: "Permission Denied"
```
Endpoint API requer JWT válido
Via SSH: Confirme permissões do script

chmod +x database/cleanup.php
```

---

## 📞 Suporte

Para dúvidas sobre qual método usar:
- **Produção Dev com API**: Use Endpoint API
- **Desenvolvimento Local**: Use PHP Script
- **Automação/CI-CD**: Use SQL Direto

