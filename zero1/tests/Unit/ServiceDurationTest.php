<?php

namespace Tests\Unit;

use App\Models\Service;
use PHPUnit\Framework\TestCase;

class ServiceDurationTest extends TestCase
{
    public function test_effective_duration_includes_buffer(): void
    {
        $this->assertSame(30, (new Service(['duration_minutes' => 30, 'buffer_minutes' => 0]))->effectiveDurationMinutes());
        $this->assertSame(70, (new Service(['duration_minutes' => 60, 'buffer_minutes' => 10]))->effectiveDurationMinutes());
    }

    public function test_formats_duration_and_price(): void
    {
        $this->assertSame('30 min', (new Service(['duration_minutes' => 30]))->formattedDuration());
        $this->assertSame('1h', (new Service(['duration_minutes' => 60]))->formattedDuration());
        $this->assertSame('1h30', (new Service(['duration_minutes' => 90]))->formattedDuration());
        $this->assertSame('a partir de R$ 80,00', (new Service(['price_cents' => 8000, 'price_is_from' => true]))->formattedPrice());
    }
}
