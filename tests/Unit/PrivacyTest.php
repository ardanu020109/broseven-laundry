<?php

namespace Tests\Unit;

use App\Support\Privacy;
use PHPUnit\Framework\TestCase;

class PrivacyTest extends TestCase
{
    public function test_mask_name(): void
    {
        $this->assertSame('Bu** Sa*****', Privacy::maskName('Budi Santoso'));
        $this->assertSame('Bu** Sa*****', Privacy::maskName("  Budi \t Santoso  "));
        $this->assertSame('Si**', Privacy::maskName('Siti'));
        $this->assertSame('A**', Privacy::maskName('Ani'));
        $this->assertSame('A*', Privacy::maskName('Al'));
        $this->assertSame('X', Privacy::maskName('X'));
        $this->assertSame('Ma******', Privacy::maskName('Maximilianus'));
        $this->assertSame('', Privacy::maskName(null));
        $this->assertSame('', Privacy::maskName('   '));
    }
}