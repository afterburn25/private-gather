# Development Roadmap

## 0.1 — Multi-Tenant Foundation

- Laravel 13 / PHP 8.3+ project foundation
- Central marketplace vs tenant-site request resolution
- Platform subdomains
- Custom domain records and DNS instruction service
- Tenant types: club, organizer, private host
- Tenant membership/role schema
- Event + RSVP schema
- CMS page/section schema
- Site settings + audit-log schema
- Tenant provisioning command
- Initial premium dark responsive UI
- Fail-closed unknown-domain handling


## 0.1.1 — Browser Installer & Root/Subdomain Deployment

- Standalone browser installer independent of Laravel boot
- Server requirement checks
- Automatic application URL/domain detection
- MySQL/MariaDB connection and optional database creation
- Complete initial schema injection
- Migration registry population for future Laravel migrations
- First platform administrator creation
- Automatic `.env` and application-key generation
- Install receipt and permanent installed lock
- Automatic `/install` deletion with disable/rename fallback
- Root-safe Apache front controller so `/public` is not exposed in URLs
- Direct domain or subdomain document-root installation, including `demo.privoralabs.com`


## 0.1.2 — Backend ZIP Upgrade Center (this package)

- Platform-administrator login for system operations
- Browser `/admin/upgrades` Update Center
- Version/product/PHP compatibility gates
- Per-file SHA-256 validation and protected path enforcement
- Optional Ed25519 package signature verification
- Exclusive upgrade lock and staging area
- Changed-file backups and MySQL/MariaDB database dump
- Maintenance mode while changes are applied
- Automatic Laravel migration execution from the release package
- Atomic file replacement and declared obsolete-file deletion
- Cache clearing and automatic return from maintenance mode
- File rollback on installation failure where safe
- Persistent upgrade history and machine-readable logs
- Administrator backup/log downloads
- Standalone future-upgrade ZIP package builder

## 0.2 — Accounts & Organizer Onboarding

- Registration/login/logout/password reset/email verification
- 18+ date-of-birth gate
- Member profiles
- Organizer/club/private-host onboarding wizard
- Owner/staff roles and policies
- Account status/suspension controls
- Complete admin dashboard/authentication hardening beyond the Update Center

## 0.3 — Event Management & RSVP

- Organizer event wizard
- Public/member/unlisted/invite-only visibility
- Instant/approval/application RSVP modes
- Capacity/waitlist
- Exact-location privacy
- Member RSVP dashboard
- Organizer attendee dashboard

## 0.4 — CMS & Website Builder

- Visual block/page editor
- Reusable sections
- Media library
- Menus, header, footer, colors, fonts, logo, favicon
- Placeholder/token engine
- Page revisions and preview
- Per-tenant SEO settings

## 0.5 — Custom Domain Automation

- DNS ownership verification worker
- TLS provider interface
- Automatic certificate provisioning
- Primary-domain switching
- WWW/root redirect controls
- Domain health checks

## 0.6 — Marketplace

- Event search and filters
- Club/organizer directories
- Favorites/following
- Marketplace visibility controls
- Location privacy rules

## 0.7 — Ticketing & Commerce

- Ticket types
- Orders
- Promo codes
- Refund state model
- Provider-neutral payment gateway interface
- Platform/organizer fee accounting

## 0.8 — Messaging, Moderation & Verification

- Organizer announcements
- Member message requests
- Blocks/reports
- Moderation cases
- Verification-provider abstraction
- Audit trails

## 0.9 — Production Operations

- Queue workers
- Redis option
- S3-compatible private media
- Backups
- observability
- rate limiting
- security review
- deployment recipes

## 1.0 — Commercial Launch Baseline

- End-to-end member, club, organizer, private-host workflows
- subscriptions/plans
- custom domains
- complete CMS
- tickets/RSVPs
- privacy/moderation tooling
- installer/deployment documentation
