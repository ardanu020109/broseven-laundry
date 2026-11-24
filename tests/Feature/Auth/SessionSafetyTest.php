<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionSafetyTest extends TestCase
{
    /**
     * Regresi: "no-referrer" membuat semua GET API dari halaman SPA kehilangan header
     * Referer/Origin, sehingga Sanctum tidak memuat sesi dan pengguna terlogout.
     */
    public function test_spa_pages_never_use_no_referrer_policy(): void
    {
        $token = str_repeat('a', 40);

        foreach (['/verify', "/verify/{$token}", "/tracking/{$token}", '/dashboard', '/login'] as $url) {
            $policy = (string) $this->get($url)->assertOk()->headers->get('Referrer-Policy');

            $this->assertNotSame('no-referrer', $policy, "Halaman {$url} memakai no-referrer");
        }

        foreach (['/verify', "/verify/{$token}", "/tracking/{$token}"] as $url) {
            $this->get($url)->assertHeader('Referrer-Policy', 'same-origin');
        }
    }
}