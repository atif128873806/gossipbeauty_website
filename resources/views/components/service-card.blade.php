{{--
    One service card.

    Shows the price basis (fixed or "from" CAD) rather than a single total, the
    real configured duration where one exists, and the booking action. Per
    the accessibility rule in AGENTS.md, the booking control names its
    service for assistive technology instead of repeating an unlabelled "Book".

    Pass an optional reveal delay so a grid animates in sequence:
        <x-service-card :service="$service" :delay="90" />
--}}
@props(['service', 'delay' => 0])

@use('App\Content\Catalog')

@php
    $duration = Catalog::durationLabel($service);
    $categorySlug = $service['category_slug'] ?? 'nails';

    // Use Gossips' supplied portfolio for nail work, matching pedicures and
    // recognisable finishes where possible. Other categories use their
    // existing photographic category image until the studio supplies more.
    $nailPhotos = [
        'work-french', 'polish', 'work-soft', 'work-dark', 'work-colour',
        'work-blue', 'work-neutral', 'hand_care', 'flat_lay',
    ];
    $serviceText = strtolower(($service['slug'] ?? '').' '.($service['name'] ?? ''));
    $imageKey = match (true) {
        $categorySlug === 'nails' && str_contains($serviceText, 'pedicure') => 'pedicure',
        $categorySlug === 'nails' && str_contains($serviceText, 'french') => 'work-french',
        $categorySlug === 'nails' && str_contains($serviceText, 'red') => 'polish',
        $categorySlug === 'nails' => $nailPhotos[hexdec(substr(hash('sha256', $service['slug'] ?? $service['name']), 0, 7)) % count($nailPhotos)],
        $categorySlug === 'brows' || $categorySlug === 'lashes' => 'brows_lashes',
        $categorySlug === 'hair' => 'hair',
        $categorySlug === 'makeup' => 'makeup',
        default => 'nails',
    };
@endphp

<article class="service-listing group reveal" data-tilt style="--reveal-delay: {{ $delay }}ms">
    <div class="service-copy">
        <p class="service-category">{{ $service['category'] }}</p>

        <h3 class="mt-2 font-display leading-snug text-ink transition-colors duration-300 group-hover:text-action">
            {{ $service['name'] }}
        </h3>

        <p class="service-result mt-3 text-sm leading-relaxed text-ink">{{ $service['result'] }}</p>

        @if (! empty($service['inclusions']))
            <p class="service-inclusions mt-3 text-xs leading-relaxed text-muted-ink">
                <span>Includes</span> {{ $service['inclusions'] }}
            </p>
        @endif

        <div class="flex-1"></div>

        <dl class="service-facts mt-5 grid grid-cols-2 gap-3 border-t border-line pt-4">
            <div class="flex items-baseline gap-2">
                <dt>Price</dt>
                <dd>{{ Catalog::priceLabel($service) }}</dd>
            </div>

            {{-- No configured duration means no time row at all, never a guessed figure. --}}
            @if ($duration)
                <div class="flex items-baseline gap-2">
                    <dt>Time</dt>
                    <dd>{{ $duration }}</dd>
                </div>
            @endif
        </dl>

        @if (! empty($service['price_note']))
            <p class="mt-2 text-sm text-muted-ink">{{ $service['price_note'] }}</p>
        @endif

        <a href="{{ route('booking', ['service' => $service['slug']]) }}"
           aria-label="Book {{ $service['name'] }}"
           class="service-link mt-5 inline-flex min-h-11 items-center justify-between gap-2">
            <span>Choose this service</span><span aria-hidden="true">↗</span>
        </a>
    </div>

    <x-photo :key="$imageKey"
             class="service-card-photo"
             sizes="(min-width: 1024px) 20vw, (min-width: 768px) 40vw, 92vw" />
</article>
