<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Studio panel — Gossips Beauty')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preload" href="{{ Vite::asset('resources/fonts/dm-sans-latin-variable.woff2') }}" as="font" type="font/woff2" crossorigin>
</head>
<body class="admin-surface @auth admin-workspace @endauth min-h-screen bg-canvas text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:inline-flex focus:min-h-11 focus:items-center focus:rounded-card focus:bg-action focus:px-5 focus:text-on-action">Skip to content</a>
    <header class="site-header admin-header border-b border-line bg-surface">
        <div class="shell flex flex-wrap items-center justify-between gap-4 py-4">
            <a href="{{ auth()->check() ? route('admin.index') : route('admin.login') }}" class="font-display text-2xl">Gossips Beauty <span class="font-sans text-sm uppercase tracking-wide text-muted-ink">Panel</span></a>
            <div class="flex items-center gap-4 text-sm text-muted-ink">
                <a href="{{ route('home') }}" class="underline">Public site</a>
                @auth
                    <span>{{ auth()->user()->name }}</span>
                    <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="underline">Sign out</button></form>
                @endauth
            </div>
        </div>
    </header>
    <div class="scroll-progress" data-scroll-progress aria-hidden="true"></div>
    @if (request()->routeIs('admin.preview'))
        <div class="shell py-5"><div class="panel-note p-4"><p class="font-medium">Panel preview</p><p class="mt-2 max-w-3xl text-base text-muted-ink">This screen is an illustrative layout only and does not read or change appointments.</p></div></div>
    @endif
    <main id="main">@yield('content')</main>
    <footer class="mt-12 border-t border-line bg-surface"><div class="shell py-6 text-sm text-muted-ink">Private studio operations · Gossips Beauty</div></footer>
</body>
</html>
