# Frontend - Gerenciamento de Vencimentos de Matrículas

## Visão Geral

Sistema de gerenciamento de vencimentos onde:
- Ao criar matrícula, o sistema calcula automaticamente a `proxima_data_vencimento` (data_inicio + duracao_dias)
- O admin pode editar manualmente essa data na tela de matrícula
- Sistema permite listar vencimentos de hoje e próximos dias para notificações
- Check-in é bloqueado quando `proxima_data_vencimento` < data atual
- **Status é atualizado automaticamente:**
  - Status `ativa` (id=1) → automaticamente vira `vencida` (id=2) quando `proxima_data_vencimento < hoje`
  - Evento MySQL roda diariamente às 00:01 para atualizar status
  - Matrículas vencidas permitem criar nova matrícula (renovação)
  - Matrículas ativas com acesso válido bloqueiam nova matrícula na mesma modalidade

## Novos Endpoints Disponíveis

### 1. Atualizar Data de Vencimento

**Endpoint:** `PUT /api/admin/matriculas/{id}/proxima-data-vencimento`

**Autenticação:** Bearer Token (Admin)

**Request Body:**
```json
{
  "proxima_data_vencimento": "2026-02-15"
}
```

**Response 200 - Sucesso:**
```json
{
  "message": "Data de vencimento atualizada com sucesso",
  "matricula_id": 26,
  "proxima_data_vencimento_anterior": "2026-02-09",
  "proxima_data_vencimento_nova": "2026-02-15"
}
```

**Response 422 - Validação:**
```json
{
  "error": "Data de vencimento é obrigatória"
}
// ou
{
  "error": "Formato de data inválido. Use YYYY-MM-DD"
}
```

**Response 404:**
```json
{
  "error": "Matrícula não encontrada"
}
```

---

### 2. Listar Vencimentos de Hoje

**Endpoint:** `GET /api/admin/matriculas/vencimentos/hoje`

**Autenticação:** Bearer Token (Admin)

**Response 200:**
```json
{
  "vencimentos": [
    {
      "id": 27,
      "aluno_id": 104,
      "plano_id": 6,
      "proxima_data_vencimento": "2026-02-06",
      "valor": "0.00",
      "dia_vencimento": 5,
      "periodo_teste": 1,
      "aluno_nome": "ADMIN SECUNDÁRIO",
      "aluno_email": "adm@aqua.com.br",
      "aluno_telefone": null,
      "plano_nome": "2x Semana Teste",
      "plano_valor": "0.00",
      "status_nome": "Ativa",
      "status_codigo": "ativa"
    }
  ],
  "total": 1,
  "data": "2026-02-06"
}
```

**Uso:** Para gerar notificações diárias automáticas (via cron job ou similar)

---

### 3. Listar Próximos Vencimentos

**Endpoint:** `GET /api/admin/matriculas/vencimentos/proximos?dias=7`

**Autenticação:** Bearer Token (Admin)

**Query Params:**
- `dias` (opcional, default: 7) - Número de dias à frente para buscar

**Response 200:**
```json
{
  "vencimentos": [
    {
      "id": 26,
      "aluno_id": 103,
      "plano_id": 7,
      "proxima_data_vencimento": "2026-02-09",
      "valor": "0.00",
      "dia_vencimento": 10,
      "periodo_teste": 1,
      "dias_restantes": 3,
      "aluno_nome": "JOÃO SILVA",
      "aluno_email": "joao@aqua.com.br",
      "aluno_telefone": null,
      "plano_nome": "3x Semana Teste",
      "plano_valor": "0.00",
      "status_nome": "Ativa",
      "status_codigo": "ativa"
    }
  ],
  "total": 1,
  "periodo": {
    "inicio": "2026-02-06",
    "fim": "2026-02-13",
    "dias": 7
  }
}
```

**Uso:** Para dashboard de administração mostrando vencimentos próximos

---

## Alterações no Frontend

### 1. Tela de Criação/Edição de Matrícula

**Arquivo sugerido:** `screens/matriculas/FormMatriculaScreen.js` ou similar

**Alterações necessárias:**

1. **Adicionar campo editável de data de vencimento:**

```jsx
const [proximaDataVencimento, setProximaDataVencimento] = useState('');
const [dataCalculadaAutomatica, setDataCalculadaAutomatica] = useState('');

// Quando selecionar plano e data de início, calcular automaticamente
useEffect(() => {
  if (planoSelecionado && dataInicio) {
    const inicio = new Date(dataInicio);
    const duracaoDias = planoSelecionado.duracao_dias;
    const vencimento = new Date(inicio);
    vencimento.setDate(vencimento.getDate() + duracaoDias);
    
    const vencimentoFormatado = vencimento.toISOString().split('T')[0];
    setDataCalculadaAutomatica(vencimentoFormatado);
    setProximaDataVencimento(vencimentoFormatado);
  }
}, [planoSelecionado, dataInicio]);

// No formulário
<FormGroup>
  <Label>Acesso Válido Até</Label>
  <Input
    type="date"
    value={proximaDataVencimento}
    onChange={(e) => setProximaDataVencimento(e.target.value)}
  />
  <FormText color="muted">
    Data calculada automaticamente: {formatarData(dataCalculadaAutomatica)}
    <br />
    Você pode alterar manualmente se necessário
  </FormText>
</FormGroup>
```

2. **Na visualização de matrícula existente, adicionar botão de edição:**

```jsx
<Card>
  <CardBody>
    <Row>
      <Col md={8}>
        <h5>Acesso Válido Até</h5>
        <p className="mb-0">
          <strong>{formatarData(matricula.proxima_data_vencimento)}</strong>
          {verificarSeVencido(matricula.proxima_data_vencimento) && (
            <Badge color="danger" className="ml-2">Vencido</Badge>
          )}
          {calcularDiasRestantes(matricula.proxima_data_vencimento) <= 7 && (
            <Badge color="warning" className="ml-2">
              {calcularDiasRestantes(matricula.proxima_data_vencimento)} dias restantes
            </Badge>
          )}
        </p>
      </Col>
      <Col md={4} className="text-right">
        <Button 
          color="primary" 
          size="sm"
          onClick={() => setModalEditarVencimento(true)}
        >
          <FaCalendar /> Alterar Data
        </Button>
      </Col>
    </Row>
  </CardBody>
</Card>

{/* Modal para editar data */}
<Modal isOpen={modalEditarVencimento} toggle={() => setModalEditarVencimento(false)}>
  <ModalHeader>Alterar Data de Vencimento</ModalHeader>
  <ModalBody>
    <FormGroup>
      <Label>Nova Data de Vencimento</Label>
      <Input
        type="date"
        value={novaDataVencimento}
        onChange={(e) => setNovaDataVencimento(e.target.value)}
      />
      <FormText color="muted">
        Data atual: {formatarData(matricula.proxima_data_vencimento)}
      </FormText>
    </FormGroup>
  </ModalBody>
  <ModalFooter>
    <Button color="secondary" onClick={() => setModalEditarVencimento(false)}>
      Cancelar
    </Button>
    <Button color="primary" onClick={handleAtualizarVencimento}>
      Salvar
    </Button>
  </ModalFooter>
</Modal>
```

3. **Função para atualizar data:**

```javascript
const handleAtualizarVencimento = async () => {
  try {
    setLoading(true);
    
    const response = await api.put(
      `/admin/matriculas/${matricula.id}/proxima-data-vencimento`,
      { proxima_data_vencimento: novaDataVencimento }
    );
    
    toast.success(response.data.message);
    setModalEditarVencimento(false);
    
    // Recarregar dados da matrícula
    await carregarMatricula();
    
  } catch (error) {
    const errorMsg = error.response?.data?.error || 'Erro ao atualizar data';
    toast.error(errorMsg);
  } finally {
    setLoading(false);
  }
};
```

---

### 2. Dashboard de Vencimentos

**Arquivo sugerido:** `screens/dashboard/VencimentosScreen.js` (NOVO)

**Implementação completa:**

```jsx
import React, { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Row, Col, Badge, Button, Table } from 'reactstrap';
import { FaBell, FaCalendarAlt, FaEnvelope } from 'react-icons/fa';
import api from '../../services/api';
import { formatarData, formatarMoeda } from '../../utils/formatters';

const VencimentosScreen = () => {
  const [vencimentosHoje, setVencimentosHoje] = useState([]);
  const [proximosVencimentos, setProximosVencimentos] = useState([]);
  const [diasFiltro, setDiasFiltro] = useState(7);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    carregarVencimentos();
  }, [diasFiltro]);

  const carregarVencimentos = async () => {
    try {
      setLoading(true);
      
      const [hojeRes, proximosRes] = await Promise.all([
        api.get('/admin/matriculas/vencimentos/hoje'),
        api.get(`/admin/matriculas/vencimentos/proximos?dias=${diasFiltro}`)
      ]);
      
      setVencimentosHoje(hojeRes.data.vencimentos);
      setProximosVencimentos(proximosRes.data.vencimentos);
      
    } catch (error) {
      console.error('Erro ao carregar vencimentos:', error);
    } finally {
      setLoading(false);
    }
  };

  const enviarNotificacao = async (aluno) => {
    // Implementar envio de notificação (email, SMS, etc)
    alert(`Notificação enviada para ${aluno.aluno_nome}`);
  };

  const getBadgeVencimento = (diasRestantes) => {
    if (diasRestantes === 0) return <Badge color="danger">Vence Hoje</Badge>;
    if (diasRestantes <= 3) return <Badge color="warning">{diasRestantes} dias</Badge>;
    return <Badge color="info">{diasRestantes} dias</Badge>;
  };

  return (
    <div className="container-fluid">
      <h2 className="mb-4">
        <FaCalendarAlt /> Vencimentos de Matrículas
      </h2>

      {/* Vencimentos de Hoje */}
      <Card className="mb-4">
        <CardHeader className="bg-danger text-white">
          <FaBell /> Vencimentos de Hoje ({vencimentosHoje.length})
        </CardHeader>
        <CardBody>
          {vencimentosHoje.length === 0 ? (
            <p className="text-muted mb-0">Nenhum vencimento hoje</p>
          ) : (
            <Table striped hover responsive>
              <thead>
                <tr>
                  <th>Aluno</th>
                  <th>Email</th>
                  <th>Plano</th>
                  <th>Valor</th>
                  <th>Tipo</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                {vencimentosHoje.map(v => (
                  <tr key={v.id}>
                    <td>{v.aluno_nome}</td>
                    <td>{v.aluno_email}</td>
                    <td>{v.plano_nome}</td>
                    <td>{formatarMoeda(v.valor)}</td>
                    <td>
                      {v.periodo_teste === 1 ? (
                        <Badge color="info">Teste</Badge>
                      ) : (
                        <Badge color="success">Pago</Badge>
                      )}
                    </td>
                    <td>
                      <Button 
                        size="sm" 
                        color="primary"
                        onClick={() => enviarNotificacao(v)}
                      >
                        <FaEnvelope /> Notificar
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* Próximos Vencimentos */}
      <Card>
        <CardHeader>
          <Row>
            <Col>
              <FaCalendarAlt /> Próximos Vencimentos ({proximosVencimentos.length})
            </Col>
            <Col className="text-right">
              <div className="btn-group">
                <Button 
                  size="sm" 
                  color={diasFiltro === 7 ? 'primary' : 'outline-primary'}
                  onClick={() => setDiasFiltro(7)}
                >
                  7 dias
                </Button>
                <Button 
                  size="sm" 
                  color={diasFiltro === 15 ? 'primary' : 'outline-primary'}
                  onClick={() => setDiasFiltro(15)}
                >
                  15 dias
                </Button>
                <Button 
                  size="sm" 
                  color={diasFiltro === 30 ? 'primary' : 'outline-primary'}
                  onClick={() => setDiasFiltro(30)}
                >
                  30 dias
                </Button>
              </div>
            </Col>
          </Row>
        </CardHeader>
        <CardBody>
          {proximosVencimentos.length === 0 ? (
            <p className="text-muted mb-0">Nenhum vencimento nos próximos {diasFiltro} dias</p>
          ) : (
            <Table striped hover responsive>
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Aluno</th>
                  <th>Email</th>
                  <th>Plano</th>
                  <th>Valor</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                {proximosVencimentos.map(v => (
                  <tr key={v.id}>
                    <td>
                      {formatarData(v.proxima_data_vencimento)}
                      <br />
                      {getBadgeVencimento(v.dias_restantes)}
                    </td>
                    <td>{v.aluno_nome}</td>
                    <td>{v.aluno_email}</td>
                    <td>{v.plano_nome}</td>
                    <td>{formatarMoeda(v.valor)}</td>
                    <td>
                      {v.periodo_teste === 1 ? (
                        <Badge color="info">Teste</Badge>
                      ) : (
                        <Badge color="success">Pago</Badge>
                      )}
                    </td>
                    <td>
                      <Button 
                        size="sm" 
                        color="primary"
                        onClick={() => enviarNotificacao(v)}
                      >
                        <FaEnvelope /> Notificar
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </Table>
          )}
        </CardBody>
      </Card>
    </div>
  );
};

export default VencimentosScreen;
```

---

### 3. Atualizar Service (matriculaService.js)

**Adicionar novos métodos:**

```javascript
// matriculaService.js

export const matriculaService = {
  // ... métodos existentes ...

  /**
   * Atualizar próxima data de vencimento
   */
  atualizarProximaDataVencimento: async (matriculaId, proximaDataVencimento) => {
    const response = await api.put(
      `/admin/matriculas/${matriculaId}/proxima-data-vencimento`,
      { proxima_data_vencimento: proximaDataVencimento }
    );
    return response.data;
  },

  /**
   * Listar vencimentos de hoje
   */
  listarVencimentosHoje: async () => {
    const response = await api.get('/admin/matriculas/vencimentos/hoje');
    return response.data;
  },

  /**
   * Listar próximos vencimentos
   */
  listarProximosVencimentos: async (dias = 7) => {
    const response = await api.get(
      `/admin/matriculas/vencimentos/proximos?dias=${dias}`
    );
    return response.data;
  }
};
```

---

### 4. Adicionar Rota no Menu

**Arquivo:** `components/Sidebar.js` ou similar

```jsx
{/* Menu Admin */}
<NavItem>
  <NavLink href="/admin/vencimentos">
    <FaCalendarAlt /> Vencimentos
    {vencimentosHojeCount > 0 && (
      <Badge color="danger" className="ml-2">{vencimentosHojeCount}</Badge>
    )}
  </NavLink>
</NavItem>
```

---

### 5. Funções Utilitárias

**Arquivo:** `utils/formatters.js`

```javascript
/**
 * Calcular dias restantes até vencimento
 */
export const calcularDiasRestantes = (dataVencimento) => {
  const hoje = new Date();
  hoje.setHours(0, 0, 0, 0);
  
  const vencimento = new Date(dataVencimento);
  vencimento.setHours(0, 0, 0, 0);
  
  const diffTime = vencimento - hoje;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  return diffDays;
};

/**
 * Verificar se está vencido
 */
export const verificarSeVencido = (dataVencimento) => {
  return calcularDiasRestantes(dataVencimento) < 0;
};

/**
 * Obter cor do badge baseado em dias restantes
 */
export const getCorVencimento = (diasRestantes) => {
  if (diasRestantes < 0) return 'danger';
  if (diasRestantes === 0) return 'danger';
  if (diasRestantes <= 3) return 'warning';
  if (diasRestantes <= 7) return 'info';
  return 'success';
};
```

---

## Notificações Automáticas (Sugestão)

### Cron Job Diário

**Arquivo:** `jobs/notificarVencimentos.js`

```javascript
const cron = require('node-cron');
const api = require('../services/api');
const emailService = require('../services/emailService');

// Rodar todo dia às 8h da manhã
cron.schedule('0 8 * * *', async () => {
  try {
    console.log('Iniciando notificação de vencimentos...');
    
    const response = await api.get('/admin/matriculas/vencimentos/hoje');
    const vencimentos = response.data.vencimentos;
    
    for (const v of vencimentos) {
      await emailService.enviarEmailVencimento({
        nome: v.aluno_nome,
        email: v.aluno_email,
        plano: v.plano_nome,
        dataVencimento: v.proxima_data_vencimento,
        valor: v.valor,
        periodoTeste: v.periodo_teste === 1
      });
    }
    
    console.log(`${vencimentos.length} notificações enviadas`);
    
  } catch (error) {
    console.error('Erro ao enviar notificações:', error);
  }
});
```

---

## Checklist de Implementação

- [ ] Atualizar `FormMatriculaScreen.js` para exibir e permitir edição de `proxima_data_vencimento`
- [ ] Adicionar modal de edição de data na visualização de matrícula
- [ ] Criar `VencimentosScreen.js` para dashboard de vencimentos
- [ ] Atualizar `matriculaService.js` com novos métodos
- [ ] Adicionar rota `/admin/vencimentos` no router
- [ ] Adicionar item no menu lateral com badge de notificação
- [ ] Criar funções utilitárias para formatação de datas
- [ ] Implementar sistema de notificações (email/SMS)
- [ ] Configurar cron job para notificações automáticas (opcional)
- [ ] Adicionar testes unitários para novas funcionalidades

---

## Observações Importantes

1. **Validação de Data:** O campo aceita apenas formato `YYYY-MM-DD`
2. **Permissões:** Todos os endpoints exigem autenticação de admin
3. **Check-in:** O sistema valida `proxima_data_vencimento` ao fazer check-in:
   - Se `proxima_data_vencimento < hoje`: Check-in é **BLOQUEADO**
   - Mensagem de erro: "Seu acesso expirou em DD/MM/YYYY. Por favor, renove sua matrícula."
   - Código de erro: `MATRICULA_VENCIDA`
4. **Período Teste:** Matrículas com `periodo_teste = 1` são planos gratuitos
5. **Notificações:** Implementar de acordo com preferência do cliente (email, SMS, push)
6. **Status Automático:** 
   - Evento MySQL atualiza status automaticamente todo dia às 00:01
   - Status `ativa` → `vencida` quando `proxima_data_vencimento < hoje`
   - Matrículas com status `vencida` permitem criar nova matrícula (renovação)
   - Matrículas `ativa` com acesso válido bloqueiam nova matrícula na mesma modalidade

### Códigos de Erro no Check-in

**403 - SEM_MATRICULA:**
```json
{
  "error": "Você não possui matrícula ativa",
  "codigo": "SEM_MATRICULA"
}
```

**403 - MATRICULA_VENCIDA:**
```json
{
  "error": "Seu acesso expirou em 05/02/2026. Por favor, renove sua matrícula.",
  "codigo": "MATRICULA_VENCIDA",
  "data_vencimento": "2026-02-05"
}
```

### Fluxo de Validação no Check-in

1. ✅ Verifica se usuário tem matrícula ativa
2. ✅ Verifica se `proxima_data_vencimento >= hoje`
3. ✅ Verifica se já tem check-in na turma
4. ✅ Verifica tolerância de horário (para alunos)
5. ✅ Registra check-in

**Importante:** Mesmo que o admin tente fazer check-in de um aluno vencido, o sistema bloqueará. O admin deve primeiro renovar a matrícula (atualizar `proxima_data_vencimento`).

### Status de Matrícula

O sistema possui 6 status:

| ID | Código | Nome | Permite Check-in | Permite Nova Matrícula | Automático |
|----|--------|------|------------------|------------------------|------------|
| 1 | ativa | Ativa | ✅ Sim (se não vencido) | ❌ Não (bloqueia mesma modalidade) | - |
| 2 | vencida | Vencida | ❌ Não | ✅ Sim (permite renovar) | ✅ Sim (atualizado às 00:01) |
| 3 | cancelada | Cancelada | ❌ Não | ✅ Sim | ✅ Sim (após 5 dias vencido) |
| 4 | finalizada | Finalizada | ❌ Não | ✅ Sim | ❌ Manual |
| 5 | pendente | Pendente | ❌ Não | - | ❌ Manual |
| 6 | bloqueado | Bloqueado | ❌ Não | ❌ Não | ❌ Manual |

**Automação de Status:**
- Todo dia às 00:01, evento MySQL executa:
  ```sql
  UPDATE matriculas
  SET status_id = 2 -- vencida
  WHERE status_id = 1 -- ativa
  AND proxima_data_vencimento < CURDATE();
  ```

---

## Suporte

Dúvidas sobre implementação ou comportamento dos endpoints, consultar:
- Documentação da API: `/docs/API_QUICK_REFERENCE.md`
- Controller: `/app/Controllers/MatriculaController.php`
- Rotas: `/routes/api.php`
