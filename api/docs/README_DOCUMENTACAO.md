# 📖 Documentação AppCheckin API

Os documentos de turmas foram centralizados em [apiV2/docs/turmas](../../apiV2/docs/turmas/README.md).
Os arquivos marcados como históricos nessa pasta descrevem apenas a API Slim antiga.

## 🚀 Comece Aqui

### Para Novo Desenvolvedor
1. Leia [API_QUICK_REFERENCE.md](./API_QUICK_REFERENCE.md) - Endpoints mais usados
2. Veja [ARCHITECTURE.md](./ARCHITECTURE.md) - Estrutura do projeto
3. Teste [GUIA_TESTES.md](./GUIA_TESTES.md) - Como testar endpoints

### Para Deploy/Produção
1. Leia [CHECKLIST_IMPLANTACAO.md](./CHECKLIST_IMPLANTACAO.md)
2. Configure [.env.production](../.env.production)
3. Execute migrations: `php database/migrations/`
4. Verifique health: `curl https://api.appcheckin.com.br/health`

### Para Manutenção/Admin
1. Leia [GUIA_MANUTENCAO.md](./GUIA_MANUTENCAO.md) - Procedimentos diários
2. Para limpar banco: [LIMPEZA_BANCO_DADOS.md](./LIMPEZA_BANCO_DADOS.md)
3. Resumo de ferramentas: [RESUMO_GERENCIAMENTO_BANCO.md](./RESUMO_GERENCIAMENTO_BANCO.md)

---

## 📚 Índice Completo de Documentação

### 🏗️ Arquitetura & Estrutura
- [ARCHITECTURE.md](./ARCHITECTURE.md) - Arquitetura geral da aplicação
- [ESTRUTURA_PASTAS.md](./ESTRUTURA_PASTAS.md) - Organização dos diretórios
- [ESTRUTURA_AULAS.md](./ESTRUTURA_AULAS.md) - Estrutura de dados de aulas

### 🔧 Setup & Configuração
- [LEIA_PRIMEIRO.md](./LEIA_PRIMEIRO.md) - Instruções iniciais
- [IMPLEMENTATION_GUIDE.md](./IMPLEMENTATION_GUIDE.md) - Guia de implementação
- [QUICK_START.md](./QUICK_START.md) - Quick start em 5 minutos
- [QUICKSTART_MULTITENANT.md](./QUICKSTART_MULTITENANT.md) - Setup multi-tenant

### 🚀 Deploy & Implantação
- [CHECKLIST_IMPLANTACAO.md](./CHECKLIST_IMPLANTACAO.md) - Checklist para produção
- [CHECKLIST_IMPLEMENTACAO.md](./CHECKLIST_IMPLEMENTACAO.md) - Checklist de implementação
- [1-LEIA-PRIMEIRO-ERRO-BANCO.md](./1-LEIA-PRIMEIRO-ERRO-BANCO.md) - Erros comuns de banco

### 🛠️ Manutenção & Gerenciamento
- **[GUIA_MANUTENCAO.md](./GUIA_MANUTENCAO.md)** ⭐ NOVO - Procedimentos de manutenção
- **[LIMPEZA_BANCO_DADOS.md](./LIMPEZA_BANCO_DADOS.md)** ⭐ NOVO - 3 formas de limpar banco
- **[RESUMO_GERENCIAMENTO_BANCO.md](./RESUMO_GERENCIAMENTO_BANCO.md)** ⭐ NOVO - Resumo de ferramentas
- [FRONTEND_QUICK_START.md](./FRONTEND_QUICK_START.md) - Quick start do frontend

### 📱 API & Endpoints
- [API_QUICK_REFERENCE.md](./API_QUICK_REFERENCE.md) - Referência rápida de endpoints
- [DASHBOARD_ENDPOINTS.md](./DASHBOARD_ENDPOINTS.md) - Endpoints do dashboard
- [API_MOBILE_ENDPOINTS.md](./API_MOBILE_ENDPOINTS.md) - Endpoints mobile
- [MOBILE_WOD_ENDPOINT.md](./MOBILE_WOD_ENDPOINT.md) - Endpoints de WOD
- [ENDPOINT_DETALHES_TURMA.md](../../apiV2/docs/turmas/legacy-api/ENDPOINT_DETALHES_TURMA.md) - Detalhes de turma (API Slim antiga)

### 🎓 Funcionalidades Específicas
- [CONTROLE_PRESENCA.md](./CONTROLE_PRESENCA.md) - Sistema de presença
- [CHECKLIST_IMPLEMENTACAO.md](./CHECKLIST_IMPLEMENTACAO.md) - Implementação de funcionalidades
- [ABATER_CREDITO_PAGAMENTO.md](./ABATER_CREDITO_PAGAMENTO.md) - Sistema de créditos
- [DESFAZER_CHECKIN.md](./DESFAZER_CHECKIN.md) - Desfazer check-ins
- [DESATIVAR_TURMAS_E_DIAS.md](../../apiV2/docs/turmas/legacy-api/DESATIVAR_TURMAS_E_DIAS.md) - Desativar turmas (API Slim antiga)

### 💳 Pagamentos
- [IMPLEMENTACAO_PAGAMENTOS.md](./IMPLEMENTACAO_PAGAMENTOS.md) - Sistema de pagamentos
- [FUNCIONALIDADE_BAIXA_PARCELAS.md](./FUNCIONALIDADE_BAIXA_PARCELAS.md) - Baixa de parcelas
- [GUIA_FRONTEND_CREDITO.md](./GUIA_FRONTEND_CREDITO.md) - Frontend de crédito

### 🔄 Dados & Migrações
- [EXECUTAR_MIGRATIONS_WOD.md](./EXECUTAR_MIGRATIONS_WOD.md) - Executar migrations WOD
- [EXECUTAR_SEED.md](./EXECUTAR_SEED.md) - Executar seeders
- [FALTANDO_MIGRATIONS.md](./FALTANDO_MIGRATIONS.md) - Migrations faltantes
- [MIGRATION_058_NOTES.md](./MIGRATION_058_NOTES.md) - Notas de migration
- [DOCUMENTACAO_REPLICACAO.md](../../apiV2/docs/turmas/legacy-api/DOCUMENTACAO_REPLICACAO.md) - Índice histórico de replicação

### 🐛 Troubleshooting
- [GUIA_TESTES.md](./GUIA_TESTES.md) - Guia completo de testes
- [ANALISE_CHECKIN_TURMA.md](../../apiV2/docs/turmas/legacy-api/ANALISE_CHECKIN_TURMA.md) - Análise de check-ins (API Slim antiga)
- [CONCLUSAO.md](./CONCLUSAO.md) - Conclusões e lições aprendidas
- [RELATORIO_FINAL.md](./RELATORIO_FINAL.md) - Relatório final

### 📊 Multi-Tenant
- [QUICKSTART_MULTITENANT.md](./QUICKSTART_MULTITENANT.md) - Setup multi-tenant
- [ANALISE_CONSTRAINTS_USUARIO.md](./ANALISE_CONSTRAINTS_USUARIO.md) - Constraints de usuários
- [CORRECAO_DIAS_TENANT.md](./CORRECAO_DIAS_TENANT.md) - Correção de dias por tenant
- [EXEMPLO_REPLICACAO_TURMAS.md](../../apiV2/docs/turmas/legacy-api/EXEMPLO_REPLICACAO_TURMAS.md) - Exemplo de replicação (API Slim antiga)
- [REGRA_MATRICULAS_UNICA_ATIVA.md](./REGRA_MATRICULAS_UNICA_ATIVA.md) - Regra de matrícula única

### ⏰ Horários & Planejamento
- [DIAS_RESUMO.md](./DIAS_RESUMO.md) - Resumo de dias
- [RESUMO_MUDANCAS_HORARIOS.md](./RESUMO_MUDANCAS_HORARIOS.md) - Mudanças de horários
- [CORRECAO_JOB.md](./CORRECAO_JOB.md) - Correção de jobs
- [JOB_LIMPAR_MATRICULAS.md](./JOB_LIMPAR_MATRICULAS.md) - Job de limpeza
- [JOB_LIMPEZA_MATRICULAS_DOCUMENTACAO.md](./JOB_LIMPEZA_MATRICULAS_DOCUMENTACAO.md) - Documentação job

### 🎯 WOD
- [README_WOD_UNIFICADO.md](./README_WOD_UNIFICADO.md) - WOD unificado
- [MODALIDADE_WOD.md](./MODALIDADE_WOD.md) - Modalidade WOD
- [FRONTEND_WOD_FORM.md](./FRONTEND_WOD_FORM.md) - Frontend WOD
- [GUIA_CONSUMO_ENDPOINTS_MOBILE_HORARIOS.md](./GUIA_CONSUMO_ENDPOINTS_MOBILE_HORARIOS.md) - Consumo de endpoints

### 📋 Referência
- [INDEX.md](./INDEX.md) - Índice geral
- [INDEX_DOCUMENTACAO.md](./INDEX_DOCUMENTACAO.md) - Índice de documentação
- [MANIFESTO.md](./MANIFESTO.md) - Manifesto do projeto
- [RESUMO_EXECUTIVO.txt](./RESUMO_EXECUTIVO.txt) - Resumo executivo
- [cron_config_exemplo.txt](./cron_config_exemplo.txt) - Exemplo de cron

---

## 🔥 Documentação Mais Usada

| Situação | Arquivo |
|----------|---------|
| Preciso testar um endpoint | [API_QUICK_REFERENCE.md](./API_QUICK_REFERENCE.md) |
| Preciso limpar o banco | [LIMPEZA_BANCO_DADOS.md](./LIMPEZA_BANCO_DADOS.md) |
| Preciso verificar saúde da API | [GUIA_MANUTENCAO.md](./GUIA_MANUTENCAO.md) |
| Preciso entender a arquitetura | [ARCHITECTURE.md](./ARCHITECTURE.md) |
| Tenho erro na produção | [CHECKLIST_IMPLANTACAO.md](./CHECKLIST_IMPLANTACAO.md) |
| Preciso fazer deploy | [IMPLEMENTATION_GUIDE.md](./IMPLEMENTATION_GUIDE.md) |

---

## ⭐ NOVO: Ferramentas de Gerenciamento de Banco

### 3 Formas de Limpar o Banco

1. **Via Endpoint API** (Mais Seguro)
   ```bash
   POST /superadmin/cleanup-database
   ```
   Ver: [LIMPEZA_BANCO_DADOS.md](./LIMPEZA_BANCO_DADOS.md) - Método 1

2. **Via Script PHP** (Desenvolvimento Local)
   ```bash
   php database/cleanup.php
   ```
   Ver: [LIMPEZA_BANCO_DADOS.md](./LIMPEZA_BANCO_DADOS.md) - Método 2

3. **Via SQL Direto** (Automação)
   ```bash
   mysql < database/migrations/999_LIMPAR_BANCO_DADOS.sql
   ```
   Ver: [LIMPEZA_BANCO_DADOS.md](./LIMPEZA_BANCO_DADOS.md) - Método 3

### Ferramentas de Diagnóstico

- **Verificar estado do banco**: `php database/check_database_state.php`
- **Criar SuperAdmin**: `php database/create_superadmin.php`
- **Health check API**: `curl https://api.appcheckin.com.br/health`

Detalhes: [RESUMO_GERENCIAMENTO_BANCO.md](./RESUMO_GERENCIAMENTO_BANCO.md)

---

## 🎯 Próximas Leituras Recomendadas

```
Para novo dev:
  1. LEIA_PRIMEIRO.md
  2. QUICK_START.md
  3. API_QUICK_REFERENCE.md
  4. GUIA_TESTES.md

Para admin/manutenção:
  1. GUIA_MANUTENCAO.md
  2. LIMPEZA_BANCO_DADOS.md
  3. RESUMO_GERENCIAMENTO_BANCO.md
  4. CHECKLIST_IMPLANTACAO.md

Para deploy:
  1. IMPLEMENTATION_GUIDE.md
  2. CHECKLIST_IMPLANTACAO.md
  3. .env.production
  4. .htaccess
```

---

## 📞 Suporte Rápido

### Erro: "Bloqueado em produção"
→ Você tentou limpar banco com `APP_ENV=production`
→ Leia: [LIMPEZA_BANCO_DADOS.md](./LIMPEZA_BANCO_DADOS.md#troubleshooting-comum)

### Erro: "Apenas SuperAdmin"
→ Seu usuário não tem role_id=3
→ Solução: `php database/create_superadmin.php`

### API não responde
→ Execute: `curl https://api.appcheckin.com.br/health`
→ Leia: [GUIA_MANUTENCAO.md](./GUIA_MANUTENCAO.md#troubleshooting-comum)

### Banco desconectado
→ Verifique: `php database/check_database_state.php`
→ Leia: [1-LEIA-PRIMEIRO-ERRO-BANCO.md](./1-LEIA-PRIMEIRO-ERRO-BANCO.md)

---

## 📊 Estatísticas

- **Total de documentos**: 60+
- **Linhas de documentação**: 5.000+
- **Últimas atualizações**: 
  - ⭐ GUIA_MANUTENCAO.md (novo)
  - ⭐ LIMPEZA_BANCO_DADOS.md (novo)
  - ⭐ RESUMO_GERENCIAMENTO_BANCO.md (novo)

---

**Última atualização**: 2026-01-19
**Versão API**: 1.0.0
**Status**: Em desenvolvimento
