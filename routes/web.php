<?php
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlatformContentController;
use App\Http\Controllers\Admin\PlatformDashboardController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Admin\UpgradeController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\MemberAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EventPublicController;
use App\Http\Controllers\EventInvitationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MembershipApplicationController;
use App\Http\Controllers\Member\DashboardController;
use App\Http\Controllers\Member\MessageController;
use App\Http\Controllers\Member\OrderController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\SecurityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RsvpController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\Tenant\AnalyticsController;
use App\Http\Controllers\Tenant\BrandingController;
use App\Http\Controllers\Tenant\CheckinController;
use App\Http\Controllers\Tenant\CmsController;
use App\Http\Controllers\Tenant\DomainController;
use App\Http\Controllers\Tenant\EventManageController;
use App\Http\Controllers\Tenant\ManageController;
use App\Http\Controllers\Tenant\MembershipApplicationManageController;
use App\Http\Controllers\Tenant\NavigationController;
use App\Http\Controllers\Tenant\SiteSettingsController;
use App\Http\Controllers\Tenant\MediaController;
use App\Http\Controllers\Tenant\OrderManageController;
use App\Http\Controllers\Tenant\OrganizationController;
use App\Http\Controllers\Tenant\PlaceholderController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\TicketManageController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantManager;
use App\Http\Middleware\EnsureTenantStaff;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class,'home'])->name('site.home');
Route::get('/events', [SiteController::class,'events'])->name('site.events');
Route::get('/events/{event}', [EventPublicController::class,'show'])->whereNumber('event')->name('events.show');
Route::get('/organizations', [SiteController::class,'organizations'])->name('site.organizations');
Route::get('/about',[SiteController::class,'about'])->name('site.about');
Route::get('/page/{slug}', [SiteController::class,'page'])->where('slug','[A-Za-z0-9_-]+')->name('site.page');
Route::get('/health', HealthController::class)->name('health');

Route::middleware('guest')->group(function():void{
 Route::get('/login',[MemberAuthController::class,'loginForm'])->name('login');
 Route::post('/login',[MemberAuthController::class,'login'])->middleware('throttle:10,1')->name('login.store');
 Route::get('/register',[MemberAuthController::class,'registerForm'])->name('register');
 Route::post('/register',[MemberAuthController::class,'register'])->middleware('throttle:6,1')->name('register.store');
 Route::get('/forgot-password',[PasswordResetController::class,'requestForm'])->name('password.request');
 Route::post('/forgot-password',[PasswordResetController::class,'send'])->middleware('throttle:5,1')->name('password.email');
 Route::get('/reset-password/{token}',[PasswordResetController::class,'resetForm'])->name('password.reset');
 Route::post('/reset-password',[PasswordResetController::class,'reset'])->name('password.update');
 Route::get('/two-factor-challenge',[TwoFactorChallengeController::class,'create'])->name('two-factor.challenge');
 Route::post('/two-factor-challenge',[TwoFactorChallengeController::class,'store'])->middleware('throttle:8,1')->name('two-factor.challenge.store');
});

Route::middleware('auth')->group(function():void{
 Route::post('/logout',[MemberAuthController::class,'logout'])->name('logout');
 Route::get('/dashboard',DashboardController::class)->name('dashboard');
 Route::get('/profile',[ProfileController::class,'edit'])->name('profile.edit');
 Route::put('/profile',[ProfileController::class,'update'])->name('profile.update');

 Route::get('/email/verify',[EmailVerificationController::class,'notice'])->name('verification.notice');
 Route::get('/email/verify/{id}/{hash}',[EmailVerificationController::class,'verify'])->middleware('signed:relative')->name('verification.verify');
 Route::post('/email/verification-notification',[EmailVerificationController::class,'send'])->middleware('throttle:6,1')->name('verification.send');

 Route::get('/security',[SecurityController::class,'index'])->name('member.security');
 Route::post('/security/two-factor',[SecurityController::class,'beginTwoFactor'])->name('member.security.2fa.begin');
 Route::post('/security/two-factor/confirm',[SecurityController::class,'confirmTwoFactor'])->name('member.security.2fa.confirm');
 Route::delete('/security/two-factor',[SecurityController::class,'disableTwoFactor'])->name('member.security.2fa.disable');
 Route::post('/security/data-request',[SecurityController::class,'dataRequest'])->name('member.security.data');

 Route::get('/my-organizations',[OrganizationController::class,'index'])->name('organizations.index');
 Route::get('/my-organizations/create',[OrganizationController::class,'create'])->name('organizations.create');
 Route::post('/my-organizations',[OrganizationController::class,'store'])->name('organizations.store');
 Route::get('/staff-invite/{token}',[StaffController::class,'accept'])->name('staff.invite.accept');

 Route::get('/membership/apply',[MembershipApplicationController::class,'create'])->name('membership.apply');
 Route::post('/membership/apply',[MembershipApplicationController::class,'store'])->name('membership.apply.store');

 Route::get('/messages',[MessageController::class,'index'])->name('messages.index');
 Route::post('/messages/start',[MessageController::class,'start'])->name('messages.start');
 Route::get('/messages/{conversation}',[MessageController::class,'show'])->name('messages.show');
 Route::post('/messages/{conversation}',[MessageController::class,'store'])->name('messages.store');

 Route::get('/orders',[OrderController::class,'index'])->name('member.orders.index');
 Route::get('/orders/{order}',[OrderController::class,'show'])->name('member.orders.show');
 Route::get('/tickets',[OrderController::class,'tickets'])->name('member.tickets');

 Route::post('/events/{event}/rsvp',[RsvpController::class,'store'])->whereNumber('event')->name('rsvp.store');
 Route::delete('/events/{event}/rsvp',[RsvpController::class,'cancel'])->whereNumber('event')->name('rsvp.cancel');
 Route::post('/events/{event}/tickets/{ticketType}',[CheckoutController::class,'store'])->whereNumber(['event','ticketType'])->name('checkout.store');
 Route::post('/reports',[ReportController::class,'store'])->middleware('throttle:10,1')->name('reports.store');
 Route::get('/event-invite/{token}',[EventInvitationController::class,'show'])->middleware('throttle:30,1')->name('event-invitations.show');
 Route::post('/event-invite/{token}',[EventInvitationController::class,'accept'])->middleware('throttle:10,1')->name('event-invitations.accept');
});

Route::middleware(['auth',EnsureTenantManager::class])->prefix('manage')->name('tenant.')->group(function():void{
 Route::get('/',[ManageController::class,'index'])->name('dashboard');
 Route::get('/domains',[DomainController::class,'index'])->name('domains.index');
 Route::post('/domains',[DomainController::class,'store'])->name('domains.store');
 Route::post('/domains/{domain}/verify',[DomainController::class,'verify'])->name('domains.verify');
 Route::post('/domains/{domain}/primary',[DomainController::class,'primary'])->name('domains.primary');

 Route::get('/membership-applications',[MembershipApplicationManageController::class,'index'])->name('membership-applications.index');
 Route::patch('/membership-applications/{application}',[MembershipApplicationManageController::class,'update'])->name('membership-applications.update');

 Route::get('/cms/pages',[CmsController::class,'pages'])->name('cms.pages');
 Route::get('/cms/pages/create',[CmsController::class,'create'])->name('cms.create');
 Route::post('/cms/pages',[CmsController::class,'store'])->name('cms.store');
 Route::get('/cms/pages/{page}/edit',[CmsController::class,'edit'])->name('cms.edit');
 Route::put('/cms/pages/{page}',[CmsController::class,'update'])->name('cms.update');
 Route::post('/cms/pages/{page}/sections',[CmsController::class,'addSection'])->name('cms.sections.store');
 Route::put('/cms/sections/{section}',[CmsController::class,'updateSection'])->name('cms.sections.update');
 Route::delete('/cms/sections/{section}',[CmsController::class,'deleteSection'])->name('cms.sections.destroy');
 Route::get('/cms/placeholders',[PlaceholderController::class,'index'])->name('cms.placeholders');

 Route::get('/site-settings',[SiteSettingsController::class,'edit'])->name('site-settings.edit');
 Route::patch('/site-settings',[SiteSettingsController::class,'update'])->name('site-settings.update');
 Route::get('/navigation',[NavigationController::class,'index'])->name('navigation.index');
 Route::post('/navigation',[NavigationController::class,'store'])->name('navigation.store');
 Route::patch('/navigation/{item}',[NavigationController::class,'update'])->name('navigation.update');
 Route::delete('/navigation/{item}',[NavigationController::class,'destroy'])->name('navigation.destroy');
 Route::get('/branding',[BrandingController::class,'edit'])->name('branding.edit');
 Route::patch('/branding',[BrandingController::class,'update'])->name('branding.update');
 Route::get('/media',[MediaController::class,'index'])->name('media.index');
 Route::post('/media',[MediaController::class,'store'])->name('media.store');
 Route::get('/media/{asset}',[MediaController::class,'show'])->name('media.show');
 Route::delete('/media/{asset}',[MediaController::class,'destroy'])->name('media.destroy');

 Route::get('/events',[EventManageController::class,'index'])->name('events.index');
 Route::get('/events/create',[EventManageController::class,'create'])->name('events.create');
 Route::post('/events',[EventManageController::class,'store'])->name('events.store');
 Route::get('/events/{event}/edit',[EventManageController::class,'edit'])->name('events.edit');
 Route::put('/events/{event}',[EventManageController::class,'update'])->name('events.update');
 Route::post('/events/{event}/duplicate',[EventManageController::class,'duplicate'])->name('events.duplicate');
 Route::delete('/events/{event}',[EventManageController::class,'destroy'])->name('events.destroy');
 Route::post('/events/{event}/questions',[EventManageController::class,'addQuestion'])->name('events.questions.store');
 Route::delete('/events/{event}/questions/{question}',[EventManageController::class,'deleteQuestion'])->name('events.questions.destroy');
 Route::post('/events/{event}/invitations',[EventManageController::class,'invite'])->name('events.invitations.store');
 Route::delete('/events/{event}/invitations/{invitation}',[EventManageController::class,'revokeInvite'])->name('events.invitations.revoke');
 Route::get('/events/{event}/attendees',[EventManageController::class,'attendees'])->name('events.attendees');
 Route::patch('/events/{event}/rsvps/{rsvp}',[EventManageController::class,'rsvpStatus'])->name('events.rsvp-status');
 Route::post('/events/{event}/ticket-types',[TicketManageController::class,'store'])->name('ticket-types.store');

 Route::get('/orders',[OrderManageController::class,'index'])->name('orders.index');
 Route::post('/orders/{order}/paid',[OrderManageController::class,'markPaid'])->name('orders.paid');
 Route::get('/analytics',[AnalyticsController::class,'index'])->name('analytics.index');
 Route::get('/analytics/export',[AnalyticsController::class,'export'])->name('analytics.export');
 Route::get('/staff',[StaffController::class,'index'])->name('staff.index');
 Route::post('/staff/invite',[StaffController::class,'invite'])->name('staff.invite');
 Route::delete('/staff/{user}',[StaffController::class,'remove'])->name('staff.remove');
});

Route::middleware(['auth',EnsureTenantStaff::class])->prefix('manage')->name('tenant.')->group(function():void{
 Route::get('/events/{event}/check-in',[CheckinController::class,'index'])->name('checkin.index');
 Route::post('/events/{event}/check-in/manual',[CheckinController::class,'manual'])->name('checkin.manual');
 Route::post('/events/{event}/check-in/ticket',[CheckinController::class,'ticket'])->name('checkin.ticket');
});

Route::middleware('throttle:10,1')->group(function():void{
 Route::get('/admin/login',[AdminAuthController::class,'create'])->name('admin.login');
 Route::post('/admin/login',[AdminAuthController::class,'store'])->name('admin.login.store');
});
Route::middleware(EnsurePlatformAdmin::class)->prefix('admin')->name('admin.')->group(function():void{
 Route::get('/',PlatformDashboardController::class)->name('home');
 Route::post('/logout',[AdminAuthController::class,'destroy'])->name('logout');
 Route::get('/users',[AdminUserController::class,'index'])->name('users.index');
 Route::patch('/users/{user}',[AdminUserController::class,'update'])->name('users.update');
 Route::get('/organizations',[AdminTenantController::class,'index'])->name('tenants.index');
 Route::patch('/organizations/{tenant}',[AdminTenantController::class,'update'])->name('tenants.update');
 Route::get('/plans',[PlanController::class,'index'])->name('plans.index');
 Route::get('/website-content',[PlatformContentController::class,'edit'])->name('content.edit');
 Route::patch('/website-content',[PlatformContentController::class,'update'])->name('content.update');
 Route::post('/plans',[PlanController::class,'store'])->name('plans.store');
 Route::patch('/plans/{plan}',[PlanController::class,'update'])->name('plans.update');
 Route::get('/moderation',[ModerationController::class,'index'])->name('moderation.index');
 Route::patch('/moderation/{report}',[ModerationController::class,'update'])->name('moderation.update');
 Route::get('/system-health',[SystemHealthController::class,'index'])->name('health.index');
 Route::get('/system-health.json',[SystemHealthController::class,'json'])->name('health.json');
 Route::get('/upgrades',[UpgradeController::class,'index'])->name('upgrades.index');
 Route::post('/upgrades',[UpgradeController::class,'store'])->name('upgrades.store');
 Route::get('/upgrades/{upgrade}/log',[UpgradeController::class,'log'])->name('upgrades.log');
 Route::get('/upgrades/{upgrade}/backup',[UpgradeController::class,'backup'])->name('upgrades.backup');
});
