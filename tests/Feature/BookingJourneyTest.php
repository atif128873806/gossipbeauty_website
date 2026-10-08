<?php

namespace Tests\Feature;

use App\Domain\Booking\AppointmentWriter;
use App\Domain\Booking\BookingConflict;
use App\Livewire\BookingJourney;
use App\Models\Appointment;
use App\Models\BookingConfiguration;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\TestCase;

/** The public flow and panel share one persisted, capacity-blocking calendar. */
class BookingJourneyTest extends TestCase
{
    private const OPEN_DAY = '2026-09-29';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00', 'America/Toronto'));
        $this->enablePublicBookingForTest();
    }

    private function bookableService(): Service
    {
        return Service::query()->where('slug', 'brow-complex')->firstOrFail();
    }

    private function eligibleStaff(): Staff
    {
        return Staff::query()->where('name', 'Olena')->firstOrFail();
    }

    private function book(string $key, string $time = '10:00'): Appointment
    {
        return app(AppointmentWriter::class)->confirm(
            [$this->bookableService()->id], [], $this->eligibleStaff()->id,
            self::OPEN_DAY, $time,
            ['first_name' => 'Test', 'last_name' => 'Visitor', 'phone' => '+1 416 555 0123', 'email' => null],
            $key,
        );
    }

    public function test_only_enabled_services_are_offered_or_preselected(): void
    {
        Service::query()->where('slug', 'shellac-manicure')->update(['is_online_bookable' => false]);

        Livewire::test(BookingJourney::class, ['service' => 'shellac-manicure'])
            ->assertSet('serviceSlug', null)
            ->assertDontSee('Shellac Manicure');

        $this->get('/booking?service=shellac-manicure')->assertOk()->assertDontSee('name="booking-service" value="shellac-manicure"', false);
    }

    public function test_the_customer_can_complete_a_booking_and_the_panel_shows_it(): void
    {
        $component = Livewire::test(BookingJourney::class, ['service' => 'brow-complex'])
            ->call('nextStep')->assertSet('step', 'contact')
            ->set('firstName', 'Jamie')->set('lastName', 'Client')
            ->set('phone', '+1 416 555 0155')
            ->call('nextStep')->assertSet('step', 'professional')
            ->set('professional', (string) $this->eligibleStaff()->id)
            ->call('nextStep')->assertSet('step', 'time')
            ->call('selectDate', self::OPEN_DAY)
            ->call('selectTime', '10:00')
            ->call('nextStep')->assertSet('step', 'review')
            ->call('confirm')->assertSet('step', 'done')
            ->assertSee('Appointment confirmed');

        $appointment = Appointment::query()->with(['customer', 'items'])->sole();
        $this->assertSame('confirmed', $appointment->status);
        $this->assertSame('website', $appointment->origin);
        $this->assertSame('Jamie', $appointment->customer->first_name);
        $this->assertSame('Brow Complex', $appointment->items->first()->name_snapshot);
        $component->assertSet('confirmationReference', $appointment->public_reference);

        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin')
            ->assertOk()
            ->assertSee('Next confirmed appointments')
            ->assertSee('Jamie Client')
            ->assertSee($appointment->public_reference);
        $this->actingAs($owner)->get('/admin?date='.self::OPEN_DAY)
            ->assertOk()->assertSee('Jamie Client')->assertSee('Brow Complex');
    }

    public function test_named_eligible_professional_is_offered_to_a_new_customer(): void
    {
        $component = Livewire::test(BookingJourney::class, ['service' => 'brow-complex'])
            ->set('firstName', 'Jamie')
            ->set('phone', '+1 416 555 0155')
            ->set('step', 'professional');
        $this->assertContains('Olena', $component->instance()->professionalOptions());
        $component
            ->assertSee('Olena')
            ->assertSee('Any available professional');
    }

    public function test_same_staff_slot_is_reserved_once_and_idempotent_retry_returns_original(): void
    {
        $first = $this->book('first-attempt');

        $this->assertSame($first->id, $this->book('first-attempt')->id);
        $this->expectException(BookingConflict::class);
        try {
            $this->book('second-attempt');
        } finally {
            $this->assertSame(1, Appointment::query()->count());
        }
    }

    public function test_a_closed_calendar_rejects_website_writes(): void
    {
        BookingConfiguration::current()->update(['online_booking_enabled' => false]);
        $this->get('/booking')->assertOk()->assertSee('Online booking is currently closed');
        $this->expectException(BookingConflict::class);
        $this->book('closed-attempt');
    }

    public function test_a_specialist_cannot_read_another_specialists_appointment(): void
    {
        $appointment = $this->book('appointment-for-olena');
        $otherStaff = Staff::query()->where('name', 'Karyna')->firstOrFail();
        $specialist = User::factory()->create(['role' => 'specialist', 'staff_id' => $otherStaff->id]);

        $this->actingAs($specialist)->get('/admin?date='.self::OPEN_DAY)
            ->assertOk()
            ->assertDontSee($appointment->public_reference)
            ->assertDontSee('Test Visitor');
        $this->actingAs($specialist)->patch(route('admin.appointments.status', $appointment), ['status' => 'completed'])
            ->assertForbidden();
    }
}
