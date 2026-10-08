{{--
    Shared shell for the public site.

    Navigation: Services and Our Work are their own pages; About and Visit are
    sections of the home page, so they link to the home route with an anchor and
    keep working from any page. When a section becomes a page of its own, give it
    a `route` entry here — this is the single place to change.

    The footer lists only confirmed facts: the address and the map link. Hours,
    phone and policy wording are published once the owner supplies them.
    See AGENTS.md for public content rules.
--}}
@php
    $navItems = [
        ['label' => 'Services', 'href' => route('services'), 'route' => 'services'],
        ['label' => 'Our Work', 'href' => route('work'), 'route' => 'work'],
        ['label' => 'About', 'href' => route('home').'#studio'],
        ['label' => 'Visit', 'href' => route('home').'#visit'],
    ];

    $location = config('gossips.location');

    $mapUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(
        $location['address_line'].', '.$location['city'].', '.$location['region']
    );
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Gossips Beauty — nails, brows, lashes, hair and makeup in Etobicoke')</title>
    <meta name="description" content="@yield('meta_description', 'Gossips Beauty is a beauty studio at 649 The Queensway in Etobicoke, Toronto, offering nail care, brows, lashes, hair and makeup.')">

    {{-- The shared theme self-hosts DM Sans and supplies both typography roles. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preload" href="{{ Vite::asset('resources/fonts/dm-sans-latin-variable.woff2') }}" as="font" type="font/woff2" crossorigin>
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:inline-flex focus:min-h-11 focus:items-center focus:rounded-card focus:bg-action focus:px-5 focus:text-on-action">
        Skip to content
    </a>

    {{--
        Sticky header: the booking action stays reachable while a visitor reads,
        and the surface turns opaque with a hairline once the page has scrolled
        (see .is-scrolled in resources/css/app.css).
    --}}
    <div class="studio-line" aria-label="Studio location">
        <div class="shell flex items-center justify-between gap-4">
            <span>649 The Queensway · Etobicoke, Toronto</span>
            <span class="hidden sm:inline">Nails · Brows · Lashes · Hair · Makeup</span>
        </div>
    </div>

    <header data-site-header
            class="site-header sticky top-0 z-40 border-b border-line/70 bg-canvas/90 transition-[background-color,box-shadow] duration-300">
        <div class="shell flex items-center justify-between gap-4 py-3">
            <a href="{{ url('/') }}" class="group inline-flex min-h-11 items-center gap-2.5">
                <span class="font-display text-2xl leading-none tracking-tight text-ink">Gossips</span>
                <span class="eyebrow leading-none">Beauty</span>
                <span class="sr-only">— home</span>
            </a>

            {{-- Desktop navigation --}}
            <nav aria-label="Primary" class="hidden items-center gap-1 md:flex">
                @foreach ($navItems as $item)
                    @php $current = ! empty($item['route']) && request()->routeIs($item['route']); @endphp

                    {{-- min-h-11 keeps every navigation target at least 44px for touch and WCAG 2.2 target size. --}}
                    <a href="{{ $item['href'] }}"
                       @if ($current) aria-current="page" @endif
                       class="nav-link">
                        {{ $item['label'] }}
                    </a>
                @endforeach

                <a href="{{ route('booking') }}" class="btn btn-primary ml-2">Book a visit</a>
            </nav>

            {{--
                Mobile menu: a native disclosure element keeps it usable by
                keyboard and without JavaScript.
            --}}
            <div class="mobile-header-actions md:hidden">
                <a href="{{ route('booking') }}" class="btn btn-primary mobile-book-button" aria-label="Book a visit">
                    <span class="mobile-book-label" aria-hidden="true">Book a visit</span>
                    <span class="mobile-book-label-short" aria-hidden="true">Book</span>
                </a>

                <details data-menu>
                    <summary class="btn btn-quiet mobile-menu-toggle cursor-pointer list-none [&::-webkit-details-marker]:hidden" aria-label="Site menu">
                        <svg class="mobile-menu-icon mobile-menu-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <path d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg class="mobile-menu-icon mobile-menu-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <path d="M5 5l14 14M19 5L5 19" />
                        </svg>
                    </summary>

                    <div class="absolute inset-x-0 top-full border-b border-line bg-canvas px-4 pb-6 pt-2 shadow-soft sm:px-6">
                        <nav aria-label="Primary mobile" class="flex flex-col">
                            @foreach ($navItems as $item)
                                <a href="{{ $item['href'] }}"
                                   @if (! empty($item['route']) && request()->routeIs($item['route'])) aria-current="page" @endif
                                   class="flex min-h-12 items-center justify-between border-b border-line text-lg text-ink last:border-b-0 hover:text-action">
                                    {{ $item['label'] }}
                                    <span aria-hidden="true" class="text-muted-ink">→</span>
                                </a>
                            @endforeach

                            <a href="{{ route('booking') }}" class="btn btn-primary mt-5 w-full">Book an appointment</a>
                        </nav>
                    </div>
                </details>
            </div>
        </div>
    </header>

    <div class="scroll-progress" data-scroll-progress aria-hidden="true"></div>

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer mt-4 border-t border-line bg-surface">
        <div class="shell grid gap-10 py-14 lg:grid-cols-4 lg:gap-8">
            <div class="lg:col-span-2">
                <p class="font-display text-3xl leading-none text-ink">Gossips Beauty</p>
                <p class="mt-4 max-w-sm text-base leading-relaxed text-muted-ink">
                    Nail care, brows, lashes, hair and makeup under one roof on The Queensway in Etobicoke.
                </p>

                <a href="{{ route('booking') }}" class="btn btn-primary mt-6">Book an appointment</a>
            </div>

            <div class="text-base">
                <p class="eyebrow">Visit</p>

                <address class="mt-3 not-italic leading-relaxed text-muted-ink">
                    {{ $location['address_line'] }}<br>
                    {{ $location['city'] }}, {{ $location['region'] }}
                </address>

                <p class="mt-3">
                    <a href="{{ $mapUrl }}" rel="noopener" class="link-underline text-base">
                        Directions on Google Maps
                    </a>
                </p>
            </div>

            <div class="text-base">
                <p class="eyebrow">Explore</p>

                <ul class="mt-3">
                    @foreach ($navItems as $item)
                        <li>
                            <a href="{{ $item['href'] }}"
                               class="inline-flex min-h-11 items-center text-muted-ink underline-offset-4 hover:text-action hover:underline">
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                    <li>
                        <a href="{{ route('booking') }}"
                           class="inline-flex min-h-11 items-center text-muted-ink underline-offset-4 hover:text-action hover:underline">
                            Book an appointment
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="hairline">
            <div class="shell flex flex-col gap-2 py-6 text-sm text-muted-ink sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ date('Y') }} Gossips Beauty</p>
                <p>Nails &middot; Brows &middot; Lashes &middot; Hair &middot; Makeup</p>
            </div>
        </div>
    </footer>
</body>
</html>
