@extends('layouts.app')

@section('title', 'Gossips Beauty — nails, brows, lashes, hair and makeup in Etobicoke')
@section('meta_description', 'Gossips Beauty is a beauty studio at 649 The Queensway in Etobicoke, Toronto, offering nail care, brows, lashes, hair and makeup. Book an appointment.')

@section('content')

    {{-- 1. Hero: the salon photo frames a centered introduction and booking action. --}}
    <section id="top" class="hero-scene story-section relative overflow-hidden bg-canvas" aria-label="Gossips Beauty introduction">
        <div class="hero-photo-panel depth-3 rise" data-depth="3" aria-hidden="true">
            <x-photo key="hero" alt=""
                     class="hero-photo h-full w-full"
                     sizes="100vw"
                     priority />
        </div>

        <div class="hero-gutter" aria-hidden="true"><span>GOSSIPS BEAUTY</span></div>

        <div class="shell hero-layout relative">
            <div class="hero-copy">
                <p class="hero-location rise">Etobicoke <span aria-hidden="true">/</span> Toronto</p>

                <h1 class="hero-title mt-5 font-display rise"
                    style="--reveal-delay: 80ms">
                    Beauty, made personal in Etobicoke
                </h1>

                <p class="hero-description mt-6 rise" style="--reveal-delay: 160ms">
                    Thoughtful nail work, brows, lashes, hair and makeup at 649 The Queensway.
                    Every appointment is confirmed with you before you arrive.
                </p>

                <div class="hero-actions mt-8 flex flex-wrap gap-3 rise" style="--reveal-delay: 240ms">
                    <a href="{{ route('booking') }}" class="btn btn-primary">Book your visit <span aria-hidden="true">↗</span></a>
                    <a href="{{ route('services') }}" class="btn btn-secondary">Explore services</a>
                </div>

                {{-- Confirmed facts only: the published platform rating and the address. --}}
                <dl class="hero-proof mt-10 flex flex-wrap gap-x-10 gap-y-4 border-t pt-6 rise" style="--reveal-delay: 320ms">
                    <div>
                        <dt class="eyebrow">Rating</dt>
                        <dd class="mt-1 text-lg">
                            {{ $rating['value'] }} / 5 on {{ $rating['platform'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="eyebrow">Studio</dt>
                        <dd class="mt-1 text-lg">
                            {{ $visit['address_lines'][0] }}, {{ config('gossips.location.city') }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    <div class="salon-marquee" aria-label="Nails, brows, lashes, hair and makeup">
        <div class="salon-marquee-track" aria-hidden="true">
            <span>NAILS</span><i></i><span>BROWS</span><i></i><span>LASHES</span><i></i><span>HAIR</span><i></i><span>MAKEUP</span><i></i>
            <span>NAILS</span><i></i><span>BROWS</span><i></i><span>LASHES</span><i></i><span>HAIR</span><i></i><span>MAKEUP</span><i></i>
        </div>
    </div>

    {{-- 2. Signature services: nails lead and the wider offer is represented. --}}
    <section id="signature-services" class="signature-services-section story-section section border-y border-line bg-canvas">
        <div class="shell service-grid-shell">
            <x-section-heading eyebrow="Signature services"
                               title="The services clients come back for"
                               lead="A starting point for choosing. Prices are in Canadian dollars, shown either as a fixed amount or as a “from” price.">
                <a href="{{ route('services') }}" class="link-underline">See every service and price</a>
            </x-section-heading>

            <div class="mt-12 grid gap-6 lg:grid-cols-2">
                @foreach ($services as $service)
                    <x-service-card :service="$service" :delay="$loop->index * 70" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- 3. What the studio offers: five category gateways, each with its own route into the catalog. --}}
    <section id="services" class="story-section section band-surface-soft border-b border-line">
        <div class="shell">
            <x-section-heading eyebrow="What Gossips offers"
                               title="Five kinds of appointment, one studio"
                               lead="Nail care is the studio’s focus, with brows, lashes, hair and makeup available under the same roof." />

            @php
                $gatewayLarge = $categories[0] ?? null;
                $gatewayRest = array_slice($categories, 1);
            @endphp

            <div class="mt-12 grid gap-5 lg:grid-cols-3">
                @if ($gatewayLarge)
                    <a href="{{ route('services', ['category' => $gatewayLarge['slug']]) }}"
                       class="media-link group relative col-span-1 overflow-hidden rounded-card bg-surface shadow-[var(--shadow-soft)] reveal lg:row-span-2">
                        <x-photo :key="$gatewayLarge['image']" alt="" class="aspect-[4/5] lg:aspect-[3/5] lg:h-full" sizes="(min-width: 1024px) 32vw, 92vw" />

                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-photo-overlay/85 to-transparent p-6 pt-16">
                            <h3 class="font-display text-3xl text-on-photo">{{ $gatewayLarge['name'] }}</h3>
                            <p class="mt-2 text-base text-on-photo/85">{{ $gatewayLarge['summary'] }}</p>
                            <span class="mt-4 inline-flex items-center gap-2 text-base font-medium text-on-photo">
                                View prices
                                <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">→</span>
                            </span>
                        </div>
                    </a>
                @endif

                <div class="grid gap-5 sm:grid-cols-2 lg:col-span-2">
                    @foreach ($gatewayRest as $category)
                        <a href="{{ route('services', ['category' => $category['slug']]) }}"
                           class="media-link group overflow-hidden rounded-card bg-surface shadow-[var(--shadow-soft)] reveal"
                           style="--reveal-delay: {{ 80 + $loop->index * 70 }}ms">
                            <x-photo :key="$category['image']" alt="" class="aspect-[4/3]" sizes="(min-width: 640px) 30vw, 92vw" />

                            <div class="flex items-start justify-between gap-4 p-5">
                                <div>
                                    <h3 class="font-display text-2xl text-ink">{{ $category['name'] }}</h3>
                                    <p class="mt-1 text-base text-muted-ink">{{ $category['summary'] }}</p>
                                </div>
                                <span aria-hidden="true"
                                      class="mt-1 text-action transition-transform duration-300 group-hover:translate-x-1">→</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 4. Work preview: the caption names the service family, never a person or a promised outcome. --}}
    <section id="work" class="story-section section border-b border-line bg-canvas">
        <div class="shell">
            <x-section-heading eyebrow="Our work"
                               title="A look at the work"
                               lead="Nail work leads, with brows, lashes, hair and makeup alongside it.">
                <a href="{{ route('work') }}" class="link-underline">See more work and reviews</a>
            </x-section-heading>

            <ul class="mt-12 grid grid-cols-2 gap-4 lg:grid-cols-3 lg:gap-6">
                @foreach ($tiles as $tile)
                    <li class="reveal" style="--reveal-delay: {{ $loop->index * 60 }}ms">
                        <a href="{{ route('work', ['category' => $tile['category_slug']]) }}"
                           class="media-link group block">
                            <x-photo :key="$tile['image']" alt="" class="aspect-square" sizes="(min-width: 1024px) 30vw, 46vw" />

                            <p class="mt-3 text-base text-ink transition-colors duration-200 group-hover:text-action">
                                {{ $tile['caption'] }}
                            </p>
                            <p class="text-sm text-muted-ink">{{ $tile['category'] }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 5. Studio introduction: real interior photos and confirmed details only. --}}
    <x-editorial-opening id="studio"
                         heading-tag="h2"
                         theme="blush"
                         eyebrow="The studio"
                         title="A closer look"
                         accent="at the studio."
                         lead="{{ $studio }}"
                         primary-image="hero"
                         secondary-image="nails"
                         note="Inside the studio"
                         variant="studio">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('services') }}" class="btn btn-primary">Browse services <span aria-hidden="true">↗</span></a>
            <a href="#visit" class="btn btn-secondary">Find the studio</a>
        </div>
    </x-editorial-opening>

    {{-- 6. Reviews: separately sourced platform scores with direct links to the original reviews. --}}
    <section id="reviews" class="story-section section border-b border-line bg-canvas">
        <div class="shell">
            <x-section-heading eyebrow="Reviews" title="Rated by the people who visit"
                               lead="Separate ratings from Google and DIKIDI. Read each review where it was originally posted." />

            <div class="mt-10 grid gap-6 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <x-rating-card :rating="$rating" :google-rating="$googleRating" />
                </div>

                <x-photo key="polish" class="mx-auto aspect-[3/4] w-full max-w-sm reveal xl:aspect-auto xl:h-full xl:max-w-none" sizes="(min-width: 1280px) 30vw, 24rem" />
            </div>
        </div>
    </section>

    {{-- 7. Visit: address, directions and booking beside real studio photography. --}}
    <x-editorial-opening id="visit"
                         heading-tag="h2"
                         eyebrow="Visit"
                         title="Visit the"
                         accent="studio."
                         lead="Gossips Beauty is at 649 The Queensway in Etobicoke, Toronto."
                         primary-image="nails"
                         secondary-image="studio-chair"
                         note="649 The Queensway">
        <address class="mb-6 not-italic text-base leading-relaxed text-ink">
            @foreach ($visit['address_lines'] as $line)
                {{ $line }}<br>
            @endforeach
        </address>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('booking') }}" class="btn btn-primary">Book an appointment <span aria-hidden="true">↗</span></a>
            <a href="{{ $visit['map_url'] }}" rel="noopener" class="btn btn-secondary">
                Open in Google Maps
            </a>
        </div>
    </x-editorial-opening>

@endsection
