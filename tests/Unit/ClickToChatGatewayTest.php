<?php

namespace Tests\Unit;

use App\Services\WhatsApp\ClickToChatGateway;
use PHPUnit\Framework\TestCase;

class ClickToChatGatewayTest extends TestCase
{
    public function test_builds_a_click_to_chat_link_and_never_claims_delivery(): void
    {
        $message = "Halo Kak Budi!\n\nTotal: Rp20.000 & bonus + diskon 100%";

        $dispatch = (new ClickToChatGateway())->deliver('6281234567890', $message);

        $this->assertSame('click_to_chat', $dispatch->mode);
        $this->assertFalse($dispatch->delivered);
        $this->assertTrue($dispatch->requiresUserConfirmation);
        $this->assertStringContainsString('konfirmasi', $dispatch->notice);

        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $dispatch->url);
        $this->assertStringNotContainsString("\n", $dispatch->url);
        $this->assertStringNotContainsString(' ', $dispatch->url);

        parse_str((string) parse_url($dispatch->url, PHP_URL_QUERY), $query);
        $this->assertSame($message, $query['text']);
    }

    public function test_phone_is_reduced_to_digits_and_base_url_trailing_slash_is_trimmed(): void
    {
        $dispatch = (new ClickToChatGateway('https://wa.me/'))->deliver('+62 812-3456-7890', 'Halo');

        $this->assertSame('https://wa.me/6281234567890?text=Halo', $dispatch->url);
    }
}