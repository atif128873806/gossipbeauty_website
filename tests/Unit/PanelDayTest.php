<?php

namespace Tests\Unit;

use App\Content\Catalog;
use App\Content\PanelDay;
use Tests\TestCase;

/**
 * The studio panel day has to hold together: every block must reference the real
 * catalog, no column may overlap, and the summary numbers must match the day it draws.
 * A preview that contradicts itself would undermine the client's trust in the product.
 */
class PanelDayTest extends TestCase
{
    public function test_every_block_references_a_service_in_the_catalog(): void
    {
        foreach (PanelDay::blocks() as $block) {
            if ($block['is_block']) {
                $this->assertSame('Break', $block['name']);

                continue;
            }

            $service = Catalog::findService($block['service']);

            $this->assertNotNull($service, "Unknown service slug: {$block['service']}");
            $this->assertStringStartsWith($service['name'], $block['name']);
            $this->assertSame($service['category'], $block['category']);

            // Extras are appended by their catalog name, and every extra must resolve.
            foreach ($block['extras'] as $extraSlug) {
                $addOn = null;

                foreach (Catalog::addOns() as $candidate) {
                    if ($candidate['slug'] === $extraSlug) {
                        $addOn = $candidate;
                    }
                }

                $this->assertNotNull($addOn, "Unknown add-on slug: {$extraSlug}");
                $this->assertStringContainsString($addOn['name'], $block['name']);
            }
        }
    }

    public function test_every_block_uses_a_known_status(): void
    {
        $statuses = array_keys(PanelDay::statuses());

        foreach (PanelDay::blocks() as $block) {
            $this->assertContains($block['status'], $statuses);
            $this->assertNotSame('', $block['status_label']);
        }
    }

    public function test_every_block_belongs_to_a_declared_column(): void
    {
        $keys = array_column(PanelDay::columns(), 'key');

        foreach (PanelDay::blocks() as $block) {
            $this->assertContains($block['column'], $keys);
        }
    }

    /** Times must run forwards and stay inside the demo's opening window. */
    public function test_block_times_are_ordered_and_inside_the_day(): void
    {
        foreach (PanelDay::blocks() as $block) {
            $this->assertGreaterThan(0, $block['minutes'], "{$block['name']} has no duration.");
            $this->assertFalse($block['clipped'], "{$block['name']} falls outside the opening hours.");
        }
    }

    /**
     * The invariant a calendar must never break: two bookings cannot occupy the same person
     * at the same time. It is exactly the overlap rule from 05, applied to the fixture.
     */
    public function test_no_two_blocks_overlap_within_a_column(): void
    {
        foreach (PanelDay::columns() as $column) {
            $blocks = PanelDay::blocksFor($column['key']);

            for ($i = 0; $i < count($blocks); $i++) {
                for ($j = $i + 1; $j < count($blocks); $j++) {
                    $a = $blocks[$i];
                    $b = $blocks[$j];

                    $overlaps = $a['start'] < $b['end'] && $a['end'] > $b['start'];

                    $this->assertFalse(
                        $overlaps,
                        "{$column['label']}: {$a['name']} ({$a['time_label']}) overlaps {$b['name']} ({$b['time_label']})."
                    );
                }
            }
        }
    }

    /** Blocks are positioned with the same scale the hour marks use. */
    public function test_grid_geometry_matches_the_day_length(): void
    {
        $this->assertSame(0.0, PanelDay::offset(PanelDay::bounds()['opens']));
        $this->assertSame(PanelDay::gridHeight(), PanelDay::offset(PanelDay::bounds()['closes']));

        foreach (PanelDay::blocks() as $block) {
            $this->assertSame(
                round($block['minutes'] * PanelDay::PIXELS_PER_MINUTE, 1),
                $block['height']
            );
            $this->assertGreaterThanOrEqual(0, $block['top']);
            $this->assertLessThanOrEqual(PanelDay::gridHeight(), $block['top'] + $block['height']);
        }
    }

    public function test_hour_marks_cover_the_opening_window(): void
    {
        $marks = PanelDay::hourMarks();

        $this->assertSame(PanelDay::bounds()['opens'], $marks[0]);
        $this->assertNotContains(PanelDay::bounds()['closes'], $marks);
        $this->assertSame(count($marks), count(array_unique($marks)));
    }

    /** The summary strip is computed from the day, so the two can never disagree. */
    public function test_statistics_match_the_blocks(): void
    {
        $statistics = PanelDay::statistics();
        $appointments = array_filter(
            PanelDay::blocks(),
            static fn (array $block): bool => ! $block['is_block']
        );

        $this->assertSame(count($appointments), $statistics['appointments']);
        $this->assertSame(
            $statistics['appointments'],
            $statistics['confirmed'] + $statistics['completed'] + $statistics['cancelled'] + $statistics['no_show']
        );
        $this->assertGreaterThan(0, $statistics['booked_minutes']);
        $this->assertGreaterThan(0, $statistics['open_minutes']);
        $this->assertLessThanOrEqual(100, $statistics['utilisation']);
    }

    /** No staff name or customer identity may be invented for a client-facing preview. */
    public function test_no_real_names_or_contact_details_are_invented(): void
    {
        $text = json_encode(PanelDay::blocks(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('@', $text, 'No email address belongs in the fixture.');
        $this->assertDoesNotMatchRegularExpression('/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/', $text);

        foreach (PanelDay::blocks() as $block) {
            $this->assertMatchesRegularExpression('/^Client [A-Z]$/', $block['visit'] ?: 'Client A');
        }

        foreach (PanelDay::columns() as $column) {
            $this->assertStringContainsString('specialist', $column['label']);
        }
    }

    public function test_the_day_label_is_a_readable_toronto_date(): void
    {
        $label = PanelDay::dayLabel();

        $this->assertMatchesRegularExpression('/^[A-Z][a-z]+ \d{1,2} [A-Z][a-z]+ \d{4}$/', $label);
    }
}
