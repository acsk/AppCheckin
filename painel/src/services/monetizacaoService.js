import api from './api';

const tratar = (e) => {
  const data = e?.response?.data || {};
  const campos = Object.values(data.errors || {}).flat();
  return new Error(campos.join(' ') || data.message || data.error || 'Não foi possível acessar a monetização.');
};

export default {
  async listar(provedor, ambiente) {
    try { return (await api.get(`/superadmin/monetizacao/${provedor}?ambiente=${ambiente}`)).data; }
    catch (e) { throw tratar(e); }
  },
  async salvar(provedor, id, dados) {
    try { return (await api.put(`/superadmin/monetizacao/${provedor}/${id}`, dados)).data; }
    catch (e) { throw tratar(e); }
  },
};
