<?php

namespace Tests\Feature;

use App\Livewire\BookingJourney;
use App\Models\Appointment;
use App\Models\BookingConfiguration;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBookingSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00', 'America/Toronto'));
        $this->enablePublicBookingForTest();
    }

    public function test_owner_can_change_booking_rules_and_public_service_visibility(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $service = Service::query()->where('slug', 'brow-complex')->firstOrFail();

        $this->actingAs($owner)->put(route('admin.booking.rules'), [
            'booking_horizon_days' => 21,
            'minimum_notice_minutes' => 120,
            'slot_increment_minutes' => 15,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(21, BookingConfiguration::current()->fresh()->booking_horizon_days);

        $this->actingAs($owner)->patch(route('admin.services.booking', $service), [
            'is_online_bookable' => 0,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($service->fresh()->is_online_bookable);
        Livewire::test(BookingJourney::class, ['service' => 'brow-complex'])
            ->assertSet('serviceSlug', null)
            ->assertDontSee('Brow Complex');
    }

    public function test_manager_cannot_open_global_booking_or_change_team_access(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = Staff::query()->where('name', 'Olena')->firstOrFail();

        $this->actingAs($manager)->patch(route('admin.booking.update'), [
            'online_booking_enabled' => 0,
        ])->assertForbidden();
        $this->actingAs($manager)->patch(route('admin.staff.booking', $staff), [
            'is_bookable' => 0,
            'accepts_new_clients' => 0,
        ])->assertForbidden();
        $this->assertTrue(BookingConfiguration::current()->online_booking_enabled);
        $this->assertTrue($staff->fresh()->is_bookable);
    }

    public function test_reception_booking_appears_in_panel_and_blocks_the_public_slot(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $service = Service::query()->where('slug', 'brow-complex')->firstOrFail();
        $staff = Staff::query()->where('name', 'Olena')->firstOrFail();

        $this->actingAs($manager)->post(route('admin.appointments.store'), [
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'date' => '2026-09-29',
            'time' => '10:00',
            'first_name' => 'Reception',
            'last_name' => 'Client',
            'phone' => '+1 416 555 0188',
        ])->assertRedirect()->assertSessionHas('success');

        $appointment = Appointment::query()->sole();
        $this->assertSame('admin', $appointment->origin);
        $this->actingAs($manager)->get('/admin?date=2026-09-29')
            ->assertOk()->assertSee('Reception Client')->assertSee($appointment->public_reference);

        Livewire::test(BookingJourney::class, ['service' => 'brow-complex'])
            ->call('selectDate', '2026-09-29')
            ->call('selectTime', '10:00')
            ->assertSet('time', null)
            ->assertSet('conflict', 'That time is no longer available. Choose another time.');
    }
}
