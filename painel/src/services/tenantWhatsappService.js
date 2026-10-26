import api from './api';

class TenantWhatsappService {
  async obterLinks() {
    const response = await api.get('/admin/tenant/whatsapp-links');
    return response.data;
  }

  async salvarLinks(whatsappLinks, instagramUrl) {
    const response = await api.put('/admin/tenant/whatsapp-links', { whatsapp_links: whatsappLinks, ...(instagramUrl !== undefined ? { instagram_url: instagramUrl } : {}) });
    return response.data;
  }
}

export default new TenantWhatsappService();
