# Notificações — Use Case para Mobile

Este documento descreve os endpoints de Notificações disponíveis para clientes Mobile, os parâmetros esperados e exemplos de uso.

**Observação:** Todas as rotas exigem autenticação (`Authorization: Bearer <token>`) e o contexto de tenant deve ser definido pelo backend (não usar tenantId padrão).

**Endpoints principais**

- **GET /notificacoes**: lista todas as notificações do usuário (ordenadas por `created_at` desc).
  - Auth: obrigatório
  - Query: opcional `limit` (inteiro)
  - Response (200): { success: true, data: [ Notificacao ], total: N }

- **GET /notificacoes/unread**: lista apenas notificações não lidas.
  - Auth: obrigatório
  - Response (200): { success: true, data: [ Notificacao ], total: N }

- **GET /notificacoes/{id}**: busca uma notificação específica (do usuário atual).
  - Auth: obrigatório
  - Path param: `id` (int)
  - Response (200): { success: true, data: Notificacao }
  - Erros: 404 se não encontrada, 400 se `tenantId` ausente

- **POST /notificacoes**: criar notificação (uso típico: backend/admin/cron)
  - Auth: obrigatório (quem criar deve ter permissão)
  - Body (application/json):
    - `usuario_id` (integer) — ID do usuário destino. Se omitido, será usado `userId` do token (actor).
    - `tipo` (string) — "info" | "warning" | "error" (opcional, default: "info")
    - `titulo` (string) — obrigatório
    - `mensagem` (string) — opcional
    - `dados` (object) — opcional (será salvo como JSON)
  - Response (201): { success: true, id: 123 }
  - Erros: 422 se `titulo`/`usuario_id` ausentes, 400 se `tenantId` ausente

- **POST /notificacoes/{id}/read**: marca notificação como lida
  - Auth: obrigatório
  - Path param: `id` (int)
  - Response (200): { success: true }
  - Erros: 404 se não encontrada/sem permissão, 400 se `tenantId` ausente

- **POST /notificacoes/read-all**: marca todas as notificações do usuário como lidas
  - Auth: obrigatório
  - Response (200): { success: true, updated: N }
  - Erros: 400 se `tenantId` ausente


**Formato do objeto `Notificacao` (fields)**

- `id` (BIGINT)
- `tenant_id` (INT) — tenant/academia
- `usuario_id` (INT) — destinatário
- `tipo` (VARCHAR) — info/warning/error
- `titulo` (VARCHAR)
- `mensagem` (TEXT)
- `dados` (JSON) — payload adicional
- `lida` (TINYINT) — 0/1
- `created_at` (DATETIME)
- `updated_at` (TIMESTAMP)


**SQL de criação da tabela (MySQL)**

```sql
CREATE TABLE notificacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  usuario_id INT NOT NULL,
  tipo VARCHAR(50) NOT NULL DEFAULT 'info',
  titulo VARCHAR(255) NOT NULL,
  mensagem TEXT,
  dados JSON DEFAULT NULL,
  lida TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_notificacoes_usuario (tenant_id, usuario_id),
  INDEX idx_notificacoes_lida (tenant_id, lida),
  INDEX idx_notificacoes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


**Exemplos cURL**

- Listar notificações:

```bash
curl -H "Authorization: Bearer $TOKEN" \
  https://api.suaacademia.com/notificacoes
```

- Listar não lidas:

```bash
curl -H "Authorization: Bearer $TOKEN" \
  https://api.suaacademia.com/notificacoes/unread
```

- Criar notificação (admin/backend):

```bash
curl -X POST -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
  -d '{"usuario_id": 123, "titulo": "Treino cancelado", "mensagem": "A aula das 19h foi cancelada"}' \
  https://api.suaacademia.com/notificacoes
```

- Marcar como lida:

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://api.suaacademia.com/notificacoes/456/read
```


**Notas de implementação / Mobile**

- Sempre envie um token válido e o backend fará o *tenant resolution* a partir do token/contexto de sessão — não envie `tenant_id` manualmente.
- Endpoints retornam `dados` em JSON (campo `dados`) quando presente; o mobile deve desserializar e tratar campos esperados.
- Limites: listagens retornam por padrão até 200 registros (paginação futura pode ser adicionada).
- Ao criar notificações via backend, prefira usar `tipo` e `dados` para permitir tratamentos distintos no cliente (ex.: deep-links, abrir tela específica, etc.).


**Contato / suporte**

- Para dúvidas sobre payloads ou integração, fale com a equipe de backend.


---
Arquivo gerado: docs/NOTIFICACOES_MOBILE_USECASE.md
