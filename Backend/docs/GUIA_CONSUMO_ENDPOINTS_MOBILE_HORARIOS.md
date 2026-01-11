# 🚀 Guia Prático: Consumindo os Endpoints Mobile

## Exemplos com cURL

### 1️⃣ Listar Horários de Hoje

```bash
TOKEN="seu_jwt_token_aqui"

curl -X GET "http://localhost:8080/mobile/horarios" \
  -H "Authorization: Bearer $TOKEN"
```

**Resposta esperada:**
```json
{
    "type": "success",
    "message": "Horários de hoje carregados",
    "data": {
        "data": "2026-01-10",
        "dia_semana": "Sábado",
        "horarios": [
            {
                "horario_id": 5,
                "horario_inicio": "07:00",
                "horario_fim": "08:00",
                "limite_alunos": 30,
                "confirmados": 18,
                "turmas": [...]
            }
        ]
    }
}
```

---

### 2️⃣ Listar Próximos 7 Dias

```bash
TOKEN="seu_jwt_token_aqui"

# Próximos 7 dias (padrão)
curl -X GET "http://localhost:8080/mobile/horarios/proximos" \
  -H "Authorization: Bearer $TOKEN"

# Próximos 14 dias
curl -X GET "http://localhost:8080/mobile/horarios/proximos?dias=14" \
  -H "Authorization: Bearer $TOKEN"
```

**Resposta esperada:**
```json
{
    "type": "success",
    "message": "Próximos dias carregados",
    "data": {
        "dias": [
            {
                "data": "2026-01-10",
                "dia_semana": "Sábado",
                "ativo": true,
                "turmas_count": 3,
                "horarios": [...]
            },
            {
                "data": "2026-01-11",
                "dia_semana": "Domingo",
                "ativo": true,
                "turmas_count": 2,
                "horarios": [...]
            }
        ]
    }
}
```

---

### 3️⃣ Listar Turmas de um Dia Específico

```bash
TOKEN="seu_jwt_token_aqui"
DIA_ID=150

curl -X GET "http://localhost:8080/mobile/horarios/$DIA_ID" \
  -H "Authorization: Bearer $TOKEN"
```

**Resposta esperada:**
```json
{
    "type": "success",
    "message": "Detalhes do dia carregado",
    "data": {
        "dia": {
            "id": 150,
            "data": "2026-01-10",
            "dia_semana": "Sábado",
            "ativo": true
        },
        "horarios": [
            {
                "horario_id": 5,
                "horario_inicio": "07:00",
                "horario_fim": "08:00",
                "duracao_minutos": 60,
                "limite_alunos": 30,
                "confirmados": 18,
                "vagas_disponiveis": 12,
                "turmas": [
                    {
                        "turma_id": 42,
                        "turma_nome": "Turma A",
                        "professor": {
                            "id": 12,
                            "nome": "João Silva",
                            "email": "joao@email.com"
                        },
                        "modalidade": {
                            "id": 3,
                            "nome": "Pilates",
                            "cor": "#FF6B6B",
                            "descricao": "Aula de pilates"
                        },
                        "confirmados": 18,
                        "vagas_disponiveis": 12,
                        "lotacao_percentual": 60
                    }
                ]
            }
        ]
    }
}
```

---

## Exemplos com JavaScript/Fetch

### 1️⃣ Carregar Próximos Dias

```javascript
async function carregarProximosDias(numeroDias = 7) {
    const token = localStorage.getItem('token');
    
    try {
        const response = await fetch(
            `http://localhost:8080/mobile/horarios/proximos?dias=${numeroDias}`,
            {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            }
        );

        const data = await response.json();

        if (data.type === 'success') {
            console.log('Dias carregados:', data.data.dias);
            return data.data.dias; // Array de dias
        } else {
            console.error('Erro:', data.message);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

// Usar
const dias = await carregarProximosDias(7);
// dias = [
//   { data: "2026-01-10", dia_semana: "Sábado", ativo: true, turmas_count: 3, ... },
//   { data: "2026-01-11", dia_semana: "Domingo", ativo: true, turmas_count: 2, ... }
// ]
```

---

### 2️⃣ Carregar Turmas de um Dia

```javascript
async function carregarTurmasDoDia(diaId) {
    const token = localStorage.getItem('token');
    
    try {
        const response = await fetch(
            `http://localhost:8080/mobile/horarios/${diaId}`,
            {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            }
        );

        const data = await response.json();

        if (data.type === 'success') {
            console.log('Turmas carregadas:', data.data);
            return data.data;
        } else {
            console.error('Erro:', data.message);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

// Usar
const dadosDia = await carregarTurmasDoDia(150);
// dadosDia = {
//   dia: { id: 150, data: "2026-01-10", dia_semana: "Sábado", ... },
//   horarios: [
//     {
//       horario_id: 5,
//       horario_inicio: "07:00",
//       turmas: [
//         { turma_id: 42, turma_nome: "Turma A", professor: {...}, modalidade: {...}, ... }
//       ]
//     }
//   ]
// }
```

---

### 3️⃣ Renderizar Barra de Dias

```javascript
function renderizarBarraDias(dias, onDiaSelect) {
    const container = document.getElementById('dias-carousel');
    
    dias.forEach((dia) => {
        const button = document.createElement('button');
        button.className = `dia-button ${!dia.ativo ? 'desativado' : ''}`;
        
        const data = new Date(dia.data);
        const diaDoMes = data.getDate();
        const mes = data.toLocaleDateString('pt-BR', { month: 'short' });
        
        button.innerHTML = `
            <div class="dia-numero">${diaDoMes}</div>
            <div class="dia-mes">${mes}</div>
            <div class="turmas-count">${dia.turmas_count} aulas</div>
        `;
        
        if (dia.ativo) {
            button.addEventListener('click', () => {
                // Remove seleção anterior
                document.querySelectorAll('.dia-button.selecionado')
                    .forEach(btn => btn.classList.remove('selecionado'));
                
                // Marca como selecionado
                button.classList.add('selecionado');
                
                // Carrega turmas do dia
                onDiaSelect(dia.id || dia.data);
            });
        }
        
        container.appendChild(button);
    });
}

// Usar
const dias = await carregarProximosDias(7);
renderizarBarraDias(dias, async (diaId) => {
    const dados = await carregarTurmasDoDia(diaId);
    renderizarTurmas(dados);
});
```

---

### 4️⃣ Renderizar Turmas por Horário

```javascript
function renderizarTurmas(dadosDia) {
    const container = document.getElementById('turmas-list');
    container.innerHTML = ''; // Limpar
    
    const { horarios } = dadosDia;
    
    // Horários já vêm ordenados por hora do backend
    horarios.forEach((horario) => {
        // Título do horário
        const horarioHeader = document.createElement('div');
        horarioHeader.className = 'horario-header';
        horarioHeader.innerHTML = `
            <span class="horario-inicio">${horario.horario_inicio}</span>
            <span class="duracao">${horario.duracao_minutos}min</span>
        `;
        container.appendChild(horarioHeader);
        
        // Turmas deste horário
        horario.turmas.forEach((turma) => {
            const card = document.createElement('div');
            card.className = 'turma-card';
            
            const percentualLotacao = turma.lotacao_percentual;
            const corBarra = percentualLotacao < 50 ? '#4CAF50' 
                            : percentualLotacao < 80 ? '#FFC107' 
                            : '#F44336';
            
            card.innerHTML = `
                <div class="turma-header">
                    <h3>${turma.turma_nome}</h3>
                    <span class="modalidade" style="background-color: ${turma.modalidade.cor}">
                        ${turma.modalidade.nome}
                    </span>
                </div>
                
                <div class="professor">
                    <strong>Professor:</strong> ${turma.professor.nome}
                </div>
                
                <div class="lotacao">
                    <div class="lotacao-info">
                        <span>${turma.confirmados}/${horario.limite_alunos} vagas</span>
                        <span class="vagas">${turma.vagas_disponiveis} disponíveis</span>
                    </div>
                    <div class="lotacao-barra">
                        <div class="preenchida" style="width: ${percentualLotacao}%; background-color: ${corBarra}"></div>
                    </div>
                </div>
                
                <button class="btn-checkin" onclick="fazerCheckin(${turma.turma_id})">
                    ${turma.vagas_disponiveis > 0 ? 'Fazer Check-in' : 'Turma Lotada'}
                </button>
            `;
            
            // Desabilitar botão se lotada
            if (turma.vagas_disponiveis === 0) {
                card.querySelector('.btn-checkin').disabled = true;
            }
            
            container.appendChild(card);
        });
    });
}
```

---

### 5️⃣ Fazer Check-in

```javascript
async function fazerCheckin(turmaId) {
    const token = localStorage.getItem('token');
    
    try {
        const response = await fetch(
            'http://localhost:8080/mobile/checkin',
            {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    turma_id: turmaId
                })
            }
        );

        const data = await response.json();

        if (data.type === 'success' || data.success) {
            alert('✅ Check-in realizado com sucesso!');
            // Recarregar dia atual
            const diaId = document.querySelector('.dia-button.selecionado')?.dataset.id;
            if (diaId) {
                const dados = await carregarTurmasDoDia(diaId);
                renderizarTurmas(dados);
            }
        } else {
            alert(`❌ ${data.message || data.error}`);
        }
    } catch (error) {
        alert('❌ Erro ao fazer check-in');
        console.error(error);
    }
}
```

---

## Fluxo Completo - App Abre

```javascript
async function inicializarApp() {
    // 1. Carregar próximos dias
    console.log('📍 Carregando próximos dias...');
    const dias = await carregarProximosDias(7);
    
    // 2. Renderizar barra de dias
    console.log('📍 Renderizando dias...');
    renderizarBarraDias(dias, async (diaId) => {
        // 3. Ao selecionar dia, carregar turmas
        console.log(`📍 Carregando turmas do dia ${diaId}...`);
        const dados = await carregarTurmasDoDia(diaId);
        
        // 4. Renderizar turmas (já ordenadas por hora)
        console.log('📍 Renderizando turmas...');
        renderizarTurmas(dados);
    });
    
    // 5. Selecionar primeiro dia automaticamente
    if (dias.length > 0) {
        document.querySelector('.dia-button')?.click();
    }
}

// Iniciar ao carregar a página
document.addEventListener('DOMContentLoaded', inicializarApp);
```

---

## 🎯 Estrutura HTML Mínima

```html
<div id="app">
    <!-- Header -->
    <header>
        <h1>Check-in</h1>
        <p>Selecione uma aula</p>
    </header>

    <!-- Barra de Dias -->
    <div class="dias-container">
        <div id="dias-carousel" class="carousel"></div>
    </div>

    <!-- Info do Dia Selecionado -->
    <div id="dia-info" class="dia-info"></div>

    <!-- Lista de Turmas -->
    <div id="turmas-list" class="turmas-list"></div>
</div>
```

---

## ⚠️ Tratamento de Erros

```javascript
async function carregarComErro(url, token) {
    try {
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        // Verificar status HTTP
        if (response.status === 401) {
            // Token expirado
            console.error('Token expirado. Faça login novamente.');
            redirectToLogin();
            return null;
        }

        if (response.status === 404) {
            console.error('Recurso não encontrado');
            return null;
        }

        if (response.status === 500) {
            console.error('Erro no servidor');
            return null;
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Erro de rede:', error);
        return null;
    }
}
```

---

## 📦 Dados Retornados - Estrutura Completa

### Array de Dias
```javascript
[
    {
        "data": "2026-01-10",
        "dia_semana": "Sábado",
        "ativo": true,
        "turmas_count": 3
    },
    {
        "data": "2026-01-11",
        "dia_semana": "Domingo",
        "ativo": true,
        "turmas_count": 2
    }
]
```

### Array de Horários com Turmas
```javascript
[
    {
        "horario_id": 5,
        "horario_inicio": "07:00",
        "horario_fim": "08:00",
        "duracao_minutos": 60,
        "limite_alunos": 30,
        "confirmados": 18,
        "vagas_disponiveis": 12,
        "turmas": [
            {
                "turma_id": 42,
                "turma_nome": "Turma A",
                "professor": {
                    "id": 12,
                    "nome": "João Silva",
                    "email": "joao@example.com"
                },
                "modalidade": {
                    "id": 3,
                    "nome": "Pilates",
                    "cor": "#FF6B6B"
                },
                "confirmados": 18,
                "vagas_disponiveis": 12,
                "lotacao_percentual": 60
            }
        ]
    }
]
```
