@extends('layouts.app')

@section('title', 'Our work & reviews — Gossips Beauty, Etobicoke')
@section('meta_description', 'Nail, brow, lash, hair and makeup work from Gossips Beauty in Etobicoke, with separate Google and DIKIDI ratings and a route to book a similar look.')

@section('content')

    @php
        $showNailPortfolio = $activeCategory === null || $activeCategory === 'nails';
        $workHeroImage = match ($activeCategory) {
            'brows', 'lashes' => 'brows_lashes',
            'hair' => 'hair',
            'makeup' => 'makeup',
            default => 'work-dark',
        };
    @endphp

    {{-- The shared editorial opening keeps each category's own photography. --}}
    <x-editorial-opening eyebrow="Our work"
                         title="The work,"
                         accent="up close."
                         lead="A closer look at manicure finishes from Gossips Beauty. Explore the gallery, then find a service close to the look you have in mind."
                         :primary-image="$showNailPortfolio ? 'work-dark' : $workHeroImage"
                         :secondary-image="$showNailPortfolio ? 'work-colour' : null"
                         :primary-alt="$showNailPortfolio ? 'Deep glossy manicure photographed against greenery.' : ''"
                         secondary-alt="Colourful manicure with decorative accents."
                         note="Work in detail"
                         priority>
        {{-- Category filters remain shareable links and work without JavaScript. --}}
        <nav aria-label="Work categories">
            <ul class="flex flex-wrap gap-2">
                <li>
                    <a href="{{ route('work') }}"
                       @if ($activeCategory === null) aria-current="true" @endif
                       class="chip font-medium">
                        All work
                    </a>
                </li>

                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('work', ['category' => $category['slug']]) }}"
                           @if ($activeCategory === $category['slug']) aria-current="true" @endif
                           class="chip">
                            {{ $category['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </x-editorial-opening>

    {{-- Gallery. Each tile names a service family; it never claims a named client or a specialist. --}}
    <section class="story-section section border-b border-line bg-canvas">
        <div class="shell service-grid-shell">
            @if ($tiles === [])
                <h2 class="font-display text-3xl text-ink sm:text-4xl">No work published in this category yet</h2>

                <p class="mt-4 max-w-2xl text-lg text-muted-ink">
                    Browse every category instead.
                </p>

                <p class="mt-8">
                    <a href="{{ route('work') }}" class="btn btn-secondary">Show all work</a>
                </p>
            @else
                {{-- Section heading for the document outline; the page title already names it. --}}
                <h2 class="sr-only">Work</h2>

                <p class="text-base text-muted-ink" role="status">
                    @if ($activeCategoryName !== null)
                        {{ $tileCount }} {{ Str::plural('piece', $tileCount) }} in {{ $activeCategoryName }}
                    @else
                        Showing all {{ $tileCount }} {{ Str::plural('piece', $tileCount) }}
                    @endif
                </p>

                <ul class="work-gallery mt-10" data-work-gallery>
                    @foreach ($tiles as $tile)
                        <li data-work-card>
                            <a href="{{ route('services', ['category' => $tile['category_slug']]) }}"
                               aria-label="Book a similar look: {{ $tile['caption'] }}, {{ $tile['category'] }} services"
                               class="tile work-gallery-card group">
                                <span class="work-index" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }} <span>/ {{ str_pad((string) $tileCount, 2, '0', STR_PAD_LEFT) }}</span></span>

                                <x-photo :key="$tile['image']" alt="" class="work-photo" data-work-image />

                                <div class="work-gallery-copy">
                                    <p class="work-category">{{ $tile['category'] }}</p>

                                    <h3 class="work-caption mt-2 font-display text-2xl leading-snug text-ink transition-colors duration-200">
                                        {{ $tile['caption'] }}
                                    </h3>

                                    {{-- The link opens this look's service category without guessing a specific treatment. --}}
                                    <span class="work-action inline-flex items-center gap-2">
                                        Book a similar look
                                        <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">→</span>
                                    </span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- Reviews: platform ratings remain separate and link to their original sources. --}}
    <section id="reviews" class="story-section section band-surface-soft border-b border-line">
        <div class="shell">
            <x-section-heading eyebrow="Reviews"
                               title="Rated by the people who visit"
                               lead="See the studio's separately published scores on Google and DIKIDI, then read the original reviews on each platform." />

            <div class="mt-10">
                <x-rating-card :rating="$rating" :google-rating="$googleRating" />
            </div>
        </div>
    </section>

    {{-- Closing actions: book, or go and choose a service first. --}}
    <section class="story-section section bg-canvas">
        <div class="shell">
            <x-section-heading eyebrow="Next step"
                               title="Seen something you want?"
                               lead="Choose the closest service and describe the look you want when you book. Colour changes and hair extensions start with a free consultation." />

            <div class="mt-8 flex flex-wrap gap-3 reveal" style="--reveal-delay: 120ms">
                <a href="{{ route('booking') }}" class="btn btn-primary">Book an appointment</a>
                <a href="{{ route('services') }}" class="btn btn-secondary">Services &amp; pricing</a>
            </div>
        </div>
    </section>

@endsection
