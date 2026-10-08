@extends('layouts.app')

@section('title', 'Services & pricing — Gossips Beauty, Etobicoke')
@section('meta_description', 'Nails, brows, lashes, hair and makeup services with prices and appointment times at Gossips Beauty, 649 The Queensway, Etobicoke. Book an appointment.')

@section('content')

    <x-editorial-opening eyebrow="Services"
                         title="The full"
                         accent="service menu."
                         lead="Nail care leads the menu, with brows, lashes, hair and makeup alongside it. Every price is in Canadian dollars. A card shows a fixed price, a “from” price where the amount depends on length or density, or a consultation quote. Times are the appointment length."
                         primary-image="work-dark"
                         secondary-image="work-colour"
                         primary-alt="Deep glossy manicure photographed against greenery."
                         secondary-alt="Colourful manicure with decorative accents."
                         note="Nail work in detail"
                         priority>
        <div class="flex flex-wrap gap-3">
            <a href="#service-menu" class="btn btn-primary">Browse services <span aria-hidden="true">↓</span></a>
            <a href="{{ route('booking') }}" class="btn btn-secondary">Book an appointment</a>
        </div>

        {{-- Category filters remain shareable links and work without JavaScript. --}}
        <nav aria-label="Service categories" class="editorial-opening-categories">
            <ul class="flex flex-wrap gap-2">
                <li>
                    <a href="{{ route('services') }}"
                       @if ($activeCategory === null) aria-current="true" @endif
                       class="chip font-medium">
                        All services
                    </a>
                </li>

                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('services', ['category' => $category['slug']]) }}"
                           @if ($activeCategory === $category['slug']) aria-current="true" @endif
                           class="chip">
                            {{ $category['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </x-editorial-opening>

    {{-- Results: grouped by category so the whole menu stays findable. --}}
    <section id="service-menu" class="story-section section border-b border-line bg-canvas">
        <div class="shell service-grid-shell">
            <form method="GET" action="{{ route('services') }}" class="mb-10 flex flex-col gap-3 sm:flex-row sm:items-end reveal">
                @if ($activeCategory !== null)
                    <input type="hidden" name="category" value="{{ $activeCategory }}">
                @endif

                <div class="w-full sm:max-w-sm">
                    <label for="service-search" class="block text-sm font-medium text-ink">
                        Search services
                    </label>
                    <input id="service-search"
                           type="search"
                           name="q"
                           value="{{ $query }}"
                           placeholder="Gel, highlights, lamination"
                           class="mt-2 block min-h-11 w-full rounded-card border border-line bg-surface px-4 text-base text-ink transition-colors duration-200 placeholder:text-muted-ink focus:border-action">
                </div>

                <button type="submit" class="btn btn-primary">Search</button>

                @if ($query !== null)
                    <a href="{{ route('services', $activeCategory === null ? [] : ['category' => $activeCategory]) }}"
                       class="link-underline inline-flex min-h-11 items-center">
                        Clear search
                    </a>
                @endif
            </form>

            @if ($groups === [])
                {{-- Empty state (QA in 08: no results is a designed state, not a blank page). --}}
                <h2 class="font-display text-3xl text-ink sm:text-4xl">No services match that search</h2>

                <p class="mt-4 max-w-2xl text-lg text-muted-ink">
                    Try a shorter word, or browse a category instead.
                </p>

                <ul class="mt-8 flex flex-wrap gap-2">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ route('services', ['category' => $category['slug']]) }}" class="chip">
                                {{ $category['name'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-8">
                    <a href="{{ route('services') }}" class="btn btn-secondary">Show every service</a>
                </p>
            @else
                <p class="text-base text-muted-ink" role="status">
                    @if ($query !== null)
                        {{ $serviceCount }} {{ Str::plural('service', $serviceCount) }} for “{{ $query }}”
                    @else
                        {{ $serviceCount }} {{ Str::plural('service', $serviceCount) }}
                    @endif
                </p>

                <div class="mt-12 space-y-16 lg:space-y-24">
                    @foreach ($groups as $group)
                        @php($groupCount = count($group['services']))

                        <div class="service-category">
                            <div class="category-hero grid items-end gap-6 lg:grid-cols-[1fr_0.8fr]">
                            {{-- Category story pairs the canonical menu with its configured photo. --}}
                            <div class="border-b border-line pb-5">
                                <h2 class="font-display text-4xl leading-tight text-ink sm:text-5xl reveal">
                                    {{ $group['category']['name'] }}
                                </h2>

                                <p class="mt-4 text-base text-muted-ink reveal" style="--reveal-delay: 60ms">
                                    {{ $group['category']['summary'] }}
                                </p>
                            </div>

                            <div class="category-image reveal" style="--reveal-delay: 100ms">
                                <x-photo :key="$group['category']['image']" alt="" class="aspect-[5/3]" sizes="(min-width: 1024px) 34vw, 92vw" />
                            </div>
                            </div>

                            <p class="mt-4 text-sm uppercase tracking-[0.14em] text-muted-ink reveal" style="--reveal-delay: 90ms">
                                {{ $groupCount }} {{ Str::plural('service', $groupCount) }} · Prices in CAD
                            </p>

                            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                                @foreach ($group['services'] as $service)
                                    <x-service-card :service="$service" :delay="$loop->index * 60" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Add-ons are separate records with their own price and time (04). --}}
    @if ($addOns !== [])
    <section class="story-section section band-surface-soft border-b border-line">
            <div class="shell">
                <x-section-heading eyebrow="Charged separately"
                                   title="Nail add-ons"
                                   lead="Removal and design are charged separately from the service and are not included in a manicure or pedicure price." />

                {{--
                    A two-column price list with a hairline under each row. It is not a
                    cell grid on purpose: with an odd number of add-ons a grid would leave
                    an empty filled cell at the end, which reads as a broken row.
                --}}
                <ul class="mt-10 grid max-w-4xl border-t border-line sm:grid-cols-2 sm:gap-x-16">
                    @foreach ($addOns as $addOn)
                        <li class="border-b border-line py-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                                <p class="text-base font-medium text-ink">{{ $addOn['name'] }}</p>

                                <p class="text-base text-ink">
                                    {{ \App\Content\Catalog::priceLabel($addOn) }}
                                    @php($addOnDuration = \App\Content\Catalog::durationLabel($addOn))
                                    @if ($addOnDuration)
                                        <span class="text-muted-ink"> · {{ $addOnDuration }}</span>
                                    @endif
                                </p>
                            </div>

                            @if (! empty($addOn['note']))
                                <p class="mt-2 text-sm leading-relaxed text-muted-ink">{{ $addOn['note'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Route for a visitor who does not know which service to pick (03). --}}
    <section class="story-section section bg-canvas">
        <div class="shell">
            <div class="reveal rounded-card border border-line bg-surface p-8 shadow-[var(--shadow-soft)] sm:p-10 lg:p-14">
                <div class="grid items-center gap-10 lg:grid-cols-[1.35fr_1fr] lg:gap-16">
                    <div>
                        <x-section-heading eyebrow="Guidance"
                                           title="Not sure which to choose?"
                                           lead="Colour changes and hair extensions begin with a free consultation, so the studio can plan the work and confirm the price before anything is booked. For anything else, book the closest service and describe the result you want." />
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row lg:flex-col lg:items-stretch">
                        <a href="{{ route('booking') }}" class="btn btn-primary">Book an appointment</a>

                        <a href="{{ route('services', ['q' => 'consultation']) }}" class="btn btn-secondary">
                            See the consultations
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
