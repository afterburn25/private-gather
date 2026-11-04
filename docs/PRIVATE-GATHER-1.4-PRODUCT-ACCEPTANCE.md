# Private Gather 1.4 — Product Acceptance Contract

Private Gather 1.3.6 is retained as the last certified technical baseline, but it is **not** considered visually/product-complete. This document is the release gate for the reconciliation line.

A feature is not accepted merely because a controller, route, table or placeholder view exists. It must be reachable from the correct workspace, render a usable interface, preserve tenant/privacy boundaries, work on desktop/mobile, and have regression coverage.

## Visual acceptance

The target is the previously approved luxury Private Gather direction:
- dark black/charcoal foundation;
- gold crest/brand treatment;
- purple/plum interaction accents;
- cinematic nightlife/lounge photography rather than generic geometric placeholders;
- polished public homepage, Discover experience, event cards, club websites and operator workspaces;
- responsive mobile/PWA presentation;
- official Private Gather logo must render from packaged local assets with root/subdirectory compatibility.

The generic 1.3.6 showcase SVGs are compatibility placeholders only and are not the final visual standard.

## Member workspace — required

- Home/dashboard
- Discover
- Events
- Clubs
- Groups/community
- Members discovery where permitted
- Messages and live/polling updates
- Saved favorites and saved searches
- Notifications center
- Browser push enrollment/removal and notification preferences
- Profile
- Security / active sessions / 2FA
- Privacy Control Center
- Connections / follows / blocks
- My memberships
- My badges
- Tickets and orders
- Invitations
- Onboarding/preferences
- Reviews/feedback
- Mobile bottom navigation and PWA installability

## Public marketplace — required

- Luxury homepage with real packaged photography
- Event discovery/search/filtering
- Club/organization directory
- Rich club profile pages
- Rich event pages
- Calendar/ICS export
- Public/private location rules
- Referral capture
- Trust/privacy messaging
- Clearly fictional showcase data on demo/fresh Hosted installations
- At least 10 fictional showcase clubs and 20 future events
- Local packaged cover/gallery imagery; no broken remote dependencies

## Club / organization websites — required

- Automatic hosted subdomain
- Custom domain support and verification
- Optional domain search/registration workflow
- Branded tenant mini-site
- Logo/colors/theme/font controls
- Navigation editor
- Media library
- CMS pages and reusable sections
- Website Builder 2.0/template workflow
- Club home/about/events/membership/rules/photos/contact presentation
- Tenant theme selection across the ten approved tenant themes
- Custom domain and subdomain-safe URLs/assets

## Club OS / operator workspace — required

- Dashboard
- Calendar / event lifecycle
- Members and guests
- Membership applications/approvals
- Membership levels/terms/renewals/cancellations
- Tickets and orders
- Payment/refund state
- Commerce dashboard
- Merchant/payout settings
- Split-ledger / platform fee visibility
- Check-in / QR/manual admission
- Door Mode
- Attendees, walk-ins and waitlist workflows
- Event capacity/admission rules
- Event invitations and screening questions
- Staff and scoped permissions
- Club badges and assignments
- Communications/announcements surface
- CRM tags and private notes
- Reviews and operator responses
- Reports/analytics/export
- Growth/referrals/contact lists/campaigns with consent controls
- Domains
- Website Builder/CMS/branding/media/navigation/site settings
- Workspace switching between member and operator contexts

## Events — required

- Draft/published lifecycle
- Start/end time and timezone
- Venue/location privacy controls
- Capacity
- Audience/admission eligibility
- RSVP and cancellation
- Waitlist and promotion
- Ticket types/inventory
- Orders/payments/refunds
- Private invitations/revocation
- Screening questions
- Hosts/co-host presentation
- Schedule/run of show
- Galleries
- FAQ
- Updates/announcements
- Cover image
- Add-to-calendar export
- Check-in and Door Mode

## Community — required

- Groups
- Group membership/approval
- Posts/comments/reactions
- Media-aware community presentation where allowed
- Community chat
- Direct conversations
- Mute/polling updates
- Reporting/moderation boundaries

## Trust, badges and privacy — required

- Identity/age/verification-related badge support without exposing raw identity documents
- Global badges
- Club badges
- Assignment/revocation rules
- Trust & Safety cases/escalation
- Report moderation
- Privacy requests operations queue
- Member visibility controls
- Blocking
- Connection/follow visibility boundaries
- No public DOB, identity documents, private addresses or verification evidence
- Security activity/session revocation

## Platform administration — required

- Dashboard
- Users
- Clubs & organizations
- Plans
- Platform website/content
- Platform theme manager for exactly five platform themes
- Global badge manager
- Moderation
- Trust & Safety
- Privacy requests
- Product/platform insights and analytics
- System health/readiness
- Update center
- Production operations/readiness documentation

## Payments and domains — required

- Provider-neutral payment architecture
- Order ledger
- Platform fee / operator net split accounting
- Refund workflow
- Merchant/payout state
- Membership subscription lifecycle
- Domain availability workflow
- Registration workflow that fails closed when no registrar is configured
- Existing-domain path
- Hosted subdomain path
- Custom-domain verification
- DNS/SSL readiness truthfulness

## Production/PWA — required

- Hosted/Self-Hosted bases remain separate products/installers
- Hosted installer contains no Self-Hosted selector/jargon
- Runtime directories self-repair safely without 0777
- Database transport auto-detection
- Required asset preflight
- Install directory removes itself only after permanent install lock/receipt succeeds
- Static asset compatibility for root/subdirectory hosting
- Service worker avoids caching authenticated HTML
- Offline page
- Push notification click handling
- Health/readiness checks
- Error pages 403/404/419/429/500/503
- Backup/restore/migration/queue/monitoring release documentation

## Release rule

Private Gather 1.4 may not be promoted to the certified redesign base until:
1. every item above is either implemented or explicitly removed from scope by the product owner;
2. all promised capabilities are reachable from the UI rather than hidden behind unlinked routes;
3. the approved visual direction is represented with real packaged imagery and the official logo;
4. Hosted/Self-Hosted, PHP 8.3, migration, structural, privacy/security and package gates are green;
5. the generated Hosted package passes an independent post-package inspection; and
6. a staging visual/browser acceptance pass is completed before production cutover.
