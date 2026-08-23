<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\ProductCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCompletionSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url'=>'http://platform.test','platform.root_domain'=>'platform.test','platform.central_domains'=>['platform.test'],'edition.name'=>'hosted']);
    }

    public function test_completion_schema_and_routes_are_available(): void
    {
        foreach (['favorites','saved_searches','user_connections','user_privacy_settings','reviews','tenant_member_tags','marketing_contacts','marketing_campaigns','referral_codes','merchant_accounts','marketplace_ledger_entries','payouts','refunds','membership_subscriptions','domain_orders','trust_cases'] as $table) $this->assertTrue(Schema::hasTable($table),$table.' is missing');
        foreach (['discover.index','clubs.show','onboarding.index','saved.index','notifications.index','privacy.index','connections.index','tenant.crm.index','tenant.commerce.index','tenant.domain-marketplace.index','tenant.builder.index','tenant.growth.index','admin.insights.index','admin.trust.index'] as $route) {
            $this->assertTrue(Route::has($route),$route.' route missing');
            $this->assertContains('web', Route::getRoutes()->getByName($route)->gatherMiddleware(), $route.' must inherit the web boundary');
        }
        foreach (['latitude','longitude','gallery','faq','hosts','updates'] as $column) $this->assertTrue(Schema::hasColumn('events',$column));
    }

    public function test_discovery_club_profile_and_favorites_work_together(): void
    {
        [$tenant,$domain,$owner]=$this->club('velvet.test');
        $event=Event::create(['tenant_id'=>$tenant->id,'title'=>'Velvet Social','slug'=>'velvet-social','summary'=>'Private social','description'=>'An evening for members and guests.','visibility'=>'public','rsvp_mode'=>'instant','status'=>'published','starts_at'=>now()->addDays(3),'timezone'=>'America/Chicago','city'=>'Dallas','region'=>'TX','category'=>'social']);
        $member=$this->user('member@example.test');
        $this->get('http://platform.test/discover?city=Dallas')->assertOk()->assertSee('Velvet Social')->assertSee('Lifestyle velvet');
        $this->get('http://platform.test/clubs/'.$tenant->slug)->assertOk()->assertSee($tenant->name)->assertSee('Visit club website');
        $this->actingAs($member)->post('http://platform.test/favorites/event/'.$event->id)->assertRedirect();
        $this->assertDatabaseHas('favorites',['user_id'=>$member->id,'target_type'=>'event','target_id'=>$event->id]);
        $this->actingAs($member)->get('http://platform.test/saved')->assertOk()->assertSee('Velvet Social');
    }

    public function test_privacy_connections_and_blocking_are_explicit(): void
    {
        $a=$this->user('a@example.test'); $b=$this->user('b@example.test');
        $this->actingAs($a)->patch('http://platform.test/privacy',['profile_visibility'=>'connections','messages_from'=>'connections','location_visibility'=>'city','memberships_visibility'=>'private','attendance_visibility'=>'private','online_visibility'=>'connections','read_receipts'=>'1'])->assertRedirect();
        $this->assertDatabaseHas('user_privacy_settings',['user_id'=>$a->id,'profile_visibility'=>'connections']);
        $this->actingAs($a)->post('http://platform.test/connections/'.$b->id)->assertRedirect();
        $connection=DB::table('user_connections')->first();
        $this->actingAs($b)->patch('http://platform.test/connections/'.$connection->id,['decision'=>'accept'])->assertRedirect();
        $this->assertDatabaseHas('user_connections',['id'=>$connection->id,'status'=>'accepted']);
        $this->actingAs($a)->post('http://platform.test/block/'.$b->id)->assertRedirect();
        $this->assertDatabaseHas('user_blocks',['user_id'=>$a->id,'blocked_user_id'=>$b->id]);
        $this->assertDatabaseMissing('user_connections',['id'=>$connection->id]);
    }

    public function test_marketing_contact_does_not_gain_consent_unless_explicitly_selected(): void
    {
        [$tenant,$domain,$owner]=$this->club('growth.test');
        $this->actingAs($owner)->post('http://'.$domain.'/manage/growth/contacts',['name'=>'Club Owner Lead','email'=>'lead@example.test'])->assertRedirect();
        $row=DB::table('marketing_contacts')->where('tenant_id',$tenant->id)->first();
        $this->assertNull($row->email_opt_in_at); $this->assertNull($row->sms_opt_in_at);
        $this->actingAs($owner)->post('http://'.$domain.'/manage/growth/campaigns',['name'=>'Welcome','channel'=>'email','subject'=>'Hello','body'=>'A consent-safe campaign.'])->assertRedirect();
        $campaign=DB::table('marketing_campaigns')->where('tenant_id',$tenant->id)->first();
        $this->assertSame(0,(int)data_get(json_decode($campaign->stats,true),'eligible'));
    }

    public function test_paid_order_generates_deterministic_platform_fee_ledger(): void
    {
        [$tenant,$domain,$owner]=$this->club('commerce.test');
        $settings=$tenant->settings; $settings['platform_fee_bps']=1000; $tenant->update(['settings'=>$settings]);
        $buyer=$this->user('buyer@example.test');
        $event=Event::create(['tenant_id'=>$tenant->id,'title'=>'Commerce Night','slug'=>'commerce-night','description'=>'Test','visibility'=>'public','rsvp_mode'=>'instant','status'=>'published','starts_at'=>now()->addDay(),'timezone'=>'UTC']);
        $order=Order::create(['public_id'=>(string)\Illuminate\Support\Str::uuid(),'tenant_id'=>$tenant->id,'event_id'=>$event->id,'user_id'=>$buyer->id,'status'=>'paid','currency'=>'USD','subtotal_cents'=>10000,'discount_cents'=>0,'fee_cents'=>0,'total_cents'=>10000]);
        app(ProductCompletionService::class)->recordPaidOrder($order);
        $this->assertDatabaseHas('marketplace_ledger_entries',['order_id'=>$order->id,'gross_cents'=>10000,'platform_fee_cents'=>1000,'tenant_net_cents'=>9000]);
        app(ProductCompletionService::class)->recordPaidOrder($order);
        $this->assertSame(1,DB::table('marketplace_ledger_entries')->where('order_id',$order->id)->count());
    }

    private function club(string $domain): array
    {
        $tenant=Tenant::create(['name'=>'Lifestyle '.str_replace('.test','',$domain),'slug'=>str_replace('.','-',$domain),'type'=>Tenant::TYPE_CLUB,'status'=>'active','plan'=>'starter','settings'=>['marketplace_enabled'=>true,'marketplace_summary'=>'Established private lifestyle club','city'=>'Dallas','adult_only'=>true]]);
        TenantDomain::create(['tenant_id'=>$tenant->id,'domain'=>$domain,'type'=>TenantDomain::TYPE_CUSTOM_DOMAIN,'is_primary'=>true,'status'=>TenantDomain::STATUS_ACTIVE,'verified_at'=>now(),'ssl_status'=>'active','dns_status'=>'active']);
        $owner=$this->user('owner-'.str_replace('.','-',$domain).'@example.test'); $tenant->users()->attach($owner->id,['role'=>'owner','status'=>'active']); return [$tenant,$domain,$owner];
    }
    private function user(string $email): User
    { return User::create(['name'=>'Lifestyle Member','display_name'=>'Lifestyle Member','email'=>$email,'password'=>'Password123','date_of_birth'=>now()->subYears(30)->toDateString(),'status'=>'active','adult_confirmed_at'=>now(),'terms_accepted_at'=>now(),'privacy_accepted_at'=>now(),'privacy_version'=>'1.0']); }
}
