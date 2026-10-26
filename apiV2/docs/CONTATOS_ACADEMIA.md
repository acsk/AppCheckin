# WhatsApp e Instagram da academia

O administrador pode editar os contatos em **Configurações → WhatsApp e
Instagram**. O Super Admin também encontra o campo **Instagram** no cadastro
da academia. O campo é opcional: em branco, o atalho não aparece no app.

O mobile mostra **Instagram da academia** na seção **Redes sociais** do menu
lateral e da aba **Conta**. O toque abre o endereço HTTPS pelo sistema, que
pode usar o aplicativo Instagram ou o navegador. O atalho acompanha a academia
selecionada e usa os estilos de tema existentes. Os grupos de WhatsApp continuam
separados, com seus nomes e links de convite.

## API e dados

- `tenants.instagram_url` guarda o endereço do perfil.
- As respostas de perfil mobile e dos vínculos do usuário incluem
  `instagram_url` junto aos contatos da academia.
- Os endpoints existentes `GET/PUT /v2/admin/tenant/whatsapp-links` passam a
  devolver/aceitar também `instagram_url`. O PUT mantém o Instagram anterior
  quando o campo não é enviado; envie `null` para remover. O mesmo vale para
  `whatsapp_links`: omitir preserva e enviar `[]` remove os grupos.
- O cadastro de academias do SA aceita o campo opcional `instagram_url`.
- Somente perfis HTTPS de `instagram.com` ou `www.instagram.com` são aceitos.
  Links de publicação, domínios externos e URLs com credenciais são rejeitados.

## Publicação

Execute `php artisan migrate --force` antes de publicar a API atualizada.
A migration `2026_10_26_100000_instagram_academia.php` adiciona a coluna e
preenche o link informado para a Cia da Natação (slug `cia-da-natacao`, sem
diferenciar maiúsculas de minúsculas). As demais academias ficam sem Instagram
até a configuração pelo painel.

Publique painel e mobile atualizados. A versão Android instalada precisa de
um novo build para exibir o atalho.

Teste de integração: `php vendor/bin/phpunit tests/Feature/TenantInstagramTest.php`.
