<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\AdminAppointmentManager;
use App\Domain\Booking\AppointmentRescheduler;
use App\Domain\Booking\AppointmentWriter;
use App\Domain\Booking\AvailabilityService;
use App\Domain\Booking\BookingConflict;
use App\Domain\Booking\ExistingAppointmentImporter;
use App\Http\Controllers\Controller;
use App\Jobs\SendAppointmentConfirmation;
use App\Models\AddOn;
use App\Models\Appointment;
use App\Models\BookingConfiguration;
use App\Models\BookingEvent;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['owner', 'manager', 'specialist'], true), 403);
        $section = match ($request->route()->getName()) {
            'admin.customers.index' => 'customers',
            'admin.services.index' => 'services',
            'admin.team.index' => 'team',
            'admin.availability.index' => 'availability',
            'admin.accounts.index' => 'accounts',
            'admin.settings.index' => 'settings',
            default => 'bookings',
        };
        abort_if(in_array($section, ['customers', 'services'], true) && ! $user->canManageAppointments(), 403);
        abort_if(in_array($section, ['team', 'availability', 'accounts'], true) && ! $user->isOwner(), 403);
        $date = $request->query('date');
        $day = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? CarbonImmutable::parse($date, 'America/Toronto')->startOfDay()
            : CarbonImmutable::now('America/Toronto')->startOfDay();
        $query = Appointment::query()->with([
            'customer', 'staff', 'items', 'notificationDeliveries',
            'events' => fn ($events) => $events->with('actor')->orderBy('created_at'),
        ])
            ->where('starts_at_utc', '>=', $day->setTimezone('UTC')->format('Y-m-d H:i:s'))
            ->where('starts_at_utc', '<', $day->addDay()->setTimezone('UTC')->format('Y-m-d H:i:s'))
            ->orderBy('starts_at_utc');
        if (! $user->canManageAppointments()) {
            $query->where('staff_id', $user->staff_id);
        }
        $upcomingQuery = Appointment::query()->with(['customer', 'staff', 'items'])
            ->where('starts_at_utc', '>=', now('UTC'))
            ->where('status', 'confirmed')
            ->orderBy('starts_at_utc');
        if (! $user->canManageAppointments()) {
            $upcomingQuery->where('staff_id', $user->staff_id);
        }
        $customerMatches = collect();
        if ($section === 'customers' && $request->query('customer_lookup') === '1') {
            $lookup = $request->session()->get('admin_customer_lookup');
            if (is_array($lookup) && in_array($lookup['field'] ?? null, ['phone_search_hash', 'email_search_hash'], true)
                && is_string($lookup['hash'] ?? null)) {
                $customerMatches = Customer::query()
                    ->where($lookup['field'], $lookup['hash'])
                    ->with(['appointments' => fn ($appointments) => $appointments
                        ->with(['staff', 'items'])
                        ->orderByDesc('starts_at_utc')
                        ->limit(20)])
                    ->limit(10)
                    ->get();
            }
        }

        $recentQuery = Appointment::query()->with(['customer', 'staff', 'items'])->orderByDesc('created_at')->orderByDesc('id');
        $reference = trim((string) $request->query('reference', ''));
        $listMode = $reference !== '' || $request->query('view') === 'all' ? 'all' : 'upcoming';
        if ($reference !== '') {
            $recentQuery->where('public_reference', $reference);
        }
        if (! $user->canManageAppointments()) {
            $recentQuery->where('staff_id', $user->staff_id);
        }

        return view('admin.index', [
            'section' => $section,
            'day' => $day,
            'appointments' => $section === 'bookings' ? $query->get() : collect(),
            'upcomingAppointments' => $section === 'bookings' ? $upcomingQuery->paginate(20, ['*'], 'upcoming_page')->withQueryString() : collect(),
            'recentAppointments' => $section === 'bookings' ? $recentQuery->paginate(20)->withQueryString() : null,
            'reference' => $reference,
            'listMode' => $listMode,
            'services' => in_array($section, ['bookings', 'services', 'team'], true) ? Service::query()->with(['category', 'addOns'])->orderBy('position')->get() : collect(),
            'addOns' => in_array($section, ['bookings', 'services'], true) ? AddOn::query()->orderBy('name')->get() : collect(),
            'categories' => $section === 'services' ? ServiceCategory::query()->where('is_active', true)->orderBy('position')->get() : collect(),
            'staff' => in_array($section, ['bookings', 'team', 'availability', 'accounts'], true) ? Staff::query()->with(['workingHours', 'services'])->orderBy('position')->get() : collect(),
            'users' => $section === 'accounts' ? User::query()->with('staffProfile')->orderBy('name')->get() : collect(),
            'exceptions' => $section === 'availability' ? ScheduleException::query()->with('staff')->orderBy('starts_at_utc')->where('ends_at_utc', '>', now('UTC')->subDays(7))->limit(100)->get() : collect(),
            'configuration' => BookingConfiguration::current(),
            'customerMatches' => $customerMatches,
            'recentCustomers' => $section === 'customers' ? Customer::query()
                ->withCount('appointments')
                ->with(['appointments' => fn ($appointments) => $appointments->with(['staff', 'items'])->orderByDesc('starts_at_utc')->limit(3)])
                ->orderByDesc('created_at')->paginate(20)->withQueryString() : null,
            'hasCustomerLookup' => $section === 'customers' && $request->query('customer_lookup') === '1',
            'canManage' => $user->canManageAppointments(),
            'isOwner' => $user->isOwner(),
        ]);
    }

    public function status(Request $request, Appointment $appointment, AdminAppointmentManager $manager): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:completed,cancelled,no_show'], 'reason' => ['nullable', 'string', 'max:255']]);
        $updated = $manager->updateStatus($appointment, $data['status'], $request->user(), $data['reason'] ?? null);
        if ($data['status'] === 'cancelled') {
            $this->queueCustomerEmail($updated, 'appointment.cancelled');
        }

        return back()->with('success', 'Appointment updated.');
    }

    public function searchCustomers(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageAppointments(), 403);
        $data = $request->validate(['customer_contact' => ['required', 'string', 'max:255']]);
        $contact = mb_strtolower(trim($data['customer_contact']));
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $field = 'email_search_hash';
            $normalized = $contact;
        } else {
            $normalized = preg_replace('/\\D+/', '', $contact);
            if (strlen($normalized) < 8 || strlen($normalized) > 15) {
                throw ValidationException::withMessages(['customer_contact' => 'Enter the customer’s exact phone number or email address.']);
            }
            $field = 'phone_search_hash';
        }
        $request->session()->put('admin_customer_lookup', [
            'field' => $field,
            'hash' => hash_hmac('sha256', $normalized, (string) config('app.key')),
        ]);

        return redirect()->route('admin.customers.index', ['customer_lookup' => '1']);
    }

    public function clearCustomerSearch(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageAppointments(), 403);
        $request->session()->forget('admin_customer_lookup');

        return redirect()->route('admin.customers.index');
    }

    public function updateCustomerNotes(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()->canManageAppointments(), 403);
        $data = $request->validate(['admin_notes' => ['nullable', 'string', 'max:2000']]);
        $customer->update(['admin_notes' => trim($data['admin_notes'] ?? '') ?: null]);
        $this->recordAdminEvent($request, 'customer.notes_changed', ['customer_id' => $customer->id]);

        return back()->with('success', 'Customer notes saved.');
    }

    public function reschedule(Request $request, Appointment $appointment, AppointmentRescheduler $rescheduler): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
        ]);
        try {
            $moved = $rescheduler->move($appointment, $data['date'], $data['time'], isset($data['staff_id']) ? (int) $data['staff_id'] : null, $request->user());
        } catch (BookingConflict $exception) {
            throw ValidationException::withMessages(['time' => $exception->getMessage()]);
        }
        $this->queueCustomerEmail($moved, 'appointment.changed');

        return back()->with('success', 'Appointment moved and the calendar updated.');
    }

    public function retryNotification(Request $request, Appointment $appointment, NotificationDelivery $delivery): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canManageAppointments() || $user->staff_id === $appointment->staff_id, 403);
        abort_unless((int) $delivery->appointment_id === (int) $appointment->id, 404);
        $queued = DB::transaction(function () use ($delivery, $appointment, $user): bool {
            $locked = NotificationDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'failed') {
                return false;
            }
            $locked->update(['status' => 'queued', 'last_error' => null]);
            BookingEvent::query()->create([
                'appointment_id' => $appointment->id,
                'actor_user_id' => $user->id,
                'actor_type' => 'staff',
                'event_type' => 'notification.retry_queued',
                'changes' => ['delivery_id' => $locked->id, 'attempts_before_retry' => $locked->attempts],
                'source' => 'admin',
            ]);
            SendAppointmentConfirmation::dispatch($locked->id)->afterCommit();

            return true;
        });

        return back()->with('success', $queued ? 'Email retry queued.' : 'This email is not in a failed state.');
    }

    public function toggleBooking(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate(['online_booking_enabled' => ['required', 'boolean']]);
        $before = BookingConfiguration::current()->online_booking_enabled;
        BookingConfiguration::query()->updateOrCreate(['id' => 1], ['online_booking_enabled' => (bool) $data['online_booking_enabled']]);
        $this->recordAdminEvent($request, 'booking.configuration_changed', ['online_booking_enabled' => ['from' => $before, 'to' => (bool) $data['online_booking_enabled']]]);

        return back()->with('success', 'Online booking setting saved.');
    }

    public function toggleService(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->canManageAppointments(), 403);
        $data = $request->validate(['is_online_bookable' => ['required', 'boolean']]);
        $before = $service->is_online_bookable;
        $service->update(['is_online_bookable' => (bool) $data['is_online_bookable']]);
        $this->recordAdminEvent($request, 'service.booking_changed', ['service_id' => $service->id, 'is_online_bookable' => ['from' => $before, 'to' => (bool) $data['is_online_bookable']]]);

        return back()->with('success', 'Service booking setting saved.');
    }

    public function toggleStaff(Request $request, Staff $staff): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate(['is_bookable' => ['required', 'boolean'], 'accepts_new_clients' => ['required', 'boolean'], 'is_active' => ['required', 'boolean']]);
        $before = $staff->only(['is_active', 'is_bookable', 'accepts_new_clients']);
        $staff->update($data);
        $this->recordAdminEvent($request, 'staff.booking_changed', ['staff_id' => $staff->id, 'before' => $before, 'after' => $staff->only(['is_active', 'is_bookable', 'accepts_new_clients'])]);

        return back()->with('success', 'Professional booking settings saved.');
    }

    public function saveHours(Request $request, Staff $staff): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'opens_at' => ['required', 'date_format:H:i'], 'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
            'enabled' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($staff, $data): void {
            Staff::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $timezone = BookingConfiguration::current()->timezone;
            $appointments = Appointment::query()->where('staff_id', $staff->id)
                ->whereIn('status', Appointment::CAPACITY_HOLDING_STATUSES)
                ->where('busy_ends_at_utc', '>', now('UTC'))->get();
            foreach ($appointments as $appointment) {
                $localStart = $appointment->busy_starts_at_utc->setTimezone($timezone);
                $localEnd = $appointment->busy_ends_at_utc->setTimezone($timezone);
                if ((int) $localStart->dayOfWeek !== (int) $data['weekday']) {
                    continue;
                }
                if (! $data['enabled'] || $localStart->format('H:i') < $data['opens_at'] || $localEnd->format('H:i') > $data['closes_at']) {
                    throw ValidationException::withMessages(['hours' => 'These hours overlap an existing appointment. Resolve that appointment before changing this shift.']);
                }
            }
            $staff->workingHours()->where('weekday', $data['weekday'])->delete();
            if ($data['enabled']) {
                $staff->workingHours()->create(['weekday' => $data['weekday'], 'opens_at' => $data['opens_at'], 'closes_at' => $data['closes_at']]);
            }
        });
        $this->recordAdminEvent($request, 'staff.hours_changed', ['staff_id' => $staff->id, 'weekday' => (int) $data['weekday'], 'enabled' => (bool) $data['enabled'], 'opens_at' => $data['opens_at'], 'closes_at' => $data['closes_at']]);

        return back()->with('success', 'Weekly hours saved. Existing appointments remain on the calendar.');
    }

    public function saveService(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->canManageAppointments(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'summary' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,600'],
            'price_mode' => ['required', 'in:fixed,from,free,quote'], 'price_amount' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'buffer_before_minutes' => ['required', 'integer', 'between:0,240'], 'buffer_after_minutes' => ['required', 'integer', 'between:0,240'],
            'is_active' => ['required', 'boolean'],
        ]);
        if (in_array($data['price_mode'], ['fixed', 'from'], true) && ($data['price_amount'] ?? null) === null) {
            throw ValidationException::withMessages(['price_amount' => 'Enter the CAD amount for this price type.']);
        }
        $before = $service->only(['name', 'summary', 'duration_minutes', 'price_mode', 'price_minor', 'is_active']);
        DB::transaction(function () use ($service, $data): void {
            $locked = Service::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $amount = in_array($data['price_mode'], ['quote', 'free'], true) ? ($data['price_mode'] === 'free' ? 0 : null) : (int) round((float) $data['price_amount'] * 100);
            unset($data['price_amount']);
            $locked->update([...$data, 'price_minor' => $amount, 'archived_at' => $data['is_active'] ? null : now()]);
        });
        $this->recordAdminEvent($request, 'service.updated', ['service_id' => $service->id, 'before' => $before, 'after' => Service::query()->find($service->id)?->only(['name', 'summary', 'duration_minutes', 'price_mode', 'price_minor', 'is_active'])]);

        return back()->with('success', 'Service saved. Existing appointment snapshots are unchanged.');
    }

    public function createService(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate([
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'], 'name' => ['required', 'string', 'max:160'],
            'duration_minutes' => ['required', 'integer', 'between:5,600'], 'price_mode' => ['required', 'in:fixed,from,free,quote'],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
        if (in_array($data['price_mode'], ['fixed', 'from'], true) && ($data['price_amount'] ?? null) === null) {
            throw ValidationException::withMessages(['price_amount' => 'Enter the CAD amount.']);
        }
        $slug = Str::slug($data['name']);
        $slug = $slug.'-'.substr(hash('sha256', $slug.microtime()), 0, 5);
        $created = Service::query()->create([
            ...$data, 'slug' => $slug, 'price_minor' => in_array($data['price_mode'], ['quote', 'free'], true) ? ($data['price_mode'] === 'free' ? 0 : null) : (int) round((float) $data['price_amount'] * 100),
            'is_active' => true, 'is_online_bookable' => false, 'position' => (int) Service::max('position') + 1,
        ]);
        $this->recordAdminEvent($request, 'service.created', ['service_id' => $created->id, 'slug' => $created->slug]);

        return back()->with('success', 'Service created. Assign eligible professionals before opening it for bookings.');
    }

    public function createAddOn(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'duration_minutes' => ['required', 'integer', 'between:0,600'],
            'price_mode' => ['required', 'in:fixed,from,free,quote'], 'price_amount' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
        if (in_array($data['price_mode'], ['fixed', 'from'], true) && ($data['price_amount'] ?? null) === null) {
            throw ValidationException::withMessages(['price_amount' => 'Enter the CAD amount.']);
        }
        $slug = Str::slug($data['name']);
        $created = AddOn::query()->create([
            'slug' => $slug.'-'.substr(hash('sha256', $slug.microtime()), 0, 5), 'name' => $data['name'],
            'duration_minutes' => $data['duration_minutes'], 'price_mode' => $data['price_mode'],
            'price_minor' => in_array($data['price_mode'], ['quote', 'free'], true) ? ($data['price_mode'] === 'free' ? 0 : null) : (int) round((float) $data['price_amount'] * 100),
            'is_active' => true,
        ]);
        $this->recordAdminEvent($request, 'add_on.created', ['add_on_id' => $created->id, 'slug' => $created->slug]);

        return back()->with('success', 'Extra created. Link it to eligible services before publishing it.');
    }

    public function saveAddOn(Request $request, AddOn $addOn): RedirectResponse
    {
        abort_unless($request->user()->canManageAppointments(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'between:0,600'], 'price_mode' => ['required', 'in:fixed,from,free,quote'],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'max:10000'], 'is_active' => ['required', 'boolean'],
        ]);
        if (in_array($data['price_mode'], ['fixed', 'from'], true) && ($data['price_amount'] ?? null) === null) {
            throw ValidationException::withMessages(['price_amount' => 'Enter the CAD amount.']);
        }
        $before = $addOn->only(['name', 'description', 'duration_minutes', 'price_mode', 'price_minor', 'is_active']);
        DB::transaction(function () use ($addOn, $data): void {
            $locked = AddOn::query()->whereKey($addOn->id)->lockForUpdate()->firstOrFail();
            $amount = in_array($data['price_mode'], ['quote', 'free'], true) ? ($data['price_mode'] === 'free' ? 0 : null) : (int) round((float) $data['price_amount'] * 100);
            unset($data['price_amount']);
            $locked->update([...$data, 'price_minor' => $amount]);
        });
        $this->recordAdminEvent($request, 'add_on.updated', ['add_on_id' => $addOn->id, 'before' => $before, 'after' => AddOn::query()->find($addOn->id)?->only(['name', 'description', 'duration_minutes', 'price_mode', 'price_minor', 'is_active'])]);

        return back()->with('success', 'Extra saved. Appointment snapshots remain unchanged.');
    }

    public function saveServiceAddOns(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->canManageAppointments(), 403);
        $data = $request->validate(['add_on_ids' => ['nullable', 'array'], 'add_on_ids.*' => ['integer', 'exists:add_ons,id']]);
        $before = $service->addOns()->pluck('add_ons.id')->map(fn ($id) => (int) $id)->all();
        DB::transaction(function () use ($service, $data): void {
            Service::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $service->addOns()->sync(collect($data['add_on_ids'] ?? [])->mapWithKeys(fn ($id, $position) => [$id => ['is_required' => false, 'position' => $position]])->all());
        });
        $this->recordAdminEvent($request, 'service.add_ons_changed', ['service_id' => $service->id, 'from' => $before, 'to' => array_map('intval', $data['add_on_ids'] ?? [])]);

        return back()->with('success', 'Service extras saved.');
    }

    public function saveBookingRules(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate([
            'booking_horizon_days' => ['required', 'integer', 'between:1,365'],
            'minimum_notice_minutes' => ['required', 'integer', 'between:0,43200'],
            'slot_increment_minutes' => ['required', 'integer', 'in:5,10,15,20,30,60'],
        ]);
        $before = BookingConfiguration::current()->only(['booking_horizon_days', 'minimum_notice_minutes', 'slot_increment_minutes']);
        BookingConfiguration::query()->updateOrCreate(['id' => 1], $data);
        $this->recordAdminEvent($request, 'booking.rules_changed', ['before' => $before, 'after' => $data]);

        return back()->with('success', 'Booking rules saved.');
    }

    public function createStaff(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'title' => ['nullable', 'string', 'max:160'],
            'service_ids' => ['required', 'array', 'min:1'], 'service_ids.*' => ['integer', 'exists:services,id'],
        ]);
        $staff = Staff::query()->create(['name' => $data['name'], 'title' => $data['title'], 'is_active' => true, 'is_bookable' => false, 'accepts_new_clients' => true, 'position' => (int) Staff::max('position') + 1]);
        $staff->services()->sync(collect($data['service_ids'])->mapWithKeys(fn ($id) => [$id => ['accepts_new_clients' => true]])->all());
        foreach ([2, 3, 4, 5, 6] as $weekday) {
            $staff->workingHours()->create(['weekday' => $weekday, 'opens_at' => '10:00', 'closes_at' => '19:00']);
        }
        $this->recordAdminEvent($request, 'staff.created', ['staff_id' => $staff->id, 'service_ids' => array_map('intval', $data['service_ids'])]);

        return back()->with('success', 'Professional added. Review the starter hours and eligibility before making them bookable.');
    }

    public function saveStaff(Request $request, Staff $staff): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'title' => ['nullable', 'string', 'max:160'],
            'service_ids' => ['required', 'array', 'min:1'], 'service_ids.*' => ['integer', 'exists:services,id'],
            'service_specs' => ['nullable', 'array'], 'service_specs.*.duration_minutes' => ['nullable', 'integer', 'between:5,600'],
            'service_specs.*.price_amount' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'new_client_service_ids' => ['nullable', 'array'], 'new_client_service_ids.*' => ['integer', 'exists:services,id'],
        ]);
        $before = ['name' => $staff->name, 'title' => $staff->title, 'service_ids' => $staff->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all()];
        DB::transaction(function () use ($staff, $data): void {
            Staff::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $staff->update(['name' => $data['name'], 'title' => $data['title']]);
            $newClientIds = array_map('intval', $data['new_client_service_ids'] ?? []);
            $specs = $data['service_specs'] ?? [];
            $assignments = collect($data['service_ids'])->mapWithKeys(function ($id) use ($newClientIds, $specs): array {
                $spec = $specs[$id] ?? $specs[(string) $id] ?? [];

                return [(int) $id => [
                    'accepts_new_clients' => in_array((int) $id, $newClientIds, true),
                    'duration_override_minutes' => ($spec['duration_minutes'] ?? '') !== '' ? (int) $spec['duration_minutes'] : null,
                    'price_override_minor' => ($spec['price_amount'] ?? '') !== '' ? (int) round((float) $spec['price_amount'] * 100) : null,
                ]];
            })->all();
            $staff->services()->sync($assignments);
        });
        $this->recordAdminEvent($request, 'staff.profile_changed', ['staff_id' => $staff->id, 'before' => $before, 'after' => ['name' => $data['name'], 'title' => $data['title'], 'service_ids' => array_map('intval', $data['service_ids'])]]);

        return back()->with('success', 'Professional profile and service eligibility saved.');
    }

    public function createUser(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:200'], 'role' => ['required', 'in:owner,manager,specialist'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
        ]);
        if ($data['role'] === 'specialist' && empty($data['staff_id'])) {
            throw ValidationException::withMessages(['staff_id' => 'Link a specialist account to a team profile.']);
        }
        if ($data['role'] === 'specialist' && Staff::query()->whereKey($data['staff_id'])->whereNotNull('user_id')->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'That team profile already has a panel account.']);
        }
        $user = User::query()->create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $data['password'], 'role' => $data['role'], 'staff_id' => $data['role'] === 'specialist' ? $data['staff_id'] : null]);
        if ($user->staff_id) {
            Staff::query()->whereKey($user->staff_id)->update(['user_id' => $user->id]);
        }
        $this->recordAdminEvent($request, 'user.created', ['user_id' => $user->id, 'role' => $user->role, 'staff_id' => $user->staff_id]);

        return back()->with('success', 'Panel account created. Give the user their sign-in details securely.');
    }

    public function updateUserAccess(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        abort_if($request->user()->id === $user->id, 422, 'Use another owner account to change your own access.');
        $data = $request->validate(['role' => ['required', 'in:owner,manager,specialist,disabled'], 'staff_id' => ['nullable', 'integer', 'exists:staff,id']]);
        if ($data['role'] === 'specialist' && empty($data['staff_id'])) {
            throw ValidationException::withMessages(['staff_id' => 'Link a specialist account to a team profile.']);
        }
        if ($data['role'] === 'specialist' && Staff::query()->whereKey($data['staff_id'])->whereNotNull('user_id')->where('user_id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'That team profile already has a panel account.']);
        }
        DB::transaction(function () use ($user, $data): void {
            if ($user->role === 'owner' && $data['role'] !== 'owner'
                && User::query()->where('role', 'owner')->lockForUpdate()->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one owner account must remain active.']);
            }
            if ($user->staff_id) {
                Staff::query()->whereKey($user->staff_id)->update(['user_id' => null]);
            }
            $staffId = $data['role'] === 'specialist' ? $data['staff_id'] : null;
            $user->update(['role' => $data['role'], 'staff_id' => $staffId]);
            if ($staffId) {
                Staff::query()->whereKey($staffId)->update(['user_id' => $user->id]);
            }
        });
        $this->recordAdminEvent($request, 'user.access_changed', ['user_id' => $user->id, 'role' => $data['role'], 'staff_id' => $data['role'] === 'specialist' ? (int) $data['staff_id'] : null]);

        return back()->with('success', 'Panel access updated.');
    }

    public function createException(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $data = $request->validate([
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'], 'kind' => ['required', 'in:break,time_off,closure'],
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'], 'ends_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);
        $timezone = BookingConfiguration::current()->timezone;
        [$startDate, $startTime] = explode('T', $data['starts_at']);
        [$endDate, $endTime] = explode('T', $data['ends_at']);
        $start = AvailabilityService::localInstant($startDate, $startTime, $timezone);
        $end = AvailabilityService::localInstant($endDate, $endTime, $timezone);
        if ($start === null || $end === null || ! $end->greaterThan($start)) {
            throw ValidationException::withMessages(['starts_at' => 'Enter a valid Toronto date and time interval.']);
        }
        $startUtc = $start->setTimezone('UTC');
        $endUtc = $end->setTimezone('UTC');
        $staffId = $data['staff_id'] ?? null;
        DB::transaction(function () use ($staffId, $startUtc, $endUtc, $data, $request): void {
            if ($staffId !== null) {
                Staff::query()->whereKey($staffId)->lockForUpdate()->firstOrFail();
            } else {
                Staff::query()->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            }
            $conflicts = Appointment::query()->whereIn('status', Appointment::CAPACITY_HOLDING_STATUSES)
                ->where('busy_starts_at_utc', '<', $endUtc->format('Y-m-d H:i:s'))
                ->where('busy_ends_at_utc', '>', $startUtc->format('Y-m-d H:i:s'))
                ->when($staffId !== null, fn ($q) => $q->where('staff_id', $staffId));
            if ($conflicts->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This interval overlaps an existing appointment. Resolve it before adding the block.']);
            }
            $exception = ScheduleException::query()->create([
                'staff_id' => $staffId, 'kind' => $data['kind'], 'starts_at_utc' => $startUtc,
                'ends_at_utc' => $endUtc, 'label' => $data['label'] ?? null, 'created_by' => $request->user()->id,
            ]);
            $this->recordAdminEvent($request, 'availability.exception_created', ['exception_id' => $exception->id, 'staff_id' => $staffId, 'kind' => $data['kind'], 'starts_at_utc' => $startUtc->toIso8601String(), 'ends_at_utc' => $endUtc->toIso8601String()]);
        });

        return back()->with('success', 'Availability block saved.');
    }

    public function deleteException(Request $request, ScheduleException $exception): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $snapshot = $exception->only(['staff_id', 'kind', 'starts_at_utc', 'ends_at_utc']);
        $id = $exception->id;
        $exception->delete();
        $this->recordAdminEvent($request, 'availability.exception_deleted', ['exception_id' => $id, 'before' => $snapshot]);

        return back()->with('success', 'Availability block removed.');
    }

    private function queueCustomerEmail(Appointment $appointment, string $type): void
    {
        if (! config('gossips.booking.email_enabled')) {
            return;
        }
        $email = $appointment->customer?->email;
        if ($email === null || $email === '') {
            return;
        }
        try {
            $delivery = NotificationDelivery::query()->create([
                'appointment_id' => $appointment->id, 'notification_type' => $type,
                'channel' => 'email', 'recipient' => mb_strtolower($email), 'status' => 'queued',
            ]);
            SendAppointmentConfirmation::dispatch($delivery->id)->afterCommit();
        } catch (\Throwable $exception) {
            if (isset($delivery)) {
                $delivery->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 4000)]);
            }
            report($exception);
        }
    }

    private function recordAdminEvent(Request $request, string $eventType, array $changes): void
    {
        BookingEvent::query()->create([
            'appointment_id' => null, 'actor_user_id' => $request->user()->id,
            'actor_type' => 'staff', 'event_type' => $eventType, 'changes' => $changes, 'source' => 'admin',
        ]);
    }

    public function manualBooking(Request $request, AppointmentWriter $writer): RedirectResponse
    {
        abort_unless($request->user()->canManageAppointments(), 403);
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'], 'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'add_on_ids' => ['nullable', 'array'], 'add_on_ids.*' => ['integer', 'exists:add_ons,id'],
            'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'],
            'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $appointment = $writer->confirm([(int) $data['service_id']], array_map('intval', $data['add_on_ids'] ?? []), (int) $data['staff_id'], $data['date'], $data['time'], $data, (string) Str::uuid(), 'admin', $request->user());
        } catch (BookingConflict $exception) {
            throw ValidationException::withMessages(['time' => $exception->getMessage()]);
        }
        if ($appointment->wasRecentlyCreated) {
            $this->queueCustomerEmail($appointment, 'appointment.confirmation');
        }

        return back()->with('success', 'Appointment '.$appointment->public_reference.' confirmed.');
    }

    public function importExistingAppointment(Request $request, ExistingAppointmentImporter $importer): RedirectResponse
    {
        abort_unless($request->user()->canManageAppointments(), 403);
        $data = $request->validate([
            'service_label' => ['required', 'string', 'max:160'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'source_reference' => ['nullable', 'string', 'max:80'],
        ]);
        try {
            $appointment = $importer->import(
                $data['service_label'],
                (int) $data['staff_id'],
                $data['date'],
                $data['start_time'],
                $data['end_time'],
                $data['source_reference'] ?? null,
                $request->user(),
            );
        } catch (BookingConflict $exception) {
            throw ValidationException::withMessages(['start_time' => $exception->getMessage()]);
        }

        return back()->with('success', 'Existing appointment '.$appointment->public_reference.' added to the shared calendar.');
    }
}
