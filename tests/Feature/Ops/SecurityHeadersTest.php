<?php

namespace Tests\Feature\Ops;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_sent_on_pages_and_api(): void
    {
        foreach (['/', '/api/public/site'] as $url) {
            $this->get($url)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        }

        $this->get('/')->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');
    }


    public function test_api_responses_have_no_csp_and_token_pages_keep_their_referrer_policy(): void
    {
        $this->getJson('/api/public/site')->assertHeaderMissing('Content-Security-Policy-Report-Only');

        $this->get('/tracking/'.str_repeat('a', 40))->assertHeader('Referrer-Policy', 'same-origin');
        $this->get('/login')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_only_on_https_in_production(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->app['env'] = 'production';

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }
}
