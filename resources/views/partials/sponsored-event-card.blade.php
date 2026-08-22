@php
    $offer = $adOffer ?? null;
    $placement = $adPlacement ?? 'featured_events';
@endphp
<article class="lux-event-card lux-sponsored-event-card">
    @if($offer)
        <a rel="sponsored nofollow noopener" href="{{ route('affiliate.go',['offer'=>$offer->id,'placement'=>$placement]) }}">
            <div class="lux-card-photo lux-sponsored-photo">
                @if($offer->image_url)<img src="{{ $offer->image_url }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
                <span class="lux-sponsored-ribbon">SPONSORED</span>
                @unless($offer->image_url)<span class="lux-sponsored-monogram">AD</span>@endunless
            </div>
            <div class="lux-card-body">
                <h3>{{ $offer->title }}</h3>
                <p>{{ $offer->advertiser_name }} · {{ \Illuminate\Support\Str::limit($offer->description,76) }}</p>
                <span class="lux-tag">{{ $offer->cta_label }} →</span>
            </div>
        </a>
    @else
        <div class="lux-sponsored-placeholder" data-ad-slot="{{ $placement }}">
            <div class="lux-card-photo lux-sponsored-photo">
                <span class="lux-sponsored-ribbon">SPONSORED</span>
                <span class="lux-sponsored-monogram">AD</span>
            </div>
            <div class="lux-card-body">
                <h3>Featured Partner</h3>
                <p>Premium advertising placement designed to blend naturally with event discovery.</p>
                <span class="lux-tag">Advertising space</span>
            </div>
        </div>
    @endif
</article>
