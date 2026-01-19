# Job: Limpeza de Matrículas Duplicadas/Sem Pagamento

## Objetivo
Cancelar automaticamente matrículas pendentes de pagamento, mantendo apenas as matrículas com pagamento confirmado. Para cada usuário e modalidade, mantém apenas a matrícula mais recente que tem pagamento.

## Lógica do Job

### 1. Identificação de Matrículas Duplicadas
- Busca todos os usuários com **múltiplas matrículas ativas/pendentes** na mesma modalidade
- Verifica se há **pagamentos associados** consultando a tabela `pagamentos_plano`

### 2. Priorização (Ordem de Manutenção)
1. **Tem pagamento confirmado?** (verifica `COUNT(pagamentos_plano)`)
   - Matrículas COM pagamento > Matrículas SEM pagamento
2. **Status da matrícula**
   - `ativa` > `pendente`
3. **Data mais recente**
   - Matrícula mais recente é mantida

### 3. Ação
- **Mantém**: A matrícula com melhor priorização
- **Cancela**: As demais (status muda para `cancelada`)

## Exemplo Prático

```
Usuario: Carolina Ferreira - Modalidade: CrossFit

ID  | Data         | Status    | Pagamentos | Ação
----|--------------|-----------|------------|--------
16  | 2026-01-11   | pendente  | 1          | ✓ MANTER (tem pagamento + mais recente)
22  | 2026-01-11   | pendente  | 0          | ✗ CANCELAR (sem pagamento)
21  | 2026-01-10   | pendente  | 0          | ✗ CANCELAR (sem pagamento + mais antiga)
```

## Instalação

### 1. Salvar o arquivo
```bash
# Já existe em:
/var/www/html/jobs/limpar_matriculas_duplicadas.php
```

### 2. Configurar no Crontab

```bash
# Adicionar ao crontab (executar diariamente às 05:00)
0 5 * * * docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php >> /var/log/limpar_matriculas.log 2>&1
```

### 3. Criar log (opcional)
```bash
mkdir -p /var/log
touch /var/log/limpar_matriculas.log
```

## Uso

### Execução Normal
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php
```

### Execução em Teste (Dry Run)
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --dry-run
```

Saída:
```
Processando 3 tenant(s)...
[Tenant #5] Fitpro 7 - Plus
  Usuários com múltiplas matrículas: 1
    ✓ Mantendo: 3x por semana (Data: 2026-01-09, Status: ativa, com 3 pagamentos)
    ✓ Mantendo: 2x por Semana (Data: 2026-01-11, Status: pendente, com 1 pagamento)

Matrículas canceladas: 0
```

## Testes Executados

### Teste 1: Verificação de Pagamentos ✅
- Criou script `analisar_pagamentos.php`
- Resultado: Todas as 4 matrículas ativas em produção têm pagamentos
- Conclusão: Nenhuma duplicada para cancelar em produção

### Teste 2: Simulação com Dados de Teste ✅
- Criou matrículas de teste SEM pagamento
- Executou job e confirmou cancelamento correto
- Resultado: 4 matrículas foram canceladas conforme esperado
- Matrículas com pagamento foram mantidas

## Resultados Esperados

- ✅ Matrículas com pagamento são mantidas
- ✅ Matrículas sem pagamento são canceladas (se houver duplicatas)
- ✅ Apenas a mais recente é mantida por modalidade por usuário
- ✅ Operação é segura (status muda para `cancelada`, não deleta)
- ✅ Funciona em ambiente multi-tenant

## Status

**PRONTO PARA PRODUÇÃO** ✅

O job foi testado, validado e está funcionando corretamente. Pode ser adicionado ao crontab para execução automática diária.

---

**Criado em**: 11 de janeiro de 2026  
**Última atualização**: 11 de janeiro de 2026  
**Status**: Operacional ✅
