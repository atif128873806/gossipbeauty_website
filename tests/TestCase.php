<?php

namespace Tests;

use App\Models\BookingConfiguration;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** Enable the deliberately closed starter booking catalog for journey tests only. */
    protected function enablePublicBookingForTest(): void
    {
        BookingConfiguration::query()->firstOrFail()->update(['online_booking_enabled' => true]);
        Service::query()->update(['is_online_bookable' => true]);
        Staff::query()->update(['is_bookable' => true]);
    }
}
