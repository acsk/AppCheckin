import React, { useMemo, useState } from 'react';
import { Badge, Button, IconButton, SegmentedControl, TextField } from '../../components/ck';

const POR_PAGINA = 10;
const data = (v) => v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—';
const regularizados = (c) => c.respostas + (c.dispensados || 0);
const normalizar = (v) => String(v).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

export default function AtualizacaoCadastralTable({ campanhas, resumo, onPendentes, onEditar, onEncerrar, onExcluir, disabled }) {
  const [busca, setBusca] = useState('');
  const [situacao, setSituacao] = useState('todas');
  const [pagina, setPagina] = useState(1);
  const ativas = campanhas.filter((c) => c.ativa).length;
  const academia = (c) => c.todas_academias ? 'Todas as academias' : c.academias.map((a) => a.nome).join(', ');
  const filtradas = useMemo(() => campanhas.filter((c) => (
    (situacao === 'todas' || (situacao === 'ativas' ? c.ativa : !c.ativa))
    && normalizar(`${c.titulo} ${academia(c)} ${resumo(c)}`).includes(normalizar(busca.trim()))
  )), [campanhas, situacao, busca, resumo]);
  const paginas = Math.max(1, Math.ceil(filtradas.length / POR_PAGINA));
  const atual = Math.min(pagina, paginas);
  const inicio = (atual - 1) * POR_PAGINA;
  const linhas = filtradas.slice(inicio, inicio + POR_PAGINA);

  return (
    <div className="ck-cad-table">
      <div className="ck-cad-table__toolbar">
        <TextField label="Buscar atualização" type="search" icon="search" value={busca} placeholder="Título, academia ou pedido"
          onChange={(e) => { setBusca(e.target.value); setPagina(1); }} />
        <SegmentedControl label="Situação das atualizações" value={situacao}
          options={[{ value: 'todas', label: `Todas (${campanhas.length})` }, { value: 'ativas', label: `Em andamento (${ativas})` }, { value: 'encerradas', label: `Encerradas (${campanhas.length - ativas})` }]}
          onChange={(v) => { setSituacao(v); setPagina(1); }} />
      </div>
      <p className="ck-mc-hint">Público, progresso e pendências consideram apenas alunos com matrícula ativa nas academias da campanha.</p>
      <div className="ck-pc__wrap">
        <table className="ck-pc__table ck-cad-table__table">
          <caption className="ck-sr">Campanhas de atualização cadastral</caption>
          <thead><tr>
            <th scope="col">Atualização</th><th scope="col">Academias</th><th scope="col">Situação</th>
            <th scope="col">Progresso</th><th scope="col">Datas</th><th scope="col"><span className="ck-sr">Ações</span></th>
          </tr></thead>
          <tbody>
            {linhas.map((c) => (
              <tr key={c.id}>
                <td><strong className="ck-cad-table__title">{c.titulo}</strong><span className="ck-cad-table__summary" title={resumo(c)}>{resumo(c)}</span></td>
                <td><span className="ck-cad-table__academies">{academia(c)}</span></td>
                <td><div className="ck-cad-table__badges">
                  <Badge tone={c.ativa ? 'success' : 'outline'} dot={c.ativa}>{c.ativa ? 'Em andamento' : 'Encerrada'}</Badge>
                  <Badge tone={c.bloquear_app ? 'warning' : 'outline'} icon={c.bloquear_app ? 'lock' : undefined}>{c.bloquear_app ? 'Bloqueia o app' : 'Pode adiar'}</Badge>
                </div></td>
                <td><div className="ck-cad-table__progress">
                  <strong>{regularizados(c)} de {c.publico} regularizados</strong>
                  <small>{c.respostas} atualizaram · {c.dispensados || 0} dispensados</small>
                  <progress value={regularizados(c)} max={Math.max(1, c.publico)} aria-label={`${c.titulo}: ${regularizados(c)} de ${c.publico} alunos regularizados`} />
                  <small>{c.publico ? `${Math.max(0, c.publico - regularizados(c))} pendente${c.publico - regularizados(c) === 1 ? '' : 's'}` : 'Sem alunos com matrícula ativa'}</small>
                </div></td>
                <td><span className="ck-cad-table__date">{data(c.created_at)}</span>
                  {c.encerrada_em ? <small className="ck-cad-table__summary">Encerrada em {data(c.encerrada_em)}</small> : null}</td>
                <td><div className="ck-cad-table__actions">
                  <Button size="sm" variant="ghost" icon="users" disabled={disabled || regularizados(c) >= c.publico} onClick={() => onPendentes(c)}>Pendentes</Button>
                  {c.ativa ? <>
                    <IconButton size="sm" icon="pencil" label={`Editar ${c.titulo}`} disabled={disabled} onClick={() => onEditar(c)} />
                    <IconButton size="sm" icon="x" label={`Encerrar ${c.titulo}`} disabled={disabled} onClick={() => onEncerrar(c)} />
                  </> : null}
                  <IconButton size="sm" icon="trash" label={`Excluir definitivamente ${c.titulo}`} className="ck-cad-table__delete" disabled={disabled} onClick={() => onExcluir(c)} />
                </div></td>
              </tr>
            ))}
            {!linhas.length ? <tr><td colSpan={6} className="ck-cad-table__empty">{campanhas.length ? 'Nenhuma atualização encontrada com estes filtros.' : 'Nenhuma atualização cadastral cadastrada.'}</td></tr> : null}
          </tbody>
        </table>
      </div>
      {filtradas.length ? <div className="ck-ml__foot">
        <span role="status">{inicio + 1}–{Math.min(inicio + POR_PAGINA, filtradas.length)} de {filtradas.length} atualizações</span>
        <nav className="ck-ml__pager" aria-label="Páginas das atualizações">
          <IconButton size="sm" icon="chevron-left" label="Página anterior" disabled={atual <= 1} onClick={() => setPagina(atual - 1)} />
          <span>{atual} / {paginas}</span>
          <IconButton size="sm" icon="chevron-right" label="Próxima página" disabled={atual >= paginas} onClick={() => setPagina(atual + 1)} />
        </nav>
      </div> : null}
    </div>
  );
}
