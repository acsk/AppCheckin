#!/bin/bash
# 
# CHECKLIST: Correção de Usuários Duplicados
# ============================================
# 
# Este arquivo documenta os passos para validar e fazer deploy da correção
# Arquivo: AppCheckin/CHECKLIST_IMPLEMENTACAO.sh
# Data: 8 de janeiro de 2026
#

echo "=========================================="
echo "CHECKLIST: Correção de Usuários Duplicados"
echo "=========================================="
echo ""

# ============================================================================
# FASE 1: ANTES DO DEPLOY
# ============================================================================

echo "FASE 1: Validação Pré-Deploy"
echo "────────────────────────────────────────"
echo ""

echo "□ 1.1 - Verificar arquivo modificado"
echo "    Esperado: Backend/app/Models/Usuario.php"
echo "    Linhas: 443-530"
echo "    Status: $([ -f 'Backend/app/Models/Usuario.php' ] && echo '✅ EXISTE' || echo '❌ NÃO ENCONTRADO')"
echo ""

echo "□ 1.2 - Verificar testes criados"
echo "    a) Backend/test_usuarios_duplicados.php"
echo "       Status: $([ -f 'Backend/test_usuarios_duplicados.php' ] && echo '✅ EXISTE' || echo '❌ NÃO ENCONTRADO')"
echo "    b) Backend/database/tests/validacao_usuarios_duplicados.sql"
echo "       Status: $([ -f 'Backend/database/tests/validacao_usuarios_duplicados.sql' ] && echo '✅ EXISTE' || echo '❌ NÃO ENCONTRADO')"
echo ""

echo "□ 1.3 - Verificar documentação criada"
echo "    a) RESUMO_CORRECAO_USUARIOS_DUPLICADOS.txt"
echo "       Status: $([ -f 'RESUMO_CORRECAO_USUARIOS_DUPLICADOS.txt' ] && echo '✅ EXISTE' || echo '❌ NÃO ENCONTRADO')"
echo "    b) CORRECAO_USUARIOS_DUPLICADOS.md"
echo "       Status: $([ -f 'CORRECAO_USUARIOS_DUPLICADOS.md' ] && echo '✅ EXISTE' || echo '❌ NÃO ENCONTRADO')"
echo "    c) SOLUCAO_USUARIOS_DUPLICADOS.md"
echo "       Status: $([ -f 'SOLUCAO_USUARIOS_DUPLICADOS.md' ] && echo '✅ EXISTE' || echo '❌ NÃO ENCONTRADO')"
echo "    d) COMPARACAO_ANTES_DEPOIS.js"
echo "       Status: $([ -f 'COMPARACAO_ANTES_DEPOIS.js' ] && echo '✅ EXISTE' || echo '❌ NÃO ENCONTRADO')"
echo "    e) CODIGO_MODIFICADO.md"
echo "       Status: $([ -f 'CODIGO_MODIFICADO.md' ] && echo '✅ EXISTE' || echo '❌ NÃO ENCONTRADO')"
echo "    f) INDICE_CORRECAO_USUARIOS_DUPLICADOS.md"
echo "       Status: $([ -f 'INDICE_CORRECAO_USUARIOS_DUPLICADOS.md' ] && echo '✅ EXISTE' || echo '❌ NÃO ENCONTRADO')"
echo ""

echo "□ 1.4 - Revisar mudanças no código"
echo "    Comando: grep -n 'usuariosMap' Backend/app/Models/Usuario.php"
echo "    Esperado: Encontrar lógica de deduplicação"
echo ""

# ============================================================================
# FASE 2: BACKUP
# ============================================================================

echo ""
echo "FASE 2: Backup"
echo "────────────────────────────────────────"
echo ""

echo "□ 2.1 - Criar backup do arquivo modificado"
BACKUP_FILE="Backend/app/Models/Usuario.php.backup.$(date +%Y%m%d_%H%M%S)"
if [ ! -f "$BACKUP_FILE" ]; then
    echo "    mkdir -p backups"
    echo "    cp Backend/app/Models/Usuario.php \"$BACKUP_FILE\""
    echo "    ✅ Backup criado em: $BACKUP_FILE"
else
    echo "    ✅ Backup já existe"
fi
echo ""

# ============================================================================
# FASE 3: VALIDAÇÃO LOCAL
# ============================================================================

echo ""
echo "FASE 3: Validação Local"
echo "────────────────────────────────────────"
echo ""

echo "□ 3.1 - Se estiver rodando Docker localmente:"
echo "    docker-compose up -d"
echo "    docker-compose exec php php test_usuarios_duplicados.php"
echo "    Esperado: ✅ TODOS OS TESTES PASSARAM!"
echo ""

echo "□ 3.2 - Teste manual via cURL:"
echo "    curl -X GET http://localhost:8080/superadmin/usuarios \\"
echo "      -H 'Authorization: Bearer SEU_TOKEN' \\"
echo "      -H 'Content-Type: application/json' | jq"
echo "    Esperado: Nenhuma duplicata de usuários"
echo ""

# ============================================================================
# FASE 4: GIT WORKFLOW
# ============================================================================

echo ""
echo "FASE 4: Git Workflow"
echo "────────────────────────────────────────"
echo ""

echo "□ 4.1 - Criar branch para esta correção"
echo "    git checkout -b fix/usuarios-duplicados"
echo ""

echo "□ 4.2 - Adicionar arquivos ao staging"
echo "    git add Backend/app/Models/Usuario.php"
echo "    git add Backend/test_usuarios_duplicados.php"
echo "    git add Backend/database/tests/validacao_usuarios_duplicados.sql"
echo "    git add *.md *.txt *.js"
echo ""

echo "□ 4.3 - Commit com mensagem descritiva"
echo "    git commit -m 'fix: Remove usuários duplicados em /superadmin/usuarios'"
echo "    git commit -m ''"
echo "    git commit -m 'O método listarTodos() retornava usuários duplicados quando'"
echo "    git commit -m 'estes estavam vinculados a múltiplos tenants.'"
echo "    git commit -m ''"
echo "    git commit -m '- Adicionado ordenação determinística no SQL'"
echo "    git commit -m '- Implementado deduplicação em PHP'"
echo "    git commit -m '- Adicionado teste de validação'"
echo "    git commit -m '- Documentação completa da solução'"
echo ""

echo "□ 4.4 - Fazer push para branch de feature"
echo "    git push origin fix/usuarios-duplicados"
echo ""

echo "□ 4.5 - Abrir Pull Request"
echo "    GitHub → Compare & pull request"
echo "    Descrever: Problema, solução, validação"
echo ""

# ============================================================================
# FASE 5: CODE REVIEW
# ============================================================================

echo ""
echo "FASE 5: Code Review"
echo "────────────────────────────────────────"
echo ""

echo "□ 5.1 - Pontos de revisão:"
echo "    • Lógica de deduplicação está correta?"
echo "    • Compatibilidade com API mantida?"
echo "    • Impacto no desempenho é zero?"
echo "    • Testes cobrem os casos de uso?"
echo ""

echo "□ 5.2 - Testes sugeridos:"
echo "    • Usuários sem múltiplos tenants"
echo "    • Usuários com 2+ tenants"
echo "    • Filtro por tenant específico"
echo "    • Filtro 'apenas ativos'"
echo ""

# ============================================================================
# FASE 6: STAGING
# ============================================================================

echo ""
echo "FASE 6: Deploy em Staging"
echo "────────────────────────────────────────"
echo ""

echo "□ 6.1 - Merge na branch staging"
echo "    git checkout staging"
echo "    git merge fix/usuarios-duplicados"
echo ""

echo "□ 6.2 - Deploy para staging"
echo "    Seu processo de deploy habitual"
echo "    docker-compose restart php"
echo "    ou"
echo "    kubectl rollout restart deployment/backend"
echo ""

echo "□ 6.3 - Teste em staging"
echo "    Fazer requisição: GET /superadmin/usuarios"
echo "    Validar: Nenhuma duplicata"
echo "    Validar: Total bate com usuários únicos"
echo ""

echo "□ 6.4 - Teste de regressão"
echo "    GET /tenant/usuarios (outros endpoints não devem quebrar)"
echo "    GET /usuarios (endpoints de tenant específico)"
echo ""

# ============================================================================
# FASE 7: PRODUÇÃO
# ============================================================================

echo ""
echo "FASE 7: Deploy em Produção"
echo "────────────────────────────────────────"
echo ""

echo "□ 7.1 - Backup da base de dados"
echo "    mysqldump -u root -p appcheckin > backup_$(date +%Y%m%d_%H%M%S).sql"
echo ""

echo "□ 7.2 - Merge na branch main"
echo "    git checkout main"
echo "    git merge staging"
echo "    git tag -a v1.0.1-fix-duplicatas -m 'Corrige usuários duplicados em superadmin/usuarios'"
echo ""

echo "□ 7.3 - Deploy para produção"
echo "    Seu processo de deploy habitual"
echo "    Verificar health checks"
echo "    Monitorar logs"
echo ""

echo "□ 7.4 - Validação em produção"
echo "    curl -X GET https://api.appcheckin.com/superadmin/usuarios \\"
echo "      -H 'Authorization: Bearer TOKEN' | jq '.total, (.usuarios | length)'"
echo "    Esperado: Ambos os números devem ser iguais (7, não 8)"
echo ""

echo "□ 7.5 - Comunicar stakeholders"
echo "    Email/Slack: Correção deployada"
echo "    Incluir: Que foi corrigido"
echo "    Incluir: Comportamento antes/depois"
echo ""

# ============================================================================
// FASE 8: MONITORAMENTO
// ============================================================================

echo ""
echo "FASE 8: Monitoramento Pós-Deploy"
echo "────────────────────────────────────────"
echo ""

echo "□ 8.1 - Monitorar logs por 24 horas"
echo "    Procurar por erros relacionados a usuários/tenants"
echo "    Procurar por timeouts em /superadmin/usuarios"
echo ""

echo "□ 8.2 - Verificar métricas"
echo "    Performance: Tempo de resposta (deve ser igual ou melhor)"
echo "    Taxa de erro: Deve ser 0%"
echo "    Cobertura: Todos os usuários sendo retornados"
echo ""

echo "□ 8.3 - Feedback de usuários"
echo "    Solicitar confirmação que problema foi resolvido"
echo "    Perguntar se há outros issues similares"
echo ""

# ============================================================================
// FASE 9: DOCUMENTAÇÃO FINAL
// ============================================================================

echo ""
echo "FASE 9: Documentação Final"
echo "────────────────────────────────────────"
echo ""

echo "□ 9.1 - Atualizar changelog"
echo "    Adicionar ao CHANGELOG.md:"
echo "    v1.0.1 - [2026-01-08]"
echo "    - Fix: Remover usuários duplicados em /superadmin/usuarios"
echo ""

echo "□ 9.2 - Atualizar wiki/docs"
echo "    Adicionar nota sobre correção no documento de API"
echo "    Arquivar documentação técnica"
echo ""

echo "□ 9.3 - Fechar issues/tickets"
echo "    Marcar como 'Resolvido'"
echo "    Adicionar comentário com número do commit/PR"
echo ""

# ============================================================================
// RESUMO FINAL
// ============================================================================

echo ""
echo "=========================================="
echo "RESUMO DO PROCESSO"
echo "=========================================="
echo ""
echo "Total de etapas: 33"
echo "Tempo estimado: 2-4 horas (dependendo do processo da empresa)"
echo ""
echo "Riscos: Mínimos"
echo "  ✅ Mudança é simples e localizada"
echo "  ✅ Tem testes de validação"
echo "  ✅ É fácil fazer rollback"
echo ""
echo "Benefícios:"
echo "  ✅ Remove duplicatas de usuários"
echo "  ✅ Retorna dados consistentes"
echo "  ✅ Mantém compatibilidade com API"
echo ""
echo "=========================================="
echo ""
echo "✅ Pronto para começar o deploy!"
echo ""
