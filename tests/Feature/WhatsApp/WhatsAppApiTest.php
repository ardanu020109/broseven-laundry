<?php

namespace Tests\Feature\WhatsApp;

use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\LaundryWebsiteSetting;
use App\Models\User;
use App\Services\TransactionService;
use App\Services\WhatsApp\ClickToChatGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Support\AppUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\CreatesTransactions;
use Tests\TestCase;

class WhatsAppApiTest extends TestCase
{
    use CreatesTransactions, RefreshDatabase;

    private function preview(User $user, LaundryTransaction $transaction, ?string $template = null)
    {
        $url = "/api/transactions/{$transaction->id}/whatsapp".($template ? "?template={$template}" : '');

        return $this->actingAs($user)->getJson($url);
    }

    private function decodedText(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['text'];
    }

    private function setting(string $key, string $value): void
    {
        LaundryWebsiteSetting::create([
            'group' => 'business',
            'key' => $key,
            'value' => $value,
            'type' => 'text',
        ]);
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/transactions/1/whatsapp')->assertUnauthorized();
        $this->postJson('/api/transactions/1/whatsapp')->assertUnauthorized();
    }

    public function test_admin_and_employee_can_prepare_messages(): void
    {
        $transaction = $this->makeTransaction();

        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $user) {
            $this->preview($user, $transaction)->assertOk();
        }
    }

    public function test_default_preview_for_a_new_transaction(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->makeTransaction();
        $token = $transaction->verification->token;
        $trackingUrl = AppUrl::to('/tracking/'.$transaction->tracking_token);

        $response = $this->preview($staff, $transaction)
            ->assertOk()
            ->assertJsonPath('data.template', 'RECEIVED')
            ->assertJsonPath('data.recommended_template', 'RECEIVED')
            ->assertJsonPath('data.phone', '6281234567890')
            ->assertJsonPath('data.mode', 'click_to_chat')
            ->assertJsonPath('data.delivered', false)
            ->assertJsonPath('data.requires_user_confirmation', true)
            ->assertJsonCount(7, 'data.templates');

        $message = $response->json('data.message');

        foreach ([
            'Halo Kak Budi Santoso!',
            'Terima kasih telah menggunakan layanan '.config('app.name'),
            'Detail transaksi:',
            "Kode: {$transaction->code}",
            "Paket: {$transaction->package_name}",
            'Berat: 2,5 kg',
            'Total: Rp20.000',
            'Estimasi selesai:',
            'WITA',
            'Status cucian: Diterima',
            $trackingUrl,
        ] as $expected) {
            $this->assertStringContainsString($expected, $message);
        }

        $this->assertStringContainsString('konfirmasi', $response->json('data.notice'));

        // Link wa.me memuat pesan yang sama persis.
        $url = $response->json('data.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $url);
        $this->assertSame($message, $this->decodedText($url));

        // Data internal tidak boleh bocor ke pesan maupun respons.
        $body = $response->getContent();
        $this->assertStringNotContainsString($token, $body);
        $this->assertStringNotContainsString('Catatan internal rahasia', $body);
    }

    public function test_every_status_has_its_own_message(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->makeTransaction();

        $expectations = [
            'RECEIVED' => 'Detail transaksi',
            'WASHING' => 'sedang kami cuci',
            'DRYING' => 'sedang kami keringkan',
            'IRONING' => 'sedang kami setrika',
            'READY' => 'siap diambil',
            'COMPLETED' => 'sudah diambil',
            'CANCELLED' => 'dibatalkan',
        ];

        foreach ($expectations as $status => $phrase) {
            $message = $this->preview($staff, $transaction, $status)
                ->assertOk()
                ->assertJsonPath('data.template', $status)
                ->json('data.message');

            $this->assertStringContainsString($phrase, $message, "Template {$status}");
            $this->assertStringContainsString('Halo Kak Budi Santoso', $message, "Template {$status}");
            $this->assertStringContainsString($transaction->code, $message, "Template {$status}");
        }
    }

    public function test_default_template_follows_the_current_status(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->readyTransaction();

        $this->preview($staff, $transaction)
            ->assertOk()
            ->assertJsonPath('data.template', 'READY')
            ->assertJsonPath('data.recommended_template', 'READY');
    }

    public function test_ready_message_includes_address_and_hours_when_configured(): void
    {
        $this->setting('business_name', 'Laundry Uji');
        $this->setting('address', "Jl. Contoh No. 7,\nMakassar");
        $this->setting('operating_hours', '08.00 - 20.00 WITA');

        $staff = User::factory()->create();
        $transaction = $this->readyTransaction();

        $message = $this->preview($staff, $transaction)->assertOk()->json('data.message');

        $this->assertStringContainsString('Alamat pengambilan: Jl. Contoh No. 7, Makassar', $message);
        $this->assertStringContainsString('Jam operasional: 08.00 - 20.00 WITA', $message);
        $this->assertStringContainsString('Laundry Uji', $message);
    }

    public function test_ready_message_skips_address_and_hours_when_not_configured(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->readyTransaction();

        $message = $this->preview($staff, $transaction)->assertOk()->json('data.message');

        $this->assertStringNotContainsString('Alamat pengambilan', $message);
        $this->assertStringNotContainsString('Jam operasional', $message);
        $this->assertStringContainsString(config('app.name'), $message);
        $this->assertDoesNotMatchRegularExpression("/\n{3,}/", $message);
    }

    public function test_per_item_packages_use_item_wording(): void
    {
        $staff = User::factory()->create();
        $package = LaundryPackage::factory()->perItem()->create(['price' => 30000, 'estimated_hours' => 72]);

        $transaction = app(TransactionService::class)->create([
            'customer_name' => 'Siti Aminah',
            'customer_phone' => '6282233445566',
            'package_id' => $package->id,
            'quantity' => 2,
        ], $staff);

        $message = $this->preview($staff, $transaction)->assertOk()->json('data.message');

        $this->assertStringContainsString('Halo Kak Siti Aminah!', $message);
        $this->assertStringContainsString('Jumlah: 2 item', $message);
        $this->assertStringContainsString('Total: Rp60.000', $message);
    }

    public function test_amounts_with_cents_keep_two_decimals(): void
    {
        $staff = User::factory()->create();
        $package = LaundryPackage::factory()->create(['price' => 7777.77]);

        $transaction = app(TransactionService::class)->create([
            'customer_name' => 'Andi',
            'customer_phone' => '6281111111111',
            'package_id' => $package->id,
            'quantity' => 0.33,
        ], $staff);

        $message = $this->preview($staff, $transaction)->assertOk()->json('data.message');

        $this->assertStringContainsString('Berat: 0,33 kg', $message);
        $this->assertStringContainsString('Total: Rp2.566,66', $message);
    }

    public function test_invalid_template_is_rejected(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->makeTransaction();

        $this->preview($staff, $transaction, 'SELESAI')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('template');

        $this->actingAs($staff)
            ->postJson("/api/transactions/{$transaction->id}/whatsapp", ['template' => 'washing'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('template');
    }

    public function test_invalid_customer_phone_returns_a_clear_error(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->makeTransaction();
        $transaction->forceFill(['customer_phone' => '123'])->save();

        $this->preview($staff, $transaction)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_phone');
    }

    public function test_unknown_and_deleted_transactions_return_404(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff)->getJson('/api/transactions/999999/whatsapp')->assertNotFound();

        $transaction = $this->makeTransaction();
        $transaction->delete();

        $this->preview($staff, $transaction)->assertNotFound();
    }

    public function test_preview_has_no_side_effects_but_opening_is_logged(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->readyTransaction();

        $this->preview($staff, $transaction)->assertOk();
        $this->assertDatabaseMissing('laundry_activity_logs', ['action' => 'whatsapp.opened']);

        $this->actingAs($staff)
            ->postJson("/api/transactions/{$transaction->id}/whatsapp", ['template' => 'READY'])
            ->assertOk()
            ->assertJsonPath('data.template', 'READY')
            ->assertJsonPath('data.delivered', false)
            ->assertJsonPath(
                'message',
                'WhatsApp dibuka. Pesan baru terkirim setelah Anda menekan Kirim di aplikasi WhatsApp.'
            );

        $this->assertDatabaseHas('laundry_activity_logs', [
            'action' => 'whatsapp.opened',
            'user_id' => $staff->id,
            'subject_id' => $transaction->id,
        ]);

        // Tidak pernah ada log yang mengklaim pesan "terkirim".
        $this->assertDatabaseMissing('laundry_activity_logs', ['action' => 'whatsapp.sent']);
    }

    public function test_gateway_driver_is_resolved_from_config(): void
    {
        $this->assertInstanceOf(ClickToChatGateway::class, app(WhatsAppGateway::class));

        config(['broseven.whatsapp.driver' => 'tidak-ada']);

        $this->expectException(InvalidArgumentException::class);
        app(WhatsAppGateway::class);
    }
}