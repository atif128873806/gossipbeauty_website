<?php

namespace App\Content;

use Carbon\CarbonImmutable;

/**
 * The illustrative day behind the studio panel screen.
 *
 * This class supports only the separate legacy preview route. The live panel
 * reads database records through authenticated controllers.
 *
 * It is not a data source for live appointments and makes no writes.
 *
 * Two deliberate choices keep it honest:
 *
 *  - **No invented people.** Columns are service areas, not named staff, and visitors are
 *    labelled "Client A"…"Client O". No staff name, biography or customer identity is
 *    fabricated, so a screenshot of this screen cannot be mistaken for real data.
 *  - **Services come from the catalog.** Every block stores a catalog slug which is resolved
 *    through `Catalog`, so the preview cannot drift from the menu the rest of the site
 *    shows. `PanelDayTest` asserts each slug resolves and that no column overlaps.
 */
class PanelDay
{
    /** Vertical scale of the day grid, in CSS pixels per minute. */
    public const PIXELS_PER_MINUTE = 1.6;

    /** A block shorter than this renders without its visitor label, to stay legible. */
    public const COMPACT_MINUTES = 45;

    /**
     * The day view columns. Service areas rather than people, because the team's names,
     * specialisms and new-client rules were unconfirmed when it was created.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public static function columns(): array
    {
        return [
            ['key' => 'nails', 'label' => 'Nail specialist'],
            ['key' => 'brows-lashes', 'label' => 'Brow & lash specialist'],
            ['key' => 'hair-makeup', 'label' => 'Hair & makeup specialist'],
        ];
    }

    /**
     * Status vocabulary shared with the product documents, so the same word means the same
     * thing here, in the customer booking flow and later in the admin panel (02, 05, 06).
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled_by_customer' => 'Cancelled by customer',
            'cancelled_by_salon' => 'Cancelled by the studio',
            'no_show' => 'No show',
            'blocked' => 'Break',
        ];
    }

    /**
     * One day of the panel: appointments and one break, in the order they start.
     *
     * `extras` are add-on slugs from the catalog, so a block can show that extras change the
     * occupied time and the price basis (05 step 2).
     *
     * @return array<int, array{column: string, service: string, visit: string, start: string, end: string, status: string, extras: list<string>}>
     */
    public static function entries(): array
    {
        return [
            // Nail column.
            self::entry('nails', 'shellac-manicure', 'Client A', '10:00', '11:00', 'confirmed'),
            self::entry('nails', 'signature-hard-gel-overlay', 'Client B', '11:15', '12:45', 'confirmed'),
            ['column' => 'nails', 'service' => 'break', 'visit' => '', 'start' => '12:45', 'end' => '13:15', 'status' => 'blocked', 'extras' => []],
            self::entry('nails', 'classic-pedicure', 'Client C', '13:30', '15:00', 'confirmed', ['dip-acrylic-extension-removal']),
            self::entry('nails', 'naked-manicure', 'Client D', '15:30', '16:00', 'completed'),
            self::entry('nails', 'nail-extensions-refill', 'Client E', '16:30', '18:15', 'confirmed'),

            // Brow and lash column.
            self::entry('brows-lashes', 'brow-complex', 'Client F', '10:30', '11:30', 'confirmed'),
            self::entry('brows-lashes', 'lash-lift', 'Client G', '12:00', '13:30', 'confirmed'),
            self::entry('brows-lashes', 'brow-shape-and-tint', 'Client H', '14:00', '14:40', 'completed'),
            self::entry('brows-lashes', 'lash-tint', 'Client I', '15:00', '15:30', 'no_show'),
            self::entry('brows-lashes', 'brow-lamination-and-shape', 'Client J', '16:00', '17:00', 'cancelled_by_customer'),

            // Hair and makeup column.
            self::entry('hair-makeup', 'womens-haircut', 'Client K', '10:00', '11:00', 'confirmed'),
            self::entry('hair-makeup', 'soft-glam-makeup', 'Client L', '11:30', '12:30', 'confirmed'),
            self::entry('hair-makeup', 'blow-out', 'Client M', '13:00', '13:30', 'completed'),
            self::entry('hair-makeup', 'hair-botox', 'Client N', '14:00', '18:00', 'confirmed'),
            self::entry('hair-makeup', 'root-touch-up', 'Client O', '18:15', '18:45', 'cancelled_by_salon'),
        ];
    }

    /**
     * Every entry with its catalog details, times and grid geometry resolved.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function blocks(): array
    {
        return array_map(function (array $entry): array {
            $bounds = self::bounds();
            $start = self::minutesFromOpen($entry['start']);
            $end = self::minutesFromOpen($entry['end']);
            $minutes = max(0, $end - $start);

            return array_merge($entry, [
                'name' => self::nameFor($entry),
                'category' => self::categoryFor($entry),
                'price' => self::priceFor($entry),
                'minutes' => $minutes,
                'duration' => Catalog::durationLabel(['duration_minutes' => $minutes]) ?? '',
                'time_label' => $entry['start'].'–'.$entry['end'],
                'status_label' => self::statuses()[$entry['status']] ?? $entry['status'],
                'is_block' => $entry['service'] === 'break',
                'is_compact' => $minutes < self::COMPACT_MINUTES,
                'top' => round($start * self::PIXELS_PER_MINUTE, 1),
                'height' => round($minutes * self::PIXELS_PER_MINUTE, 1),
                'clipped' => $entry['start'] < $bounds['opens'] || $entry['end'] > $bounds['closes'],
            ]);
        }, self::entries());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function blocksFor(string $columnKey): array
    {
        return array_values(array_filter(
            self::blocks(),
            static fn (array $block): bool => $block['column'] === $columnKey
        ));
    }

    /**
     * Counts and occupied time for the screen's summary strip. Everything is computed from
     * the fixture above, so the numbers cannot disagree with the day view.
     *
     * @return array<string, int>
     */
    public static function statistics(): array
    {
        $blocks = self::blocks();
        $appointments = array_filter($blocks, static fn (array $block): bool => ! $block['is_block']);
        $held = array_filter(
            $appointments,
            static fn (array $block): bool => in_array($block['status'], ['confirmed', 'completed'], true)
        );

        $booked = array_sum(array_column($held, 'minutes'));
        $breaks = array_sum(array_column(
            array_filter($blocks, static fn (array $block): bool => $block['is_block']),
            'minutes'
        ));

        $capacity = self::minutesOpen() * count(self::columns());

        return [
            'appointments' => count($appointments),
            'confirmed' => self::countStatus($appointments, 'confirmed'),
            'completed' => self::countStatus($appointments, 'completed'),
            'cancelled' => self::countStatus($appointments, 'cancelled_by_customer')
                + self::countStatus($appointments, 'cancelled_by_salon'),
            'no_show' => self::countStatus($appointments, 'no_show'),
            'booked_minutes' => $booked,
            'breaks_minutes' => $breaks,
            'open_minutes' => max(0, $capacity - $booked - $breaks),
            'capacity_minutes' => $capacity,
            'utilisation' => $capacity === 0 ? 0 : (int) round($booked / $capacity * 100),
        ];
    }

    /**
     * @return array{opens: string, closes: string}
     */
    public static function bounds(): array
    {
        $settings = StudioSchedule::settings();

        return ['opens' => $settings['opens_at'], 'closes' => $settings['closes_at']];
    }

    /**
     * Whole-hour marks down the side of the day grid.
     *
     * @return list<string>
     */
    public static function hourMarks(): array
    {
        $bounds = self::bounds();
        $cursor = CarbonImmutable::createFromFormat('H:i', $bounds['opens']);
        $end = CarbonImmutable::createFromFormat('H:i', $bounds['closes']);

        if ($cursor === false || $end === false) {
            return [];
        }

        $marks = [];

        while ($cursor->lessThan($end)) {
            $marks[] = $cursor->format('H:i');
            $cursor = $cursor->addHour();
        }

        return $marks;
    }

    /**
     * How far down the grid a clock time sits, in pixels, so the view can place hour lines
     * and labels with the same scale the blocks use.
     */
    public static function offset(string $time): float
    {
        return round(self::minutesFromOpen($time) * self::PIXELS_PER_MINUTE, 1);
    }

    /** The height of the whole day grid in pixels, including the closing mark. */
    public static function gridHeight(): float
    {
        return round(self::minutesOpen() * self::PIXELS_PER_MINUTE, 1);
    }

    public static function minutesOpen(): int
    {
        return max(0, self::minutesFromOpen(self::bounds()['closes']));
    }

    /** Today in Toronto, labelled so the screen is unambiguous about the zone (02, 05). */
    public static function dayLabel(?CarbonImmutable $now = null): string
    {
        $today = ($now ?? CarbonImmutable::now(StudioSchedule::timezone()))->locale('en_CA');

        return $today->format('l j F Y');
    }

    private static function minutesFromOpen(string $time): int
    {
        $bounds = self::bounds();
        $open = CarbonImmutable::createFromFormat('H:i', $bounds['opens']);
        $point = CarbonImmutable::createFromFormat('H:i', $time);

        if ($open === false || $point === false) {
            return 0;
        }

        return (int) $open->diffInMinutes($point, false);
    }

    /**
     * @param  list<string>  $extras
     * @return array{column: string, service: string, visit: string, start: string, end: string, status: string, extras: list<string>}
     */
    private static function entry(
        string $column,
        string $service,
        string $visit,
        string $start,
        string $end,
        string $status,
        array $extras = []
    ): array {
        return [
            'column' => $column,
            'service' => $service,
            'visit' => $visit,
            'start' => $start,
            'end' => $end,
            'status' => $status,
            'extras' => $extras,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private static function nameFor(array $entry): string
    {
        if ($entry['service'] === 'break') {
            return 'Break';
        }

        $service = Catalog::findService($entry['service']);
        $name = $service['name'] ?? 'Unknown service';

        foreach ($entry['extras'] as $extraSlug) {
            foreach (Catalog::addOns() as $addOn) {
                if ($addOn['slug'] === $extraSlug) {
                    $name .= ' + '.$addOn['name'];
                }
            }
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private static function categoryFor(array $entry): string
    {
        if ($entry['service'] === 'break') {
            return '';
        }

        return Catalog::findService($entry['service'])['category'] ?? '';
    }

    /**
     * The price basis the studio would see on the block, reusing the same rules as the
     * customer review step so a "from" price never reads like a total (00, 04, 05).
     *
     * @param  array<string, mixed>  $entry
     */
    private static function priceFor(array $entry): string
    {
        if ($entry['service'] === 'break') {
            return '';
        }

        $items = [];

        if (($service = Catalog::findService($entry['service'])) !== null) {
            $items[] = $service;
        }

        foreach ($entry['extras'] as $extraSlug) {
            foreach (Catalog::addOns() as $addOn) {
                if ($addOn['slug'] === $extraSlug) {
                    $items[] = $addOn;
                }
            }
        }

        return Pricing::summary($items)['value'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $appointments
     */
    private static function countStatus(array $appointments, string $status): int
    {
        return count(array_filter(
            $appointments,
            static fn (array $block): bool => $block['status'] === $status
        ));
    }
}
