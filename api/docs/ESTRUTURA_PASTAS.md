# 📁 Estrutura de Organização - AppCheckin Backend

## 📍 Pastas Principais

### `/docs` - Documentação Completa
Toda a documentação técnica do projeto, guias e exemplos:

#### 📱 APIs Mobile
- **API_MOBILE_ENDPOINTS.md** - Documentação oficial dos 3 endpoints mobile
- **GUIA_CONSUMO_ENDPOINTS_MOBILE_HORARIOS.md** - Exemplos práticos (cURL, JavaScript, HTML)

#### 📚 APIs de Turmas & Replicação
- **[Documentação atual e arquivo histórico](../../apiV2/docs/turmas/README.md)** - Turmas na API v2 e documentos Slim arquivados
- **[DOCUMENTACAO_REPLICACAO.md](../../apiV2/docs/turmas/legacy-api/DOCUMENTACAO_REPLICACAO.md)** - Índice histórico do endpoint Slim

#### 🏗️ Estrutura & Mudanças
- **ESTRUTURA_AULAS.md** - Modelo de dados de dias/horários/turmas
- **DIAS_RESUMO.md** - Resumo do sistema de dias
- **CORRECAO_DIAS_TENANT.md** - Correções aplicadas no tenant isolation
- **RESUMO_MUDANCAS_HORARIOS.md** - Mudanças nos horários
- **[RESUMO_REPLICACAO_TURMAS.md](../../apiV2/docs/turmas/legacy-api/RESUMO_REPLICACAO_TURMAS.md)** - Resumo histórico de replicação
- **RESOLUCAO_DUPLICIDADES.md** - Como foram resolvidas duplicidades

#### 🧪 Testes & Seeds
- **GUIA_TESTES.md** - Como testar os endpoints
- **SEED_JOBS_DIAS.md** - Seeding de dias/horários
- **[EXEMPLO_REPLICACAO_TURMAS.md](../../apiV2/docs/turmas/legacy-api/EXEMPLO_REPLICACAO_TURMAS.md)** - Exemplos históricos de replicação

#### 💻 Exemplos de Código Frontend
- **REPLICAR_FRONTEND_EXEMPLO.js** - Exemplos JavaScript de como consumir os endpoints

---

### `/scripts` - Scripts Utilitários & Testes
Scripts para debug, testes, migrations e operações administrativas:

#### 🧹 Limpeza & Deleção
- **apagar_replicados.php** - Apagar turmas replicadas
- **apagar_replicados.sql** - Query SQL para apagar replicados
- **limpar_agendamentos.php** - Limpar agendamentos
- **cleanup_duplicate_turmas.php** - Remover turmas duplicadas

#### 🔄 Migrations & Updates
- **apply_migrations_tipos_baixa.php** - Aplicar migrations
- **apply_migration_remove_horarios.php** - Remover coluna de horários
- **final_migration_remove_horario_id.php** - Migration final
- **atualizar_tipo_baixa.php** - Atualizar tipo de baixa

#### 🧪 Testes
- **test_replicar_turmas.php** - Testar replicação
- **test_custom_horarios.php** - Testar horários customizados
- **test_horario_ocupado.php** - Testar conflito de horários
- **test_tipos_baixa.php** - Testar tipos de baixa
- **test_usuarios_duplicados.php** - Testar usuários duplicados

#### ✅ Verificação
- **verify_replication_endpoint.php** - Verificar endpoint de replicação
- **verify_turmas_final.php** - Verificar turmas finais
- **check_turmas_structure.php** - Verificar estrutura de turmas
- **verificar_dia_id.sql** - Verificar IDs dos dias

#### 🎯 Replicação & Exemplos
- **replicar_tenant5.php** - Script de replicação para tenant 5
- **EXEMPLO_ATUALIZACAO_MODEL.php** - Exemplo de atualização de model

#### 🚀 Automação
- **QUICK_START_REPLICACAO.sh** - Script rápido para replicação
- **test_seed_dias.sh** - Script para seeding de dias

---

## 🗂️ Estrutura Completa

```
Backend/
├── docs/                                      # 📚 Documentação
│   ├── API_MOBILE_ENDPOINTS.md
│   ├── GUIA_CONSUMO_ENDPOINTS_MOBILE_HORARIOS.md
│   ├── ESTRUTURA_AULAS.md
│   ├── DIAS_RESUMO.md
│   ├── CORRECAO_DIAS_TENANT.md
│   ├── RESUMO_MUDANCAS_HORARIOS.md
│   ├── RESOLUCAO_DUPLICIDADES.md
│   ├── GUIA_TESTES.md
│   ├── SEED_JOBS_DIAS.md
│   └── REPLICAR_FRONTEND_EXEMPLO.js
│
├── apiV2/docs/turmas/                          # Turmas v2 e arquivo histórico da Slim
│
├── scripts/                                   # 🔧 Scripts Utilitários
│   ├── apagar_replicados.php
│   ├── apagar_replicados.sql
│   ├── limpar_agendamentos.php
│   ├── cleanup_duplicate_turmas.php
│   ├── apply_migrations_tipos_baixa.php
│   ├── apply_migration_remove_horarios.php
│   ├── final_migration_remove_horario_id.php
│   ├── atualizar_tipo_baixa.php
│   ├── test_replicar_turmas.php
│   ├── test_custom_horarios.php
│   ├── test_horario_ocupado.php
│   ├── test_tipos_baixa.php
│   ├── test_usuarios_duplicados.php
│   ├── verify_replication_endpoint.php
│   ├── verify_turmas_final.php
│   ├── check_turmas_structure.php
│   ├── verificar_dia_id.sql
│   ├── replicar_tenant5.php
│   ├── EXEMPLO_ATUALIZACAO_MODEL.php
│   ├── QUICK_START_REPLICACAO.sh
│   └── test_seed_dias.sh
│
├── app/                                       # 🏗️ Código Fonte
│   ├── Controllers/
│   ├── Middlewares/
│   ├── Models/
│   └── Services/
│
├── database/                                  # 💾 Database
│   ├── migrations/
│   ├── seeds/
│   └── tests/
│
├── config/                                    # ⚙️ Configuração
│   ├── database.php
│   └── settings.php
│
├── routes/                                    # 🛣️ Rotas
│   └── api.php
│
├── public/                                    # 🌐 Public
│   └── index.php
│
└── composer.json, Dockerfile, etc.            # 📦 Arquivos de Configuração

```

---

## 🚀 Como Usar

### Para Consumir APIs Mobile
1. Abra **docs/API_MOBILE_ENDPOINTS.md** - Entenda os endpoints
2. Abra **docs/GUIA_CONSUMO_ENDPOINTS_MOBILE_HORARIOS.md** - Veja exemplos práticos

### Para Entender a Estrutura
1. Abra **docs/ESTRUTURA_AULAS.md** - Entenda o modelo de dados
2. Abra **docs/DIAS_RESUMO.md** - Veja o resumo do sistema

### Para Replicar Turmas
1. Abra **apiV2/docs/turmas/README.md** para a documentação atual
2. Para consultar a implementação antiga, veja **apiV2/docs/turmas/legacy-api/README.md**

### Para Desativar Turmas/Dias
1. Consulte **apiV2/docs/turmas/legacy-api/DESATIVAR_TURMAS_E_DIAS.md** - Documentação Slim arquivada

### Para Executar Scripts
1. Vá para `scripts/` e escolha o script desejado
2. Execute com `php scripts/seu_script.php` ou `bash scripts/seu_script.sh`

### Para Testar Endpoints
1. Abra **docs/GUIA_TESTES.md** - Instruções de teste
2. Execute scripts em `scripts/test_*.php`

---

## 📊 Distribuição de Documentos

| Tipo | Quantidade | Pasta |
|------|-----------|-------|
| 📚 Documentação | 16 docs | `/docs` |
| 🔧 Scripts | 21 scripts | `/scripts` |
| 📦 Config | ~5 arquivos | Raiz |

---

## ✅ Checklist de Organização

- ✅ Documentações em `/docs`
- ✅ Scripts em `/scripts`
- ✅ Código fonte em `/app`
- ✅ Database em `/database`
- ✅ Configuração em `/config`
- ✅ Rotas em `/routes`
- ✅ README criado

**Projeto bem organizado!** 🎉
