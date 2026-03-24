# 🔴 Problema: Data de Matrícula Fica com Amanhã

## Resumo
Quando um aluno compra um plano hoje, a matrícula é criada com `data_inicio` = amanhã (ou posterior), em vez de hoje.

## Investigação do Código

### 📍 Localização do Problema
**Arquivo:** `/app/Controllers/MobileController.php`  
**Função:** `comprarPlano()`  
**Linhas:** 6078-6230

### 🔍 Análise

#### Parte 1: Cálculo de Datas (Linhas 6078-6091)
```php
// Linha 6078
$dataInicio = date('Y-m-d');           // ← Deveria ser "hoje"
$dataMatricula = $dataInicio;           // ← OK
$dataInicioObj = new \DateTime($dataInicio);

// Calcular vencimento baseado no ciclo (meses) ou duração do plano (dias)
$dataVencimento = clone $dataInicioObj;
if ($duracaoMeses > 1) {
    $dataVencimento->modify("+{$duracaoMeses} months");
} else {
    $duracaoDias = (int) $plano['duracao_dias'];
    $dataVencimento->modify("+{$duracaoDias} days");           // ← Adiciona dias ao vencimento
}
```

**Análise:** O código parece correto aqui. `$dataInicio` é definido como "hoje", e `$dataVencimento` é calculado adicionando a duração.

---

#### Parte 2: INSERT da Matrícula (Linhas 6225-6238)
```php
$stmtInsert = $this->db->prepare("
    INSERT INTO matriculas 
    (tenant_id, aluno_id, plano_id, plano_ciclo_id, tipo_cobranca, 
     data_matricula, data_inicio, data_vencimento, 
     valor, status_id, motivo_id, dia_vencimento, 
     periodo_teste, proxima_data_vencimento, criado_por)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
");

$stmtInsert->execute([
    // ...
    $dataMatricula,                               // Parâmetro 6
    $dataInicio,                                  // Parâmetro 7
    $dataVencimento->format('Y-m-d'),            // Parâmetro 8
    // ...
    $proximaDataVencimento->format('Y-m-d'),     // Parâmetro 14
    // ...
]);
```

**Análise:** O INSERT parece correto. `data_inicio` recebe `$dataInicio` (hoje), `data_vencimento` recebe `$dataVencimento` (hoje + duração).

---

### 🤔 Possíveis Causas

#### Hipótese 1: Webhook Ativando com Data Errada
**Localização:** `/app/Controllers/MercadoPagoWebhookController.php`, função `ativarMatricula()` (Linha 2155-2160)

```php
private function ativarMatricula(int $matriculaId): void
{
    // ...
    $hoje = new \DateTimeImmutable(date('Y-m-d'));
    // ...
    if ($duracaoMeses > 0) {
        $dataVencimento = $hoje->modify("+{$duracaoMeses} months")->format('Y-m-d');
    } else {
        $duracaoDias = max(1, (int) ($matricula['duracao_dias'] ?? 30));
        $dataVencimento = $hoje->modify("+{$duracaoDias} days")->format('Y-m-d');
    }
    
    $stmtUpdate = $this->db->prepare("
        UPDATE matriculas
        SET status_id = ?,
            data_inicio = ?,                      // ← Aqui!
            data_vencimento = ?,
            proxima_data_vencimento = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmtUpdate->execute([
        $statusAtivaId,
        $hoje->format('Y-m-d'),                   // ← Passando "hoje" aqui
        $dataVencimento,
        $dataVencimento,
        $matriculaId
    ]);
}
```

**⚠️ Problema Potencial:** Se a webhook é disparada DEPOIS da matrícula criada, e se houver timezone desatualizado, o `$hoje` pode ser diferente do `date('Y-m-d')` que foi usado ao criar a matrícula.

---

#### Hipótese 2: Timezone Diferente Entre Execuções
**Localização:** Arquivos de configuração

O PHP pode estar usando timezone diferente do MySQL:
- **PHP** usando UTC: `2026-03-24 00:00:00 UTC` = `2026-03-23 21:00:00 BRT`
- **MySQL** usando `BRT` (UTC-3): `2026-03-24`

Resultado: Quando PHP faz `date('Y-m-d')` retorna `2026-03-23`, mas MySQL `CURDATE()` retorna `2026-03-24`.

---

#### Hipótese 3: Problema em Pacotes (Linha 1587)
**Localização:** `/app/Controllers/MercadoPagoWebhookController.php`, função `ativarPacoteContrato()` (Linha 1587)

```php
$dataInicio = date('Y-m-d');                     // ← Hoje
$dataFim = null;
// ... cálculos ...
if (!$dataFim) {
    $stmtPlano = $this->db->prepare("SELECT duracao_dias FROM planos WHERE id = ? AND tenant_id = ? LIMIT 1");
    $stmtPlano->execute([(int) $contrato['plano_id'], $tenantId]);
    $duracaoDias = max(1, (int) ($stmtPlano->fetchColumn() ?: 30));
    $dataFim = date('Y-m-d', strtotime("+{$duracaoDias} days"));  // ← Adiciona dias
}

// ... INSERT
$stmtMat->execute([
    // ...
    $dataInicio,        // ← Inserindo como data_inicio e data_matricula
    $dataInicio,
    $dataFim,           // ← Inserindo como data_vencimento
    // ...
]);
```

**Análise:** Aqui o código também parece correto. `$dataInicio` é "hoje".

---

## ✅ Próximos Passos para Debug

1. **Executar o script de análise:**
   ```bash
   php debug_data_matricula.php --dias=15 --tenant=3 --verbose
   ```

2. **Verificar timezone configurado:**
   ```bash
   grep -r "timezone\|date_default_timezone" config/
   ```

3. **Verificar se há modulo que modifique timezone entre requisições:**
   ```bash
   grep -r "date_default_timezone_set\|strtotime\|modify.*day\|modify.*month" app/Controllers/*.php
   ```

4. **Simular compra de plano e verificar valores inseridos em tempo real:**
   ```bash
   tail -f storage/logs/error.log | grep -i "comprarPlano\|matricula"
   ```

---

## 🚀 Possível Solução

Se o problema for confirmado, aplicar uma das seguintes correções:

### Opção 1: Garantir Timezone Correto Sempre
```php
// No início de MobileController::comprarPlano()
date_default_timezone_set('America/Sao_Paulo');
```

### Opção 2: Usar CURDATE() do MySQL em vez de date('Y-m-d')
```php
// Ao invés de:
$dataInicio = date('Y-m-d');

// Usar:
$stmt = $this->db->prepare("SELECT CURDATE() as hoje");
$stmt->execute();
$dataInicio = $stmt->fetchColumn();
```

### Opção 3: Forçar data explicitamente
```php
// Ao invés de:
$dataInicio = date('Y-m-d');

// Usar com validação:
$dataInicio = date('Y-m-d', now());  // now() em PHP 8.1+
// Ou
$dataInicio = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
```

