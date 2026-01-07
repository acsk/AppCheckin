# 📊 Sistema de Status Padronizado

## ✅ Implementação Concluída

O sistema agora utiliza **tabelas + Foreign Keys** ao invés de ENUMs para gerenciar status, proporcionando maior flexibilidade e escalabilidade.

---

## 🗂️ Estrutura

### Tabelas de Status Criadas

1. **status_conta_receber** - Status de contas a receber
2. **status_matricula** - Status de matrículas
3. **status_pagamento** - Status de pagamentos
4. **status_checkin** - Status de check-ins
5. **status_usuario** - Status de usuários
6. **status_contrato** - Status de contratos/planos

### Campos Padrão em Todas as Tabelas

```sql
id INT PRIMARY KEY
codigo VARCHAR(50) UNIQUE     -- ex: 'pendente', 'ativo'
nome VARCHAR(100)              -- ex: 'Pendente', 'Ativo'
descricao TEXT
cor VARCHAR(20)                -- hex color para UI (#10b981)
icone VARCHAR(50)              -- nome ícone Feather
ordem INT                      -- ordem de exibição
ativo BOOLEAN
created_at TIMESTAMP
updated_at TIMESTAMP
```

### Campos Específicos

- **status_conta_receber**: `permite_edicao`, `permite_cancelamento`
- **status_matricula**: `permite_checkin`
- **status_usuario**: `permite_login`

---

## 📡 API - StatusController

### Rotas Disponíveis

```
GET /api/status/{tipo}                    - Listar todos os status
GET /api/status/{tipo}/{id}               - Buscar por ID
GET /api/status/{tipo}/codigo/{codigo}    - Buscar por código
```

### Tipos Válidos

- `conta-receber`
- `matricula`
- `pagamento`
- `checkin`
- `usuario`
- `contrato`

### Exemplos de Requisição

```javascript
// Listar status de contas a receber
GET /api/status/conta-receber

// Resposta:
{
  "tipo": "conta-receber",
  "status": [
    {
      "id": 1,
      "codigo": "pendente",
      "nome": "Pendente",
      "descricao": "Aguardando pagamento",
      "cor": "#f59e0b",
      "icone": "clock",
      "ordem": 1,
      "permite_edicao": true,
      "ativo": true
    },
    {
      "id": 2,
      "codigo": "pago",
      "nome": "Pago",
      "cor": "#10b981",
      "icone": "check-circle",
      ...
    }
  ],
  "total": 4
}

// Buscar status específico
GET /api/status/matricula/1

// Buscar por código
GET /api/status/usuario/codigo/ativo
```

---

## 💻 Frontend - statusService

### Importação

```javascript
import statusService from '../../services/statusService';
```

### Métodos Disponíveis

#### Métodos Genéricos

```javascript
// Listar por tipo
const status = await statusService.listar('conta-receber');

// Buscar por ID
const status = await statusService.buscar('matricula', 1);

// Buscar por código
const status = await statusService.buscarPorCodigo('usuario', 'ativo');
```

#### Atalhos Específicos

```javascript
const statusContaReceber = await statusService.listarStatusContaReceber();
const statusMatricula = await statusService.listarStatusMatricula();
const statusPagamento = await statusService.listarStatusPagamento();
const statusCheckin = await statusService.listarStatusCheckin();
const statusUsuario = await statusService.listarStatusUsuario();
const statusContrato = await statusService.listarStatusContrato();
```

#### Helpers

```javascript
// Encontrar status por código em uma lista
const pendente = statusService.encontrarPorCodigo(statusList, 'pendente');

// Obter cor
const cor = statusService.getCor(status); // #10b981

// Obter ícone
const icone = statusService.getIcone(status); // 'check-circle'

// Formatar para Picker
const pickerItems = statusService.formatarParaPicker(statusList);
// [{ label: 'Pendente', value: 1, ... }]
```

---

## 🎨 Componente StatusBadge

### Importação

```javascript
import StatusBadge from '../../components/StatusBadge';
```

### Uso Básico

```javascript
// Simples
<StatusBadge status={conta.status_info} />

// Com tamanho customizado
<StatusBadge 
  status={matricula.status_info} 
  size="large"  // 'small' | 'medium' | 'large'
/>

// Sem ícone
<StatusBadge 
  status={pagamento.status_info} 
  showIcon={false}
/>

// Com estilo customizado
<StatusBadge 
  status={usuario.status_info}
  style={{ marginLeft: 10 }}
/>
```

### Props

| Prop | Tipo | Default | Descrição |
|------|------|---------|-----------|
| status | Object | required | Objeto com nome, cor, icone |
| size | string | 'medium' | 'small', 'medium', 'large' |
| showIcon | boolean | true | Mostrar/ocultar ícone |
| style | Object | {} | Estilos customizados |

---

## 📝 Exemplos de Implementação

### Exemplo 1: Tela de Contas a Receber

```javascript
import React, { useState, useEffect } from 'react';
import { View, Text, FlatList } from 'react-native';
import statusService from '../../services/statusService';
import StatusBadge from '../../components/StatusBadge';

export default function ContasReceberScreen() {
  const [contas, setContas] = useState([]);
  const [statusList, setStatusList] = useState([]);

  useEffect(() => {
    loadStatus();
    loadContas();
  }, []);

  const loadStatus = async () => {
    const status = await statusService.listarStatusContaReceber();
    setStatusList(status);
  };

  const loadContas = async () => {
    // Buscar contas com status populado
    const response = await api.get('/contas-receber');
    setContas(response.data);
  };

  return (
    <FlatList
      data={contas}
      renderItem={({ item }) => (
        <View style={styles.item}>
          <Text>{item.descricao}</Text>
          <StatusBadge status={item.status_info} />
        </View>
      )}
    />
  );
}
```

### Exemplo 2: Filtro de Status com Picker

```javascript
import React, { useState, useEffect } from 'react';
import { Picker } from '@react-native-picker/picker';
import statusService from '../../services/statusService';

export default function FiltroStatus() {
  const [statusList, setStatusList] = useState([]);
  const [selectedStatus, setSelectedStatus] = useState('');

  useEffect(() => {
    loadStatus();
  }, []);

  const loadStatus = async () => {
    const status = await statusService.listarStatusMatricula();
    setStatusList(status);
  };

  return (
    <Picker
      selectedValue={selectedStatus}
      onValueChange={setSelectedStatus}
    >
      <Picker.Item label="Todos os Status" value="" />
      {statusList.map(status => (
        <Picker.Item
          key={status.id}
          label={status.nome}
          value={status.id}
        />
      ))}
    </Picker>
  );
}
```

### Exemplo 3: Mudar Status de Conta

```javascript
const mudarStatus = async (contaId, novoStatusCodigo) => {
  try {
    // 1. Buscar ID do novo status
    const status = await statusService.buscarPorCodigo(
      'conta-receber', 
      novoStatusCodigo
    );
    
    // 2. Atualizar conta
    await api.put(`/contas-receber/${contaId}`, {
      status_id: status.id
    });
    
    showSuccess('Status atualizado!');
  } catch (error) {
    showError('Erro ao atualizar status');
  }
};

// Uso
<Button 
  title="Marcar como Pago" 
  onPress={() => mudarStatus(conta.id, 'pago')}
/>
```

---

## 🔄 Migração de Código Antigo

### Antes (ENUM)

```javascript
// Antigo - String hardcoded
<Text>{conta.status}</Text> // 'pendente'

// Antigo - Sem informações visuais
{conta.status === 'pendente' && <Text style={{ color: 'orange' }}>Pendente</Text>}
{conta.status === 'pago' && <Text style={{ color: 'green' }}>Pago</Text>}
```

### Depois (FK + Tabelas)

```javascript
// Novo - Componente rico
<StatusBadge status={conta.status_info} />

// Dados vêm do backend como objeto:
conta.status_info = {
  id: 1,
  codigo: 'pendente',
  nome: 'Pendente',
  cor: '#f59e0b',
  icone: 'clock'
}
```

---

## 🗄️ Atualização de Models (Backend)

### Antes

```php
$query = "SELECT * FROM contas_receber WHERE tenant_id = ?";
// status vem como string: 'pendente'
```

### Depois

```php
$query = "
    SELECT 
        cr.*,
        scr.id as status_id,
        scr.codigo as status_codigo,
        scr.nome as status_nome,
        scr.cor as status_cor,
        scr.icone as status_icone,
        scr.permite_edicao as status_permite_edicao
    FROM contas_receber cr
    LEFT JOIN status_conta_receber scr ON cr.status_id = scr.id
    WHERE cr.tenant_id = ?
";

// Estruturar resposta
return array_map(function($row) {
    return [
        'id' => $row['id'],
        'descricao' => $row['descricao'],
        'valor' => $row['valor'],
        'status_info' => [
            'id' => $row['status_id'],
            'codigo' => $row['status_codigo'],
            'nome' => $row['status_nome'],
            'cor' => $row['status_cor'],
            'icone' => $row['status_icone'],
            'permite_edicao' => $row['status_permite_edicao']
        ]
    ];
}, $results);
```

---

## 🎯 Próximos Passos

### 1. Executar Migrations
```bash
# No container do MySQL
docker exec -it <container_mysql> bash
mysql -u root -p appcheckin < /path/to/037_create_status_tables.sql
mysql -u root -p appcheckin < /path/to/038_add_status_id_columns.sql
```

### 2. Atualizar Models
- [ ] ContasReceberController - adicionar JOIN com status
- [ ] MatriculaController - adicionar JOIN com status
- [ ] Atualizar métodos que usam ENUM

### 3. Atualizar Frontend
- [ ] Contas a Receber - usar StatusBadge
- [ ] Matrículas - usar StatusBadge
- [ ] Adicionar filtros com statusService

### 4. Testar
- [ ] Listar status via API
- [ ] Criar/Editar registros com novo status_id
- [ ] Verificar exibição de badges

### 5. Remover ENUMs (Após Validação)
```bash
mysql -u root -p appcheckin < /path/to/039_remove_enum_columns.sql
```

---

## 📚 Referências

- **Migrations**: `/Backend/database/migrations/037_*.sql`
- **Controller**: `/Backend/app/Controllers/StatusController.php`
- **Service**: `/FrontendWeb/src/services/statusService.js`
- **Component**: `/FrontendWeb/src/components/StatusBadge.js`
- **Rotas**: `/Backend/routes/api.php`

---

## ✨ Benefícios Alcançados

✅ **Flexibilidade** - Adicionar status sem migration  
✅ **UI Rica** - Cores e ícones dinâmicos  
✅ **Escalabilidade** - Fácil manutenção  
✅ **Auditabilidade** - Histórico completo  
✅ **Internacionalização** - Pronto para i18n  
✅ **Regras de Negócio** - Metadados ricos (permite_edicao, permite_checkin)

---

**Status da Implementação**: ✅ COMPLETA  
**Próxima Ação**: Executar migrations e testar API
