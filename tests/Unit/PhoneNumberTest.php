<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_normalize_handles_common_indonesian_formats(): void
    {
        $cases = [
            '0812-3456-7890' => '6281234567890',
            '+62 812 3456 7890' => '6281234567890',
            '81234567890' => '6281234567890',
            '6281234567890' => '6281234567890',
            '0062 812 3456 7890' => '6281234567890',
            '62081234567890' => '6281234567890',
            '+1 (415) 555-2671' => '14155552671',
            'abc' => '',
            '' => '',
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, PhoneNumber::normalize((string) $input), "input: {$input}");
        }
    }

    public function test_is_valid(): void
    {
        $this->assertTrue(PhoneNumber::isValid('6281234567890'));
        $this->assertTrue(PhoneNumber::isValid('14155552671'));

        $this->assertFalse(PhoneNumber::isValid(''));
        $this->assertFalse(PhoneNumber::isValid('62812'));
        $this->assertFalse(PhoneNumber::isValid('628123456789012345'));
        $this->assertFalse(PhoneNumber::isValid('0812345678'));
    }
}