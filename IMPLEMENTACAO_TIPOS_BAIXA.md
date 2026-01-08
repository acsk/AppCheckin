# Implementação de Tipos de Baixa de Pagamentos

## Resumo das Alterações

Este documento descreve as modificações implementadas para adicionar controle de tipos de baixa nos pagamentos de planos.

## 1. Estrutura de Banco de Dados

### 1.1 Nova Tabela: `tipos_baixa`

**Migration:** `051_create_tipos_baixa_table.sql`

Criada tabela para armazenar os tipos de baixa com os seguintes registros iniciais:
- **1 - Manual**: Baixa realizada manualmente pelo administrador
- **2 - Automática**: Baixa realizada automaticamente pelo sistema
- **3 - Importação**: Baixa realizada através de importação de dados
- **4 - Integração**: Baixa realizada através de integração com sistema externo

```sql
CREATE TABLE IF NOT EXISTS tipos_baixa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao VARCHAR(255) NULL,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 1.2 Alteração na Tabela: `pagamentos_plano`

**Migration:** `052_add_tipo_baixa_to_pagamentos_plano.sql`

Adicionado campo `tipo_baixa_id` para relacionar o tipo de baixa utilizado:

```sql
ALTER TABLE pagamentos_plano
ADD COLUMN tipo_baixa_id INT NULL AFTER baixado_por,
ADD FOREIGN KEY fk_tipo_baixa (tipo_baixa_id) REFERENCES tipos_baixa(id);
```

**Campos relacionados à baixa na tabela `pagamentos_plano`:**
- `baixado_por` (INT): ID do usuário admin que realizou a baixa
- `tipo_baixa_id` (INT): ID do tipo de baixa utilizado
- `data_pagamento` (DATE): Data em que o pagamento foi confirmado

## 2. Backend - Alterações no Model

**Arquivo:** `Backend/app/Models/PagamentoPlano.php`

### 2.1 Método `listarPorMatricula()`

Atualizado para incluir JOIN com a tabela `tipos_baixa` e retornar:
- `baixado_por_nome`: Nome do usuário que realizou a baixa
- `tipo_baixa_nome`: Nome do tipo de baixa utilizado

```php
LEFT JOIN usuarios baixador ON p.baixado_por = baixador.id
LEFT JOIN tipos_baixa tb ON p.tipo_baixa_id = tb.id
```

### 2.2 Método `confirmarPagamento()`

Adicionado parâmetro `$tipoBaixaId` com valor padrão `1` (Manual):

```php
public function confirmarPagamento(
    int $tenantId,
    int $id,
    int $adminId,
    ?string $dataPagamento = null,
    ?int $formaPagamentoId = null,
    ?string $comprovante = null,
    ?string $observacoes = null,
    ?int $tipoBaixaId = 1  // Novo parâmetro
): bool
```

O campo `tipo_baixa_id` agora é salvo automaticamente ao confirmar um pagamento.

## 3. Backend - Alterações no Controller

**Arquivo:** `Backend/app/Controllers/PagamentoPlanoController.php`

### 3.1 Método `confirmar()`

Atualizado para passar o `tipo_baixa_id = 1` (Manual) ao confirmar pagamentos:

```php
$pagamentoModel->confirmarPagamento(
    $tenantId,
    $pagamentoId,
    (int) $adminId,
    $data['data_pagamento'] ?? null,
    $data['forma_pagamento_id'] ?? null,
    $data['comprovante'] ?? null,
    $data['observacoes'] ?? null,
    1 // tipo_baixa_id = 1 (Manual)
);
```

## 4. Frontend - Alterações na Tela de Histórico

**Arquivo:** `FrontendWeb/src/screens/matriculas/MatriculaDetalheScreen.js`

### 4.1 Tabela de Histórico de Pagamentos

Adicionada coluna "Baixado por" que exibe:
- Nome do usuário que realizou a baixa (`baixado_por_nome`)
- Tipo de baixa utilizado (`tipo_baixa_nome`) em formato secundário

```javascript
<Text style={[styles.tabelaHeaderText, { flex: 1.5 }]}>Baixado por</Text>

// Na renderização:
<View style={{ flex: 1.5 }}>
  <Text style={[styles.tabelaCelula, { fontSize: 12, color: '#6b7280' }]}>
    {pagamento.baixado_por_nome || '-'}
  </Text>
  {pagamento.tipo_baixa_nome && (
    <Text style={[styles.tabelaCelula, { fontSize: 10, color: '#9ca3af', fontStyle: 'italic' }]}>
      ({pagamento.tipo_baixa_nome})
    </Text>
  )}
</View>
```

### 4.2 Modal de Baixa de Pagamento

**Arquivo:** `FrontendWeb/src/components/BaixaPagamentoPlanoModal.js`

Removido o texto "Baixa Manual" do campo de observações, já que agora o tipo de baixa é controlado pela tabela `tipos_baixa`:

```javascript
setFormData({
    data_pagamento: hoje,
    forma_pagamento_id: pagamento.forma_pagamento_id || '',
    comprovante: '',
    observacoes: '' // Removido 'Baixa Manual'
});
```

## 5. Scripts de Migração

### 5.1 Script Shell

**Arquivo:** `apply_migrations_tipos_baixa.sh`

Script bash para aplicar as migrations via MySQL CLI.

### 5.2 Script PHP

**Arquivo:** `Backend/apply_migrations_tipos_baixa.php`

Script PHP que pode ser executado via Docker ou PHP CLI para aplicar as migrations.

**Execução:**
```bash
docker exec -it appcheckin_php php /var/www/html/apply_migrations_tipos_baixa.php
```

## 6. Como Funciona

### 6.1 Baixa Manual (tipo_baixa_id = 1)

1. Usuário Admin acessa a tela de detalhes da matrícula
2. Clica em "Confirmar Pagamento" em um pagamento pendente
3. Preenche os dados no modal (data, forma de pagamento, observações)
4. Ao confirmar, o sistema:
   - Marca o pagamento como pago (status_pagamento_id = 2)
   - Salva a data do pagamento
   - Registra o ID do usuário admin em `baixado_por`
   - Define `tipo_baixa_id = 1` (Manual)
   - Gera automaticamente o próximo pagamento

### 6.2 Visualização no Histórico

Na tabela de histórico de pagamentos, cada linha exibe:
- Número da parcela
- Data de vencimento
- Data do pagamento
- **Nome do usuário que realizou a baixa** (novo)
- **Tipo de baixa** (novo, em formato secundário)
- Valor
- Status

Exemplo de exibição:
```
João Silva
(Manual)
```

## 7. Benefícios da Implementação

1. **Rastreabilidade**: Sabe-se exatamente quem realizou cada baixa de pagamento
2. **Auditoria**: Histórico completo de ações nos pagamentos
3. **Flexibilidade**: Possibilidade de criar baixas automáticas no futuro
4. **Transparência**: Usuários visualizam quem processou seus pagamentos
5. **Escalabilidade**: Estrutura preparada para integração com sistemas externos

## 8. Próximos Passos Sugeridos

1. Implementar baixas automáticas (tipo_baixa_id = 2) para integrações de pagamento
2. Criar relatórios de auditoria por tipo de baixa
3. Adicionar filtros na listagem de pagamentos por tipo de baixa
4. Criar dashboard com estatísticas por tipo de baixa
5. Implementar logs de alterações em pagamentos

---

**Data de Implementação:** 07/01/2026  
**Versão:** 1.0
