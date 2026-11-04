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
 @php
  $content=$section->content??[];
  $sectionSettings=$section->settings??[];
  $heading=$renderer->render((string)data_get($content,'heading',$section->name),$templateContext);
  $body=$renderer->render((string)data_get($content,'body',''),$templateContext);
  $sectionBackground=in_array(data_get($sectionSettings,'background'),['default','muted','accent'],true)?data_get($sectionSettings,'background'):'default';
  $sectionLayout=in_array(data_get($sectionSettings,'layout'),['default','centered','split','cards','grid'],true)?data_get($sectionSettings,'layout'):'default';
  $sectionColumns=max(1,min(4,(int)data_get($sectionSettings,'columns',3)));
 @endphp
 @switch($section->type)
  @case('hero')
   @php
    $heroLabel=data_get($content,'button_label',data_get($content,'primary_label'));
    $heroUrl=data_get($content,'button_url',data_get($content,'primary_url','#'));
   @endphp
   <section class="pg-page-hero tenant-site-hero tenant-section tenant-section-hero tenant-section-bg-{{$sectionBackground}} tenant-section-layout-{{$sectionLayout}}">
    <div class="tenant-shell pg-grid pg-grid-2" style="align-items:center">
     <div class="tenant-hero-copy">
      <span class="pg-eyebrow">{{$renderer->render((string)data_get($content,'eyebrow','WELCOME'),$templateContext)}}</span>
      <h1>{{$heading}}</h1>
      <p>{{$body}}</p>
      <div class="pg-trust-row">
       @if(data_get($tenant->settings,'city'))<span class="pg-pill">{{data_get($tenant->settings,'city')}}@if(data_get($tenant->settings,'region')), {{data_get($tenant->settings,'region')}}@endif</span>@endif
       <span class="pg-pill">{{$tenant->isClub() ? 'Private club community' : 'Private community'}}</span>
      </div>
      @if($heroLabel && \App\Support\MountUrl::to($heroUrl)!=='')
       <div class="pg-actions" style="margin-top:24px"><a class="tenant-button tenant-button-primary" href="{{\App\Support\MountUrl::to($heroUrl)}}">{{$renderer->render((string)$heroLabel,$templateContext)}}</a></div>
      @endif
     </div>
     <div class="pg-media tenant-hero-media" style="min-height:390px">
      @if(data_get($content,'image_url') && \App\Support\MountUrl::to(data_get($content,'image_url'))!=='')<img src="{{\App\Support\MountUrl::to(data_get($content,'image_url'))}}" alt="{{data_get($content,'image_alt','')}}">@endif
      <div class="pg-media-overlay"><span class="pg-eyebrow">{{strtoupper($tenant->name)}}</span><h2 style="margin:.35rem 0 0">Community. Events. Membership.</h2></div>
     </div>
    </div>
   </section>
   @break

  @case('event_grid')
   <section class="pg-section tenant-section tenant-section-events tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell"><div class="pg-section-head"><div><span class="pg-eyebrow">UPCOMING</span><h2>{{$heading}}</h2>@if($body)<p>{{$body}}</p>@endif</div><a class="text-link" href="{{route('site.events')}}">View all →</a></div><div class="pg-grid pg-grid-3">
    @forelse($events->take((int)data_get($content,'limit',6)) as $event)
     <article class="pg-card pg-card-interactive pg-event-card"><div class="pg-media"><div class="pg-media-overlay"><span class="pg-pill">{{$event->starts_at->format('M j')}}</span></div></div><div class="pg-event-body"><div class="pg-event-meta">@if($event->category)<span class="pg-pill">{{$event->category}}</span>@endif<span class="pg-pill">{{$event->public_location_label?:$event->city?:'Location protected'}}</span></div><h3 class="pg-event-title">{{$event->title}}</h3><p class="pg-event-copy">{{$event->starts_at->format('F j · g:i A')}}</p><a class="text-link" href="{{route('events.show',$event)}}">View event →</a></div></article>
    @empty<div class="pg-empty">No upcoming events are currently visible to you.</div>@endforelse
   </div></div></section>
   @break

  @case('chapter_grid')
   @if($tenant->isOrganization())
   <section class="pg-section tenant-section tenant-section-chapters tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell"><div class="pg-section-head"><div><span class="pg-eyebrow">LOCAL COMMUNITY</span><h2>{{$heading}}</h2>@if($body)<p>{{$body}}</p>@endif</div>@if(\App\Support\TenantMembership::canAccessMembersContent(auth()->user(),$tenant->id))<a class="text-link" href="{{route('community.groups.index')}}">Member chapter directory →</a>@endif</div>
    <div class="pg-grid pg-grid-3">
     @forelse(($chapters??collect())->take((int)data_get($content,'limit',12)) as $chapter)
      <article class="pg-card"><div class="pg-event-meta"><span class="pg-pill">Chapter</span>@if($chapter->locationLabel())<span class="pg-pill">{{$chapter->locationLabel()}}</span>@endif</div><h3 style="font-size:1.4rem;margin:.75rem 0 .4rem">{{$chapter->name}}</h3>@if($chapter->description)<p class="muted">{{$chapter->description}}</p>@endif @if($chapter->chapterLead)<p class="muted">Chapter lead: <strong>{{$chapter->chapterLead->display_name?:$chapter->chapterLead->name}}</strong></p>@endif @if(\App\Support\TenantMembership::canAccessMembersContent(auth()->user(),$tenant->id))<a class="text-link" href="{{route('community.groups.show',$chapter)}}">Open chapter →</a>@else<span class="muted">Sign in as an organization member for chapter discussions.</span>@endif</article>
     @empty<div class="pg-empty">No chapters are currently visible on this website.</div>@endforelse
    </div>
   </div></section>
   @endif
   @break

  @case('membership_grid')
   <section class="pg-section tenant-section tenant-section-memberships tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell"><div class="pg-section-head"><div><span class="pg-eyebrow">MEMBERSHIP</span><h2>{{$heading}}</h2>@if($body)<p>{{$body}}</p>@endif</div></div><div class="pg-grid pg-grid-3">
    @forelse($tenant->membershipLevels()->where('is_active',true)->orderBy('sort_order')->limit(12)->get() as $level)
     <article class="pg-card"><h3>{{$level->name}}</h3><p class="pg-lead">{{$level->priceLabel()}}</p>@if($level->description)<p class="muted">{{$level->description}}</p>@endif @if(is_array($level->benefits))<ul>@foreach($level->benefits as $benefit)<li>{{$benefit}}</li>@endforeach</ul>@endif</article>
    @empty<div class="pg-empty">Membership options will appear here when the organization publishes a level.</div>@endforelse
   </div></div></section>
   @break

  @case('gallery')
   <section class="pg-section tenant-section tenant-section-gallery tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell"><div class="pg-section-head"><div><span class="pg-eyebrow">GALLERY</span><h2>{{$heading}}</h2>@if($body)<p>{{$body}}</p>@endif</div></div><div class="pg-grid pg-grid-{{$sectionColumns}}">
    @forelse((array)data_get($content,'items',[]) as $item)
     @if(is_array($item) && \App\Support\MountUrl::to($item['url']??'')!=='')<figure class="pg-card"><div class="pg-media"><img src="{{\App\Support\MountUrl::to($item['url']??'')}}" alt="{{$item['alt']??''}}"></div>@if($item['caption']??null)<figcaption class="muted" style="margin-top:10px">{{$item['caption']}}</figcaption>@endif</figure>@endif
    @empty<div class="pg-empty">Gallery images have not been added yet.</div>@endforelse
   </div></div></section>
   @break

  @case('faq')
   <section class="pg-section tenant-section tenant-section-faq tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell narrow"><div class="pg-section-head"><div><span class="pg-eyebrow">FAQ</span><h2>{{$heading}}</h2>@if($body)<p>{{$body}}</p>@endif</div></div><div class="pg-grid">
    @forelse((array)data_get($content,'items',[]) as $item)
     @if(is_array($item) && ($item['question']??'')!=='')<details class="pg-card"><summary><strong>{{$item['question']}}</strong></summary><div class="prose" style="margin-top:14px">{!!nl2br(e((string)($item['answer']??'')))!!}</div></details>@endif
    @empty<div class="pg-empty">No frequently asked questions are published yet.</div>@endforelse
   </div></div></section>
   @break

  @case('testimonials')
   <section class="pg-section tenant-section tenant-section-testimonials tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell"><div class="pg-section-head"><div><span class="pg-eyebrow">TESTIMONIALS</span><h2>{{$heading}}</h2>@if($body)<p>{{$body}}</p>@endif</div></div><div class="pg-grid pg-grid-3">
    @forelse((array)data_get($content,'items',[]) as $item)
     @if(is_array($item) && ($item['quote']??'')!=='')<blockquote class="pg-card"><p class="pg-lead">“{{$item['quote']}}”</p>@if($item['name']??null)<footer><strong>{{$item['name']}}</strong>@if($item['detail']??null)<small style="display:block">{{$item['detail']}}</small>@endif</footer>@endif</blockquote>@endif
    @empty<div class="pg-empty">Testimonials have not been added yet.</div>@endforelse
   </div></div></section>
   @break

  @case('stats')
   <section class="pg-section tenant-section tenant-section-stats tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell"><div class="pg-section-head"><div><span class="pg-eyebrow">AT A GLANCE</span><h2>{{$heading}}</h2>@if($body)<p>{{$body}}</p>@endif</div></div><div class="pg-grid pg-grid-{{$sectionColumns}}">
    @forelse((array)data_get($content,'items',[]) as $item)
     @if(is_array($item) && ($item['value']??'')!=='')<div class="pg-stat"><strong>{{$item['value']}}</strong><span>{{$item['label']??''}}</span></div>@endif
    @empty<div class="pg-empty">Stats have not been added yet.</div>@endforelse
   </div></div></section>
   @break

  @case('contact')
   <section class="pg-section tenant-section tenant-section-contact tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell narrow"><div class="pg-card"><span class="pg-eyebrow">CONTACT</span><h2>{{$heading}}</h2>@if($body)<p>{{$body}}</p>@endif
    <form method="post" action="{{route('site.forms.store',$section)}}" class="form-stack">@csrf
     <label>Name<input name="name" maxlength="190" required value="{{old('name')}}"></label>
     <label>Email<input name="email" type="email" maxlength="255" required value="{{old('email')}}"></label>
     <label>Message<textarea name="message" rows="6" maxlength="10000" required>{{old('message')}}</textarea></label>
     <label class="row-actions"><input type="checkbox" name="privacy_ack" value="1" required> I agree that {{$tenant->name}} may use these details to respond to this inquiry.</label>
     <small class="muted">Submitting this form does not subscribe you to marketing.</small>
     <button class="tenant-button tenant-button-primary">{{$renderer->render((string)data_get($content,'button_label','Send message'),$templateContext)}}</button>
    </form>
   </div></div></section>
   @break

  @case('cta')
   <section class="pg-section tenant-section tenant-section-cta tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell"><div class="pg-card tenant-cta" style="padding:clamp(28px,5vw,50px)"><div class="pg-grid pg-grid-2" style="align-items:center"><div><span class="pg-eyebrow">MEMBERSHIP & COMMUNITY</span><h2 style="font-size:clamp(2rem,4vw,3.5rem);letter-spacing:-.05em;margin:.45rem 0">{{$heading}}</h2><p class="pg-lead">{{$body}}</p></div><div class="pg-actions" style="justify-content:flex-end">@if(\App\Support\MountUrl::to(data_get($content,'button_url','#'))!=='')<a class="tenant-button tenant-button-primary" href="{{\App\Support\MountUrl::to(data_get($content,'button_url','#'))}}">{{$renderer->render((string)data_get($content,'button_label','Learn more'),$templateContext)}}</a>@endif</div></div></div></div></section>
   @break

  @case('image_text')
   <section class="pg-section tenant-section tenant-section-story tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell pg-grid pg-grid-2" style="align-items:center"><div class="pg-media tenant-story-media">@if(data_get($content,'image_url') && \App\Support\MountUrl::to(data_get($content,'image_url'))!=='')<img src="{{\App\Support\MountUrl::to(data_get($content,'image_url'))}}" alt="{{data_get($content,'image_alt','')}}">@endif</div><div class="pg-card tenant-story-copy"><span class="pg-eyebrow">ABOUT THIS COMMUNITY</span><h2>{{$heading}}</h2><div class="prose">{!!nl2br(e($body))!!}</div></div></div></section>
   @break

  @case('spacer')
   <div class="tenant-section-spacer" aria-hidden="true" style="height:clamp(24px,5vw,72px)"></div>
   @break

  @default
   <section class="pg-section tenant-section tenant-section-content tenant-section-bg-{{$sectionBackground}}"><div class="tenant-shell narrow"><div class="pg-card"><h2>{{$heading}}</h2>@if(data_get($content,'image_url') && \App\Support\MountUrl::to(data_get($content,'image_url'))!=='')<div class="pg-media" style="margin:18px 0"><img src="{{\App\Support\MountUrl::to(data_get($content,'image_url'))}}" alt="{{data_get($content,'image_alt','')}}"></div>@endif @if($body)<div class="prose">{!!nl2br(e($body))!!}</div>@endif</div></div></section>
 @endswitch
@endforeach
