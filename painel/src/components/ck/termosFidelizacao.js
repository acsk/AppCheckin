import React, { useEffect, useState } from 'react';
import { Badge, Button, Callout, Modal, SelectField, Switch, TextAreaField } from './index';
import { TERMO_MODELO_FIDELIZACAO } from './planos';

/**
 * Termos adicionais da fidelização da academia: um texto herdado por todos os ciclos com fidelização.
 * Ciclos com termos próprios aparecem para importar o texto deles ou fazê-los voltar a herdar.
 * Props: open, dados ({ texto, versao, ciclos }), saving, onCancel, onSave({ texto, aplicar_a_todos }).
 */
export default function TermosFidelizacaoModal({ open, dados, saving, onCancel, onSave }) {
  const [texto, setTexto] = useState('');
  const [todos, setTodos] = useState(false);
  const [importar, setImportar] = useState('');
  useEffect(() => { if (open) { setTexto(dados?.texto || ''); setTodos(false); setImportar(''); } }, [open, dados]);

  const ciclos = dados?.ciclos || [];
  const proprios = ciclos.filter((c) => !c.herda);
  const herdam = ciclos.length - proprios.length;
  const mudou = texto.trim() !== (dados?.texto || '').trim();

  return <Modal open={open} size="lg" title="Termos da fidelidade"
    description="Texto adicional mostrado no aceite de todos os ciclos com fidelização, depois dos valores, prazo e regras de cada ciclo."
    dismissable={!saving} onClose={onCancel}
    footer={<>
      <span style={{ flex: 1 }} />
      <Button variant="ghost" disabled={saving} onClick={onCancel}>Cancelar</Button>
      <Button variant="primary" icon="save" loading={saving} disabled={!mudou && !todos} onClick={() => onSave({ texto, aplicar_a_todos: todos })}>Salvar termos</Button>
    </>}>
    <div className="ck-tf">
      <div className="ck-tf__resumo">
        <Badge tone="success" icon="check">{herdam === 1 ? '1 ciclo usa estes termos' : `${herdam} ciclos usam estes termos`}</Badge>
        {proprios.length ? <Badge tone="warning">{proprios.length === 1 ? '1 ciclo com termos próprios' : `${proprios.length} ciclos com termos próprios`}</Badge> : null}
        {dados?.versao ? <span>versão {dados.versao}</span> : null}
      </div>

      <div className="ck-tf__acoes">
        <Button size="sm" icon="file-text" onClick={() => setTexto(TERMO_MODELO_FIDELIZACAO)}>Usar termo modelo</Button>
        {proprios.length ? <>
          <SelectField aria-label="Importar de um ciclo" value={importar} onChange={(e) => setImportar(e.target.value)}
            options={[{ value: '', label: 'Importar de um ciclo…' }, ...proprios.map((c) => ({ value: String(c.id), label: `${c.plano_nome} · ${c.ciclo_nome}` }))]} />
          <Button size="sm" icon="download" disabled={!importar} onClick={() => { const c = proprios.find((x) => String(x.id) === importar); if (c) setTexto(c.termos || ''); }}>Importar</Button>
        </> : null}
      </div>

      <TextAreaField label="Termos adicionais" rows={14} maxLength={5000} value={texto} onChange={(e) => setTexto(e.target.value)}
        hint={`${texto.length}/5.000 caracteres. Mudar o texto vale para novas assinaturas; quem já assinou continua com o que aceitou.`} />

      {proprios.length ? <Switch label="Aplicar a todos os ciclos" checked={todos} onChange={setTodos}
        description={todos
          ? `Os ${proprios.length === 1 ? 'ciclo com termos próprios volta' : `${proprios.length} ciclos com termos próprios voltam`} a usar estes termos.`
          : `Os ciclos com termos próprios continuam com o texto deles: ${proprios.map((c) => `${c.plano_nome} · ${c.ciclo_nome}`).join(', ')}.`} /> : null}

      {!texto.trim() ? <Callout tone="info">Sem termos da academia, o aceite mostra só o texto gerado com os valores, prazo e regras de cada ciclo.</Callout> : null}
    </div>
  </Modal>;
}
