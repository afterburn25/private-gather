<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembershipLevel;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCompletionOperationalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url'=>'http://platform.test','platform.root_domain'=>'platform.test','platform.central_domains'=>['platform.test']]);
    }

    public function test_private_event_coordinates_are_cleared_while_structured_experience_is_saved(): void
    {
        [$tenant,$host,$owner]=$this->tenant('experience.test');
        $event=Event::create(['tenant_id'=>$tenant->id,'title'=>'Experience Night','slug'=>'experience-night','visibility'=>'public','rsvp_mode'=>'instant','status'=>'draft','starts_at'=>now()->addDay(),'timezone'=>'UTC','exact_address_visibility'=>'approved_attendees']);
        $response=$this->actingAs($owner)->put('http://'.$host.'/manage/events/'.$event->id,[
            'title'=>'Experience Night','slug'=>'experience-night','visibility'=>'public','rsvp_mode'=>'instant','status'=>'published','starts_at'=>now()->addDay()->format('Y-m-d H:i:s'),'timezone'=>'UTC','exact_address_visibility'=>'approved_attendees',
            'latitude'=>'32.7767000','longitude'=>'-96.7970000','hosts_text'=>"Alex|Host\nJordan|Hospitality",'schedule_text'=>"8:00 PM|Doors\n9:00 PM|Social",'faq_text'=>'Parking?|Use the public garage.','updates_text'=>'Welcome|Review the house rules.','gallery_urls'=>"/images/one.webp\nhttps://cdn.example.test/two.webp",
        ]);
        $response->assertRedirect();
        $event->refresh();
        $this->assertNull($event->latitude); $this->assertNull($event->longitude);
        $this->assertSame('Alex',$event->hosts[0]['name']);
        $this->assertSame('Doors',$event->schedule[0]['label']);
        $this->assertSame('Parking?',$event->faq[0]['question']);
        $this->assertSame('Welcome',$event->updates[0]['title']);
        $this->assertCount(2,$event->gallery);
    }

    public function test_cms_sections_can_be_reordered_without_drag_and_drop_and_stay_tenant_scoped(): void
    {
        [$tenant,$host,$owner]=$this->tenant('builder.test');
        $page=CmsPage::create(['tenant_id'=>$tenant->id,'slug'=>'home','title'=>'Home','status'=>'published','is_homepage'=>true]);
        $first=CmsSection::create(['cms_page_id'=>$page->id,'type'=>'hero','name'=>'Hero','content'=>[],'settings'=>[],'sort_order'=>10,'is_enabled'=>true]);
        $second=CmsSection::create(['cms_page_id'=>$page->id,'type'=>'cta','name'=>'Join','content'=>[],'settings'=>[],'sort_order'=>20,'is_enabled'=>true]);
        $this->actingAs($owner)->post('http://'.$host.'/manage/cms/sections/'.$second->id.'/move/up')->assertRedirect();
        $this->assertSame(20,(int)$first->fresh()->sort_order);
        $this->assertSame(10,(int)$second->fresh()->sort_order);

        [$other,$otherHost,$otherOwner]=$this->tenant('other-builder.test');
        $otherPage=CmsPage::create(['tenant_id'=>$other->id,'slug'=>'home','title'=>'Other','status'=>'published','is_homepage'=>true]);
        $foreign=CmsSection::create(['cms_page_id'=>$otherPage->id,'type'=>'hero','name'=>'Foreign','content'=>[],'settings'=>[],'sort_order'=>10,'is_enabled'=>true]);
        $this->actingAs($owner)->post('http://'.$host.'/manage/cms/sections/'.$foreign->id.'/move/down')->assertNotFound();
    }

    public function test_push_subscription_material_is_encrypted_at_rest(): void
    {
        config(['services.webpush.public_key'=>'BElongFakePublicKeyForTestOnly']);
        $user=$this->user('push@example.test');
        $endpoint='https://push.example.test/subscription/abc';
        $this->actingAs($user)->postJson('http://platform.test/notifications/push',['endpoint'=>$endpoint,'p256dh'=>'public-key-material','auth'=>'auth-secret'])->assertCreated();
        $row=DB::table('push_subscriptions')->where('user_id',$user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(hash('sha256',$endpoint),$row->endpoint_hash);
        $this->assertNotSame($endpoint,$row->endpoint);
        $this->assertStringNotContainsString('auth-secret',$row->auth_token);
    }

    public function test_complimentary_subscription_creates_active_term_and_subscription(): void
    {
        [$tenant,$host,$owner]=$this->tenant('membership-billing.test');
        $member=$this->user('member-billing@example.test'); $tenant->users()->attach($member->id,['role'=>'member','status'=>'active']);
        $level=TenantMembershipLevel::create(['tenant_id'=>$tenant->id,'name'=>'Gold','slug'=>'gold','description'=>'Gold member','price_cents'=>5000,'currency'=>'USD','billing_interval'=>'monthly','profile_eligibility'=>'any','guest_limit'=>0,'event_discount_percent'=>0,'requires_approval'=>false,'is_default'=>true,'is_active'=>true,'sort_order'=>10,'benefits'=>['Member access']]);
        $this->actingAs($owner)->post('http://'.$host.'/manage/commerce/subscriptions',['user_id'=>$member->id,'membership_level_id'=>$level->id,'complimentary'=>'1'])->assertRedirect();
        $this->assertDatabaseHas('membership_subscriptions',['tenant_id'=>$tenant->id,'user_id'=>$member->id,'membership_level_id'=>$level->id,'status'=>'active','amount_cents'=>0]);
        $this->assertDatabaseHas('tenant_membership_terms',['tenant_id'=>$tenant->id,'user_id'=>$member->id,'membership_level_id'=>$level->id,'status'=>'active']);
    }

    public function test_verification_escalation_never_copies_sensitive_metadata_into_trust_case(): void
    {
        $admin=$this->user('platform-admin@example.test',true);
        $subject=$this->user('verify@example.test');
        $verification=Verification::create(['user_id'=>$subject->id,'type'=>'age_identity','status'=>'failed','provider'=>'test-provider','provider_reference'=>'ref-123','metadata'=>['raw_document'=>'DO-NOT-COPY','provider_payload'=>'SECRET']]);
        $this->actingAs($admin)->post('http://platform.test/admin/trust-safety/verifications/'.$verification->id.'/escalate',['priority'=>'high'])->assertRedirect();
        $case=DB::table('trust_cases')->where('subject_user_id',$subject->id)->where('type','verification_review')->first();
        $this->assertNotNull($case);
        $this->assertStringNotContainsString('DO-NOT-COPY',(string)$case->notes);
        $this->assertStringNotContainsString('SECRET',(string)$case->notes);
        $this->assertStringContainsString('Verification #'.$verification->id,(string)$case->notes);
    }

    private function tenant(string $domain): array
    {
        $tenant=Tenant::create(['name'=>'Club '.str_replace('.test','',$domain),'slug'=>str_replace('.','-',$domain),'type'=>Tenant::TYPE_CLUB,'status'=>'active','plan'=>'starter','settings'=>['marketplace_enabled'=>true,'adult_only'=>true]]);
        TenantDomain::create(['tenant_id'=>$tenant->id,'domain'=>$domain,'type'=>TenantDomain::TYPE_CUSTOM_DOMAIN,'is_primary'=>true,'status'=>TenantDomain::STATUS_ACTIVE,'verified_at'=>now(),'ssl_status'=>'active','dns_status'=>'active']);
        $owner=$this->user('owner-'.str_replace('.','-',$domain).'@example.test'); $tenant->users()->attach($owner->id,['role'=>'owner','status'=>'active']);
        return [$tenant,$domain,$owner];
    }

    private function user(string $email,bool $admin=false): User
    {
        return User::create(['name'=>'Test Member','display_name'=>'Test Member','email'=>$email,'password'=>'Password123!','date_of_birth'=>now()->subYears(30)->toDateString(),'status'=>'active','is_platform_admin'=>$admin,'adult_confirmed_at'=>now(),'terms_accepted_at'=>now(),'privacy_accepted_at'=>now(),'privacy_version'=>'1.0']);
    }
}
