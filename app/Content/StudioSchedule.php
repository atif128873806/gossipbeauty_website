<?php

namespace App\Content;

use Carbon\CarbonImmutable;

/**
 * Legacy opening-pattern and slot generator, not live availability.
 *
 * This preview fixture builds times from `config('gossips.booking.schedule')`.
 * Live customer and admin booking use the database-backed availability service.
 *
 * It is deliberately **not** the availability engine. Live availability uses
 * working hours, schedule exceptions, buffers, existing appointments and capacity;
 * final bookings are rechecked inside a transaction.
 *
 * Two invariants are honoured here as well, because they keep
 * the offered times honest:
 *
 *  1. Times are built in `config('gossips.timezone')` (America/Toronto) and each slot also
 *     exposes its unambiguous UTC instant, rather than a local string that has to be
 *     reinterpreted later.
 *  2. A local time that does not exist on that date (the spring-forward gap) is never
 *     offered. `localInstant()` rejects it by round-tripping the wall-clock value.
 */
class StudioSchedule
{
    /**
     * The schedule settings, with starter defaults filled in so a missing config
     * key degrades to a usable pattern rather than an error.
     *
     * @return array{days: list<int>, opens_at: string, closes_at: string, slot_minutes: int, days_ahead: int}
     */
    public static function settings(): array
    {
        $schedule = config('gossips.booking.schedule', []);

        return [
            'days' => is_array($schedule['days'] ?? null) ? array_map('intval', $schedule['days']) : [2, 3, 4, 5, 6],
            'opens_at' => is_string($schedule['opens_at'] ?? null) ? $schedule['opens_at'] : '10:00',
            'closes_at' => is_string($schedule['closes_at'] ?? null) ? $schedule['closes_at'] : '19:00',
            'slot_minutes' => max(5, (int) ($schedule['slot_minutes'] ?? 30)),
            'days_ahead' => max(1, (int) ($schedule['days_ahead'] ?? 21)),
        ];
    }

    public static function timezone(): string
    {
        return (string) config('gossips.timezone', 'America/Toronto');
    }

    /**
     * The open days a customer can pick from, starting today in Toronto.
     *
     * @return list<array{value: string, label: string, weekday: string, is_today: bool}>
     */
    public static function openDates(?CarbonImmutable $now = null): array
    {
        $settings = self::settings();
        $today = ($now ?? CarbonImmutable::now(self::timezone()))->startOfDay();
        $dates = [];

        for ($offset = 0; $offset < $settings['days_ahead']; $offset++) {
            $day = $today->addDays($offset);

            if (! in_array($day->dayOfWeek, $settings['days'], true)) {
                continue;
            }

            $dates[] = [
                'value' => $day->format('Y-m-d'),
                'label' => $day->format('D j M'),
                'weekday' => $day->format('l'),
                'is_today' => $offset === 0,
            ];
        }

        return $dates;
    }

    /**
     * Whether a date is one of the days the studio opens. Anything else — a closed
     * weekday, a past date, a date beyond the horizon or a malformed string — is not.
     */
    public static function isOpenDate(string $date, ?CarbonImmutable $now = null): bool
    {
        return in_array($date, array_column(self::openDates($now), 'value'), true);
    }

    /**
     * Start times that fit the whole appointment inside the opening window on a
     * given date. A slot is returned only when the appointment ends by closing time, so a
     * 4-hour service is never offered in a 3-hour gap (05, invariants 1 and 2).
     *
     * @return list<array{value: string, label: string, instant: string}>
     */
    public static function slots(string $date, int $durationMinutes, ?CarbonImmutable $now = null): array
    {
        $settings = self::settings();

        if ($durationMinutes <= 0 || ! self::isParsableDate($date)) {
            return [];
        }

        $opens = self::localInstant($date, $settings['opens_at']);
        $closes = self::localInstant($date, $settings['closes_at']);

        if ($opens === null || $closes === null) {
            return [];
        }

        $now ??= CarbonImmutable::now(self::timezone());
        $slots = [];

        for (
            $cursor = $opens;
            $cursor->addMinutes($durationMinutes)->lessThanOrEqualTo($closes);
            $cursor = $cursor->addMinutes($settings['slot_minutes'])
        ) {
            $local = self::localInstant($date, $cursor->format('H:i'));

            // Skip times that do not exist on that date, and times already past.
            if ($local === null || $local->lessThanOrEqualTo($now)) {
                continue;
            }

            $slots[] = [
                'value' => $local->format('H:i'),
                'label' => $local->format('H:i'),
                'instant' => $local->setTimezone('UTC')->toIso8601String(),
            ];
        }

        return $slots;
    }

    /**
     * A Toronto date and wall-clock time as an instant, or null when that local time does
     * not exist on that date. On the spring-forward day, 02:30 never happens: PHP shifts
     * it to 03:30, which the round-trip check below catches.
     */
    public static function localInstant(string $date, string $time): ?CarbonImmutable
    {
        if (! self::isParsableDate($date) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            return null;
        }

        $candidate = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $date.' '.$time,
            self::timezone()
        );

        if (! $candidate instanceof CarbonImmutable) {
            return null;
        }

        return $candidate->format('Y-m-d H:i') === $date.' '.$time ? $candidate : null;
    }

    /**
     * A human label for a chosen slot, always naming the zone it is displayed in
     * (05, invariant 3: "Toronto time" in the confirmation).
     */
    public static function labelFor(string $date, string $time): ?string
    {
        $instant = self::localInstant($date, $time);

        if ($instant === null) {
            return null;
        }

        return $instant->format('l j F, H:i').' (Toronto time)';
    }

    private static function isParsableDate(string $date): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
