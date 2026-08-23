@php
$renderer=app(\App\Services\PlaceholderRenderer::class);
$templateContext=[
    'site'=>['name'=>config('app.name'),'url'=>config('app.url')],
    'club'=>['name'=>$tenant->name,'city'=>data_get($tenant->settings,'city','')],
    'organization'=>['name'=>$tenant->name,'city'=>data_get($tenant->settings,'city','')],
    'organizer'=>['name'=>$tenant->name],
    'member'=>['display_name'=>auth()->user()?->display_name??''],
];
@endphp
@foreach($page->sections as $section)
 @php($content=$section->content??[])
 @php($heading=$renderer->render((string)data_get($content,'heading',$section->name),$templateContext))
 @php($body=$renderer->render((string)data_get($content,'body',''),$templateContext))
 @switch($section->type)
  @case('hero')
   <section class="pg-page-hero">
    <div class="pg-shell pg-grid pg-grid-2" style="align-items:center">
     <div>
      <span class="pg-eyebrow">{{$renderer->render((string)data_get($content,'eyebrow','WELCOME'),$templateContext)}}</span>
      <h1>{{$heading}}</h1>
      <p>{{$body}}</p>
      <div class="pg-trust-row">
       @if(data_get($tenant->settings,'city'))<span class="pg-pill">{{data_get($tenant->settings,'city')}}@if(data_get($tenant->settings,'region')), {{data_get($tenant->settings,'region')}}@endif</span>@endif
       <span class="pg-pill">{{ $tenant->isClub() ? 'Private club community' : 'Private Gather community' }}</span>
      </div>
      @if(data_get($content,'primary_label'))
       <div class="pg-actions" style="margin-top:24px"><a class="button button-primary" href="{{\App\Support\MountUrl::to(data_get($content,'primary_url','#'))}}">{{$renderer->render((string)data_get($content,'primary_label'),$templateContext)}}</a></div>
      @endif
     </div>
     <div class="pg-media" style="min-height:390px">
      @if(data_get($content,'image_url'))<img src="{{\App\Support\MountUrl::to(data_get($content,'image_url'))}}" alt="{{data_get($content,'image_alt','')}}">@endif
      <div class="pg-media-overlay"><span class="pg-eyebrow">{{ strtoupper($tenant->name) }}</span><h2 style="margin:.35rem 0 0">Community. Events. Membership.</h2></div>
     </div>
    </div>
   </section>
   @break

  @case('event_grid')
   <section class="pg-section"><div class="pg-shell"><div class="pg-section-head"><div><span class="pg-eyebrow">UPCOMING</span><h2>{{$heading}}</h2></div><a class="text-link" href="{{route('site.events')}}">View all →</a></div><div class="pg-grid pg-grid-3">
    @forelse($events->take((int)data_get($content,'limit',6)) as $event)
     <article class="pg-card pg-card-interactive pg-event-card"><div class="pg-media"><div class="pg-media-overlay"><span class="pg-pill">{{$event->starts_at->format('M j')}}</span></div></div><div class="pg-event-body"><div class="pg-event-meta">@if($event->category)<span class="pg-pill">{{$event->category}}</span>@endif<span class="pg-pill">{{$event->public_location_label?:$event->city?:'Location protected'}}</span></div><h3 class="pg-event-title">{{$event->title}}</h3><p class="pg-event-copy">{{$event->starts_at->format('F j · g:i A')}}</p><a class="text-link" href="{{route('events.show',$event)}}">View event →</a></div></article>
    @empty<div class="pg-empty">No upcoming events are currently visible to you.</div>@endforelse
   </div></div></section>
   @break

  @case('cta')
   <section class="pg-section"><div class="pg-shell"><div class="pg-card" style="padding:clamp(28px,5vw,50px)"><div class="pg-grid pg-grid-2" style="align-items:center"><div><span class="pg-eyebrow">MEMBERSHIP & COMMUNITY</span><h2 style="font-size:clamp(2rem,4vw,3.5rem);letter-spacing:-.05em;margin:.45rem 0">{{$heading}}</h2><p class="pg-lead">{{$body}}</p></div><div class="pg-actions" style="justify-content:flex-end"><a class="button button-primary button-large" href="{{\App\Support\MountUrl::to(data_get($content,'button_url','#'))}}">{{$renderer->render((string)data_get($content,'button_label','Learn more'),$templateContext)}}</a></div></div></div></div></section>
   @break

  @case('image_text')
   <section class="pg-section"><div class="pg-shell pg-grid pg-grid-2" style="align-items:center"><div class="pg-media">@if(data_get($content,'image_url'))<img src="{{\App\Support\MountUrl::to(data_get($content,'image_url'))}}" alt="{{data_get($content,'image_alt','')}}">@endif</div><div class="pg-card"><span class="pg-eyebrow">ABOUT THIS COMMUNITY</span><h2>{{$heading}}</h2><div class="prose">{!!nl2br(e($body))!!}</div></div></div></section>
   @break

  @default
   <section class="pg-section"><div class="pg-shell narrow"><div class="pg-card"><h2>{{$heading}}</h2>@if(data_get($content,'image_url'))<div class="pg-media" style="margin:18px 0"><img src="{{\App\Support\MountUrl::to(data_get($content,'image_url'))}}" alt="{{data_get($content,'image_alt','')}}"></div>@endif @if($body)<div class="prose">{!!nl2br(e($body))!!}</div>@endif</div></div></section>
 @endswitch
@endforeach
