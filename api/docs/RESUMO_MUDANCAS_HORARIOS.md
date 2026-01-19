# Resumo das Mudanças - Remoção de Dependência da Tabela Horarios em Turmas

## 🎯 Objetivo
Permitir que o frontend crie turmas (classes) com qualquer horário customizado, sem depender de uma tabela pré-existente de horarios.

## ✅ Mudanças Realizadas

### 1. **Banco de Dados**
- ✅ Removida coluna `horario_id` da tabela `turmas`
- ✅ Removida foreign key constraint `turmas_ibfk_5` (relacionada a horario_id)
- ✅ Adicionadas colunas `horario_inicio TIME` e `horario_fim TIME` na tabela `turmas`
- ✅ Migrações criadas e executadas com sucesso

### 2. **Model - `app/Models/Turma.php`**
- ✅ Removidos todos os JOINs com tabela `horarios`
- ✅ Removidos campos `h.hora`, `h.horario_inicio`, `h.horario_fim` do SELECT
- ✅ Adicionados campos `t.horario_inicio`, `t.horario_fim` diretamente no SELECT
- ✅ Atualizado ORDER BY de `h.hora ASC` para `t.horario_inicio ASC`
- ✅ Método `create()` agora aceita `horario_inicio` e `horario_fim` diretos (não mais `horario_id`)
- ✅ Método `update()` permite atualizar `horario_inicio` e `horario_fim`
- ✅ Método `verificarHorarioOcupado()` reescrito para detectar **sobreposição** de horários
  - Nova assinatura: `verificarHorarioOcupado(int $tenantId, int $diaId, string $horarioInicio, string $horarioFim, ?int $turmaIdExcluir = null)`
  - Detecta conflito quando: `horario_inicio_nova < horario_fim_existente AND horario_fim_nova > horario_inicio_existente`
- ✅ Adicionado método helper `normalizarHorario()` para converter "HH:MM" → "HH:MM:SS"

### 3. **Controller - `app/Controllers/TurmaController.php`**
- ✅ Removida importação: `use App\Models\Horario`
- ✅ Removida propriedade: `private Horario $horarioModel`
- ✅ Removida inicialização: `new Horario($db)`
- ✅ Método `create()`:
  - Agora aceita `horario_inicio` e `horario_fim` no request body
  - Remove lógica de busca de horário (`findByDiaAndHorario`)
  - Passa horários diretamente para o model
  - Valida que `horario_fim > horario_inicio`
- ✅ Método `update()`:
  - Aceita `horario_inicio` e `horario_fim` para atualização
  - Remove lógica de conversão de horário
  - Valida sobreposição de horários se mudando horário/dia
- ✅ Método `verificarHorarioOcupado()` atualizado para receber horários (strings) em vez de IDs

### 4. **Rotas - `routes/api.php`**
- ✅ Removido endpoint: `GET /admin/turmas/horarios/{diaId}` (não há mais necessidade)
- ✅ Mantidos endpoints de turmas:
  - `GET /admin/turmas`
  - `GET /admin/turmas/dia/{diaId}`
  - `GET /admin/turmas/{id}`
  - `POST /admin/turmas`
  - `PUT /admin/turmas/{id}`
  - `DELETE /admin/turmas/{id}`

### 5. **Documentação - `DOCUMENTACAO_API_TURMAS.md`**
- ✅ Atualizada documentação de endpoints
- ✅ Removida documentação do endpoint `/admin/turmas/horarios/{diaId}`
- ✅ Exemplos de request body agora mostram `horario_inicio` e `horario_fim` como strings (HH:MM ou HH:MM:SS)
- ✅ Adicionadas notas sobre detecção de sobreposição de horários

## 📝 Request Body (POST /admin/turmas)

### Antes
```json
{
  "nome": "Turma A",
  "professor_id": 1,
  "modalidade_id": 1,
  "dia_id": 18,
  "horario_id": 5,
  "limite_alunos": 20
}
```

### Depois ✅
```json
{
  "nome": "Turma A",
  "professor_id": 1,
  "modalidade_id": 1,
  "dia_id": 18,
  "horario_inicio": "04:00",
  "horario_fim": "04:30",
  "limite_alunos": 20
}
```

## 📊 Response Fields

### Antes
```json
{
  "turma": {
    "id": 1,
    "horario_id": 5,
    "horario_hora": "04:00:00",
    "horario_inicio": "04:00:00",
    "horario_fim": "04:30:00",
    ...
  }
}
```

### Depois ✅
```json
{
  "turma": {
    "id": 1,
    "horario_inicio": "04:00:00",
    "horario_fim": "04:30:00",
    ...
  }
}
```

## 🧪 Testes Realizados

✅ Estrutura da tabela `turmas` verificada:
- `horario_inicio` adicionado
- `horario_fim` adicionado
- `horario_id` removido

✅ Inserção de turma com horário customizado (04:00 - 04:30) funcionando

✅ Validação de conflito de horário:
- ✅ Detecta sobreposição (04:15 - 04:45 conflita com 04:00 - 04:30)
- ✅ Permite horários adjacentes (04:30 - 05:00 não conflita com 04:00 - 04:30)

## 🚀 Como Usar a Nova API

### Criar Turma com Horário Customizado
```bash
curl -X POST "http://localhost:8080/admin/turmas" \
  -H "Authorization: Bearer seu_token" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Pilates 04:00-04:30",
    "professor_id": 1,
    "modalidade_id": 1,
    "dia_id": 18,
    "horario_inicio": "04:00",
    "horario_fim": "04:30",
    "limite_alunos": 20
  }'
```

### Atualizar Horário de uma Turma
```bash
curl -X PUT "http://localhost:8080/admin/turmas/1" \
  -H "Authorization: Bearer seu_token" \
  -H "Content-Type: application/json" \
  -d '{
    "horario_inicio": "05:00",
    "horario_fim": "05:30"
  }'
```

## 📌 Notas Importantes

1. **Formato de Horário**: O sistema aceita "HH:MM" e "HH:MM:SS", normalizando para "HH:MM:SS" internamente
2. **Sobreposição**: O sistema detecta conflito quando há qualquer sobreposição de horários no mesmo dia
3. **Tabela horarios**: Ainda existe no banco para referência/histórico, mas não é mais usada por turmas
4. **Compatibilidade**: Turmas existentes foram migradas com seus horários originais (06:00-07:00 por padrão)

## ✨ Benefícios

✅ Frontend tem liberdade total para criar turmas com qualquer horário
✅ Não depende mais de tabela pré-existente de horarios
✅ Validação inteligente de sobreposição (não apenas valores exatos)
✅ Código mais simples (sem JOINs desnecessários)
✅ Melhor performance (uma tabela a menos)
