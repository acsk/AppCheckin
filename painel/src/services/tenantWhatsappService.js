import api from './api';

class TenantWhatsappService {
  async obterLinks() {
    const response = await api.get('/admin/tenant/whatsapp-links');
    return response.data;
  }

  async salvarLinks(whatsappLinks) {
    const response = await api.put('/admin/tenant/whatsapp-links', { whatsapp_links: whatsappLinks });
    return response.data;
  }
}

export default new TenantWhatsappService();
