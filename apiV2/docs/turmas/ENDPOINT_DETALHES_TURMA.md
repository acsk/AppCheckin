# Detalhes da turma — API v2

Documentação do endpoint Laravel em `apiV2`. A resposta abaixo acompanha a implementação atual;
ela difere de versões antigas do documento em `api/docs`.

## Endpoint

```http
GET /v2/mobile/turma/{turmaId}/detalhes
```

- **Autenticação:** JWT (`Authorization: Bearer <token>`).
- **Tenant:** o tenant selecionado no token é usado para localizar a turma.
- **Path:** `turmaId` — ID numérico da turma.
- **Query opcional:** `data=YYYY-MM-DD` — data da aula usada para calcular a janela de check-in.
  Quando omitida ou vazia, a API usa a data da aula retornada pela turma ou, na ausência dela,
  a data atual da academia.

```bash
curl -G "http://localhost:8080/v2/mobile/turma/494/detalhes" \
  -H "Authorization: Bearer <JWT>" \
  --data-urlencode "data=2026-11-06"
```

## Resposta de sucesso

HTTP `200`:

```json
{
  "success": true,
  "data": {
    "reposicoes": [],
    "turma": {
      "id": 494,
      "nome": "CrossFit - 05:00",
      "professor": {
        "id": 12,
        "nome": "Beatriz Oliveira",
        "email": "beatriz@example.com"
      },
      "modalidade": {
        "id": 3,
        "nome": "CrossFit",
        "icone": null,
        "cor": null
      },
      "horario": {
        "inicio": "05:00:00",
        "fim": "06:00:00"
      },
      "hora_inicio": "05:00:00",
      "hora_fim": "06:00:00",
      "horario_inicio": "05:00:00",
      "horario_fim": "06:00:00",
      "dia_aula": "2026-11-06",
      "checkin": {
        "disponivel": false,
        "ja_abriu": false,
        "ja_fechou": false,
        "abertura": null,
        "fechamento": null,
        "tolerancia_antes_minutos": 480,
        "tolerancia_antes_turma_minutos": 480,
        "max_tolerancia_academia_minutos": null,
        "tolerancia_depois_minutos": 10,
        "tolerancia_antes_checkin_minutos": 0,
        "tolerancia_cancelamento_minutos": 0,
        "cancelamento_ate": null
      },
      "ativo": true,
      "checkin_bloqueado": false,
      "limite_alunos": 15,
      "alunos_inscritos": 12,
      "total_alunos_matriculados": 12,
      "vagas_disponiveis": 3,
      "reposicao": false,
      "reposicoes_confirmadas": 0,
      "vagas_fixas": 0,
      "vaga_reservada": false,
      "tipo_acesso": "horario_livre",
      "percentual_ocupacao": 80.0,
      "total_checkins": 12
    },
    "alunos": {
      "total": 1,
      "lista": [
        {
          "aluno_id": 11,
          "nome": "Carolina Ferreira",
          "email": "carolina@example.com",
          "foto_caminho": null,
          "checkins": 8,
          "aniversario_hoje": false,
          "idade": null
        }
      ]
    },
    "checkins_recentes": {
      "total": 1,
      "lista": [
        {
          "checkin_id": 45,
          "aluno_id": 11,
          "tipo_presenca": "Regular",
          "usuario_nome": "Carolina Ferreira",
          "data_checkin": "2026-11-06 05:10:00",
          "hora_checkin": "05:10:00",
          "data_checkin_formatada": "06/11/2026",
          "presente": null,
          "presenca_confirmada_em": null,
          "presenca_confirmada_por": null,
          "aniversario_hoje": false,
          "idade": null
        }
      ]
    },
    "resumo": {
      "alunos_ativos": 1,
      "presentes_hoje": 1,
      "percentual_presenca": 100.0
    }
  }
}
```

Os valores e a quantidade de itens variam. `alunos.lista` contém `aluno_id`, nome, email, caminho da
foto, check-ins e dados de aniversário/idade. `checkins_recentes.lista` contém o aluno, tipo de
presença (`Regular` ou `Reposição`), data/hora e estado da confirmação de presença.

### Campos principais de `data.turma`

| Campo | Descrição |
|---|---|
| `professor`, `modalidade` | Objetos com ID, nome e dados complementares disponíveis. |
| `horario`, `hora_inicio`, `hora_fim`, `horario_inicio`, `horario_fim` | Horários da turma; os campos individuais são mantidos por compatibilidade. |
| `dia_aula` | Data considerada para esta consulta. |
| `checkin` | Disponibilidade, janela de check-in, tolerâncias e limite de cancelamento. |
| `checkin_bloqueado` | Informa se o check-in da turma está bloqueado. |
| `limite_alunos`, `vagas_disponiveis`, `percentual_ocupacao` | Limite e indicadores de ocupação calculados pela API. |
| `alunos_inscritos`, `total_alunos_matriculados`, `total_checkins` | Contagens retornadas para a turma. |
| `reposicao`, `reposicoes_confirmadas`, `vagas_fixas`, `vaga_reservada`, `tipo_acesso` | Dados de reposição e acesso híbrido, quando aplicáveis. |

`resumo` contém as contagens `alunos_ativos`, `presentes_hoje` e `percentual_presenca`.

## Erros

As respostas de erro mobile usam `{ "success": false, "error": "..." }`.

| HTTP | Quando ocorre |
|---|---|
| `400` | Tenant não selecionado ou ID inválido. |
| `401` | JWT ausente ou inválido. |
| `404` | Turma não encontrada no tenant selecionado. |
| `500` | Falha inesperada ao carregar detalhes. |

## Rotas relacionadas

Estas rotas também pertencem à API v2 e exigem JWT:

| Método | Rota | Uso |
|---|---|---|
| `GET` | `/v2/mobile/turmas` | Listar turmas móveis ativas. |
| `GET` | `/v2/mobile/turma/{turmaId}/participantes` | Consultar participantes/check-ins da turma. |
| `POST` | `/v2/mobile/turma/{turmaId}/confirmar-presenca` | Confirmar presenças. |
| `POST` | `/v2/mobile/turma/{turmaId}/bloquear-checkin` | Bloquear check-in. |
| `POST` | `/v2/mobile/turma/{turmaId}/desbloquear-checkin` | Liberar check-in. |

Para a gestão administrativa de turmas fixas, grade individual, versionamento e ações em lote,
consulte [TURMAS_FIXAS_E_ACESSO_HIBRIDO.md](TURMAS_FIXAS_E_ACESSO_HIBRIDO.md) e a seção
**Acesso híbrido às aulas** no [README da API v2](../../README.md).
