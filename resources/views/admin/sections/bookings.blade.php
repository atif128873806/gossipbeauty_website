<div class="admin-page-actions flex flex-wrap gap-3">
    @if ($canManage)<a class="btn btn-primary" href="#add-appointment">Add appointment</a>@endif
    <a class="btn btn-secondary" href="#day-view">View calendar day</a>
</div>
<section class="mt-6 card p-5 md:p-6" aria-labelledby="booking-list-heading">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 id="booking-list-heading">Appointments</h2>
            <p class="mt-1 text-muted-ink">Toronto time · Select a booking to open its details.</p>
        </div>
        <form method="get" action="{{ route('admin.index') }}" class="admin-reference-search flex flex-wrap items-end gap-2">
            <label class="text-sm">Find by booking reference
                <input name="reference" value="{{ $reference }}" maxlength="40" placeholder="GB-..." class="mt-1 block min-h-11 w-full rounded-card border border-line bg-surface px-3">
            </label>
            <button class="btn btn-secondary">Find</button>
        </form>
    </div>
    <nav class="admin-list-tabs mt-6" aria-label="Appointment lists">
        <a href="{{ route('admin.index') }}" @if($listMode === 'upcoming') aria-current="page" @endif>Upcoming</a>
        <a href="{{ route('admin.index', ['view' => 'all']) }}" @if($listMode === 'all') aria-current="page" @endif>All bookings</a>
    </nav>
    @if ($listMode === 'upcoming')
        @if ($upcomingAppointments->isEmpty())
            <p class="py-6 text-muted-ink">No upcoming appointments are confirmed. Use All bookings to see past or cancelled visits.</p>
        @else
            <ol class="divide-y divide-line">
                @foreach ($upcomingAppointments as $upcoming)
                    <li class="admin-booking-row py-4">
                        <div class="admin-booking-time">{{ $upcoming->starts_at_utc->setTimezone($upcoming->timezone)->format('D, M j') }}<span>{{ $upcoming->starts_at_utc->setTimezone($upcoming->timezone)->format('g:i A') }}</span></div>
                        <div class="min-w-0"><p class="font-semibold">{{ $upcoming->customer?->first_name }} {{ $upcoming->customer?->last_name }}</p><p class="mt-1">{{ $upcoming->items->pluck('name_snapshot')->join(', ') }}</p><p class="mt-1 text-sm text-muted-ink">{{ $upcoming->staff?->name ?? 'Unassigned' }} · {{ $upcoming->public_reference }}</p></div>
                        <a class="admin-row-link" href="{{ route('admin.index', ['date' => $upcoming->starts_at_utc->setTimezone($upcoming->timezone)->format('Y-m-d')]) }}#appointment-{{ $upcoming->id }}">Open booking</a>
                    </li>
                @endforeach
            </ol>
            <div class="mt-4">{{ $upcomingAppointments->links() }}</div>
        @endif
    @else
        @if ($recentAppointments->isEmpty())
            <p class="py-6 text-muted-ink">{{ $reference !== '' ? 'No booking matched that reference. Check the code and try again.' : 'No bookings found.' }}</p>
        @else
            <ol class="divide-y divide-line">
                @foreach ($recentAppointments as $recent)
                    <li class="admin-booking-row py-4">
                        <div class="admin-booking-time">{{ $recent->starts_at_utc->setTimezone($recent->timezone)->format('D, M j') }}<span>{{ $recent->starts_at_utc->setTimezone($recent->timezone)->format('g:i A') }}</span></div>
                        <div class="min-w-0"><p class="font-semibold">{{ $recent->customer?->first_name }} {{ $recent->customer?->last_name }}</p><p class="mt-1">{{ $recent->items->pluck('name_snapshot')->join(', ') }}</p><p class="mt-1 text-sm text-muted-ink">{{ $recent->staff?->name ?? 'Unassigned' }} · {{ $recent->public_reference }} · {{ ucfirst(str_replace('_', ' ', $recent->status)) }}</p></div>
                        <a class="admin-row-link" href="{{ route('admin.index', ['date' => $recent->starts_at_utc->setTimezone($recent->timezone)->format('Y-m-d')]) }}#appointment-{{ $recent->id }}">Open booking</a>
                    </li>
                @endforeach
            </ol>
            <div class="mt-4">{{ $recentAppointments->links() }}</div>
        @endif
    @endif
</section>
    <section id="day-view" class="mt-10 flex scroll-mt-24 flex-wrap items-end justify-between gap-4">
        <div>
            <h2>{{ $day->format('l, F j, Y') }}</h2>
            <p class="mt-1 text-muted-ink">Calendar day · Toronto time</p>
        </div>
        <form method="get" action="{{ route('admin.index') }}" class="flex flex-wrap items-end gap-2">
            <label class="text-sm">Calendar day<input type="date" name="date" value="{{ $day->format('Y-m-d') }}" class="mt-1 block min-h-11 rounded-card border border-line bg-surface px-3"></label>
            <button class="btn btn-secondary">View day</button>
        </form>
    </section>
    <div class="admin-day-summary mt-5" aria-label="Day summary">
        <span><strong>{{ $appointments->count() }}</strong> appointments</span>
        <span><strong>{{ $appointments->where('status', 'confirmed')->count() }}</strong> confirmed</span>
        <span>Online booking <strong>{{ $configuration->online_booking_enabled ? 'open' : 'paused' }}</strong></span>
    </div>
    <section class="mt-6" aria-label="Appointments for selected day">
        @if ($appointments->isEmpty())
            <div class="mt-4 rounded-card border border-line bg-surface p-8"><p class="font-display text-2xl">Nothing booked for this day</p><p class="mt-2 text-muted-ink">New website appointments and staff bookings appear here.</p></div>
        @else
            <ol class="mt-4 space-y-3">
                @foreach ($appointments as $appointment)
                    <li id="appointment-{{ $appointment->id }}" class="card scroll-mt-24 p-5">
                        <div class="grid gap-4 md:grid-cols-[8rem_1fr_auto] md:items-start">
                            <div><p class="admin-appointment-time">{{ $appointment->starts_at_utc->setTimezone($appointment->timezone)->format('g:i A') }}</p><p class="mt-1 text-sm text-muted-ink">{{ $appointment->duration_minutes }} min</p></div>
                            <div>
                                <div class="flex flex-wrap items-center gap-3"><h3>@if($appointment->origin === 'dikidi' && $appointment->customer === null)Existing DIKIDI reservation@else{{ $appointment->customer?->first_name }} {{ $appointment->customer?->last_name }}@endif</h3><span class="admin-status">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</span></div>
                                <p class="mt-1 font-medium">{{ $appointment->items->pluck('name_snapshot')->join(', ') }}</p>
                                <dl class="admin-appointment-facts mt-3 text-sm">
                                    <div><dt>Professional</dt><dd>{{ $appointment->staff?->name ?? 'Unassigned' }}</dd></div>
                                    <div><dt>Reference</dt><dd>{{ $appointment->public_reference }}</dd></div>
                                    <div><dt>Booked through</dt><dd>{{ ucfirst($appointment->origin) }}</dd></div>
                                    @if($appointment->external_reference)<div><dt>Source reference</dt><dd>{{ $appointment->external_reference }}</dd></div>@endif
                                    @if($appointment->customer?->phone)<div><dt>Phone</dt><dd><a class="underline" href="tel:{{ $appointment->customer->phone }}">{{ $appointment->customer->phone }}</a></dd></div>@endif
                                    @if($appointment->customer?->email)<div><dt>Email</dt><dd><a class="underline" href="mailto:{{ $appointment->customer->email }}">{{ $appointment->customer->email }}</a></dd></div>@endif
                                </dl>
                                @if ($appointment->customer_note)<p class="mt-2 text-sm">Customer note: {{ $appointment->customer_note }}</p>@endif
                                @foreach($appointment->notificationDeliveries as $delivery)
                                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm {{ $delivery->status === 'failed' ? 'text-action' : 'text-muted-ink' }}">
                                        <span>Email {{ str_replace('_', ' ', $delivery->notification_type) }}: {{ $delivery->status }} · {{ $delivery->attempts }} attempt{{ $delivery->attempts === 1 ? '' : 's' }}</span>
                                        @if($delivery->status === 'failed' && ($canManage || auth()->user()->staff_id === $appointment->staff_id))
                                            <form method="post" action="{{ route('admin.appointments.notifications.retry', [$appointment, $delivery]) }}">@csrf<button class="underline">Retry email</button></form>
                                        @endif
                                    </div>
                                @endforeach
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-sm underline">Booking details and change history</summary>
                                    <div class="mt-3 rounded-card border border-line bg-canvas p-4">
                                        @php
                                            $appointmentPrice = match ($appointment->price_mode) {
                                                'fixed' => '$'.number_format(((int) $appointment->price_minor) / 100, 2),
                                                'from' => 'From $'.number_format(((int) $appointment->price_minor) / 100, 2),
                                                'free' => 'No charge',
                                                default => 'Price confirmed at consultation',
                                            };
                                        @endphp
                                        <p class="text-sm">Booked price: <span class="font-medium">{{ $appointmentPrice }}</span> · {{ $appointment->duration_minutes }} minutes</p>
                                        <ul class="mt-2 space-y-1 text-sm">
                                            @foreach($appointment->items as $item)
                                                @php
                                                    $itemPrice = match ($item->price_mode_snapshot) {
                                                        'fixed' => '$'.number_format(((int) $item->price_snapshot_minor) / 100, 2),
                                                        'from' => 'From $'.number_format(((int) $item->price_snapshot_minor) / 100, 2),
                                                        'free' => 'No charge',
                                                        default => 'Price confirmed at consultation',
                                                    };
                                                @endphp
                                                <li>{{ $item->name_snapshot }} · {{ $item->duration_snapshot_minutes }} min · {{ $itemPrice }}</li>
                                            @endforeach
                                        </ul>
                                        <h4 class="mt-4 text-sm font-medium">Activity</h4>
                                        <ol class="mt-1 divide-y divide-line">
                                            @foreach($appointment->events as $event)
                                                <li class="py-2 text-sm">{{ str_replace('.', ' · ', $event->event_type) }} · {{ $event->actor?->name ?? ucfirst($event->actor_type) }} · {{ $event->created_at->setTimezone($appointment->timezone)->format('M j, Y · g:i A') }}</li>
                                            @endforeach
                                        </ol>
                                    </div>
                                </details>
                                @if (in_array($appointment->status, ['confirmed', 'pending'], true) && ($canManage || auth()->user()->staff_id === $appointment->staff_id) && ($appointment->origin !== 'dikidi' || $appointment->items->contains(fn ($item) => $item->service_id !== null)))
                                    <details class="mt-3"><summary class="cursor-pointer text-sm underline">Reschedule appointment</summary>
                                        <form method="post" action="{{ route('admin.appointments.reschedule', $appointment) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf @method('PATCH')
                                            <label class="text-xs">Date<input type="date" name="date" required class="mt-1 min-h-10 rounded-card border border-line px-2"></label>
                                            <label class="text-xs">Time<input type="time" name="time" required class="mt-1 min-h-10 rounded-card border border-line px-2"></label>
                                            @if($canManage)<label class="text-xs">Professional<select name="staff_id" class="mt-1 min-h-10 rounded-card border border-line px-2"><option value="">Any eligible</option>@foreach($staff->where('is_bookable',true) as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>@endif
                                            <button class="btn btn-secondary">Save new time</button>
                                        </form>
                                    </details>
                                @elseif($appointment->origin === 'dikidi' && $appointment->items->isEmpty() === false && $appointment->items->every(fn ($item) => $item->service_id === null))
                                    <p class="mt-3 text-sm text-muted-ink">Match this reservation to a current service before rescheduling it.</p>
                                @endif
                            </div>
                            @if (in_array($appointment->status, ['confirmed', 'pending'], true) && ($canManage || auth()->user()->staff_id === $appointment->staff_id))
                                <div class="flex flex-wrap gap-2">
                                    <form method="post" action="{{ route('admin.appointments.status', $appointment) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="btn btn-secondary">Complete</button></form>
                                    <form method="post" action="{{ route('admin.appointments.status', $appointment) }}" onsubmit="return confirm('Cancel this appointment? A reason is required.')">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><input type="hidden" name="reason" value="Cancelled by studio"><button class="btn btn-quiet">Cancel</button></form>
                                    <form method="post" action="{{ route('admin.appointments.status', $appointment) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="no_show"><button class="btn btn-quiet">No show</button></form>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@if ($canManage)
<section id="add-appointment" class="mt-12 scroll-mt-24">
            <div class="card p-6">
                <p class="eyebrow">Phone & reception</p><h2 class="mt-2 font-display text-2xl">Add an appointment</h2>
                <p class="mt-2 text-sm text-muted-ink">Uses the same eligibility, hours, resource and overlap checks as website bookings.</p>
                <form method="post" action="{{ route('admin.appointments.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
                    <label class="text-sm">Service<select name="service_id" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3">@foreach ($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select></label>
                    <label class="text-sm">Professional<select name="staff_id" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3">@foreach ($staff->where('is_active', true)->where('is_bookable', true) as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
                    <label class="text-sm sm:col-span-2">Extras (optional)<select name="add_on_ids[]" multiple class="mt-1 min-h-24 w-full rounded-card border border-line bg-surface px-3">@foreach($addOns->where('is_active',true) as $addOn)<option value="{{ $addOn->id }}">{{ $addOn->name }}</option>@endforeach</select></label>
                    <label class="text-sm">Date<input name="date" type="date" required value="{{ $day->format('Y-m-d') }}" class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                    <label class="text-sm">Start time<input name="time" type="time" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                    <label class="text-sm">First name<input name="first_name" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                    <label class="text-sm">Last name<input name="last_name" class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                    <label class="text-sm">Phone<input name="phone" type="tel" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                    <label class="text-sm">Email<input name="email" type="email" class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                    <button class="btn btn-primary sm:col-span-2">Confirm appointment</button>
                </form>
                <details class="mt-6 border-t border-line pt-5">
                    <summary class="cursor-pointer font-medium">Add an existing DIKIDI appointment</summary>
                    <p class="mt-2 text-sm text-muted-ink">Record each upcoming reservation before cutover. Enter its service label, assigned professional and exact time range. Customer details and price are not imported; use only a non-sensitive DIKIDI appointment ID as the optional reference.</p>
                    <form method="post" action="{{ route('admin.appointments.import-existing') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                        <label class="text-sm sm:col-span-2">Service as listed<input name="service_label" required maxlength="160" class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                        <label class="text-sm sm:col-span-2">Professional<select name="staff_id" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3">@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}{{ $person->is_active ? '' : ' (inactive)' }}</option>@endforeach</select></label>
                        <label class="text-sm">Date<input name="date" type="date" required value="{{ $day->format('Y-m-d') }}" class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                        <label class="text-sm">DIKIDI appointment ID (optional)<input name="source_reference" maxlength="80" class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                        <label class="text-sm">Start time<input name="start_time" type="time" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                        <label class="text-sm">End time<input name="end_time" type="time" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
                        <button class="btn btn-secondary sm:col-span-2">Add reservation to calendar</button>
                    </form>
                </details>
            </div>
</section>
@endif
