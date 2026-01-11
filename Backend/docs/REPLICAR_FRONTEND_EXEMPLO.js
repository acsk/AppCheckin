/**
 * Exemplos de como fazer requisições para o endpoint de replicação de turmas
 * Frontend (JavaScript/TypeScript)
 */

// ════════════════════════════════════════════════════════════════
// 1️⃣ PRÓXIMA SEMANA
// ════════════════════════════════════════════════════════════════

async function replicarProximaSemana(diaId) {
    try {
        const response = await fetch('http://localhost:8080/admin/turmas/replicar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify({
                dia_id: diaId,
                periodo: 'proxima_semana'
            })
        });

        const data = await response.json();
        console.log('✅ Replicação Próxima Semana:', data);
        
        if (data.type === 'success') {
            alert(`✅ ${data.summary.total_criadas} turmas criadas!`);
        }
        
        return data;
    } catch (error) {
        console.error('❌ Erro:', error);
        alert('Erro ao replicar turmas');
    }
}

// Uso:
// replicarProximaSemana(17);


// ════════════════════════════════════════════════════════════════
// 2️⃣ MÊS INTEIRO
// ════════════════════════════════════════════════════════════════

async function replicarMesTodo(diaId, mes = null) {
    // Se não passar mês, usa o mês atual
    const mesAtual = mes || new Date().toISOString().slice(0, 7); // "2026-01"
    
    try {
        const response = await fetch('http://localhost:8080/admin/turmas/replicar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify({
                dia_id: diaId,
                periodo: 'mes_todo',
                mes: mesAtual
            })
        });

        const data = await response.json();
        console.log('✅ Replicação Mês Todo:', data);
        
        if (data.type === 'success') {
            alert(`✅ ${data.summary.total_criadas} turmas criadas para o mês!`);
        }
        
        return data;
    } catch (error) {
        console.error('❌ Erro:', error);
        alert('Erro ao replicar turmas');
    }
}

// Uso:
// replicarMesTodo(17);           // Mês atual
// replicarMesTodo(17, '2026-02'); // Fevereiro


// ════════════════════════════════════════════════════════════════
// 3️⃣ CUSTOMIZADO (DIAS ESPECÍFICOS)
// ════════════════════════════════════════════════════════════════

async function replicarDiasCustomizados(diaId, diasSemana, mes = null) {
    // diasSemana = [2, 3, 4, 5, 6] para seg-sexta
    const mesAtual = mes || new Date().toISOString().slice(0, 7);
    
    try {
        const response = await fetch('http://localhost:8080/admin/turmas/replicar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify({
                dia_id: diaId,
                periodo: 'custom',
                dias_semana: diasSemana,
                mes: mesAtual
            })
        });

        const data = await response.json();
        console.log('✅ Replicação Customizada:', data);
        
        if (data.type === 'success') {
            alert(`✅ ${data.summary.total_criadas} turmas criadas!`);
        }
        
        return data;
    } catch (error) {
        console.error('❌ Erro:', error);
        alert('Erro ao replicar turmas');
    }
}

// Uso:
// replicarDiasCustomizados(17, [2, 3, 4, 5, 6]);           // Seg-sexta
// replicarDiasCustomizados(17, [1, 2, 3, 4, 5, 6, 7]);     // Toda semana
// replicarDiasCustomizados(17, [5], '2026-02');            // Apenas sextas de fevereiro


// ════════════════════════════════════════════════════════════════
// 4️⃣ DESATIVAR TURMA
// ════════════════════════════════════════════════════════════════

async function desativarTurma(turmaId, periodo = 'apenas_esta', mes = null) {
    const mesAtual = mes || new Date().toISOString().slice(0, 7);
    
    try {
        const response = await fetch('http://localhost:8080/admin/turmas/desativar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify({
                turma_id: turmaId,
                periodo: periodo,
                mes: mesAtual
            })
        });

        const data = await response.json();
        console.log('✅ Turma(s) Desativada(s):', data);
        
        if (data.type === 'success') {
            alert(`✅ ${data.summary.total_desativadas} turma(s) desativada(s)!`);
        }
        
        return data;
    } catch (error) {
        console.error('❌ Erro:', error);
        alert('Erro ao desativar turma');
    }
}

// Uso:
// desativarTurma(1);                              // Desativa apenas esta turma
// desativarTurma(1, 'proxima_semana');            // Desativa na próxima semana (mesmo horário)
// desativarTurma(1, 'mes_todo');                  // Desativa o mês inteiro (mesmo horário)
// desativarTurma(1, 'custom', [2, 3, 4], '2026-02'); // Desativa seg-qua em fevereiro


// ════════════════════════════════════════════════════════════════
// 5️⃣ DESATIVAR DIA (FERIADO, SEM AULA)
// ════════════════════════════════════════════════════════════════

async function desativarDias(diaId, periodo = 'apenas_este', diasSemana = null, mes = null) {
    const mesAtual = mes || new Date().toISOString().slice(0, 7);
    
    try {
        let body = {
            dia_id: diaId,
            periodo: periodo,
            mes: mesAtual
        };
        
        if (diasSemana) {
            body.dias_semana = diasSemana;
        }
        
        const response = await fetch('http://localhost:8080/admin/dias/desativar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify(body)
        });

        const data = await response.json();
        console.log('✅ Dia(s) Desativado(s):', data);
        
        if (data.type === 'success') {
            alert(`✅ ${data.summary.total_desativados} dia(s) desativado(s)!`);
        }
        
        return data;
    } catch (error) {
        console.error('❌ Erro:', error);
        alert('Erro ao desativar dias');
    }
}

// Uso:
// desativarDias(17);                           // Desativa apenas este dia (feriado específico)
// desativarDias(17, 'proxima_semana');         // Desativa próxima semana (mesmo dia semana)
// desativarDias(17, 'mes_todo');               // Desativa o mês inteiro (todos os dias)
// desativarDias(17, 'custom', [1], '2026-02'); // Desativa todos os domingos de fevereiro


// ════════════════════════════════════════════════════════════════
// COMPONENTE REACT EXEMPLO
// ════════════════════════════════════════════════════════════════

/*
import React, { useState } from 'react';

export function ReplicarTurmasModal() {
    const [diaId, setDiaId] = useState(17);
    const [periodo, setPeriodo] = useState('proxima_semana');
    const [mes, setMes] = useState(new Date().toISOString().slice(0, 7));
    const [loading, setLoading] = useState(false);

    const handleReplicar = async () => {
        setLoading(true);
        
        let body = {
            dia_id: diaId,
            periodo: periodo
        };

        if (periodo === 'mes_todo' || periodo === 'custom') {
            body.mes = mes;
        }

        if (periodo === 'custom') {
            body.dias_semana = [2, 3, 4, 5, 6]; // seg-sexta (mude conforme necessário)
        }

        try {
            const response = await fetch('http://localhost:8080/admin/turmas/replicar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                },
                body: JSON.stringify(body)
            });

            const data = await response.json();

            if (data.type === 'success') {
                alert(`✅ ${data.summary.total_criadas} turmas criadas!`);
            } else {
                alert(`❌ ${data.message}`);
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao replicar turmas');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="modal">
            <h2>Replicar Turmas</h2>
            
            <div>
                <label>Período:</label>
                <select value={periodo} onChange={(e) => setPeriodo(e.target.value)}>
                    <option value="proxima_semana">Próxima Semana</option>
                    <option value="mes_todo">Mês Inteiro</option>
                    <option value="custom">Customizado</option>
                </select>
            </div>

            {(periodo === 'mes_todo' || periodo === 'custom') && (
                <div>
                    <label>Mês:</label>
                    <input 
                        type="month" 
                        value={mes}
                        onChange={(e) => setMes(e.target.value)}
                    />
                </div>
            )}

            <button 
                onClick={handleReplicar}
                disabled={loading}
            >
                {loading ? 'Replicando...' : 'Replicar'}
            </button>
        </div>
    );
}
*/


// ════════════════════════════════════════════════════════════════
// CURL EXEMPLOS (para testar no terminal)
// ════════════════════════════════════════════════════════════════

/*

# REPLICAÇÃO DE TURMAS

# 1. PRÓXIMA SEMANA
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "dia_id": 17,
    "periodo": "proxima_semana"
  }'

# 2. MÊS INTEIRO
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "dia_id": 17,
    "periodo": "mes_todo",
    "mes": "2026-01"
  }'

# 3. CUSTOMIZADO (SEG-SEXTA)
curl -X POST http://localhost:8080/admin/turmas/replicar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "dia_id": 17,
    "periodo": "custom",
    "dias_semana": [2, 3, 4, 5, 6],
    "mes": "2026-01"
  }'

# ════════════════════════════════════════════════════════════════
# DESATIVAÇÃO DE TURMAS

# 4. DESATIVAR TURMA (apenas esta)
curl -X POST http://localhost:8080/admin/turmas/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "turma_id": 1
  }'

# 5. DESATIVAR TURMA (próxima semana, mesmo horário)
curl -X POST http://localhost:8080/admin/turmas/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "turma_id": 1,
    "periodo": "proxima_semana"
  }'

# 6. DESATIVAR TURMA (mês inteiro, mesmo horário)
curl -X POST http://localhost:8080/admin/turmas/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "turma_id": 1,
    "periodo": "mes_todo",
    "mes": "2026-01"
  }'

# ════════════════════════════════════════════════════════════════
# DESATIVAÇÃO DE DIAS (FERIADOS, SEM AULA)

# 7. DESATIVAR DIA (apenas este - feriado específico)
curl -X POST http://localhost:8080/admin/dias/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "dia_id": 17
  }'

# 8. DESATIVAR DIAS (próxima semana, mesmo dia da semana)
curl -X POST http://localhost:8080/admin/dias/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "dia_id": 17,
    "periodo": "proxima_semana"
  }'

# 9. DESATIVAR DIAS (mês inteiro, todos os dias)
curl -X POST http://localhost:8080/admin/dias/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "dia_id": 17,
    "periodo": "mes_todo",
    "mes": "2026-01"
  }'

# 10. DESATIVAR DIAS (custom - todos os domingos do mês)
curl -X POST http://localhost:8080/admin/dias/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "dia_id": 17,
    "periodo": "custom",
    "dias_semana": [1],
    "mes": "2026-01"
  }'

*/


// ════════════════════════════════════════════════════════════════
// DIAS DA SEMANA
// ════════════════════════════════════════════════════════════════

const DIAS_SEMANA = {
    1: 'Domingo',
    2: 'Segunda',
    3: 'Terça',
    4: 'Quarta',
    5: 'Quinta',
    6: 'Sexta',
    7: 'Sábado'
};

// Exemplo de uso para checkbox:
const OPCOES_DIAS = [
    { value: 2, label: 'Segunda' },
    { value: 3, label: 'Terça' },
    { value: 4, label: 'Quarta' },
    { value: 5, label: 'Quinta' },
    { value: 6, label: 'Sexta' },
    { value: 7, label: 'Sábado' },
    { value: 1, label: 'Domingo' }
];
