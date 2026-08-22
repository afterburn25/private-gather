<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ClubNewsPost;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ClubNewsController extends Controller
{
    public function publicIndex(Request $request, TenantContext $context): View
    {
        $tenant=$context->requireTenant();
        $member=$request->user() && $request->user()->tenants()->whereKey($tenant->id)->wherePivot('status','active')->exists();
        $posts=ClubNewsPost::query()->where('tenant_id',$tenant->id)->where('status','published')->where('published_at','<=',now())
            ->when(!$member,fn($q)=>$q->where('visibility','public'))->orderByDesc('is_pinned')->latest('published_at')->paginate(12);
        return view('tenant.news.index',compact('tenant','posts'));
    }

    public function publicShow(Request $request, TenantContext $context, ClubNewsPost $post): View
    {
        $tenant=$context->requireTenant();abort_unless((int)$post->tenant_id===(int)$tenant->id&&$post->status==='published'&&$post->published_at?->lte(now()),404);
        if($post->visibility==='members')abort_unless($request->user()&&$request->user()->tenants()->whereKey($tenant->id)->wherePivot('status','active')->exists(),403);
        return view('tenant.news.show',compact('tenant','post'));
    }

    public function manage(TenantContext $context): View
    {
        $tenant=$context->requireTenant();$posts=ClubNewsPost::where('tenant_id',$tenant->id)->latest()->paginate(50);return view('tenant.manage.news',compact('tenant','posts'));
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant=$context->requireTenant();$data=$request->validate(['title'=>'required|string|max:190','excerpt'=>'nullable|string|max:1000','body'=>'required|string|max:50000','visibility'=>'required|in:public,members','status'=>'required|in:draft,published']);
        $base=Str::slug($data['title'])?:'news';$slug=$base;$i=2;while(ClubNewsPost::where('tenant_id',$tenant->id)->where('slug',$slug)->exists())$slug=$base.'-'.$i++;
        ClubNewsPost::create(['tenant_id'=>$tenant->id,'user_id'=>$request->user()->id,'title'=>$data['title'],'slug'=>$slug,'excerpt'=>$data['excerpt']??null,'body'=>$data['body'],'visibility'=>$data['visibility'],'status'=>$data['status'],'is_pinned'=>$request->boolean('is_pinned'),'published_at'=>$data['status']==='published'?now():null]);
        return back()->with('status','Club news post created.');
    }

    public function update(Request $request, TenantContext $context, ClubNewsPost $post): RedirectResponse
    {
        $tenant=$context->requireTenant();abort_unless((int)$post->tenant_id===(int)$tenant->id,404);$data=$request->validate(['title'=>'required|string|max:190','excerpt'=>'nullable|string|max:1000','body'=>'required|string|max:50000','visibility'=>'required|in:public,members','status'=>'required|in:draft,published']);
        $post->update($data+['is_pinned'=>$request->boolean('is_pinned'),'published_at'=>$data['status']==='published'?($post->published_at??now()):null]);return back()->with('status','Club news post updated.');
    }

    public function destroy(TenantContext $context, ClubNewsPost $post): RedirectResponse
    {
        $tenant=$context->requireTenant();abort_unless((int)$post->tenant_id===(int)$tenant->id,404);$post->delete();return back()->with('status','Club news post removed.');
    }
}
