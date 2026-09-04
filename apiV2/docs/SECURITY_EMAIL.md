# Segurança de e-mail (Resend) — resposta a abuso

## Diagnóstico

Os e-mails que você listou (**RingGo**, parking fee, etc.) **não são gerados pelo AppCheckin**.

O código só envia **dois assuntos fixos**:

| Assunto | Quando |
|---------|--------|
| `🔐 Código de Recuperação de Senha - App Check-in` | Recuperação de senha |
| `🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso` | Cadastro mobile |

Conclusão: alguém está usando a **`RESEND_API_KEY` diretamente** na API da Resend (fora do Laravel). Isso explica spam para domínios aleatórios (.uk, hotmail, etc.).

---

## Ação imediata (faça agora)

1. **Resend → API Keys → revogar** a chave atual (`re_...`).
2. **Criar nova chave** e atualizar só no `.env` da apiV2 (e Slim se ainda usar Resend).
3. **Nunca commitar** `.env` — verificar se a chave vazou em git, logs, prints ou painel Hostinger.
4. **Resend → Domains** — confirmar que só `appcheckin.com.br` (ou subdomínio verificado) pode enviar.
5. **Contatar suporte Resend** reportando abuso/phishing na conta.
6. Opcional: **rotacionar** `RECAPTCHA_SECRET_KEY` se estiver no mesmo `.env` exposto.

---

## Proteções implementadas na apiV2

### 1. Mail guard (`EnforceAllowedOutboundMail`)

Bloqueia qualquer envio via Laravel Mail que **não** seja:

- Remetente: `MAIL_FROM_ADDRESS` (padrão `mail@appcheckin.com.br`)
- Assunto na lista em `config/appcheckin.php` → `mail_allowed_subjects`

Desativar só em dev: `MAIL_GUARD_ENABLED=false`

### 2. Rate limit em recuperação de senha

`POST /v2/auth/password-recovery/request`:

- Máx. **3 tentativas / 15 min** por IP
- Máx. **3 tentativas / 15 min** por e-mail

Variáveis:

```env
RATE_LIMIT_PASSWORD_RECOVERY_MAX=3
RATE_LIMIT_PASSWORD_RECOVERY_DECAY_MINUTES=15
```

### 3. reCAPTCHA (opcional / recomendado em produção)

```env
PASSWORD_RECOVERY_REQUIRE_RECAPTCHA=true
RECAPTCHA_SECRET_KEY=sua_chave
```

O mobile/painel deve enviar `recaptcha_token` no body (igual ao `register-mobile`).

---

## Limitação importante

O **mail guard protege só envios pelo Laravel**. Se a chave Resend vazar de novo, o atacante **ainda pode** chamar `api.resend.com` diretamente.

Por isso **rotacionar e revogar a chave** é obrigatório — o guard é camada extra, não substituto.

---

## Verificação pós-deploy

```bash
# Deve retornar 429 após 3 tentativas rápidas do mesmo IP
curl -X POST https://apiv2.appcheckin.com.br/v2/auth/password-recovery/request \
  -H "Content-Type: application/json" \
  -d '{"email":"teste@example.com"}'
```

Logs Laravel (`storage/logs/laravel.log`): linhas `Mail guard bloqueou assunto não autorizado` indicam tentativa de abuso via app.
