<?php

namespace Tests\Unit;

use App\Content\StudioSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * The demo schedule is the fixture the journey offers times from. Even as a fixture it must
 * not offer a time that cannot exist or that overruns closing, per the scheduling invariants
 * summarized in AGENTS.md.
 */
class StudioScheduleTest extends TestCase
{
    /** Tuesday 29 September 2026, 08:00 Toronto — a member of the demo's open days. */
    private function mondayMorning(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00', StudioSchedule::timezone()));
    }

    public function test_open_days_follow_the_configured_weekdays(): void
    {
        $this->mondayMorning();

        $openWeekdays = array_map(
            static fn (array $day): int => CarbonImmutable::parse($day['value'], StudioSchedule::timezone())->dayOfWeek,
            StudioSchedule::openDates()
        );

        $this->assertNotSame([], $openWeekdays);
        $this->assertSame([], array_diff($openWeekdays, StudioSchedule::settings()['days']));
        $this->assertNotContains(0, $openWeekdays, 'The demo schedule does not open on Sunday.');
        $this->assertNotContains(1, $openWeekdays, 'The demo schedule does not open on Monday.');
    }

    public function test_today_is_offered_first_when_it_is_an_open_day(): void
    {
        $this->mondayMorning();

        $dates = StudioSchedule::openDates();

        // Monday is closed, so the first offer is Tuesday 29 September.
        $this->assertSame('2026-09-29', $dates[0]['value']);
        $this->assertFalse($dates[0]['is_today']);
        $this->assertStringContainsString('Sep', $dates[0]['label']);
    }

    public function test_the_horizon_bounds_how_far_ahead_days_are_offered(): void
    {
        $this->mondayMorning();

        $dates = StudioSchedule::openDates();
        $last = CarbonImmutable::parse(end($dates)['value'], StudioSchedule::timezone());

        $this->assertLessThanOrEqual(
            StudioSchedule::settings()['days_ahead'] - 1,
            CarbonImmutable::parse('2026-09-28', StudioSchedule::timezone())->diffInDays($last)
        );
    }

    public function test_a_closed_weekday_is_not_an_open_date(): void
    {
        $this->mondayMorning();

        $this->assertFalse(StudioSchedule::isOpenDate('2026-09-27'), 'Sunday is closed in the demo schedule.');
        $this->assertFalse(StudioSchedule::isOpenDate('2026-09-28'), 'Monday is closed in the demo schedule.');
        $this->assertTrue(StudioSchedule::isOpenDate('2026-09-29'));
    }

    public function test_a_date_outside_the_horizon_is_not_an_open_date(): void
    {
        $this->mondayMorning();

        $this->assertFalse(StudioSchedule::isOpenDate('2027-01-05'));
    }

    public function test_every_slot_finishes_before_closing_and_starts_on_the_increment(): void
    {
        $this->mondayMorning();

        $settings = StudioSchedule::settings();
        $slots = StudioSchedule::slots('2026-09-29', 90);

        $this->assertNotSame([], $slots);

        $close = StudioSchedule::localInstant('2026-09-29', $settings['closes_at']);

        foreach ($slots as $slot) {
            $start = StudioSchedule::localInstant('2026-09-29', $slot['value']);

            $this->assertNotNull($start);
            $this->assertTrue(
                $start->addMinutes(90)->lessThanOrEqualTo($close),
                "Slot {$slot['value']} would run past closing time."
            );
            $this->assertSame(0, ((int) explode(':', $slot['value'])[1]) % $settings['slot_minutes']);
        }
    }

    public function test_a_slot_carries_the_same_moment_as_a_utc_instant(): void
    {
        $this->mondayMorning();

        foreach (StudioSchedule::slots('2026-09-29', 60) as $slot) {
            $local = StudioSchedule::localInstant('2026-09-29', $slot['value']);

            $this->assertSame(
                $local->setTimezone('UTC')->toIso8601String(),
                $slot['instant'],
                'A slot must expose the same instant in UTC, never a bare local time.'
            );
        }
    }

    public function test_a_service_too_long_for_the_opening_window_is_offered_no_slots(): void
    {
        $this->mondayMorning();

        // Nine hours open, so a ten-hour appointment cannot fit anywhere in the day.
        $this->assertSame([], StudioSchedule::slots('2026-09-29', 600));
        $this->assertSame([], StudioSchedule::slots('2026-09-29', 0));
        $this->assertSame([], StudioSchedule::slots('2026-09-29', -30));
    }

    public function test_times_already_past_today_are_not_offered(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-29 14:10', StudioSchedule::timezone()));

        $slots = array_column(StudioSchedule::slots('2026-09-29', 30), 'value');

        $this->assertNotSame([], $slots);
        $this->assertNotContains('10:00', $slots);
        $this->assertNotContains('14:00', $slots);
        $this->assertContains('14:30', $slots);
    }

    /**
     * 8 March 2026 is the Toronto spring-forward day: 02:00–02:59 never happens. A demo
     * window that covers it must skip those times rather than offer a time that does not
     * exist (05, invariant 3).
     */
    public function test_a_local_time_that_does_not_exist_is_never_offered(): void
    {
        Config::set('gossips.booking.schedule', [
            'days' => [0],
            'opens_at' => '01:00',
            'closes_at' => '04:00',
            'slot_minutes' => 30,
            'days_ahead' => 1,
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-03-08 00:30', StudioSchedule::timezone()));

        $this->assertNull(StudioSchedule::localInstant('2026-03-08', '02:00'));
        $this->assertNull(StudioSchedule::localInstant('2026-03-08', '02:30'));

        $offered = array_column(StudioSchedule::slots('2026-03-08', 30), 'value');

        $this->assertContains('01:00', $offered);
        $this->assertContains('03:00', $offered);
        $this->assertNotContains('02:00', $offered);
        $this->assertNotContains('02:30', $offered);
    }

    public function test_malformed_input_is_rejected(): void
    {
        $this->mondayMorning();

        $this->assertNull(StudioSchedule::localInstant('2026-13-40', '10:00'));
        $this->assertNull(StudioSchedule::localInstant('2026-09-29', '25:00'));
        $this->assertNull(StudioSchedule::localInstant('2026-09-29', 'ten oclock'));
        $this->assertSame([], StudioSchedule::slots('2026-02-31', 60));
    }

    public function test_a_chosen_slot_is_labelled_with_its_zone(): void
    {
        $this->mondayMorning();

        $label = StudioSchedule::labelFor('2026-09-29', '10:00');

        $this->assertNotNull($label);
        $this->assertStringContainsString('10:00', $label);
        $this->assertStringContainsString('Toronto time', $label);
        $this->assertNull(StudioSchedule::labelFor('2026-09-29', '25:00'), 'A time that cannot exist has no label.');
    }
}
