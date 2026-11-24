<?php

namespace Tests\Feature\Qr;

use App\Models\LaundryTransaction;
use App\Services\QRCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_svg_is_generated_without_xml_declaration(): void
    {
        $svg = app(QRCodeService::class)->svg('https://contoh.test/verify/abc');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
    }

    public function test_verification_url_uses_app_url_and_has_no_customer_data(): void
    {
        config(['app.url' => 'https://laundry.example.com']);

        $transaction = LaundryTransaction::factory()->create([
            'customer_name' => 'Budi Rahasia',
            'customer_phone' => '6281234567890',
        ]);

        $service = app(QRCodeService::class);
        $verification = $service->ensureVerification($transaction);
        $url = $service->verificationUrl($verification);

        $this->assertSame('https://laundry.example.com/verify/'.$verification->token, $url);
        $this->assertSame(48, strlen($verification->token));

        foreach ([$transaction->code, 'Budi', 'Rahasia', '6281234567890'] as $secret) {
            $this->assertStringNotContainsString($secret, $url);
        }
    }

    public function test_ensure_verification_is_idempotent(): void
    {
        $transaction = LaundryTransaction::factory()->create();
        $service = app(QRCodeService::class);

        $first = $service->ensureVerification($transaction);
        $second = $service->ensureVerification($transaction->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('laundry_transaction_verifications', 1);
    }
}