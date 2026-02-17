# Pacotes (Plano Família) — Especificação para Admin e Mobile

## Objetivo
Permitir a contratação de pacotes (ex: Plano Família) onde **um pagante** realiza o pagamento e o valor é **rateado** entre os beneficiários. Ao confirmar pagamento, as matrículas dos beneficiários são ativadas com o valor rateado.

---

## Conceitos
- **Pacote**: produto comercial com preço total e quantidade de beneficiários.
- **Contrato de Pacote**: instância de compra do pacote (pagante + status + datas).
- **Beneficiários**: alunos que recebem matrícula ativa após o pagamento.

---

## Regras
1. **Somente Admin define beneficiários.**
2. **Aluno não vê dados de outros alunos.**
3. **Pagante vê beneficiários** do seu pacote em `/mobile/assinaturas`.
4. **Pagamento único** ativa várias matrículas.
5. **Rateio**: `valor_total / qtd_beneficiarios` → aplicado em cada matrícula.
6. **Ciclos**: pacote possui `plano_ciclo_id` (ou usa `duracao_dias` do plano).

---

## Modelo de Dados (Banco)
### Novas tabelas
- `pacotes`
  - `id`, `tenant_id`, `nome`, `descricao`, `valor_total`, `qtd_beneficiarios`, `plano_id`, `plano_ciclo_id`, `ativo`, `created_at`, `updated_at`

- `pacote_contratos`
  - `id`, `tenant_id`, `pacote_id`, `pagante_usuario_id`, `pagamento_id`, `payment_url`, `payment_preference_id`, `status`, `valor_total`, `data_inicio`, `data_fim`, `created_at`, `updated_at`

- `pacote_beneficiarios`
  - `id`, `tenant_id`, `pacote_contrato_id`, `aluno_id`, `matricula_id`, `valor_rateado`, `status`, `created_at`, `updated_at`

### Alterações
- `matriculas`:
  - `pacote_contrato_id` (nullable)
  - `valor_rateado` (nullable)
- `pagamentos_plano`:
  - `pacote_contrato_id` (nullable)

Migrations:
- `database/migrations/2026_02_16_create_pacotes.sql`
- `database/migrations/2026_02_16_001_add_payment_url_to_pacote_contratos.sql`

---

## Endpoints Admin
### Criar pacote
`POST /admin/pacotes`
```json
{
  "nome": "Plano Família 1x",
  "descricao": "Até 4 beneficiários",
  "valor_total": 200.00,
  "qtd_beneficiarios": 4,
  "plano_id": 19,
  "plano_ciclo_id": 61
}
```

### Listar pacotes
`GET /admin/pacotes`

### Atualizar pacote
`PUT /admin/pacotes/{id}`
```json
{
  "nome": "Pacote família 2",
  "descricao": "",
  "valor_total": 120,
  "qtd_beneficiarios": 2,
  "plano_id": 7,
  "plano_ciclo_id": 47,
  "ativo": 1
}
```

### Contratar pacote
`POST /admin/pacotes/{pacoteId}/contratar`
```json
{
  "pagante_usuario_id": 123,
  "beneficiarios": [10, 11, 12, 13]
}
```

### Definir beneficiários
`POST /admin/pacotes/contratos/{contratoId}/beneficiarios`
```json
{
  "beneficiarios": [10, 11, 12, 13]
}
```

### Confirmar pagamento e ativar
`POST /admin/pacotes/contratos/{contratoId}/confirmar-pagamento`
```json
{
  "pagamento_id": "MP-123456"
}
```

Efeito:
- Atualiza contrato para `ativo`
- Cria matrículas para beneficiários
- Cria pagamentos rateados

---

## Mobile
### Listar pacotes pendentes do pagante
`GET /mobile/pacotes/pendentes`
- Retorna contratos de pacote pendentes para o usuário pagante.

### Gerar pagamento do pacote (pagante)
`POST /mobile/pacotes/contratos/{contratoId}/pagar`
- Gera `payment_url` (checkout) e salva no contrato.
- Reutiliza se já existir `payment_url`.

### Assinaturas do pagante
`GET /mobile/assinaturas`
- Inclui seção `pacotes` **apenas para o pagante**.
- Beneficiários aparecem com `aluno_id` e `nome`.

---

## Fluxo resumido
1. Admin cria pacote.
2. Admin contrata pacote, define pagante.
3. Admin escolhe beneficiários.
4. Pagante abre `/mobile/pacotes/pendentes` e gera pagamento.
5. Pagamento confirmado → ativa matrículas e rateia valores.
6. Pagante vê beneficiários no app.

---

## Observações
- Beneficiário não enxerga dados de outros alunos.
- Pagante vê somente os beneficiários do seu contrato.
- Pacotes seguem ciclo definido em `plano_ciclo_id`.
