# Script de Importação de Matrículas em Lote

Scripts para automatizar a criação de matrículas para múltiplos alunos.

## 📁 Arquivos

- `importar_matriculas.php` - Script principal de importação
- `csv_para_json.php` - Conversor de CSV para JSON
- `exemplo_alunos.csv` - Exemplo de arquivo CSV
- `exemplo_alunos.json` - Exemplo de arquivo JSON

## 🚀 Uso Rápido

### Opção 1: Usar JSON direto

1. Edite o arquivo JSON com os dados dos alunos:
```bash
cp scripts/exemplo_alunos.json scripts/meus_alunos.json
# Edite meus_alunos.json com os dados reais
```

2. Execute a importação:
```bash
php scripts/importar_matriculas.php scripts/meus_alunos.json
```

### Opção 2: Converter de CSV

1. Crie/edite um arquivo CSV com os dados:
```
nome,email,cpf,telefone,plano_nome,ciclo_meses,data_inicio
Maria Silva,maria@email.com,12345678901,82999999999,2x por Semana,2,2026-02-10
```

2. Converta para JSON:
```bash
php scripts/csv_para_json.php scripts/meus_alunos.csv scripts/meus_alunos.json
```

3. Execute a importação:
```bash
php scripts/importar_matriculas.php scripts/meus_alunos.json
```

## 📋 Formato dos Dados

### Campos obrigatórios:
- `nome` - Nome completo do aluno
- `email` - Email único do aluno

### Campos opcionais:
- `cpf` - CPF do aluno (somente números ou formatado)
- `telefone` - Telefone (somente números ou formatado)
- `plano_nome` - Nome exato do plano (vazio = apenas associa ao tenant)
- `ciclo_meses` - 1, 2 ou 4 (mensal, bimestral, quadrimestral)
- `data_inicio` - Data de início da matrícula (padrão = hoje)

### Planos disponíveis (Cia da Natação):

**Planos Pagos:**
- `1x por Semana` - Ciclos: 1, 2, 4 meses
- `2x por Semana` - Ciclos: 1, 2, 4 meses
- `3x por Semana` - Ciclos: 1, 2, 4 meses

**Planos Temporários (gratuitos):**
- `1x Temp` - Ciclo: 1 mês
- `2x Temp` - Ciclo: 1 mês
- `3x Temp` - Ciclo: 1 mês

### Exemplo JSON:
```json
[
  {
    "nome": "Maria Silva Santos",
    "email": "maria.santos@email.com",
    "cpf": "12345678901",
    "telefone": "82999887766",
    "plano_nome": "2x por Semana",
    "ciclo_meses": 2,
    "data_inicio": "2026-02-10"
  },
  {
    "nome": "João Sem Plano",
    "email": "joao@email.com",
    "cpf": "",
    "telefone": "",
    "plano_nome": "",
    "ciclo_meses": 1,
    "data_inicio": "2026-02-10"
  }
]
```

## ⚙️ O que o script faz:

1. ✅ Cria usuário (se não existir)
   - Senha padrão: `123456`
   - Email único no sistema

2. ✅ Cria registro de aluno (se não existir)

3. ✅ Adiciona vínculo `tenant_usuario_papel`
   - papel_id = 1 (Aluno)
   - tenant_id = 3 (Cia da Natação)

4. ✅ Cria matrícula (se plano especificado)
   - Status: ATIVA
   - Data de início e vencimento
   - Valor do ciclo escolhido

## 📊 Saída do Script

```
📋 Total de alunos a processar: 5

---
[1] Maria Silva Santos
  ✅ Usuário criado (ID: 123)
  ✅ Aluno criado (ID: 456)
  ✅ Vínculo com tenant criado
  ✅ Matrícula criada (ID: 789) - 2x por Semana (2 mês(es)) - R$ 200,00

---
[2] João Sem Plano
  ℹ️  Usuário já existe (ID: 124)
  ℹ️  Aluno já existe (ID: 457)
  ℹ️  Vínculo com tenant já existe
  ⚠️  Sem plano especificado, apenas associado ao tenant

==========================================
📊 RESUMO
==========================================
✅ Matrículas criadas: 4
⚠️  Apenas vínculo: 1
❌ Erros: 0
📋 Total processado: 5
==========================================
```

## ⚠️ Observações

- Se o aluno já possui matrícula ativa no mesmo plano, não cria duplicada
- Se deixar `plano_nome` vazio, apenas associa o aluno ao tenant (sem matrícula)
- Se o plano for dos "Temp", usar apenas ciclo mensal (1 mês)
- Emails devem ser únicos no sistema
- Todos os alunos criados terão senha padrão `123456` (devem trocar no primeiro login)

## 🔧 Configuração

Edite o arquivo `importar_matriculas.php` se precisar mudar:

```php
$TENANT_ID = 3;        // Cia da Natação
$MODALIDADE_ID = 3;    // Natação  
$CRIADO_POR = 69;      // ID do admin que está importando
```
