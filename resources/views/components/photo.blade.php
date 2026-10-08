{{--
    A studio photograph.

    The frame owns the aspect ratio and the crop, the image fills it, and the
    alt text and intrinsic size come from config/gossips.php so a missing
    attribute cannot shift the layout or hide the image from a screen reader.

    Usage:
        <x-photo key="hero" class="aspect-[4/5]" sizes="(min-width: 1024px) 40vw, 90vw" priority />
        <x-photo key="nail-art" class="aspect-square" />
--}}
@props(['key', 'alt' => null, 'sizes' => null, 'priority' => false])

@php
    $photo = config("gossips.photography.{$key}");

    if (! $photo) {
        throw new InvalidArgumentException("No photograph is configured for [{$key}] in config/gossips.php.");
    }
@endphp

<div {{ $attributes->merge(['class' => 'media']) }}>
    <img src="{{ asset($photo['file']) }}"
         alt="{{ $alt ?? $photo['alt'] }}"
         width="{{ $photo['width'] }}"
         height="{{ $photo['height'] }}"
         @if ($sizes) sizes="{{ $sizes }}" @endif
         @if ($priority) fetchpriority="high" @endif
         loading="{{ $priority ? 'eager' : 'lazy' }}"
         decoding="async">
</div>
