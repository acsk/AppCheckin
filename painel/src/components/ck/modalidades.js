/* AppCheckin — Modalidades: ModalidadesLista e ModalidadeForm, portados do design system.
   Estilos: src/theme/ck-modalidades.css (+ ck-al-* de ck-pessoas.css e ck-ml* de ck-enrollments.css).
   Adaptado à API: ícone é um nome do MaterialCommunityIcons (o app do aluno usa o mesmo) e a cor é hex. */
import React, { useState } from 'react';
import { ActionMenu, Badge, Button, Icon, IconButton, PageHeader, Switch, TextAreaField, TextField } from './index';

const cx = (...a) => a.filter(Boolean).join(' ');
const num = (v) => new Intl.NumberFormat('pt-BR').format(v || 0);

export const MOD_ICONS = [
  ['dumbbell', 'Musculação'], ['weight-lifter', 'Levantamento'], ['arm-flex', 'Fitness'], ['run', 'Corrida'],
  ['heart-pulse', 'Cardio'], ['bike', 'Ciclismo'], ['boxing-glove', 'Boxe'], ['karate', 'Artes marciais'],
  ['yoga', 'Yoga'], ['meditation', 'Meditação'], ['swim', 'Natação'], ['waves', 'Hidroginástica'],
  ['pool', 'Piscina'], ['soccer', 'Futebol'], ['basketball', 'Basquete'], ['volleyball', 'Vôlei'],
  ['tennis', 'Tênis'], ['dance-ballroom', 'Dança'], ['gymnastics', 'Ginástica'], ['hiking', 'Trilha'],
  ['timer-outline', 'HIIT'], ['fire', 'Intenso'], ['trophy', 'Competição'], ['account-group', 'Turma'],
];
// Cores do design system (texto branco com contraste sobre todas)
export const MOD_COLORS = [
  ['#ea580c', 'Laranja'], ['#2563eb', 'Azul'], ['#16a34a', 'Verde'], ['#7c3aed', 'Roxo'],
  ['#dc2626', 'Vermelho'], ['#b45309', 'Âmbar'], ['#0e7490', 'Ciano'], ['#db2777', 'Rosa'],
];

export function ModTile({ icon, color, size }) {
  return (
    <span className={cx('ck-mod__tile', size && `ck-mod__tile--${size}`)} style={{ background: color || MOD_COLORS[0][0] }} aria-hidden="true">
      <Icon glyph name={icon || 'dumbbell'} size={size === 'lg' ? 28 : 20} strokeWidth={2} />
    </span>
  );
}

const plural = (n, one, many) => (n === 1 ? `1 ${one}` : `${num(n)} ${many}`);

export function ModalidadesLista({ rows = [], loading, onNew, onEdit, onOpenPlans, onToggleActive }) {
  const [q, setQ] = useState('');
  const [f, setF] = useState('todas');
  const counts = { todas: rows.length, ativa: 0, inativa: 0 };
  rows.forEach((r) => { counts[r.active === false ? 'inativa' : 'ativa'] += 1; });
  const list = rows.filter((r) => {
    if (f === 'ativa' && r.active === false) return false;
    if (f === 'inativa' && r.active !== false) return false;
    return !q || `${r.name} ${r.description || ''}`.toLowerCase().includes(q.toLowerCase());
  });

  return (
    <div className="ck-ml ck-mod-page">
      <PageHeader
        title="Modalidades"
        description="O que a academia oferece. Cada modalidade aparece para os alunos no app com o ícone e a cor escolhidos."
        actions={<Button variant="primary" icon="plus" onClick={onNew}>Nova modalidade</Button>}
      />
      <div className="ck-mod__toolbar">
        <div className="ck-control ck-ml__search">
          <Icon name="search" size={18} className="ck-control__icon" />
          <input type="search" className="ck-control__input" value={q} placeholder="Buscar modalidade" aria-label="Buscar modalidades" onChange={(e) => setQ(e.target.value)} />
          {q ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setQ('')} /> : null}
        </div>
        {counts.ativa && counts.inativa ? (
          <div className="ck-ml__filters" role="radiogroup" aria-label="Filtrar modalidades">
            {[['todas', 'Todas'], ['ativa', 'Ativas', 'success'], ['inativa', 'Inativas', 'outline']].map(([k, label, dot]) => (
              <button key={k} type="button" role="radio" aria-checked={f === k} className="ck-ml__chip" onClick={() => setF(k)}>
                {dot ? <span className={`ck-ml__chipdot ck-ml__chipdot--${dot}`} aria-hidden="true" /> : null}
                {label}<span className="ck-ml__count">{counts[k]}</span>
              </button>
            ))}
          </div>
        ) : null}
      </div>

      {loading ? (
        <p className="ck-al-hint" role="status"><Icon name="loader" size={15} className="ck-spin" />Carregando modalidades…</p>
      ) : list.length ? (
        <ul className="ck-mod__grid">
          {list.map((r) => {
            const off = r.active === false;
            const n = r.plans || 0;
            return (
              <li key={r.id} className={cx('ck-card ck-mod', off && 'is-off')}>
                <div className="ck-mod__head">
                  <ModTile icon={r.icon} color={r.color} />
                  <div className="ck-mod__titles">
                    <h2 className="ck-mod__name">{r.name}</h2>
                    {off ? <Badge tone="outline">Inativa</Badge> : <Badge tone="success" dot>Ativa</Badge>}
                  </div>
                  {onEdit ? <IconButton icon="pencil" size="sm" label={`Editar ${r.name}`} onClick={() => onEdit(r)} /> : null}
                  <ActionMenu
                    label={`Mais ações para ${r.name}`}
                    items={[
                      onOpenPlans ? { label: 'Ver planos', icon: 'layers', onClick: () => onOpenPlans(r) } : null,
                      onToggleActive ? '-' : null,
                      onToggleActive ? (off
                        ? { label: 'Ativar modalidade', icon: 'power', onClick: () => onToggleActive(r) }
                        : { label: 'Desativar modalidade', icon: 'ban', danger: true, onClick: () => onToggleActive(r) }) : null,
                    ].filter(Boolean)}
                  />
                </div>
                {r.description ? <p className="ck-mod__desc">{r.description}</p> : <p className="ck-mod__desc ck-al__missing">Sem descrição</p>}
                <div className="ck-mod__foot">
                  <span><Icon name="layers" size={15} />{n ? plural(n, 'plano', 'planos') : 'Nenhum plano'}</span>
                  {r.students != null ? <span><Icon name="users" size={15} />{plural(r.students, 'aluno ativo', 'alunos ativos')}</span> : null}
                </div>
              </li>
            );
          })}
        </ul>
      ) : (
        <div className="ck-card ck-ml__empty">
          <span className="ck-mt-empty__icon" aria-hidden="true"><Icon name={q ? 'search' : 'activity'} size={20} /></span>
          <strong>{q ? `Nenhuma modalidade para "${q}"` : 'Nenhuma modalidade ainda'}</strong>
          <p>{q ? 'Confira a grafia ou limpe a busca.' : 'Cadastre a primeira para poder criar planos e matricular alunos.'}</p>
          {q ? <Button size="sm" onClick={() => setQ('')}>Limpar busca</Button> : <Button size="sm" icon="plus" onClick={onNew}>Nova modalidade</Button>}
        </div>
      )}
    </div>
  );
}

function Section({ id, icon, title, done, children }) {
  return (
    <section className="ck-al-sec" aria-labelledby={id}>
      <header className="ck-al-sec__head">
        <span className={cx('ck-al-sec__icon', done && 'is-done')} aria-hidden="true"><Icon name={done ? 'check' : icon} size={17} strokeWidth={done ? 2.5 : 1.75} /></span>
        <div><h2 className="ck-al-sec__title" id={id}>{title}</h2></div>
      </header>
      <div className="ck-al-sec__body">{children}</div>
    </section>
  );
}

export function ModalidadeForm({ modality, saving, onSave, onBack, onCancel, onOpenPlans }) {
  const m = modality || {};
  const edit = m.id != null;
  const [init] = useState(() => ({ name: m.name || '', description: m.description || '', icon: m.icon || 'dumbbell', color: m.color || MOD_COLORS[0][0], active: m.active !== false }));
  const [f, setForm] = useState(init);
  const [tried, setTried] = useState(false);
  const set = (k, v) => setForm((cur) => ({ ...cur, [k]: v }));
  const err = !f.name.trim() ? 'Dê um nome à modalidade.' : null;
  const dirty = JSON.stringify(f) !== JSON.stringify(init);
  const plansN = m.plans || 0;

  // Ícone ou cor salvos fora das listas continuam escolhíveis
  const icons = MOD_ICONS.some(([k]) => k === f.icon) ? MOD_ICONS : [[f.icon, 'Atual'], ...MOD_ICONS];
  const colors = MOD_COLORS.some(([k]) => k.toLowerCase() === String(f.color).toLowerCase()) ? MOD_COLORS : [[f.color, 'Atual'], ...MOD_COLORS];

  const submit = (e) => {
    e.preventDefault();
    setTried(true);
    if (err) return;
    onSave && onSave({ id: m.id, name: f.name.trim(), description: f.description.trim(), icon: f.icon, color: f.color, active: f.active });
  };

  return (
    <form className="ck-al-form" onSubmit={submit} noValidate>
      <div>
        <button type="button" className="ck-mt-back" onClick={onBack}><Icon name="arrow-left" size={16} />Modalidades</button>
        <PageHeader title={edit ? 'Editar modalidade' : 'Nova modalidade'} description="Nome, ícone e cor aparecem para os alunos no app. Os planos são criados depois, em Planos." />
      </div>
      <div className="ck-al-form__grid">
        <div className="ck-card ck-al-form__main">
          <Section id="md-s1" icon="activity" title="Dados da modalidade" done={!!f.name.trim()}>
            <TextField label="Nome" value={f.name} error={tried ? err : null} placeholder="Ex.: Musculação" onChange={(e) => set('name', e.target.value)} />
            <TextAreaField label="Descrição" value={f.description} placeholder="Para quem é e como funcionam as aulas" hint="Opcional. Aparece no app." onChange={(e) => set('description', e.target.value)} />
          </Section>
          <Section id="md-s2" icon="star" title="Aparência" done>
            <div className="ck-field">
              <span className="ck-field__label">Ícone</span>
              <div className="ck-mod-icons" role="radiogroup" aria-label="Ícone">
                {icons.map(([k, label]) => (
                  <button key={k} type="button" role="radio" aria-checked={f.icon === k} aria-label={label} title={label} className="ck-mod-icons__opt" onClick={() => set('icon', k)}>
                    <Icon glyph name={k} size={22} strokeWidth={f.icon === k ? 2 : 1.75} />
                  </button>
                ))}
              </div>
            </div>
            <div className="ck-field">
              <span className="ck-field__label">Cor</span>
              <div className="ck-mod-colors" role="radiogroup" aria-label="Cor">
                {colors.map(([k, label]) => {
                  const on = String(f.color).toLowerCase() === k.toLowerCase();
                  return (
                    <button key={k} type="button" role="radio" aria-checked={on} aria-label={label} title={label} className="ck-mod-colors__opt" onClick={() => set('color', k)}>
                      <span className="ck-mod-colors__dot" style={{ background: k }}>{on ? <Icon name="check" size={14} strokeWidth={3} /> : null}</span>
                      <span>{label}</span>
                    </button>
                  );
                })}
              </div>
            </div>
          </Section>
          <Section id="md-s3" icon="power" title="Situação" done>
            <div className="ck-al-active">
              <Switch
                label="Modalidade ativa"
                description={f.active ? 'Aparece no app e aceita novas matrículas.' : 'Some do app e não aceita novas matrículas. Quem já está matriculado continua.'}
                checked={f.active}
                onChange={(v) => set('active', v)}
              />
            </div>
          </Section>
        </div>

        <aside className="ck-al-sum" aria-label="Prévia no app">
          <span className="ck-mod-sumlabel">Como o aluno vê no app</span>
          <div className="ck-mod-prev">
            <ModTile icon={f.icon} color={f.color} size="lg" />
            <div style={{ minWidth: 0 }}>
              <strong>{f.name.trim() || 'Nome da modalidade'}</strong>
              <span className="ck-mod-prev__desc">{f.description.trim() || 'A descrição aparece aqui.'}</span>
            </div>
            {!f.active ? <span className="ck-mod-prev__tag">Oculta</span> : null}
          </div>
          <div className="ck-mod-plans">
            <Icon name="layers" size={16} />
            <div>
              <strong>{edit ? (plansN ? `${plural(plansN, 'plano', 'planos')} nesta modalidade` : 'Nenhum plano ainda') : 'Planos vêm depois'}</strong>
              <span>{edit ? 'Planos são criados e editados na tela de Planos.' : 'Depois de salvar, crie os planos desta modalidade na tela de Planos.'}</span>
            </div>
            {edit && onOpenPlans ? <button type="button" className="ck-al-plan__link" onClick={onOpenPlans}>Ver planos<Icon name="arrow-right" size={15} /></button> : null}
          </div>
          {tried && err ? <p className="ck-al-sum__err" role="alert">{err}</p> : null}
          <Button type="submit" variant="primary" size="lg" block icon={edit ? 'save' : 'plus'} loading={saving} disabled={edit && !dirty}>
            {saving ? 'Salvando…' : edit ? 'Salvar alterações' : f.name.trim() ? `Cadastrar ${f.name.trim()}` : 'Cadastrar modalidade'}
          </Button>
          {edit && !dirty ? <span className="ck-al-sum__note">Nenhuma alteração ainda.</span> : null}
          {onCancel ? <button type="button" className="ck-al-sum__cancel" onClick={onCancel}>Cancelar</button> : null}
        </aside>
      </div>
    </form>
  );
}
