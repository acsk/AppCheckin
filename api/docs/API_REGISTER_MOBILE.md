# Cadastro Público para Mobile (/auth/register-mobile)

- Método: POST
- Rota: /auth/register-mobile
- Autenticação: Pública (sem token)
- Descrição: Cria um usuário do tipo Aluno, vincula ao tenant informado e retorna um token JWT pronto para uso no app. A senha inicial é definida como o CPF informado (somente dígitos), armazenada com hash (bcrypt).

## Requisitos
- Campos obrigatórios: `nome`, `email`, `cpf`, `data_nascimento`
- Campos opcionais: `telefone`, `whatsapp`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`
- Regras:
  - `cpf` deve conter 11 dígitos (os caracteres não numéricos são ignorados)
  - `email` e `cpf` devem ser únicos no sistema

## Exemplo de Request (JSON)
```json
{
  "nome": "João da Silva",
  "email": "joao.silva@example.com",
  "cpf": "123.456.789-09",
  "data_nascimento": "2001-05-20",
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
