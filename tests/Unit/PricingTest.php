<?php

namespace Tests\Unit;

use App\Content\Pricing;
use PHPUnit\Framework\TestCase;

/**
 * A "from" price must never turn into a total, and a quote must never become an amount
 * (see AGENTS.md).
 */
class PricingTest extends TestCase
{
    public function test_fixed_items_add_up_to_an_exact_total(): void
    {
        $summary = Pricing::summary([
            ['price_mode' => 'fixed', 'price_minor' => 10500],
            ['price_mode' => 'fixed', 'price_minor' => 3500],
        ]);

        $this->assertSame('Total', $summary['label']);
        $this->assertSame('CAD 140', $summary['value']);
    }

    public function test_a_from_item_keeps_the_result_an_estimate(): void
    {
        $summary = Pricing::summary([
            ['price_mode' => 'from', 'price_minor' => 7500],
            ['price_mode' => 'fixed', 'price_minor' => 2000],
        ]);

        $this->assertSame('Estimated total', $summary['label']);
        $this->assertSame('from CAD 95', $summary['value']);
        $this->assertNotNull($summary['note']);
    }

    public function test_a_quote_item_hides_every_amount_including_fixed_extras(): void
    {
        $summary = Pricing::summary([
            ['price_mode' => 'quote', 'price_minor' => null],
            ['price_mode' => 'fixed', 'price_minor' => 2000],
        ]);

        $this->assertSame('Quoted at booking', $summary['value']);
        $this->assertStringNotContainsString('CAD', $summary['value']);
    }

    public function test_free_items_are_free_rather_than_zero(): void
    {
        $summary = Pricing::summary([
            ['price_mode' => 'free', 'price_minor' => 0],
        ]);

        $this->assertSame('Total', $summary['label']);
        $this->assertSame('Free', $summary['value']);
    }

    public function test_an_empty_selection_promises_no_amount(): void
    {
        $summary = Pricing::summary([]);

        $this->assertSame('Quoted at booking', $summary['value']);
        $this->assertStringNotContainsString('CAD', $summary['value']);
    }
}
