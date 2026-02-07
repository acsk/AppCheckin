# Implementação de Endpoints de Assinaturas

## 📋 Resumo

Este guia explica como implementar o sistema completo de gerenciamento de assinaturas para o painel AppCheckin. O sistema permite que administradores e superadministradores gerenciem assinaturas de planos dos alunos.

## 📁 Arquivos Criados/Modificados

### Frontend (Painel - React Native)

#### 1. **`src/services/assinaturaService.js`** ✅ CRIADO
- Serviço de integração com API de assinaturas
- Métodos para CRUD completo de assinaturas
- Suporte para renovação, suspensão, cancelamento e reativação
- Métodos para relatórios e filtragem

**Métodos disponíveis:**
```javascript
- listar(filtros)                  // Listar assinaturas da academia
- listarTodas(tenantId, filtros)   // Listar todas (SuperAdmin)
- buscar(id)                       // Buscar assinatura específica
- criar(dados)                     // Criar nova assinatura
- atualizar(id, dados)             // Atualizar assinatura
- renovar(id, dados)               // Renovar assinatura
- suspender(id, motivo)            // Suspender assinatura
- reativar(id)                     // Reativar assinatura
- cancelar(id, motivo)             // Cancelar assinatura
- listarProximasVencer(dias)       // Listar assinaturas próximas de vencer
- listarHistoricoAluno(alunoId)    // Histórico de assinaturas de um aluno
- relatorio(filtros)               // Gerar relatório analítico
```

#### 2. **`src/screens/assinaturas/AssinaturasScreen.js`** ✅ CRIADO
- Tela principal de gerenciamento de assinaturas
- Listagem com filtros por status e busca por aluno/plano
- Modal com detalhes completos da assinatura
- Ações rápidas: renovar, suspender, reativar, cancelar
- Suporte para SuperAdmin com seleção de academia
- Responsivo para mobile e web

**Features:**
- ✅ Filtro por status (ativa, suspensa, cancelada, vencida)
- ✅ Busca em tempo real
- ✅ Seleção de academia para SuperAdmin
- ✅ Modal com detalhes completos
- ✅ Ações contextuais por status
- ✅ Integração com toast notifications

### Backend (API PHP/Slim)

#### 3. **`docs/ASSINATURAS_ENDPOINTS.md`** ✅ CRIADO
Documentação completa dos endpoints com:
- Descrição de cada endpoint
- Request/Response examples
- Query parameters e body parameters
- Status HTTP codes
- Erros possíveis
- Estrutura de dados SQL
- Middleware requerido

#### 4. **`docs/EXEMPLO_AssinaturaController.php`** ✅ CRIADO
Exemplo de implementação do controlador PHP com:
- Método `listar()` - GET /admin/assinaturas
- Método `listarTodas()` - GET /superadmin/assinaturas
- Método `buscar()` - GET /admin/assinaturas/:id
- Método `criar()` - POST /admin/assinaturas
- Método `suspender()` - POST /admin/assinaturas/:id/suspender
- Método `cancelar()` - POST /admin/assinaturas/:id/cancelar
- Helper `calcularDataVencimento()` para cálculos de datas

#### 5. **`docs/EXEMPLO_ROTAS_ASSINATURAS.md`** ✅ CRIADO
Exemplo de como registrar as rotas no arquivo `routes/api.php`:
- Rotas de admin
- Rotas de superadmin
- Middleware necessário
- Importações necessárias

## 🚀 Passos de Implementação

### PASSO 1: Frontend - Serviço de Assinaturas

✅ **Arquivo já criado:** `src/services/assinaturaService.js`

O serviço já está pronto para uso. Apenas certifique-se de que ele está sendo importado corretamente.

### PASSO 2: Frontend - Tela de Assinaturas

✅ **Arquivo já criado:** `src/screens/assinaturas/AssinaturasScreen.js`

A tela está pronta. Você pode adicionar uma rota no arquivo de navegação:

```javascript
// app/assinaturas/index.js
import AssinaturasScreen from '../../src/screens/assinaturas/AssinaturasScreen';

export default AssinaturasScreen;
```

### PASSO 3: Backend - Criação da Tabela

Execute o SQL para criar as tabelas de assinaturas:

```sql
-- Tabela principal de assinaturas
CREATE TABLE assinaturas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  aluno_id INT NOT NULL,
  plano_id INT NOT NULL,
  academia_id INT NOT NULL,
  status ENUM('ativa', 'suspensa', 'cancelada', 'vencida') DEFAULT 'ativa',
  data_inicio DATE NOT NULL,
  data_vencimento DATE NOT NULL,
  data_suspensao DATE NULL,
  data_cancelamento DATE NULL,
  data_reativacao DATETIME NULL,
  motivo_suspensao VARCHAR(255) NULL,
  motivo_cancelamento VARCHAR(255) NULL,
  valor_mensal DECIMAL(10, 2) NOT NULL,
  forma_pagamento ENUM('dinheiro', 'cartao_credito', 'cartao_debito', 'pix', 'boleto') DEFAULT 'dinheiro',
  ciclo_tipo VARCHAR(50) NOT NULL,
  permite_recorrencia BOOLEAN DEFAULT true,
  renovacoes_restantes INT DEFAULT 0,
  observacoes TEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
  FOREIGN KEY (plano_id) REFERENCES planos(id),
  FOREIGN KEY (academia_id) REFERENCES academias(id) ON DELETE CASCADE,
  INDEX idx_aluno_id (aluno_id),
  INDEX idx_plano_id (plano_id),
  INDEX idx_academia_id (academia_id),
  INDEX idx_status (status),
  INDEX idx_data_vencimento (data_vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de histórico de renovações
CREATE TABLE assinatura_renovacoes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  assinatura_id INT NOT NULL,
  data_renovacao DATE NOT NULL,
  proxima_data_vencimento DATE NOT NULL,
  valor_renovacao DECIMAL(10, 2) NOT NULL,
  forma_pagamento VARCHAR(50) DEFAULT 'mesma',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assinatura_id) REFERENCES assinaturas(id) ON DELETE CASCADE,
  INDEX idx_assinatura_id (assinatura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### PASSO 4: Backend - Criar o Controlador

1. Copie o arquivo `docs/EXEMPLO_AssinaturaController.php`
2. Crie o arquivo `app/Controllers/AssinaturaController.php` no backend
3. Ajuste o namespace e imports conforme necessário
4. Implemente os métodos adicionais não mostrados no exemplo (renovar, reativar, relatorio, etc)

### PASSO 5: Backend - Registrar as Rotas

1. Abra o arquivo `routes/api.php` do backend
2. Adicione o import: `use App\Controllers\AssinaturaController;`
3. Adicione as rotas conforme mostrado em `docs/EXEMPLO_ROTAS_ASSINATURAS.md`

### PASSO 6: Testes

#### Teste 1: Listar Assinaturas
```bash
curl -X GET http://localhost:8080/admin/assinaturas \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json"
```

#### Teste 2: Criar Assinatura
```bash
curl -X POST http://localhost:8080/admin/assinaturas \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "aluno_id": 5,
    "plano_id": 2,
    "data_inicio": "2025-01-15",
    "forma_pagamento": "cartao_credito",
    "renovacoes": 12
  }'
```

#### Teste 3: Suspender Assinatura
```bash
curl -X POST http://localhost:8080/admin/assinaturas/1/suspender \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "motivo": "Pagamento pendente"
  }'
```

## 📊 Fluxo de Dados

```
┌─────────────────────────────────────────────────────────────┐
│ AssinaturasScreen (React Native Frontend)                   │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│ assinaturaService.js (Service Layer)                        │
│ - Preparação de dados                                       │
│ - Chamadas HTTP via api client                              │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│ GET/POST /admin/assinaturas*                                │
│ Middleware:                                                  │
│ - AuthMiddleware (validação JWT)                            │
│ - TenantMiddleware (isolamento de dados)                    │
│ - AdminMiddleware (validação de role)                       │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│ AssinaturaController (PHP/Slim Backend)                     │
│ - Validação de dados                                        │
│ - Queries ao banco de dados                                 │
│ - Resposta formatada em JSON                                │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│ Banco de Dados (MySQL)                                      │
│ - Tabela: assinaturas                                       │
│ - Tabela: assinatura_renovacoes                             │
└─────────────────────────────────────────────────────────────┘
```

## 🔐 Segurança

Todos os endpoints requerem:

1. **AuthMiddleware**: Validação de JWT token válido
2. **TenantMiddleware**: Isolamento de dados por academia
3. **AdminMiddleware**: Apenas usuários com papel Admin ou SuperAdmin
4. **SuperAdminMiddleware**: Apenas para rotas /superadmin/*

## 📈 Exemplos de Uso

### Criar Assinatura
```javascript
import assinaturaService from '../../services/assinaturaService';

const novaAssinatura = await assinaturaService.criar({
  aluno_id: 5,
  plano_id: 2,
  data_inicio: '2025-01-15',
  forma_pagamento: 'cartao_credito',
  renovacoes: 12
});
```

### Listar Assinaturas com Filtros
```javascript
const assinaturas = await assinaturaService.listar({
  status: 'ativa',
  plano_id: 2
});
```

### Renovar Assinatura
```javascript
const renovada = await assinaturaService.renovar(assinaturaId, {
  gerar_cobranca: true
});
```

### Suspender Assinatura
```javascript
const suspensa = await assinaturaService.suspender(assinaturaId, 'Pagamento pendente');
```

## 🐛 Troubleshooting

### Erro: "Assinatura não encontrada"
- Verificar se a assinatura pertence à academia do usuário (tenant_id)
- Verificar se o ID está correto

### Erro: "Aluno já possui assinatura ativa"
- O aluno já tem uma assinatura ativa para este plano
- Cancelar ou suspender a assinatura anterior primeiro

### Erro 401: "Unauthorized"
- JWT token inválido ou expirado
- Fazer novo login

### Erro 403: "Forbidden"
- Usuário não tem permissão (não é Admin/SuperAdmin)
- Verificar papel do usuário

## 📞 Próximos Passos

1. ✅ Implementar tabelas no banco de dados
2. ✅ Criar AssinaturaController no backend
3. ✅ Registrar rotas no routes/api.php
4. ✅ Testar endpoints com cURL ou Postman
5. ⏳ Integração com sistema de pagamentos (MercadoPago, etc)
6. ⏳ Webhooks para atualizar status automaticamente
7. ⏳ Envio de notificações (email/SMS) para renovação próxima
8. ⏳ Relatório analítico de receita/churn
9. ⏳ Dashboard com métricas de assinaturas

## 📚 Documentação

Para mais detalhes, consulte:
- [ASSINATURAS_ENDPOINTS.md](./ASSINATURAS_ENDPOINTS.md) - Documentação completa dos endpoints
- [EXEMPLO_AssinaturaController.php](./EXEMPLO_AssinaturaController.php) - Implementação do controlador
- [EXEMPLO_ROTAS_ASSINATURAS.md](./EXEMPLO_ROTAS_ASSINATURAS.md) - Rotas do Slim Framework

---

**Versão:** 1.0.0  
**Data:** Fevereiro 2026  
**Status:** Documentação e Frontend Completos | Backend Pendente de Implementação
