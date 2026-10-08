    @if ($canManage)
        <section class="mt-8 card p-6" aria-labelledby="customer-lookup-heading">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="eyebrow">Customer records</p>
                    <h2 id="customer-lookup-heading" class="mt-2 font-display text-2xl">Find a customer</h2>
                    <p class="mt-1 text-sm text-muted-ink">Search by the exact phone number or email used for an appointment.</p>
                </div>
                @if ($hasCustomerLookup)
                    <a class="text-sm underline" href="{{ route('admin.customers.clear-search') }}">Clear results</a>
                @endif
            </div>
            <form method="post" action="{{ route('admin.customers.search') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <label for="customer-contact" class="min-w-0 flex-1 text-sm">Phone or email
                    <input id="customer-contact" name="customer_contact" type="text" required maxlength="255" autocomplete="off" placeholder="Enter a phone number or email" class="mt-1 min-h-11 w-full rounded-card border border-line bg-surface px-3">
                </label>
                <button class="btn btn-secondary">Search customer</button>
            </form>
            @error('customer_contact')<p role="alert" class="mt-2 text-sm text-action">{{ $message }}</p>@enderror

            @if ($hasCustomerLookup)
                <div class="mt-5 space-y-4">
                    @forelse ($customerMatches as $customer)
                        <article class="rounded-card border border-line bg-surface p-4">
                            <h3 class="font-medium">{{ $customer->first_name }} {{ $customer->last_name }}</h3>
                            <p class="mt-1 text-sm text-muted-ink">{{ $customer->phone }}@if($customer->email) · {{ $customer->email }}@endif</p>
                            <h4 class="mt-4 text-sm font-medium">Appointment history</h4>
                            <ol class="mt-2 divide-y divide-line">
                                @forelse ($customer->appointments as $history)
                                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2 text-sm">
                                        <span>{{ $history->starts_at_utc->setTimezone($history->timezone)->format('D, M j, Y · g:i A') }} · {{ $history->items->pluck('name_snapshot')->join(', ') }}</span>
                                        <span class="text-muted-ink">{{ $history->staff?->name ?? 'Unassigned' }} · {{ str_replace('_', ' ', $history->status) }} · {{ $history->public_reference }}</span>
                                    </li>
                                @empty
                                    <li class="py-2 text-sm text-muted-ink">No appointments found.</li>
                                @endforelse
                            </ol>
                        </article>
                    @empty
                        <p class="rounded-card border border-line bg-surface p-4 text-sm">No customer matched that exact phone or email.</p>
                    @endforelse
                </div>
            @endif
        </section>
    @endif
    <section class="mt-8 card p-6" aria-labelledby="recent-customers-heading">
        <h2 id="recent-customers-heading" class="font-display text-2xl">Customer records</h2>
        <p class="mt-1 text-sm text-muted-ink">Customers who have booked with the studio, newest first.</p>
        @if ($recentCustomers->isEmpty())
            <p class="mt-5 text-sm text-muted-ink">No customer records yet.</p>
        @else
            <ul class="mt-5 divide-y divide-line">
                @foreach ($recentCustomers as $customer)
                    <li class="py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-medium">{{ $customer->first_name }} {{ $customer->last_name }}</h3>
                                <p class="mt-1 text-sm text-muted-ink">{{ $customer->phone }}@if ($customer->email) · {{ $customer->email }}@endif</p>
                            </div>
                            <span class="text-sm text-muted-ink">{{ $customer->appointments_count }} appointment{{ $customer->appointments_count === 1 ? '' : 's' }}</span>
                        </div>
                        @if ($customer->appointments->isNotEmpty())
                            <details class="mt-2 text-sm"><summary class="cursor-pointer underline">Recent appointments</summary>
                                <ol class="mt-2 divide-y divide-line">
                                    @foreach ($customer->appointments as $history)
                                        <li class="py-2"><a class="underline" href="{{ route('admin.index', ['date' => $history->starts_at_utc->setTimezone($history->timezone)->format('Y-m-d')]) }}#appointment-{{ $history->id }}">{{ $history->starts_at_utc->setTimezone($history->timezone)->format('D, M j · g:i A') }}</a> · {{ $history->items->pluck('name_snapshot')->join(', ') }} · {{ ucfirst(str_replace('_', ' ', $history->status)) }}</li>
                                    @endforeach
                                </ol>
                            </details>
                        @endif
                        <details class="mt-3 text-sm"><summary class="cursor-pointer underline">Studio notes</summary>
                            <form method="post" action="{{ route('admin.customers.notes', $customer) }}" class="mt-3 grid gap-2">
                                @csrf @method('PUT')
                                <label for="customer-notes-{{ $customer->id }}">Notes visible only to studio staff</label>
                                <textarea id="customer-notes-{{ $customer->id }}" name="admin_notes" rows="3" maxlength="2000" class="w-full rounded-card border border-line bg-surface p-3">{{ $customer->admin_notes }}</textarea>
                                <button class="btn btn-secondary justify-self-start">Save notes</button>
                            </form>
                        </details>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $recentCustomers->links() }}</div>
        @endif
    </section>
