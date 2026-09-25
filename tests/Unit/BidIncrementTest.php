<?php

namespace Tests\Unit;

use App\Services\Auction\BidIncrement;
use PHPUnit\Framework\TestCase;

class BidIncrementTest extends TestCase
{
    public function test_increment_follows_tier_table(): void
    {
        $increments = new BidIncrement([0 => 10_000, 1_000_000 => 50_000, 10_000_000 => 250_000]);

        $this->assertSame(10_000, $increments->for(0));
        $this->assertSame(10_000, $increments->for(999_999));
        $this->assertSame(50_000, $increments->for(1_000_000));
        $this->assertSame(250_000, $increments->for(25_000_000));
        $this->assertSame(1_050_000, $increments->after(1_000_000));
    }
}
