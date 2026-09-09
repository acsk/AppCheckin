<?php

namespace Tests\Unit;

use App\Support\TenantWhatsappLinks;
use Tests\TestCase;

class TenantWhatsappLinksTest extends TestCase
{
    public function test_parses_json_links(): void
    {
        $json = '[{"nome":"CN AVISOS","url":"https://chat.whatsapp.com/JitTVMzLDA19gmfp5BLibG"}]';
        $links = TenantWhatsappLinks::parse($json);

        $this->assertCount(1, $links);
        $this->assertSame('CN AVISOS', $links[0]['nome']);
    }

    public function test_validates_whatsapp_invite_url(): void
    {
        $this->assertTrue(TenantWhatsappLinks::isValidInviteUrl(
            'https://chat.whatsapp.com/CSlKzYzBvonFIpCwxs2e3u?s=sw&p=a&mlu=4',
        ));
        $this->assertFalse(TenantWhatsappLinks::isValidInviteUrl('https://example.com/grupo'));
    }
}
