import AsyncStorage from "@/src/utils/storage";
import { trocarContextoConfigRemota } from "@/src/services/remoteConfig";
import api from "./api";

/**
 * Dependentes: menores cadastrados na conta do responsável (sem e-mail nem CPF próprios).
 * O responsável troca para o perfil do dependente: a API devolve um token do dependente
 * (com o vínculo conferido a cada requisição) e o app passa a funcionar como ele.
 */
export const PERFIL_DEPENDENTE_KEY = "@appcheckin:perfil_dependente";

// Troca de perfil: o layout raiz remonta tudo para não sobrar dado do perfil anterior em memória
const ouvintes = new Set();
export function onTrocaDePerfil(fn) {
  ouvintes.add(fn);
  return () => ouvintes.delete(fn);
}

const erroDaApi = (error) => {
  const d = error?.response?.data || error || {};
  return { ...d, message: d.message || d.error || (Array.isArray(d.errors) ? d.errors.join("\n") : "") || "Não foi possível concluir. Tente de novo." };
};

async function gravarSessao(data, perfil) {
  const tenant = data?.tenants?.[0]?.tenant;
  const pares = [
    ["@appcheckin:token", data.token],
    ["@appcheckin:user", JSON.stringify(data.user)],
  ];
  if (tenant?.id) {
    pares.push(
      ["@appcheckin:tenants", JSON.stringify(data.tenants)],
      ["@appcheckin:current_tenant", JSON.stringify(tenant)],
      ["@appcheckin:tenant_id", String(tenant.id)],
      ["@appcheckin:tenant_slug", tenant.slug || ""],
      ["@appcheckin:tenant_nome", tenant.nome || ""],
    );
  }
  for (const [k, v] of pares) await AsyncStorage.setItem(k, v);
  if (perfil) await AsyncStorage.setItem(PERFIL_DEPENDENTE_KEY, JSON.stringify(perfil));
  else await AsyncStorage.removeItem(PERFIL_DEPENDENTE_KEY);

  void trocarContextoConfigRemota();
  ouvintes.forEach((fn) => {
    try {
      fn(perfil);
    } catch {
      // ouvinte com erro não impede a troca
    }
  });
}

export const dependenteService = {
  async listar() {
    try {
      const r = await api.get("/mobile/dependentes");
      return r.data?.data?.dependentes || [];
    } catch (error) {
      throw erroDaApi(error);
    }
  },

  /** payload: nome, data_nascimento (AAAA-MM-DD), sexo, parentesco; cpf e email opcionais. */
  async criar(payload) {
    try {
      const r = await api.post("/mobile/dependentes", payload);
      return r.data?.data?.dependente;
    } catch (error) {
      throw erroDaApi(error);
    }
  },

  /** Passa a usar o app como o dependente. */
  async entrar(dependente) {
    let responsavelNome = "";
    try {
      const atual = JSON.parse((await AsyncStorage.getItem("@appcheckin:user")) || "null");
      responsavelNome = atual?.nome || "";
    } catch {
      responsavelNome = "";
    }
    try {
      const r = await api.post(`/mobile/dependentes/${dependente.usuario_id}/entrar`);
      await gravarSessao(r.data, { usuario_id: dependente.usuario_id, nome: r.data?.user?.nome || dependente.nome, responsavel_nome: responsavelNome });
      return r.data;
    } catch (error) {
      throw erroDaApi(error);
    }
  },

  /** Volta para o perfil do responsável. */
  async voltar() {
    try {
      const r = await api.post("/mobile/dependentes/voltar");
      await gravarSessao(r.data, null);
      return r.data;
    } catch (error) {
      throw erroDaApi(error);
    }
  },

  async perfilAtual() {
    try {
      const v = await AsyncStorage.getItem(PERFIL_DEPENDENTE_KEY);
      return v ? JSON.parse(v) : null;
    } catch {
      return null;
    }
  },
};

export default dependenteService;
