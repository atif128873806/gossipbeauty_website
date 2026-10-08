{{--
    A section opening with a clear editorial heading, supporting line, and
    optional action. Category context stays in the content instead of a kicker.

    Usage:
        <x-section-heading eyebrow="Studio" title="Signature services" lead="…">
            <a href="…">See every service and price</a>
        </x-section-heading>
--}}
@props(['eyebrow' => null, 'title', 'lead' => null, 'level' => 2, 'tone' => 'ink'])

@php
    $heading = $level === 2 ? 'font-display text-3xl leading-tight sm:text-4xl lg:text-[2.75rem]' : 'font-display text-2xl leading-tight sm:text-3xl';
@endphp

<div class="section-heading max-w-2xl">
    <h{{ $level }} class="{{ $heading }} {{ $tone === 'surface' ? 'text-on-photo' : 'text-ink' }} reveal"
        style="--reveal-delay: 60ms">
        {{ $title }}
    </h{{ $level }}>

    @if ($lead)
        <p class="mt-4 text-lg leading-relaxed text-muted-ink reveal" style="--reveal-delay: 120ms">{{ $lead }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-5 text-lg reveal" style="--reveal-delay: 180ms">{{ $slot }}</div>
    @endif
</div>
