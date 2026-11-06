# Documentação de turmas — API v2

Este é o índice único dos documentos de turmas mantidos com a API v2.

## Documentação atual

- [Turmas fixas e acesso híbrido](TURMAS_FIXAS_E_ACESSO_HIBRIDO.md) — regras, gestão,
  matrículas, grade, reposições, publicação e referências de implementação.
- [Detalhes da turma no app](ENDPOINT_DETALHES_TURMA.md) — contrato do endpoint
  `GET /v2/mobile/turma/{turmaId}/detalhes`.
- [README da API v2](../../README.md) — rotas, desenvolvimento e operação da API.

## Arquivo histórico da API Slim

Os documentos em [legacy-api](legacy-api/README.md) descrevem endpoints e estruturas da API
Slim antiga (por exemplo, `/admin/turmas`). Eles foram preservados para consulta histórica, mas
**não são o contrato da API v2**. Para novas integrações, use apenas as rotas e os formatos
documentados na seção **Documentação atual**.
