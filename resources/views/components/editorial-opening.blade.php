{{-- Reusable, image-led introduction shared by the public editorial pages. --}}
@props([
    'id' => null,
    'headingTag' => 'h1',
    'theme' => 'canvas',
    'eyebrow',
    'title',
    'accent',
    'lead',
    'primaryImage',
    'secondaryImage' => null,
    'primaryAlt' => null,
    'secondaryAlt' => null,
    'note',
    'priority' => false,
    'variant' => null,
])

<section @if ($id) id="{{ $id }}" @endif
         class="editorial-opening story-section border-b border-line {{ $theme === 'blush' ? 'band-surface-soft' : 'bg-canvas' }} {{ $variant ? 'editorial-opening-'.$variant : '' }}"
         data-editorial-opening
         @if ($variant) data-editorial-motion="{{ $variant }}" @endif>
    <div class="shell service-grid-shell editorial-opening-shell">
        <div class="editorial-opening-copy">
            <p class="eyebrow editorial-opening-kicker" data-editorial-copy>
                {{ $eyebrow }} <span aria-hidden="true">/</span> Etobicoke
            </p>

            @if ($headingTag === 'h2')
                <h2 class="editorial-opening-title font-display" data-editorial-copy>
                    {{ $title }}<br><span>{{ $accent }}</span>
                </h2>
            @else
                <h1 class="editorial-opening-title font-display" data-editorial-copy>
                    {{ $title }}<br><span>{{ $accent }}</span>
                </h1>
            @endif

            <p class="editorial-opening-description" data-editorial-copy>{{ $lead }}</p>

            <div class="editorial-opening-actions" data-editorial-copy>
                {{ $slot }}
            </div>
        </div>

        <div class="editorial-opening-stage" role="group" aria-label="{{ $note }}">
            <x-photo :key="$primaryImage"
                     :alt="$primaryAlt"
                     class="editorial-opening-photo editorial-opening-photo-primary {{ $secondaryImage ? '' : 'editorial-opening-photo-solo' }}"
                     sizes="(min-width: 1024px) 42vw, 92vw"
                     :priority="$priority"
                     data-editorial-frame
                     data-depth="3" />

            @if ($secondaryImage)
                <x-photo :key="$secondaryImage"
                         :alt="$secondaryAlt"
                         class="editorial-opening-photo editorial-opening-photo-secondary"
                         sizes="(min-width: 1024px) 24vw, 64vw"
                         :priority="$priority"
                         data-editorial-frame
                         data-depth="2" />
            @endif

            <p class="editorial-opening-note" aria-hidden="true">{{ $note }} <span>·</span> Gossips Beauty</p>
        </div>
    </div>
</section>
