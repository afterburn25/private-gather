@php($offers = isset($affiliateOffers) ? $affiliateOffers : collect())
<section class="pg-affiliate-strip lux-affiliate-strip" aria-label="Sponsored offers">
    <div class="pg-affiliate-heading"><span class="eyebrow">SPONSORED &amp; AFFILIATE OFFERS</span><h2>{{ $affiliateHeading ?? 'Lifestyle travel & experiences' }}</h2></div>
    <div class="pg-affiliate-grid">
        @forelse($offers as $offer)
            <article class="pg-affiliate-card">
                @if($offer->image_url)<img src="{{ $offer->image_url }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
                <div class="pg-affiliate-copy">
                    <span class="pill">Sponsored · {{ ucfirst($offer->category) }}</span>
                    <h3>{{ $offer->title }}</h3>
                    <p>{{ $offer->description }}</p>
                    <small class="pg-affiliate-disclosure">{{ $offer->disclosure }}</small>
                    <a class="button button-primary" rel="sponsored nofollow noopener" href="{{ route('affiliate.go',['offer'=>$offer->id,'placement'=>$affiliatePlacement ?? 'unknown']) }}">{{ $offer->cta_label }}</a>
                </div>
            </article>
        @empty
            <article class="pg-affiliate-card lux-affiliate-placeholder">
                <div class="lux-affiliate-placeholder-art">AD</div>
                <div class="pg-affiliate-copy">
                    <span class="pill">Sponsored</span>
                    <h3>Premium partner placement</h3>
                    <p>This space is reserved for approved Private Gather advertising and affiliate partners.</p>
                    <small class="pg-affiliate-disclosure">Advertising placeholder · no advertiser is currently attached to this slot.</small>
                    <span class="button button-ghost">Advertising space</span>
                </div>
            </article>
        @endforelse
    </div>
</section>
