@extends('layouts.app')
@section('title',$conversation->subject?:'Conversation')
@section('content')
<section class="shell section">
@php($others=$conversation->participants->where('id','!=',auth()->id()))
<div class="panel-head"><div><div class="eyebrow">PRIVATE CHAT · {{$tenant->name}}</div><h1>{{$conversation->subject ?: $others->map(fn($u)=>$u->display_name?:$u->name)->join(', ') ?: 'Conversation'}}</h1><p class="muted">Only active members of this site can participate.</p></div><div class="row-actions"><a class="btn" href="{{route('messages.index')}}">Inbox</a><form method="post" action="{{route('messages.mute',$conversation)}}">@csrf @method('PATCH')<button class="btn">{{$conversation->participants->firstWhere('id',auth()->id())?->pivot?->muted?'Unmute':'Mute'}}</button></form></div></div>
<div id="message-thread" class="message-thread panel" style="min-height:320px;max-height:62vh;overflow:auto">
@foreach($messages as $message)
<div class="message {{$message->user_id===auth()->id()?'mine':''}}" data-message-id="{{$message->id}}"><strong>{{$message->user?->display_name ?: $message->user?->name ?: 'Former member'}}</strong><p>{{$message->body}}</p><small>{{$message->created_at->format('M j, g:i A')}}</small></div>
@endforeach
</div>
<form id="message-form" method="post" action="{{route('messages.store',$conversation)}}" class="panel form-inline">@csrf<textarea id="message-body" name="body" maxlength="10000" required placeholder="Write a message"></textarea><button class="btn primary">Send</button></form>
</section>
<script>
(()=>{const thread=document.getElementById('message-thread'),form=document.getElementById('message-form'),body=document.getElementById('message-body'),csrf=document.querySelector('meta[name="csrf-token"]').content;let last=Number(thread.querySelector('[data-message-id]:last-of-type')?.dataset.messageId||0);const add=m=>{if(thread.querySelector(`[data-message-id="${m.id}"]`))return;const box=document.createElement('div');box.className='message'+(m.mine?' mine':'');box.dataset.messageId=m.id;const strong=document.createElement('strong');strong.textContent=m.name;const p=document.createElement('p');p.textContent=m.body;const small=document.createElement('small');small.textContent=new Date(m.created_at).toLocaleString();box.append(strong,p,small);thread.appendChild(box);last=Math.max(last,Number(m.id));thread.scrollTop=thread.scrollHeight;};const poll=async()=>{try{const r=await fetch(`{{route('messages.poll',$conversation)}}?after=${last}`,{headers:{Accept:'application/json'}});if(r.ok){const d=await r.json();(d.messages||[]).forEach(add);}}catch(e){}};form.addEventListener('submit',async e=>{e.preventDefault();const text=body.value.trim();if(!text)return;const r=await fetch(form.action,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({body:text})});if(r.ok){const d=await r.json();add(d.message);body.value='';}});thread.scrollTop=thread.scrollHeight;setInterval(poll,2000);})();
</script>
@endsection
