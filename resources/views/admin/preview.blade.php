@extends('layouts.admin')

@section('title', 'Today — studio panel — Gossips Beauty')

@section('content')
    @php
        // Status styling: a colour on the block's left edge plus the status written out in
        // every block, so meaning is never carried by colour alone (02, 06).
        $edge = [
            'confirmed' => 'border-l-action',
            'completed' => 'border-l-ink',
            'cancelled_by_customer' => 'border-l-line',
            'cancelled_by_salon' => 'border-l-line',
            'no_show' => 'border-l-accent',
            'blocked' => 'border-l-line',
        ];

        $surface = [
            'confirmed' => 'bg-surface',
            'completed' => 'bg-surface',
            'cancelled_by_customer' => 'bg-canvas text-muted-ink',
            'cancelled_by_salon' => 'bg-canvas text-muted-ink',
            'no_show' => 'bg-surface',
            'blocked' => 'bg-surface-soft',
        ];

        $bookedLabel = \App\Content\Catalog::durationLabel(['duration_minutes' => $statistics['booked_minutes']]);
        $openLabel = \App\Content\Catalog::durationLabel(['duration_minutes' => $statistics['open_minutes']]);
    @endphp

    <section class="border-b border-line bg-canvas">
        <div class="mx-auto max-w-content px-4 py-10 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
                <div>
                    <h1 class="font-display text-4xl leading-tight text-ink sm:text-5xl">Today</h1>

                    <p class="mt-2 text-base text-ink">
                        {{ $dayLabel }}
                        <span class="text-muted-ink">· times shown in Toronto time</span>
                    </p>
                </div>

                {{-- Read as labels, not as working controls: nothing on this screen acts. --}}
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" disabled aria-disabled="true"
                            class="inline-flex min-h-11 cursor-not-allowed items-center rounded-card border border-line bg-surface px-5 text-base text-muted-ink">
                        New appointment
                    </button>
                    <button type="button" disabled aria-disabled="true"
                            class="inline-flex min-h-11 cursor-not-allowed items-center rounded-card border border-line bg-surface px-5 text-base text-muted-ink">
                        Block time
                    </button>

                    <p class="w-full text-sm text-muted-ink">
                        These actions are shown for layout only. The working panel performs them,
                        with authentication and permissions; this preview performs nothing.
                    </p>
                </div>
            </div>

            <dl class="mt-8 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-6">
                <div>
                    <dt class="text-sm text-muted-ink">Appointments</dt>
                    <dd class="font-display text-2xl text-ink">{{ $statistics['appointments'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-ink">Confirmed</dt>
                    <dd class="font-display text-2xl text-ink">{{ $statistics['confirmed'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-ink">Completed</dt>
                    <dd class="font-display text-2xl text-ink">{{ $statistics['completed'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-ink">Cancelled</dt>
                    <dd class="font-display text-2xl text-ink">{{ $statistics['cancelled'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-ink">No show</dt>
                    <dd class="font-display text-2xl text-ink">{{ $statistics['no_show'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-ink">Booked</dt>
                    <dd class="font-display text-2xl text-ink">{{ $bookedLabel }}</dd>
                    <dd class="text-sm text-muted-ink">{{ $statistics['utilisation'] }}% of the day</dd>
                </div>
            </dl>

            <p class="mt-6 max-w-3xl text-base text-ink">
                <span class="font-medium">Needs attention:</span>
                one confirmation email has not been delivered yet. The working panel would show
                the failure, retry it, and keep the appointment valid in the meantime.
            </p>
        </div>
    </section>

    {{-- Day view. A time grid on wide screens, an agenda per service area on small ones. --}}
    <section class="border-b border-line bg-canvas py-10 lg:py-12">
        <div class="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-2xl text-ink">Day view</h2>

            <p class="mt-2 max-w-3xl text-base text-muted-ink">
                Columns are service areas rather than named team members, because the team's
                names and eligibility are not confirmed on the site yet.
            </p>

            {{-- Wide screens: the shape the working calendar will take. --}}
            <div class="mt-8 hidden lg:grid lg:grid-cols-[4rem_repeat(3,minmax(0,1fr))] lg:gap-x-4">
                {{-- Hour scale. --}}
                <div class="relative" style="height: {{ $gridHeight }}px" aria-hidden="true">
                    @foreach ($hourMarks as $mark)
                        <span class="absolute right-2 -translate-y-1/2 text-xs text-muted-ink"
                              style="top: {{ \App\Content\PanelDay::offset($mark) }}px">
                            {{ $mark }}
                        </span>
                    @endforeach
                </div>

                @foreach ($columns as $column)
                    @php($columnBlocks = \App\Content\PanelDay::blocksFor($column['key']))

                    <div>
                        <h3 class="font-display text-xl text-ink">
                            {{ $column['label'] }}
                            <span class="ml-2 font-sans text-sm text-muted-ink">
                                {{ count($columnBlocks) }} bookings
                            </span>
                        </h3>

                        <div class="relative mt-3 rounded-card border border-line bg-canvas"
                             style="height: {{ $gridHeight }}px">
                            @foreach ($hourMarks as $mark)
                                <div class="absolute inset-x-0 border-t border-line"
                                     style="top: {{ \App\Content\PanelDay::offset($mark) }}px"
                                     aria-hidden="true"></div>
                            @endforeach

                            <ol>
                                @foreach ($columnBlocks as $block)
                                    <li class="absolute inset-x-1 rounded-card border border-line border-l-4 p-2 text-xs leading-tight shadow-none {{ $edge[$block['status']] }} {{ $surface[$block['status']] }}"
                                        style="top: {{ $block['top'] }}px; height: {{ $block['height'] }}px">
                                        <p class="font-medium">
                                            {{ $block['time_label'] }}
                                            @unless ($block['is_block'])
                                                <span class="font-normal">· {{ $block['status_label'] }}</span>
                                            @endunless
                                        </p>

                                        <p class="mt-0.5">{{ $block['name'] }}</p>

                                        @unless ($block['is_compact'])
                                            <p class="mt-0.5 text-muted-ink">
                                                @if ($block['visit'] !== '')
                                                    {{ $block['visit'] }} ·
                                                @endif
                                                {{ $block['duration'] }}
                                                @if ($block['price'] !== '')
                                                    · {{ $block['price'] }}
                                                @endif
                                            </p>
                                        @endunless
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Small screens: the same day, read as a list per service area. --}}
            <div class="mt-8 space-y-6 lg:hidden">
                @foreach ($columns as $column)
                    @php($columnBlocks = \App\Content\PanelDay::blocksFor($column['key']))

                    <section class="rounded-card border border-line bg-surface p-5">
                        <h3 class="font-display text-xl text-ink">{{ $column['label'] }}</h3>

                        <ul class="mt-3 divide-y divide-line">
                            @foreach ($columnBlocks as $block)
                                <li class="py-3">
                                    <p class="text-sm text-muted-ink">
                                        {{ $block['time_label'] }}
                                        @unless ($block['is_block'])
                                            · {{ $block['status_label'] }}
                                        @endunless
                                    </p>

                                    <p class="mt-1 text-base font-medium text-ink">{{ $block['name'] }}</p>

                                    <p class="mt-1 text-sm text-muted-ink">
                                        @if ($block['visit'] !== '')
                                            {{ $block['visit'] }} ·
                                        @endif
                                        {{ $block['duration'] }}
                                        @if ($block['price'] !== '')
                                            · {{ $block['price'] }}
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>

            {{-- Legend: the status words that appear on every block. --}}
            <div class="mt-8 rounded-card border border-line bg-surface p-5">
                <h3 class="text-base font-medium text-ink">Status</h3>

                <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
                    @foreach ($statuses as $key => $label)
                        <li class="flex items-center gap-2 text-sm text-muted-ink">
                            <span class="inline-block h-4 w-4 rounded-sm border border-line border-l-4 {{ $edge[$key] }}"
                                  aria-hidden="true"></span>
                            {{ $label }}
                        </li>
                    @endforeach
                </ul>

                <p class="mt-3 text-sm text-muted-ink">
                    Every block also writes its status in words, so colour is never the only signal.
                </p>
            </div>
        </div>
    </section>

    {{-- What the working panel would add, so the client knows this is not the full product. --}}
    <section class="bg-canvas py-10">
        <div class="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-2xl text-ink">What the working panel adds</h2>

            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li class="rounded-card border border-line bg-surface p-5">
                    <p class="text-base font-medium text-ink">Real appointments</p>
                    <p class="mt-2 text-base text-muted-ink">
                        Bookings made on the website or by phone, stored and confirmed by the
                        studio rather than shown as a preview.
                    </p>
                </li>
                <li class="rounded-card border border-line bg-surface p-5">
                    <p class="text-base font-medium text-ink">Team and opening hours</p>
                    <p class="mt-2 text-base text-muted-ink">
                        Named specialists, their shifts, breaks, leave and which services each one
                        takes, once the studio confirms them.
                    </p>
                </li>
                <li class="rounded-card border border-line bg-surface p-5">
                    <p class="text-base font-medium text-ink">Access and records</p>
                    <p class="mt-2 text-base text-muted-ink">
                        Sign-in with per-role permissions, plus a history of who changed what and
                        when for every booking.
                    </p>
                </li>
            </ul>
        </div>
    </section>
@endsection
