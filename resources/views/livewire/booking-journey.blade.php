@php
    $steps = $this->steps();
    $currentIndex = array_search($this->step, $steps, true);
    $labels = $this->stepLabels();
    $service = $this->selectedService();
    $summary = $this->summary();
    $showSummary = $this->confirmationReference === null && $service !== null && in_array($this->step, ['professional', 'time', 'review'], true);
@endphp

<div class="space-y-8">

    {{-- Progress: which step is current and which are already chosen (02). --}}
    <nav aria-label="Booking progress">
        <ol class="flex flex-wrap gap-x-6 gap-y-3">
            @foreach ($steps as $index => $name)
                @php
                    $isCurrent = $index === $currentIndex;
                    $isDone = $index < $currentIndex;
                @endphp

                <li class="flex items-center gap-2" @if ($isCurrent) aria-current="step" @endif>
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-card border text-sm {{ $isDone || $isCurrent ? 'border-action bg-action text-on-action' : 'border-line bg-surface text-muted-ink' }}">
                        {{ $index + 1 }}
                    </span>

                    <span class="{{ $isCurrent ? 'font-medium text-ink' : 'text-muted-ink' }}">
                        {{ $labels[$name] }}
                    </span>

                    @if ($isDone)
                        <span class="text-sm text-muted-ink">Chosen</span>
                    @endif

                    @if ($isCurrent)
                        <span class="sr-only">(current step)</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    @if ($conflict !== null)
        <p role="alert" class="rounded-card border border-action bg-surface p-4 text-base text-ink">
            {{ $conflict }}
        </p>
    @endif

    {{--
        Source order is panels → summary → actions, so on a phone the summary sits above
        the final action as the design system requires, while on wide screens the summary
        takes the third column and the actions sit under the panels.
    --}}
    <div class="lg:grid lg:grid-cols-3 lg:items-start lg:gap-10">
        <div class="space-y-6 lg:col-span-2">

            {{--
                The editable steps disappear once the summary is shown: the outcome
                already recaps everything, so leaving the review form underneath it would
                invite a customer to confirm a second time.
            --}}
            @unless ($this->confirmationReference !== null)

            {{-- 1. Service ------------------------------------------------------ --}}
            @if ($this->step === 'service')
                <section aria-labelledby="booking-step-service" class="card p-6">
                    <h2 id="booking-step-service" class="font-display text-2xl text-ink">Choose your service</h2>

                    <p class="mt-3 text-base text-muted-ink">
                        Prices show either a fixed amount or a “from” price where the final amount
                        depends on length or density.
                    </p>

                    @if ($service !== null)
                        <div class="mt-5 rounded-card border border-line bg-canvas p-5">
                            <p class="text-sm font-medium uppercase tracking-wide text-muted-ink">
                                {{ $service['category'] }}
                            </p>

                            <p class="mt-1 font-display text-2xl text-ink">{{ $service['name'] }}</p>

                            <p class="mt-2 text-base text-ink">{{ $service['summary'] }}</p>

                            <dl class="mt-3 flex flex-wrap items-baseline gap-x-6 gap-y-2 text-base">
                                <div class="flex items-baseline gap-2">
                                    <dt class="text-sm text-muted-ink">Price</dt>
                                    <dd class="font-medium text-ink">{{ $this->priceLines()[0]['price'] ?? 'By consultation' }}</dd>
                                </div>

                                @php($serviceDuration = $this->durationLabel())
                                @if ($serviceDuration !== null)
                                    <div class="flex items-baseline gap-2">
                                        <dt class="text-sm text-muted-ink">Time</dt>
                                        <dd class="text-ink">{{ $serviceDuration }}</dd>
                                    </div>
                                @endif
                            </dl>

                            @if (! empty($service['price_note']))
                                <p class="mt-2 text-sm text-muted-ink">{{ $service['price_note'] }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="mt-6">
                        <label for="booking-service" class="block text-base font-medium text-ink">Service</label>

                        <select id="booking-service"
                                wire:model.live="serviceSlug"
                                class="mt-2 block min-h-11 w-full rounded-card border border-line bg-surface px-4 text-base text-ink">
                            <option value="">Choose a service</option>

                            @foreach ($categories as $category)
                                <optgroup label="{{ $category['name'] }}">
                                    @foreach ($category->services as $option)
                                        <option value="{{ $option['slug'] }}">
                                            {{ $option['name'] }} — {{ $this->priceLabel($option['price_mode'], $option['price_minor']) }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>

                        @error('serviceSlug')
                            <p role="alert" class="mt-2 text-base text-ink">{{ $message }}</p>
                        @enderror

                        <p class="mt-3 text-base text-muted-ink">
                            <a href="{{ route('services') }}" class="text-action underline underline-offset-4 hover:text-action-hover">
                                Browse the full menu with prices and times
                            </a>
                        </p>
                    </div>
                </section>
            @endif

            {{-- 2. Extras ------------------------------------------------------- --}}
            @if ($this->step === 'extras')
                <section aria-labelledby="booking-step-extras" class="card p-6">
                    <h2 id="booking-step-extras" class="font-display text-2xl text-ink">Add extras</h2>

                    <p class="mt-3 text-base text-muted-ink">
                        Removal and design are charged separately from the service and change how
                        long the appointment takes. Choose only what you need.
                    </p>

                    <div class="mt-5 space-y-3">
                        @foreach ($this->availableAddOns() as $addOn)
                            <div class="flex items-start gap-3 rounded-card border border-line bg-canvas p-4">
                                <input id="booking-add-on-{{ $addOn['slug'] }}"
                                       type="checkbox"
                                       value="{{ $addOn['slug'] }}"
                                       wire:model.live="addOnSlugs"
                                       class="mt-1 h-6 w-6 shrink-0 rounded border-line">

                                <label for="booking-add-on-{{ $addOn['slug'] }}" class="flex-1 cursor-pointer">
                                    <span class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                        <span class="text-base font-medium text-ink">{{ $addOn['name'] }}</span>
                                        <span class="text-base text-ink">
                                            {{ $this->priceLabel($addOn['price_mode'], $addOn['price_minor']) }}
                                            @php($addOnDuration = $addOn['duration_minutes'] ? $addOn['duration_minutes'].' min' : null)
                                            @if ($addOnDuration !== null)
                                                <span class="text-muted-ink"> · {{ $addOnDuration }}</span>
                                            @endif
                                        </span>
                                    </span>

                                    @if (! empty($addOn['note']))
                                        <span class="mt-1 block text-sm text-muted-ink">{{ $addOn['note'] }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>

                    {{-- Time and price basis recompute on every tick, not only at the end (05 step 2). --}}
                    <p class="mt-5 text-base text-ink" role="status">
                        Appointment length so far:
                        <span class="font-medium">{{ $this->durationLabel() }}</span>
                        <span class="text-muted-ink"> · </span>
                        {{ $summary['label'] }}:
                        <span class="font-medium">{{ $summary['value'] }}</span>
                    </p>
                </section>
            @endif

            {{-- 3. Professional ------------------------------------------------- --}}
            @if ($this->step === 'professional')
                <section aria-labelledby="booking-step-professional" class="card p-6">
                    <h2 id="booking-step-professional" class="font-display text-2xl text-ink">Your professional</h2>

                    <p class="mt-3 text-base text-muted-ink">
                        Choose a professional or let the studio assign the first eligible one with an available time.
                    </p>

                    <fieldset class="mt-5">
                        <legend class="sr-only">Professional</legend>

                        <div class="space-y-3">
                            @foreach ($this->professionalOptions() as $value => $label)
                                <label class="flex items-center gap-3 rounded-card border border-line bg-canvas p-4">
                                    <input type="radio"
                                           name="booking-professional"
                                           value="{{ $value }}"
                                           wire:model.live="professional"
                                           class="h-6 w-6 shrink-0 border-line">

                                    <span class="text-base text-ink">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </section>
            @endif

            {{-- 4. Time --------------------------------------------------------- --}}
            @if ($this->step === 'time')
                <section aria-labelledby="booking-step-time" class="card p-6">
                    <h2 id="booking-step-time" class="font-display text-2xl text-ink">Pick a time</h2>

                    <p class="mt-3 text-base text-muted-ink">
                        Times are shown in Toronto time. A time is confirmed only after you submit your details and the calendar accepts the booking.
                    </p>

                    @if (! \App\Models\BookingConfiguration::current()->online_booking_enabled)
                        <p class="mt-5 max-w-2xl text-base text-ink">Online booking is being prepared. Please contact the studio to arrange an appointment.</p>
                    @elseif ($this->totalMinutes() === null)
                        <p class="mt-5 max-w-2xl text-base text-ink">
                            This service has no confirmed appointment length, so no times are shown.
                            The studio confirms the length with you.
                        </p>
                    @else
                        <p class="mt-3 text-base text-ink">
                            This appointment needs
                            <span class="font-medium">{{ $this->durationLabel() }}</span>.
                        </p>

                        @if ($this->dates() === [])
                            <p class="mt-5 max-w-2xl text-base text-ink">
                                No days are open in the next two weeks for a service this long. Browse the
                                services and come back, or start with a shorter service.
                            </p>
                        @else
                            <div class="mt-5">
                                <h3 class="text-base font-medium text-ink">Day</h3>

                                <ul class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($this->dates() as $day)
                                        <li>
                                            <button type="button"
                                                    wire:click="selectDate('{{ $day['value'] }}')"
                                                    aria-pressed="{{ $this->date === $day['value'] ? 'true' : 'false' }}"
                                                    class="chip">
                                                {{ $day['label'] }}
                                                @if ($day['is_today'])
                                                    <span class="ml-1 text-sm">(today)</span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            @if ($this->date !== null)
                                <div class="mt-6">
                                    <h3 class="text-base font-medium text-ink">Time</h3>

                                    @if ($this->slots() === [])
                                        <p role="status" class="mt-2 max-w-2xl text-base text-ink">
                                            No time on this day fits the appointment. Try another day.
                                        </p>
                                    @else
                                        <ul class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($this->slots() as $slot)
                                                <li>
                                                    <button type="button"
                                                            wire:click="selectTime('{{ $slot['value'] }}')"
                                                            aria-pressed="{{ $this->time === $slot['value'] ? 'true' : 'false' }}"
                                                            class="chip">
                                                        {{ $slot['label'] }}
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif
                        @endif
                    @endif

                    @error('time')
                        <p role="alert" class="mt-4 text-base text-ink">{{ $message }}</p>
                    @enderror
                </section>
            @endif

            {{-- 5. Review ------------------------------------------------------- --}}
            @if ($this->step === 'review')
                <section aria-labelledby="booking-step-review" class="card p-6">
                    <h2 id="booking-step-review" class="font-display text-2xl text-ink">Check your appointment</h2>

                    <p class="mt-3 text-base text-muted-ink">
                        Review your choices and contact details. Submitting confirms the appointment if the selected time is still available.
                    </p>

                    <dl class="mt-5 divide-y divide-line rounded-card border border-line bg-canvas">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Service</dt>
                            <dd class="text-base font-medium text-ink">{{ $service['name'] ?? '—' }}</dd>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Extras</dt>
                            <dd class="text-base text-ink">
                                @forelse ($this->selectedAddOns() as $addOn)
                                    {{ $addOn['name'] }}{{ $loop->last ? '' : ', ' }}
                                @empty
                                    None chosen
                                @endforelse
                            </dd>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Professional</dt>
                            <dd class="text-base text-ink">{{ $this->professionalLabel() }}</dd>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Booked for</dt>
                            <dd class="text-base text-ink">{{ trim($this->firstName.' '.$this->lastName) }} · {{ $this->phone }}</dd>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Time</dt>
                            <dd class="text-base text-ink">{{ $this->timeLabel() ?? '—' }}</dd>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Appointment length</dt>
                            <dd class="text-base text-ink">{{ $this->durationLabel() ?? '—' }}</dd>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Studio</dt>
                            <dd class="text-base text-ink">{{ config('gossips.location.address_line') }}, {{ config('gossips.location.city') }}</dd>
                        </div>

                        @foreach ($this->priceLines() as $line)
                            <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                                <dt class="text-sm text-muted-ink">{{ $line['name'] }}</dt>
                                <dd class="text-base text-ink">{{ $line['price'] }}</dd>
                            </div>
                        @endforeach

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-base font-medium text-ink">{{ $summary['label'] }}</dt>
                            <dd class="text-base font-medium text-ink">{{ $summary['value'] }}</dd>
                        </div>
                    </dl>

                    @if ($summary['note'] !== null)
                        <p class="mt-3 text-sm text-muted-ink">{{ $summary['note'] }}</p>
                    @endif

                    <p class="mt-5 text-sm text-muted-ink">
                        A “from” price is a starting point, not a total, and anything shown as quoted
                        is confirmed at the studio.
                    </p>
                </section>
            @endif

            @if ($this->step === 'contact')
                <section aria-labelledby="booking-step-contact" class="card p-6">
                    <h2 id="booking-step-contact" class="font-display text-2xl text-ink">Your contact details</h2>
                    <p class="mt-3 text-base text-muted-ink">We use your phone number to match your customer record and manage this appointment.</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <label class="block text-base font-medium text-ink">First name
                            <input wire:model.blur="firstName" autocomplete="given-name" class="mt-2 min-h-11 w-full rounded-card border border-line bg-surface px-4" />
                            @error('firstName') <span role="alert" class="mt-1 block text-sm">{{ $message }}</span> @enderror
                        </label>
                        <label class="block text-base font-medium text-ink">Last name <span class="font-normal text-muted-ink">(optional)</span>
                            <input wire:model.blur="lastName" autocomplete="family-name" class="mt-2 min-h-11 w-full rounded-card border border-line bg-surface px-4" />
                            @error('lastName') <span role="alert" class="mt-1 block text-sm">{{ $message }}</span> @enderror
                        </label>
                        <label class="block text-base font-medium text-ink">Mobile phone
                            <input wire:model.blur="phone" type="tel" autocomplete="tel" class="mt-2 min-h-11 w-full rounded-card border border-line bg-surface px-4" />
                            @error('phone') <span role="alert" class="mt-1 block text-sm">{{ $message }}</span> @enderror
                        </label>
                        <label class="block text-base font-medium text-ink">Email <span class="font-normal text-muted-ink">(optional)</span>
                            <input wire:model.blur="email" type="email" autocomplete="email" class="mt-2 min-h-11 w-full rounded-card border border-line bg-surface px-4" />
                            @error('email') <span role="alert" class="mt-1 block text-sm">{{ $message }}</span> @enderror
                        </label>
                        <label class="block text-base font-medium text-ink sm:col-span-2">Note for the studio <span class="font-normal text-muted-ink">(optional)</span>
                            <textarea wire:model.blur="customerNote" rows="3" class="mt-2 w-full rounded-card border border-line bg-surface px-4 py-3"></textarea>
                            @error('customerNote') <span role="alert" class="mt-1 block text-sm">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </section>
            @endif

            @endunless

            {{-- 6. Outcome ------------------------------------------------------- --}}
            @if ($this->confirmationReference !== null)
                <section aria-labelledby="booking-step-done" class="card p-6">
                    <h2 id="booking-step-done" class="font-display text-2xl text-ink">
                        Appointment confirmed
                    </h2>

                    <p class="mt-3 max-w-2xl text-base text-ink">
                        Your appointment is confirmed. Save this reference for your records.
                    </p>
                    <p class="mt-4 text-lg font-medium text-ink">{{ $this->confirmationReference }}</p>
                    <p class="mt-2 text-base text-ink">{{ $this->confirmationTime }} with {{ $this->confirmationStaff }}</p>

                    <dl class="mt-5 divide-y divide-line rounded-card border border-line bg-canvas">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Service</dt>
                            <dd class="text-base font-medium text-ink">{{ $service['name'] ?? '—' }}</dd>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Extras</dt>
                            <dd class="text-base text-ink">
                                @forelse ($this->selectedAddOns() as $addOn)
                                    {{ $addOn['name'] }}{{ $loop->last ? '' : ', ' }}
                                @empty
                                    None chosen
                                @endforelse
                            </dd>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">Time</dt>
                            <dd class="text-base text-ink">{{ $this->timeLabel() ?? '—' }}</dd>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 p-4">
                            <dt class="text-sm text-muted-ink">{{ $summary['label'] }}</dt>
                            <dd class="text-base text-ink">{{ $summary['value'] }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="button"
                                wire:click="restart"
                                class="btn btn-primary">
                            Start again
                        </button>

                        <a href="{{ route('services') }}"
                           class="btn btn-secondary">
                            Services &amp; pricing
                        </a>

                        <a href="{{ route('home') }}"
                           class="btn btn-secondary">
                            Back to Home
                        </a>
                    </div>
                </section>
            @endif

        </div>

        {{-- Summary: what has been chosen so far, above the final action on mobile (02). --}}
        @if ($showSummary)
            <aside class="mt-10 rounded-card border border-line bg-surface-soft p-6 lg:mt-0 lg:col-span-1"
                   aria-labelledby="booking-summary">
                <h2 id="booking-summary" class="font-display text-xl text-ink">Your selection</h2>

                <dl class="mt-4 space-y-3 text-base">
                    <div>
                        <dt class="text-sm text-muted-ink">Service</dt>
                        <dd class="text-ink">{{ $service['name'] }}</dd>
                    </div>

                    @if ($this->selectedAddOns() !== [])
                        <div>
                            <dt class="text-sm text-muted-ink">Extras</dt>
                            <dd class="text-ink">
                                @foreach ($this->selectedAddOns() as $addOn)
                                    <span class="block">{{ $addOn['name'] }}</span>
                                @endforeach
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-sm text-muted-ink">Professional</dt>
                        <dd class="text-ink">{{ $this->professionalLabel() }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm text-muted-ink">Length</dt>
                        <dd class="text-ink">{{ $this->durationLabel() ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm text-muted-ink">Time</dt>
                        <dd class="text-ink">{{ $this->timeLabel() ?? 'Not chosen yet' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm text-muted-ink">{{ $summary['label'] }}</dt>
                        <dd class="font-medium text-ink">{{ $summary['value'] }}</dd>
                    </div>
                </dl>

                    <p class="mt-4 text-sm text-muted-ink">The appointment is not confirmed until the final step succeeds.</p>
            </aside>
        @endif

        <div class="lg:col-span-2">
            {{-- Step controls ---------------------------------------------------- --}}
            @if ($this->confirmationReference === null)
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    @if ($currentIndex > 0)
                        <button type="button"
                                wire:click="previousStep"
                                wire:loading.attr="disabled"
                                class="btn btn-secondary">
                            Back
                        </button>
                    @endif

                    @if ($this->step === 'review')
                        <button type="button"
                                wire:click="confirm"
                                wire:loading.attr="disabled"
                                class="btn btn-primary">
                            Confirm appointment
                        </button>
                    @else
                        <button type="button"
                                wire:click="nextStep"
                                wire:loading.attr="disabled"
                                class="btn btn-primary">
                            Continue
                        </button>
                    @endif

                    <span wire:loading class="text-base text-muted-ink">Working…</span>
                </div>
            @endif
        </div>
    </div>
</div>
