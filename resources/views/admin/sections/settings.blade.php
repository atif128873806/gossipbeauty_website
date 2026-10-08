    @if ($isOwner)
        <section class="mt-8 card p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="font-display text-2xl">Online booking</h2><p class="mt-1 text-sm text-muted-ink">Turning this on publishes eligible services and times from the same calendar used here.</p></div>
                <form method="post" action="{{ route('admin.booking.update') }}" class="flex items-center gap-3">@csrf @method('PATCH')
                    <input type="hidden" name="online_booking_enabled" value="{{ $configuration->online_booking_enabled ? 0 : 1 }}">
                    <button class="btn {{ $configuration->online_booking_enabled ? 'btn-secondary' : 'btn-primary' }}">{{ $configuration->online_booking_enabled ? 'Pause online booking' : 'Open online booking' }}</button>
                </form>
            </div>
        </section>
        <section class="mt-4 card p-5">
            <h2 class="font-display text-2xl">Booking rules</h2>
            <form method="post" action="{{ route('admin.booking.rules') }}" class="mt-4 grid gap-3 sm:grid-cols-4">@csrf @method('PUT')
                <label class="text-sm">Booking window (days)<input name="booking_horizon_days" type="number" min="1" max="365" value="{{ $configuration->booking_horizon_days }}" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                <label class="text-sm">Minimum notice (minutes)<input name="minimum_notice_minutes" type="number" min="0" max="43200" value="{{ $configuration->minimum_notice_minutes }}" class="mt-1 min-h-11 w-full rounded-card border border-line px-3"></label>
                <label class="text-sm">Start-time interval<select name="slot_increment_minutes" class="mt-1 min-h-11 w-full rounded-card border border-line px-3">@foreach([5,10,15,20,30,60] as $increment)<option value="{{ $increment }}" @selected($configuration->slot_increment_minutes===$increment)>{{ $increment }} minutes</option>@endforeach</select></label>
                <button class="btn btn-secondary self-end">Save rules</button>
            </form>
        </section>
    @endif
    <section class="mt-10 card p-5" aria-labelledby="profile-security-heading">
        <h2 id="profile-security-heading" class="font-display text-2xl">Your password</h2>
        <p class="mt-1 text-sm text-muted-ink">Use a unique password for studio access.</p>
        <form method="post" action="{{ route('admin.profile.password') }}" class="mt-4 grid gap-3 sm:grid-cols-3">
            @csrf @method('PUT')
            <label class="text-sm">Current password<input name="current_password" type="password" autocomplete="current-password" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
            <label class="text-sm">New password<input name="password" type="password" autocomplete="new-password" minlength="12" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
            <label class="text-sm">Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3"></label>
            <button class="btn btn-secondary sm:col-span-3 sm:justify-self-start">Update password</button>
        </form>
    </section>
