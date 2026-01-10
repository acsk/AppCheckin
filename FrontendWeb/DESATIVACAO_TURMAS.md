# 📋 Implementação de Desativação de Turmas e Bloqueio de Dias

## ✅ O que foi implementado

### 1️⃣ **Serviços criados/atualizados**

#### `turmaService.js`
- Novo método: `desativar(turmaId, periodo, mes)`
  - Desativa turmas com opções de período

#### `diaService.js` (novo arquivo)
- Método: `desativar(diaId, periodo, diasSemana, mes)`
  - Desativa/bloqueia dias (feriados, sem aula)

### 2️⃣ **UI implementada em TurmasScreen.js**

#### Botão de Desativar
- Ícone 🔴 pause-circle em vermelho (#ef4444)
- Aparece em cada turma da lista

#### Modal de Desativação
- **Seleção de período:**
  - Apenas Esta
  - Próxima Semana
  - Mês Inteiro
  
- **Seleção de mês** (para períodos que requerem)

- **Botões:**
  - Cancelar (cinza)
  - Desativar (vermelho)

### 3️⃣ **Estados adicionados**

```javascript
const [modalDesativarVisible, setModalDesativarVisible] = useState(false);
const [turmaDesativar, setTurmaDesativar] = useState(null);
const [periodoDesativacao, setPeriodoDesativacao] = useState('apenas_esta');
const [desativando, setDesativando] = useState(false);
```

### 4️⃣ **Função de desativação**

```javascript
const handleDesativarTurma = async () => {
  // Valida dados
  // Chama turmaService.desativar()
  // Mostra success/error toast
  // Recarrega dados
}
```

---

## 🎯 Como Usar

### Para o Usuário Final:

1. Na tela de turmas, clique no ícone **🔴 pause** na turma desejada
2. Escolha o período de desativação
3. Se for "Mês Inteiro", selecione o mês (ex: 2026-01)
4. Clique em "Desativar"

### Para o Desenvolvedor:

#### Desativar apenas uma turma:
```javascript
await turmaService.desativar(1);
```

#### Desativar próxima semana (mesmo horário):
```javascript
await turmaService.desativar(1, 'proxima_semana');
```

#### Desativar mês inteiro (mesmo horário):
```javascript
await turmaService.desativar(1, 'mes_todo', '2026-02');
```

---

## 📍 Endpoints da API esperados

### POST /admin/turmas/desativar
```json
{
  "turma_id": 1,
  "periodo": "apenas_esta",  // ou proxima_semana, mes_todo
  "mes": "2026-01"          // obrigatório se periodo for mes_todo
}
```

**Response:**
```json
{
  "type": "success",
  "message": "Turma(s) desativada(s) com sucesso",
  "summary": {
    "total_desativadas": 1
  }
}
```

### POST /admin/dias/desativar
```json
{
  "dia_id": 17,
  "periodo": "apenas_este",  // ou proxima_semana, mes_todo, custom
  "dias_semana": [1],        // obrigatório se periodo for custom
  "mes": "2026-01"           // obrigatório se periodo for mes_todo ou custom
}
```

---

## 🎨 Componentes e Estilos

### Cores utilizadas:
- **Desativar:** `#ef4444` (vermelho)
- **Cancelar:** `#f3f4f6` (cinza claro)
- **Info:** `#fef3c7` (amarelo claro)

### Ícones:
- Desativar: `MaterialCommunityIcons pause-circle`
- Editar: `Feather arrow-right`

---

## 🔧 Configuração

### Estados para modal de desativação:
```javascript
// No componente
const [modalDesativarVisible, setModalDesativarVisible] = useState(false);
const [turmaDesativar, setTurmaDesativar] = useState(null);
const [periodoDesativacao, setPeriodoDesativacao] = useState('apenas_esta');
const [desativando, setDesativando] = useState(false);
```

### Abrir modal:
```javascript
<TouchableOpacity
  onPress={() => {
    setTurmaDesativar(turma);
    setModalDesativarVisible(true);
  }}
>
  <MaterialCommunityIcons name="pause-circle" size={18} color="#ef4444" />
</TouchableOpacity>
```

---

## 📦 Arquivos criados/modificados

### Criados:
- `src/services/diaService.js`
- `src/utils/constants.js`

### Modificados:
- `src/services/turmaService.js` - Adicionado método `desativar()`
- `src/screens/turmas/TurmasScreen.js` - UI completa para desativação

---

## 🚀 Próximos Passos

### Backend:
1. Implementar endpoint `POST /admin/turmas/desativar`
2. Implementar endpoint `POST /admin/dias/desativar`
3. Adicionar testes

### Frontend:
1. Adicionar modal de desativar dias (bloquear feriados)
2. Adicionar histórico de desativações
3. Adicionar função de reativar turmas
4. Adicionar notificações aos alunos

---

## 💡 Exemplos de Uso

### Caso 1: Pausa rápida de uma aula
```javascript
// Clique no ícone pause-circle da turma
// Deixe "Apenas Esta" selecionado
// Clique em "Desativar"
```

### Caso 2: Férias de um professor (mês inteiro)
```javascript
// 1. Clique no ícone pause-circle
// 2. Selecione "Mês Inteiro"
// 3. Digite "2026-02" (fevereiro)
// 4. Clique em "Desativar"
```

### Caso 3: Bloquear feriado (em desenvolvimento)
```javascript
// No futuro, terá opção similar para dias
// diaService.desativar(dia_id, 'apenas_este')
```

---

## ⚠️ Validações

- Todos os campos obrigatórios são validados
- Apenas admin pode desativar turmas
- Mês deve estar no formato YYYY-MM

---

## 🔍 Troubleshooting

### Modal não aparece?
- Verifique se `setModalDesativarVisible(true)` está sendo chamado
- Verifique se o estado `modalDesativarVisible` é true

### Erro "Turma não encontrada"?
- Verifique se o turma_id é válido
- Verifique se a turma pertence ao tenant atual

### Erro 404 no endpoint?
- Implemente `POST /admin/turmas/desativar` no backend
- Implemente `POST /admin/dias/desativar` no backend

---

**Status:** ✅ Frontend Pronto para Integração  
**Versão:** 1.0.0  
**Última atualização:** 2026-01-10
