import React, { useState } from 'react';
import { Badge, Button, Callout, IconButton, Modal, SegmentedControl, SelectField, TextAreaField, TextField } from '../../components/ck';
import { extrairExcecoes, prepararExcecoes } from '../../utils/appConfigExcecoes';
import { showSuccess } from '../../utils/toast';

export default function AppConfigExcecoesModal({ def, estado, academias, onDefinir, onClose }) {
  const originais = extrairExcecoes(estado.rascunho.tenants, def.chave);
  const [valores, setValores] = useState(() => ({ ...originais }));
  const [nova, setNova] = useState('');
  const [salvando, setSalvando] = useState(false);
  const [erro, setErro] = useState('');
  const geral = estado.rascunho.global[def.chave] ?? def.padrao;
  const booleana = def.tipo === 'booleano';
  const ids = Object.keys(valores);
  const livres = academias.filter((a) => valores[a.id] === undefined);
  const nome = (id) => academias.find((a) => String(a.id) === String(id))?.nome || estado.academias?.[id] || `Academia ${id}`;
  const definir = (id, valor) => { setValores((atual) => ({ ...atual, [id]: valor })); setErro(''); };
  const remover = (id) => {
    setValores((atual) => { const copia = { ...atual }; delete copia[id]; return copia; });
    setErro('');
  };
  const adicionar = () => {
    if (!nova) return;
    definir(nova, booleana ? !geral : geral);
    setNova('');
  };
  const igualAoGeral = (valor) => def.tipo === 'inteiro'
    ? String(valor).trim() !== '' && Number(valor) === Number(geral)
    : valor === geral;

  const aplicar = async () => {
    if (salvando) return;
    setSalvando(true);
    setErro('');
    let aplicadas = 0;
    try {
      const alteracoes = prepararExcecoes(def, originais, valores);
      for (const { tenantId, valor } of alteracoes) {
        await onDefinir(def.chave, valor, tenantId);
        aplicadas++;
      }
      if (alteracoes.length) showSuccess('Exceções aplicadas no rascunho');
      onClose();
    } catch (e) {
      setErro(`${e.message}${aplicadas ? ' Algumas alterações já foram salvas no rascunho. Tente novamente para concluir as restantes.' : ''}`);
    } finally {
      setSalvando(false);
    }
  };

  return (
    <Modal open size="md" dialogClassName="ck-cf-exceptions" title={`${def.nome}: exceções por academia`}
      description="Escolha academias que devem ficar diferentes do valor geral. Bloqueio emergencial e versão mínima continuam valendo para todas."
      autoFocus={false} dismissable={!salvando} onClose={onClose}
      footer={<>
        <Button variant="ghost" disabled={salvando} onClick={onClose}>Cancelar</Button>
        <Button variant="primary" icon="check" loading={salvando} onClick={aplicar}>Aplicar no rascunho</Button>
      </>}>
      <div className="ck-cf-exceptions__body">
        <ul className="ck-cf-exceptions__list">
          <li className="ck-cf-exceptions__general">
            <div className="ck-cf-exceptions__name"><strong>Demais academias</strong><small>Valor geral, definido na tela</small></div>
            {booleana ? <Badge tone={geral ? 'success' : 'outline'} dot={!!geral}>{geral ? 'Ligada' : 'Desligada'}</Badge>
              : <span className="ck-cf-exceptions__value">{geral === '' ? '(vazio)' : String(geral)}</span>}
          </li>
          {ids.map((id) => {
            const igual = igualAoGeral(valores[id]);
            const Campo = def.tipo === 'texto' ? TextAreaField : TextField;
            return (
              <li key={id}>
                <div className="ck-cf-exceptions__name"><strong>{nome(id)}</strong>
                  <small className={igual ? 'is-same' : undefined}>{igual ? 'Igual ao valor geral; pode remover' : 'Exceção'}</small>
                </div>
                <div className="ck-cf-exceptions__controls">
                  {booleana ? <div className={salvando ? 'ck-cf-exceptions__disabled' : undefined} inert={salvando ? true : undefined}>
                    <SegmentedControl label={`${nome(id)}: ${def.nome}`} value={valores[id] ? 'ligada' : 'desligada'}
                      options={[{ value: 'ligada', label: 'Ligada' }, { value: 'desligada', label: 'Desligada' }]}
                      onChange={(v) => definir(id, v === 'ligada')} />
                  </div> : <Campo aria-label={`${nome(id)}: ${def.nome}`} value={String(valores[id])} disabled={salvando}
                    inputMode={def.tipo === 'inteiro' ? 'numeric' : undefined} maxLength={def.tipo === 'texto' ? def.max : undefined}
                    onChange={(e) => definir(id, def.tipo === 'inteiro' ? e.target.value.replace(/\D/g, '').slice(0, 6) : e.target.value)} />}
                  <IconButton icon="x" size="sm" label={`Remover exceção de ${nome(id)}`} disabled={salvando} onClick={() => remover(id)} />
                </div>
              </li>
            );
          })}
        </ul>
        {erro ? <Callout tone="danger">{erro}</Callout> : null}
        {livres.length ? <div className="ck-cf-exceptions__add">
          <SelectField label="Adicionar academia" value={nova} placeholder="Escolha a academia" disabled={salvando}
            options={livres.map((a) => ({ value: String(a.id), label: a.nome }))} onChange={(e) => setNova(e.target.value)} />
          <Button icon="plus" disabled={!nova || salvando} onClick={adicionar}>Adicionar</Button>
        </div> : <p className="ck-cf-none">{academias.length ? 'Todas as academias já têm exceção.' : 'Nenhuma academia disponível para adicionar.'}</p>}
      </div>
    </Modal>
  );
}
