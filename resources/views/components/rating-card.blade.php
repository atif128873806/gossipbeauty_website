{{-- Keep each published rating attributed to its original platform. --}}
@props(['rating', 'googleRating'])

<div class="rating-grid">
    @foreach ([$googleRating, $rating] as $source)
        @php($isGoogle = $source['platform'] === 'Google')
        <section class="rating-panel {{ $isGoogle ? 'rating-panel-featured' : 'rating-panel-secondary' }} reveal"
                 aria-label="{{ $source['platform'] }} rating"
                 style="--rating-progress: {{ (float) $source['value'] * 20 }}%">
            <div class="rating-panel-topline">
                @if ($isGoogle)
                    <span class="google-wordmark" role="img" aria-label="Google">
                        <span aria-hidden="true"><i>G</i><i>o</i><i>o</i><i>g</i><i>l</i><i>e</i></span>
                    </span>
                @else
                    <p class="eyebrow">{{ $source['platform'] }}</p>
                @endif
                <span class="rating-published">Published rating</span>
            </div>

            <div class="rating-score-row">
                <p class="rating-score">{{ $source['value'] }}<span>/ 5</span></p>
                <div class="rating-stars {{ $isGoogle ? 'rating-stars-google' : '' }}" aria-hidden="true">★★★★★</div>
            </div>

            <div class="rating-scale" role="img" aria-label="{{ $source['value'] }} out of 5">
                <span></span>
            </div>

            <div class="rating-panel-footer">
                <p class="rating-count">{{ $source['count'] }}</p>
                <p class="rating-date">Checked {{ $source['observed'] }}</p>
            </div>

            <a href="{{ $source['url'] }}" rel="nofollow noopener" class="rating-link">
                Read reviews on {{ $source['platform'] }}
                <span aria-hidden="true">↗</span>
            </a>
        </section>
    @endforeach
</div>
