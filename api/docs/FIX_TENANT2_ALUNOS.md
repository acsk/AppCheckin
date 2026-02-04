# 🔧 Correção: Alunos do Tenant 2 não aparecem

## 📋 Problema Identificado

**Sintoma:** Endpoint `GET /admin/alunos` retorna lista vazia em produção para o Tenant 2.

**Causa Raiz:** Alunos existem na tabela `alunos`, mas **não têm registros** na tabela `tenant_usuario_papel` com `papel_id=1` (papel de aluno) para o `tenant_id=2`.

### 🔍 Diagnóstico

A query do endpoint usa INNER JOIN:

```php
SELECT a.*
FROM alunos a
INNER JOIN tenant_usuario_papel tup 
    ON tup.usuario_id = a.usuario_id 
    AND tup.tenant_id = :tenant_id 
    AND tup.papel_id = 1         -- papel de aluno
    AND tup.ativo = 1
WHERE a.ativo = 1
```

**Problema:** Se um aluno não tem registro em `tenant_usuario_papel`, ele não aparece no resultado do INNER JOIN.

---

## 📊 Análise dos Dados

### Estado Atual (Produção)
- ✅ **40 alunos** na tabela `alunos` (ativo = 1)
- ❌ **Apenas ~10 registros** em `tenant_usuario_papel` para tenant_id=2 com papel_id=1
- ⚠️ **~30 alunos faltando** no tenant_usuario_papel

### Por que aconteceu?

Durante a migração de `usuario_tenant` → `tenant_usuario_papel`, apenas os registros **existentes** foram migrados. Alunos criados **sem vínculo explícito** não foram incluídos.

---

## ✅ Solução

### Passo 1: Análise (Opcional, mas recomendado)

Execute o script de análise para ver exatamente quais alunos estão faltando:

```bash
mysql -u [usuario] -p [database] < database/migrations/20260204_analise_tenant2_alunos.sql
```

Este script mostra:
- Quantos alunos existem
- Quantos estão no tenant_usuario_papel
- Lista de alunos faltantes
- Simulação da query do endpoint

### Passo 2: Correção (EXECUTAR EM PRODUÇÃO)

Execute o script de correção:

```bash
mysql -u [usuario] -p [database] < database/migrations/20260204_fix_tenant2_alunos.sql
```

**O que o script faz:**
1. ✅ Identifica todos os alunos ativos que não têm registro em `tenant_usuario_papel`
2. ✅ Insere os registros faltantes com:
   - `tenant_id = 2`
   - `papel_id = 1` (aluno)
   - `ativo = 1`
3. ✅ Verifica a integridade dos dados após inserção
4. ✅ Testa a query do endpoint para confirmar que retorna dados

### Passo 3: Validação

Após executar o script:

1. **Teste o endpoint:**
   ```bash
   curl -X GET "https://api.appcheckin.com.br/admin/alunos?page=1&limit=20" \
        -H "Authorization: Bearer SEU_TOKEN" \
        -H "X-Tenant-ID: 2"
   ```

2. **Verifique no frontend:**
   - Acesse o painel admin
   - Vá para a lista de alunos
   - Confirme que todos os 40 alunos aparecem

---

## 🔒 Segurança

✅ O script é **idempotente**: pode ser executado múltiplas vezes sem criar duplicatas.

✅ Usa `NOT EXISTS` para inserir apenas registros faltantes.

✅ Não altera registros existentes.

✅ Não deleta dados.

---

## 🎯 Prevenção Futura

Para evitar que isso aconteça novamente, garanta que:

### 1. Ao criar um aluno, sempre criar o vínculo:

```php
// Em Usuario->createUsuarioAluno() ou similar
$this->db->prepare("
    INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
    VALUES (:tenant_id, :usuario_id, 1, 1)
    ON DUPLICATE KEY UPDATE ativo = 1
")->execute([
    'tenant_id' => $tenantId,
    'usuario_id' => $usuarioId
]);
```

### 2. Validar na matricula:

```php
// Em MatriculaController->criarMatricula()
// Já existe um trecho que faz isso automaticamente
$stmtVinculo = $db->prepare("
    SELECT * FROM tenant_usuario_papel 
    WHERE usuario_id = ? AND tenant_id = ? AND papel_id = 1
");
$stmtVinculo->execute([$usuarioId, $tenantId]);
$vinculo = $stmtVinculo->fetch();

if (!$vinculo) {
    // Criar vínculo automaticamente
    $stmtCriarVinculo = $db->prepare("
        INSERT INTO tenant_usuario_papel (usuario_id, tenant_id, papel_id, ativo, created_at)
        VALUES (?, ?, 1, 1, NOW())
    ");
    $stmtCriarVinculo->execute([$usuarioId, $tenantId]);
}
```

---

## 📝 Checklist de Execução

- [ ] 1. Fazer backup do banco de dados antes de executar
- [ ] 2. Executar análise: `20260204_analise_tenant2_alunos.sql`
- [ ] 3. Revisar output da análise (quantos faltam)
- [ ] 4. Executar correção: `20260204_fix_tenant2_alunos.sql`
- [ ] 5. Conferir output (quantos foram inseridos)
- [ ] 6. Testar endpoint via curl ou Postman
- [ ] 7. Testar no frontend (painel admin)
- [ ] 8. Validar que todos os 40 alunos aparecem
- [ ] 9. Verificar logs do servidor (sem erros)
- [ ] 10. Monitorar por 24h após correção

---

## 🆘 Se Problema Persistir

Se após executar os scripts os alunos ainda não aparecerem:

### 1. Verificar autenticação
```bash
# Conferir se o token é válido e tem tenant_id=2
SELECT * FROM usuarios WHERE id = <seu_usuario_id>;
```

### 2. Verificar middlewares
- AdminMiddleware deve estar validando tenant_id corretamente
- Verificar logs do PHP para ver qual tenant_id está sendo usado

### 3. Verificar cache
```bash
# Limpar cache do opcache (se habilitado)
# ou reiniciar PHP-FPM
sudo systemctl restart php-fpm
```

### 4. Verificar resposta da API
```bash
# Ver resposta completa (com headers)
curl -v -X GET "https://api.appcheckin.com.br/admin/alunos?page=1&limit=20" \
     -H "Authorization: Bearer SEU_TOKEN" \
     -H "X-Tenant-ID: 2"
```

---

## 📞 Suporte

Se precisar de ajuda:
1. Execute o script de análise e envie o output
2. Verifique logs do PHP: `/var/log/php-fpm/error.log`
3. Verifique logs do MySQL: `SHOW ENGINE INNODB STATUS;`

---

**Criado em:** 04/02/2026  
**Versão:** 1.0  
**Status:** Pronto para execução em produção
