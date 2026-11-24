<?php

namespace Tests\Feature\Tracking;

use App\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTransactions;
use Tests\TestCase;

class TrackingApiTest extends TestCase
{
    use CreatesTransactions, RefreshDatabase;

    private function states(string $token): array
    {
        $timeline = $this->getJson("/api/public/tracking/{$token}")->assertOk()->json('data.timeline');

        return collect($timeline)->pluck('state', 'status')->all();
    }

    public function test_tracking_returns_limited_data_only(): void
    {
        $transaction = $this->makeTransaction([TransactionStatus::Washing]);

        $response = $this->getJson("/api/public/tracking/{$transaction->tracking_token}")
            ->assertOk()
            ->assertJsonPath('data.code', $transaction->code)
            ->assertJsonPath('data.customer_name', 'Bu** Sa*****')
            ->assertJsonPath('data.package_name', $transaction->package_name)
            ->assertJsonPath('data.status', 'WASHING')
            ->assertJsonPath('data.status_label', 'Sedang dicuci')
            ->assertJsonPath('data.is_ready', false)
            ->assertJsonPath('data.qr.available', true)
            ->assertJsonPath('data.qr.used', false)
            ->assertJsonMissingPath('data.customer_phone')
            ->assertJsonMissingPath('data.total_price')
            ->assertJsonMissingPath('data.notes')
            ->assertJsonMissingPath('data.received_by');

        $body = $response->getContent();

        foreach (['Budi Santoso', '6281234567890', 'Catatan internal rahasia', $transaction->verification->token] as $secret) {
            $this->assertStringNotContainsString($secret, $body, "Data rahasia bocor: {$secret}");
        }
    }

    public function test_timeline_reflects_progress_skipped_steps_and_cancellation(): void
    {
        $processing = $this->makeTransaction([TransactionStatus::Washing]);
        $this->assertSame([
            'RECEIVED' => 'done',
            'WASHING' => 'current',
            'DRYING' => 'pending',
            'IRONING' => 'pending',
            'READY' => 'pending',
            'COMPLETED' => 'pending',
        ], $this->states($processing->tracking_token));

        $skipped = $this->makeTransaction([TransactionStatus::Washing, TransactionStatus::Drying, TransactionStatus::Ready]);
        $this->assertSame([
            'RECEIVED' => 'done',
            'WASHING' => 'done',
            'DRYING' => 'done',
            'IRONING' => 'skipped',
            'READY' => 'current',
            'COMPLETED' => 'pending',
        ], $this->states($skipped->tracking_token));

        $cancelled = $this->makeTransaction([TransactionStatus::Washing, TransactionStatus::Cancelled]);
        $this->assertSame([
            'RECEIVED' => 'done',
            'WASHING' => 'done',
            'DRYING' => 'inactive',
            'IRONING' => 'inactive',
            'READY' => 'inactive',
            'COMPLETED' => 'inactive',
            'CANCELLED' => 'cancelled',
        ], $this->states($cancelled->tracking_token));
    }

    public function test_ready_transaction_shows_pickup_information(): void
    {
        $transaction = $this->readyTransaction();

        $response = $this->getJson("/api/public/tracking/{$transaction->tracking_token}")
            ->assertOk()
            ->assertJsonPath('data.is_ready', true)
            ->assertJsonPath('data.status', 'READY');

        $this->assertStringContainsString('siap diambil', $response->json('data.pickup.message'));
    }

    public function test_qr_image_is_served_for_active_transactions(): void
    {
        $transaction = $this->readyTransaction();

        $response = $this->get("/api/public/tracking/{$transaction->tracking_token}/qr")->assertOk();

        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('<svg', $response->getContent());
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_qr_is_unavailable_for_cancelled_and_completed_transactions(): void
    {
        $cancelled = $this->makeTransaction([TransactionStatus::Washing, TransactionStatus::Cancelled]);

        $this->getJson("/api/public/tracking/{$cancelled->tracking_token}")
            ->assertOk()
            ->assertJsonPath('data.qr.available', false)
            ->assertJsonPath('data.qr.image_url', null)
            ->assertJsonPath('data.is_cancelled', true);
        $this->get("/api/public/tracking/{$cancelled->tracking_token}/qr")->assertNotFound();

        $completed = $this->makeTransaction([
            TransactionStatus::Washing,
            TransactionStatus::Drying,
            TransactionStatus::Ready,
            TransactionStatus::Completed,
        ]);

        $this->getJson("/api/public/tracking/{$completed->tracking_token}")
            ->assertOk()
            ->assertJsonPath('data.qr.available', false)
            ->assertJsonPath('data.qr.used', true)
            ->assertJsonPath('data.is_completed', true);
        $this->get("/api/public/tracking/{$completed->tracking_token}/qr")->assertNotFound();
    }

    public function test_invalid_unknown_and_deleted_tokens_return_404(): void
    {
        $this->getJson('/api/public/tracking/terlalu-pendek')->assertNotFound();
        $this->getJson('/api/public/tracking/'.str_repeat('a', 40))->assertNotFound();
        $this->getJson('/api/public/tracking/'.str_repeat('a', 41))->assertNotFound();

        $transaction = $this->makeTransaction();
        $token = $transaction->tracking_token;

        $this->getJson("/api/public/tracking/{$token}")->assertOk();

        $transaction->delete();
        $this->getJson("/api/public/tracking/{$token}")->assertNotFound();
    }

    public function test_token_matching_is_case_sensitive(): void
    {
        $transaction = $this->makeTransaction();
        $swapped = strtoupper($transaction->tracking_token);

        if ($swapped === $transaction->tracking_token) {
            $this->markTestSkipped('Token tidak memiliki huruf kecil.');
        }

        $this->getJson("/api/public/tracking/{$swapped}")->assertNotFound();
    }

    public function test_tracking_endpoint_is_rate_limited(): void
    {
        $token = str_repeat('a', 40);

        for ($i = 0; $i < 40; $i++) {
            $this->getJson("/api/public/tracking/{$token}")->assertNotFound();
        }

        $this->getJson("/api/public/tracking/{$token}")
            ->assertStatus(429)
            ->assertJsonPath('message', 'Terlalu banyak permintaan. Silakan coba lagi beberapa saat lagi.');
    }

    public function test_token_pages_send_privacy_headers(): void
    {
        $token = str_repeat('a', 40);

        $this->get("/tracking/{$token}")
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Referrer-Policy', 'same-origin');

        $this->get("/verify/{$token}")
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'same-origin');

        $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }
}