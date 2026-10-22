const TIPOS = { 1: 'aluno', 2: 'professor', 3: 'admin', 4: 'suporte', 5: 'comercial', 6: 'financeiro' };
export function usuarioAtivo(u) {
  if (u.status === 'ativo') return true;
  if (u.status === 'inativo') return false;
  return u.ativo === true || u.ativo === 1 || u.ativo === '1';
}
export function usuarioParaLayout(u) {
  const papeis = Array.isArray(u.papeis) && u.papeis.length ? u.papeis : [u.papel_id || 1];
  return { id: u.id, name: u.nome || '', email: u.email || '', phone: u.telefone || '', cpf: u.cpf || '',
    types: [...new Set(papeis.map((p) => TIPOS[Number(p)]).filter(Boolean))], active: usuarioAtivo(u),
    lastAccess: u.ultimo_acesso || u.ultimo_login || null, createdAt: u.created_at?.slice(0, 10) || null,
    academy: u.tenant?.nome || u.tenant_nome || '',
    address: { cep: u.cep, street: u.logradouro, number: u.numero, complement: u.complemento, district: u.bairro, city: u.cidade, uf: u.estado },
    original: u };
}
export function usuarioParaApi(d, papelId = 1) {
  const a = d.address || {};
  const payload = { nome: d.name, email: d.email, telefone: d.phone || '', cpf: d.cpf || '', papel_id: papelId,
    cep: a.cep || '', logradouro: a.street || '', numero: a.number || '', complemento: a.complement || '', bairro: a.district || '', cidade: a.city || '', estado: a.uf || '' };
  if (d.password) payload.senha = d.password;
  return payload;
}
