# Cadastro Público para Mobile (/auth/register-mobile)

- Método: POST
- Rota: /auth/register-mobile
- Autenticação: Pública (sem token)
- Descrição: Cria um usuário do tipo Aluno, vincula ao tenant informado e retorna um token JWT pronto para uso no app. A senha inicial é definida como o CPF informado (somente dígitos), armazenada com hash (bcrypt).

## Requisitos
- Campos obrigatórios: `nome`, `email`, `cpf`, `data_nascimento`
- Campo obrigatório para Web: `recaptcha_token` (não necessário para apps mobile nativos)
- Campos opcionais: `telefone`, `whatsapp`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`
- Regras:
  - `cpf` deve conter 11 dígitos (os caracteres não numéricos são ignorados)
  - `email` e `cpf` devem ser únicos no sistema
  - `recaptcha_token` é obrigatório apenas para cadastros via navegador web
  - `telefone` e `whatsapp` são salvos apenas com números (máscaras são removidas)
  - Rate limit: máximo 5 tentativas a cada 15 minutos por IP

## Exemplo de Request (JSON)

### Mobile App (sem reCAPTCHA)
```json
{
  "nome": "João da Silva",
  "email": "joao.silva@example.com",
  "cpf": "123.456.789-09",
  "data_nascimento": "2001-05-20",
  "telefone": "(11) 99999-9999",
  "whatsapp": "(11) 98888-7777"
}
```

### Web (com reCAPTCHA)
```json
{
  "nome": "João da Silva",
  "email": "joao.silva@example.com",
  "cpf": "123.456.789-09",
  "data_nascimento": "2001-05-20",
  "recaptcha_token": "03AGdBq27...",
  "telefone": "(11) 99999-9999",
  "whatsapp": "(11) 98888-7777",
  "cep": "01234-567",
  "logradouro": "Rua A",
  "numero": "123",
  "complemento": "Ap 45",
  "bairro": "Centro",
  "cidade": "São Paulo",
  "estado": "SP"
}
```

## Exemplo cURL

### Mobile App
```bash
curl -i -X POST https://api.appcheckin.com.br/auth/register-mobile \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "João da Silva",
    "email": "joao.silva@example.com",
    "cpf": "123.456.789-09",
    "data_nascimento": "2001-05-20"
  }'
```

### Web (com reCAPTCHA)
```bash
curl -i -X POST https://api.appcheckin.com.br/auth/register-mobile \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "João da Silva",
    "email": "joao.silva@example.com",
    "cpf": "123.456.789-09",
    "data_nascimento": "2001-05-20",
    "recaptcha_token": "03AGdBq27..."
  }'
```

## Respostas
- 201 Created
```json
{
  "message": "Cadastro realizado com sucesso",
  "token": "<JWT>",
  "user": {
    "id": 123,
    "nome": "JOÃO DA SILVA",
    "email": "joao.silva@example.com",
    "telefone": "(11) 99999-9999",
    "whatsapp": "(11) 98888-7777",
    "cpf": "12345678909",
    "data_nascimento": "2001-05-20"
  }
}
```

- 422 Validation Error
```json
{
  "type": "error",
  "code": "VALIDATION_ERROR",
  "errors": ["cpf é obrigatório", "cpf inválido (use 11 dígitos)"]
}
```

- 429 Rate Limit Exceeded
```json
{
  "type": "error",
  "code": "RATE_LIMIT_EXCEEDED",
  "message": "Muitas tentativas de cadastro. Tente novamente em 15 minutos",
  "retryAfter": 900
}
```

- 403 reCAPTCHA Failed
```json
{
  "type": "error",
  "code": "RECAPTCHA_VALIDATION_FAILED",
  "message": "Falha na validação de segurança. Por favor, tente novamente"
}
```

- 409 Conflito (Email/CPF duplicado)
```json
{
  "type": "error",
  "code": "EMAIL_ALREADY_EXISTS",
  "message": "Email já cadastrado"
}
```

- 500 Erro interno
```json
{
  "type": "error",
  "code": "USER_CREATION_FAILED",
  "message": "Não foi possível criar o usuário"
}
```

## Observações
- A senha inicial do usuário é igual ao CPF (somente dígitos). Recomenda-se forçar a troca de senha no primeiro login.
- O token retornado não inclui `tenant_id`. O vínculo com tenant será feito no fluxo de matrícula; após vincular, o app deve obter um token com `tenant_id` (ex.: via seleção ou login) para acessar endpoints sensíveis como `/mobile/perfil`.
- O cadastro cria registros em `usuarios` e `alunos`. O vínculo com tenant (`usuario_tenant` e `tenant_usuario_papel`) ocorre apenas na matrícula.

## Segurança

### Detecção Automática de App Mobile
A API detecta automaticamente se a requisição vem de um app mobile nativo através do User-Agent:
- Apps Flutter, React Native, ou nativos não precisam enviar `recaptcha_token`
- Requisições via navegador web **devem** incluir `recaptcha_token`
- User-Agents detectados como mobile: `okhttp`, `dart`, `flutter`, `appcheckin`

### Google reCAPTCHA v3 (apenas Web)
Para cadastros via navegador web, implemente o reCAPTCHA v3:

```html
<script src="https://www.google.com/recaptcha/api.js?render=6Lc4Q18sAAAAAH-aVJ28-3pG93k3wy2Kl7Eh8Xv9"></script>
```

```javascript
grecaptcha.ready(function() {
  grecaptcha.execute('6Lc4Q18sAAAAAH-aVJ28-3pG93k3wy2Kl7Eh8Xv9', {action: 'register'})
    .then(function(token) {
      fetch('/auth/register-mobile', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          nome: 'João',
          email: 'joao@example.com',
          cpf: '12345678909',
          data_nascimento: '2001-05-20',
          recaptcha_token: token
        })
      });
    });
});
```

### Rate Limiting (aplicado a todos)
- Máximo de **5 tentativas** por IP
- Janela de tempo: **15 minutos**
- Após 5 tentativas falhas, o IP será bloqueado temporariamente
- O contador é resetado automaticamente após sucesso ou após 15 minutos
- Headers retornados em erro 429:
  - `Retry-After`: segundos até poder tentar novamente

### Recomendação para App Mobile
Para aumentar a segurança do app mobile, considere:
1. ✅ **Rate Limiting** (já implementado)
2. Adicionar header customizado: `X-App-Version` para validar versão do app
3. Implementar certificado SSL pinning no app
4. Usar device fingerprinting (device ID, modelo, SO)
