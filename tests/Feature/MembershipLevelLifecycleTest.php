<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembershipApplication;
use App\Models\TenantMembershipLevel;
use App\Models\TenantMembershipTerm;
use App\Models\User;
use App\Services\BadgeService;
use App\Support\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipLevelLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url'=>'http://platform.test','platform.root_domain'=>'platform.test','platform.central_domains'=>['platform.test']]);
    }

    public function test_annual_tier_controls_member_access_and_tier_badge_without_affecting_operator_roles(): void
    {
        [$tenant,$domain,$owner]=$this->createClub('levels.test');$member=$this->createUser('member@levels.test','couple');$tenant->users()->attach($member->id,['role'=>'member','status'=>'active']);
        $annual=TenantMembershipLevel::create(['tenant_id'=>$tenant->id,'name'=>'VIP Annual','slug'=>'vip-annual','price_cents'=>12000,'currency'=>'USD','billing_interval'=>'annual','profile_eligibility'=>'couple','guest_limit'=>1,'event_discount_percent'=>10,'requires_approval'=>true,'is_default'=>false,'is_active'=>true,'sort_order'=>20,'benefits'=>['VIP events']]);
        $badge=Badge::create(['tenant_id'=>$tenant->id,'scope'=>Badge::SCOPE_TENANT,'name'=>'VIP Member','slug'=>'vip-member','category'=>'membership','visibility'=>'tenant_members','issuance_type'=>Badge::ISSUE_MEMBERSHIP,'criteria'=>['membership_level_id'=>$annual->id],'is_active'=>true,'created_by'=>$owner->id]);

        $this->actingAs($owner)->post('http://'.$domain.'/manage/memberships/assign',['email'=>$member->email,'membership_level_id'=>$annual->id])->assertRedirect();
        $term=TenantMembershipTerm::where('tenant_id',$tenant->id)->where('user_id',$member->id)->firstOrFail();
        $this->assertSame($annual->id,$term->membership_level_id);$this->assertTrue($term->expires_at->isAfter(now()->addMonths(11)));$this->assertTrue(TenantMembership::hasActiveMembership($member,$tenant->id));
        app(BadgeService::class)->syncForUser($member,$tenant->id);$this->assertDatabaseHas('user_badges',['badge_id'=>$badge->id,'user_id'=>$member->id,'tenant_id'=>$tenant->id,'revoked_at'=>null]);

        $this->actingAs($owner)->post('http://'.$domain.'/manage/memberships/'.$term->id.'/cancel',['when'=>'now','reason'=>'Member requested cancellation'])->assertRedirect();
        $this->assertFalse(TenantMembership::hasActiveMembership($member,$tenant->id));app(BadgeService::class)->syncForUser($member,$tenant->id);$this->assertNotNull($badge->assignments()->where('user_id',$member->id)->latest('id')->firstOrFail()->revoked_at);

        // Operator access remains role-based even if an accidental membership term exists and expires.
        TenantMembershipTerm::updateOrCreate(['tenant_id'=>$tenant->id,'user_id'=>$owner->id],['membership_level_id'=>$annual->id,'status'=>'expired','starts_at'=>now()->subYear(),'current_period_starts_at'=>now()->subYear(),'current_period_ends_at'=>now()->subDay(),'expires_at'=>now()->subDay(),'source'=>'staff']);
        $this->assertTrue(TenantMembership::hasActiveMembership($owner,$tenant->id));
    }

    public function test_profile_eligibility_renewal_and_cross_tenant_level_isolation_are_enforced(): void
    {
        [$tenantA,$domainA,$ownerA]=$this->createClub('club-a.test');[$tenantB]=$this->createClub('club-b.test');$individual=$this->createUser('individual@example.test','individual');$tenantA->users()->attach($individual->id,['role'=>'member','status'=>'active']);
        $coupleLevel=TenantMembershipLevel::create(['tenant_id'=>$tenantA->id,'name'=>'Couple Annual','slug'=>'couple-annual','price_cents'=>10000,'currency'=>'USD','billing_interval'=>'annual','profile_eligibility'=>'couple','guest_limit'=>0,'event_discount_percent'=>0,'requires_approval'=>true,'is_default'=>false,'is_active'=>true]);
        $otherLevel=TenantMembershipLevel::create(['tenant_id'=>$tenantB->id,'name'=>'Other Club VIP','slug'=>'other-vip','price_cents'=>0,'currency'=>'USD','billing_interval'=>'annual','profile_eligibility'=>'any','guest_limit'=>0,'event_discount_percent'=>0,'requires_approval'=>true,'is_default'=>true,'is_active'=>true]);
        $this->actingAs($ownerA)->from('http://'.$domainA.'/manage/membership-levels')->post('http://'.$domainA.'/manage/memberships/assign',['email'=>$individual->email,'membership_level_id'=>$coupleLevel->id])->assertRedirect('http://'.$domainA.'/manage/membership-levels')->assertSessionHasErrors('membership_level_id');
        $this->actingAs($ownerA)->post('http://'.$domainA.'/manage/memberships/assign',['email'=>$individual->email,'membership_level_id'=>$otherLevel->id])->assertNotFound();

        $individual->profile->update(['profile_type'=>'couple']);
        $this->actingAs($ownerA)->post('http://'.$domainA.'/manage/memberships/assign',['email'=>$individual->email,'membership_level_id'=>$coupleLevel->id])->assertRedirect();
        $term=TenantMembershipTerm::where('tenant_id',$tenantA->id)->where('user_id',$individual->id)->firstOrFail();$firstEnd=$term->expires_at->copy();
        $this->actingAs($ownerA)->post('http://'.$domainA.'/manage/memberships/'.$term->id.'/renew')->assertRedirect();
        $this->assertTrue($term->fresh()->expires_at->isAfter($firstEnd->addMonths(11)));
    }

    public function test_approved_application_receives_default_structured_membership_and_member_can_view_it(): void
    {
        [$tenant,$domain,$owner]=$this->createClub('approval-tier.test');$applicant=$this->createUser('applicant@example.test','couple');
        $default=TenantMembershipLevel::create(['tenant_id'=>$tenant->id,'name'=>'Standard Lifestyle Member','slug'=>'standard-lifestyle','price_cents'=>0,'currency'=>'USD','billing_interval'=>'none','profile_eligibility'=>'any','guest_limit'=>0,'event_discount_percent'=>0,'requires_approval'=>true,'is_default'=>true,'is_active'=>true]);
        $application=TenantMembershipApplication::create(['tenant_id'=>$tenant->id,'user_id'=>$applicant->id,'profile_type'=>'couple','status'=>'pending','introduction'=>'Adult couple applying for a private, respectful lifestyle community membership.','answers'=>['rules_acknowledged'=>true,'privacy_acknowledged'=>true]]);
        $this->actingAs($owner)->patch('http://'.$domain.'/manage/membership-applications/'.$application->id,['decision'=>'approved'])->assertRedirect();
        $this->assertDatabaseHas('tenant_membership_terms',['tenant_id'=>$tenant->id,'user_id'=>$applicant->id,'membership_level_id'=>$default->id,'status'=>'active']);
        $this->actingAs($applicant)->get('http://'.$domain.'/membership')->assertOk()->assertSee('Standard Lifestyle Member')->assertSee('My Membership');
    }

    private function createClub(string $domain):array
    {
        $tenant=Tenant::create(['name'=>'Lifestyle '.str_replace('.test','',$domain),'slug'=>str_replace('.','-',$domain),'type'=>Tenant::TYPE_CLUB,'status'=>'active','plan'=>'starter','settings'=>['marketplace_enabled'=>true,'market'=>'adult_lifestyle','adult_only'=>true]]);
        TenantDomain::create(['tenant_id'=>$tenant->id,'domain'=>$domain,'type'=>TenantDomain::TYPE_CUSTOM_DOMAIN,'is_primary'=>true,'status'=>TenantDomain::STATUS_ACTIVE,'verified_at'=>now(),'ssl_status'=>'active','dns_status'=>'active']);
        $owner=$this->createUser('owner-'.str_replace('.','-',$domain).'@example.test','individual');$tenant->users()->attach($owner->id,['role'=>'owner','status'=>'active']);return[$tenant,$domain,$owner];
    }

    private function createUser(string $email,string $profileType):User
    {
        $user=User::create(['name'=>'Lifestyle Member','display_name'=>'Lifestyle Member','email'=>$email,'password'=>'Password123','date_of_birth'=>now()->subYears(30)->toDateString(),'status'=>'active','adult_confirmed_at'=>now(),'terms_accepted_at'=>now(),'privacy_accepted_at'=>now(),'privacy_version'=>'1.0']);
        Profile::create(['user_id'=>$user->id,'profile_type'=>$profileType,'headline'=>'Lifestyle profile','visibility'=>[],'discoverable'=>true]);return$user;
    }
}
