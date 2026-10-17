<?php

namespace Tests\Unit;

use App\Support\BangladeshPhone;
use PHPUnit\Framework\TestCase;

class BangladeshPhoneTest extends TestCase
{
    public function test_normalizes_leading_zero_to_880(): void
    {
        $this->assertSame('8801712345678', BangladeshPhone::toE164('01712345678'));
    }

    public function test_passes_through_already_normalized_e164(): void
    {
        $this->assertSame('8801712345678', BangladeshPhone::toE164('8801712345678'));
    }

    public function test_strips_non_digit_characters(): void
    {
        $this->assertSame('8801712345678', BangladeshPhone::toE164('+88 017-1234 5678'));
    }

    public function test_prepends_880_to_ten_digit_raw_number(): void
    {
        $this->assertSame('8801712345678', BangladeshPhone::toE164('1712345678'));
    }

    public function test_returns_null_for_empty(): void
    {
        $this->assertNull(BangladeshPhone::toE164(''));
    }

    public function test_returns_null_for_garbage(): void
    {
        $this->assertNull(BangladeshPhone::toE164('abc12'));
    }
}