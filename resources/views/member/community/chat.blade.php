@extends('layouts.app')
@section('title','Live Chat')
@section('content')
<section class="shell section">
<div class="panel-head"><div><div class="eyebrow">LIVE COMMUNITY CHAT</div><h1>{{$tenant->name}} Live Chat</h1><p class="muted">A real-time members-only room for this Private Gather site.</p></div><div class="row-actions"><a class="btn" href="{{route('community.index')}}">Community Wall</a><a class="btn" href="{{route('messages.index')}}">Private Messages</a></div></div>
<div id="chat-thread" class="message-thread panel" style="min-height:420px;max-height:65vh;overflow:auto">
@foreach($messages as $message)
<div class="message {{$message->user_id===auth()->id()?'mine':''}}" data-chat-id="{{$message->id}}"><strong>{{$message->user?->display_name ?: $message->user?->name ?: 'Former member'}}</strong><p>{{$message->body}}</p><small>{{$message->created_at->format('M j, g:i A')}}</small></div>
@endforeach
</div>
<form id="chat-form" class="panel form-inline" method="post" action="{{route('community.chat.store')}}">@csrf<textarea id="chat-body" name="body" maxlength="3000" required placeholder="Message the room"></textarea><button class="btn primary">Send</button></form>
</section>
<script>
(()=>{const thread=document.getElementById('chat-thread'),form=document.getElementById('chat-form'),body=document.getElementById('chat-body'),csrf=document.querySelector('meta[name="csrf-token"]').content;let last=Number(thread.querySelector('[data-chat-id]:last-of-type')?.dataset.chatId||0);const add=m=>{if(thread.querySelector(`[data-chat-id="${m.id}"]`))return;const box=document.createElement('div');box.className='message'+(m.mine?' mine':'');box.dataset.chatId=m.id;const strong=document.createElement('strong');strong.textContent=m.name;const p=document.createElement('p');p.textContent=m.body;const small=document.createElement('small');small.textContent=new Date(m.created_at).toLocaleString();box.append(strong,p,small);thread.appendChild(box);last=Math.max(last,Number(m.id));thread.scrollTop=thread.scrollHeight;};const poll=async()=>{try{const r=await fetch(`{{route('community.chat.poll')}}?after=${last}`,{headers:{Accept:'application/json'}});if(r.ok){const d=await r.json();(d.messages||[]).forEach(add);}}catch(e){}};form.addEventListener('submit',async e=>{e.preventDefault();const text=body.value.trim();if(!text)return;const r=await fetch(form.action,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({body:text})});if(r.ok){const d=await r.json();add(d.message);body.value='';}});thread.scrollTop=thread.scrollHeight;setInterval(poll,2000);})();
</script>
@endsection
