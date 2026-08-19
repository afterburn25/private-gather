# Private Gather Unified Editions

Private Gather uses one shared application core and two runtime editions. This is intentionally not a fork: shared features, security fixes, database changes, and Upgrade Center releases remain on the same source line.

## Shared Core

Both editions use the same authentication, members/profiles, events, RSVP, invitations, messaging, tickets, check-in, CMS, media, branding, navigation, staff permissions, security/2FA, analytics, migrations, and upgrade engine.

## Hosted Edition

`PRIVATE_GATHER_EDITION=hosted`

Hosted Edition is the multi-organization Private Gather SaaS platform. It keeps tenant isolation, Create Website, My Sites, wildcard hosted subdomains, marketplace/discovery behavior, platform plans, and platform administration.

## Self-Hosted Edition

`PRIVATE_GATHER_EDITION=self_hosted`

Self-Hosted Edition is installed by one customer on hosting they control. The installer creates one organization, assigns the first administrator as owner, and records that tenant in `SELF_HOSTED_TENANT_ID`. All requests resolve to that one organization instead of looking up a tenant from a wildcard hostname.

Self-Hosted does not use Create Website or the multi-site switcher. The same `/manage` event, CMS, media, staff, branding, ticketing, and analytics tools operate on the single local organization.

### Privacy

`SELF_HOSTED_VISIBILITY=private` is the default. Anonymous visitors are redirected to login before organization or event content is rendered. Authentication and password-recovery pages remain reachable. `public` can be chosen for installations that want a public-facing club site.

### Registration

`SELF_HOSTED_REGISTRATION=approval` is the default. New members are created with pending account status and cannot log in until a local owner, administrator, or manager activates them in **Manage → Members & Approvals**.

The member-management screen is tenant-scoped and does not expose Hosted Edition platform administration. Local managers can review/search member-role accounts and change account status between pending, active, suspended, and banned. Users outside the Self-Hosted organization and privileged owner/admin/manager/staff accounts cannot be changed through the member endpoint.

Other modes:

- `open` — account is active immediately.
- `disabled` — public registration is unavailable.

### Local administration

The installer-created local administrator can use `/admin` for installation-level operations when that account is both marked as an installation administrator and has an active `owner` or `admin` membership in the configured Self-Hosted organization.

Self-Hosted Local Administration includes the local dashboard, user administration, moderation, System Health, and the Update Center. Hosted-only SaaS controls remain unavailable even by direct URL: Organizations, platform Plans, and Platform Website administration return 404 in Self-Hosted Edition.

This keeps system maintenance and upgrades available to the customer who owns the installation without turning a single-site deployment into the multi-tenant Hosted control plane.

## Release rule

Shared product work belongs in Core unless it is inherently edition-specific. Hosted-only infrastructure such as wildcard tenant DNS stays behind Hosted behavior. Self-Hosted-only deployment policy stays behind Self-Hosted behavior. CI runs Hosted runtime tests and a separate Self-Hosted policy/runtime lane on every branch and pull request.

The Upgrade Center product ID remains `privategather/private-gather` for both editions so a shared release can deliver compatible Core updates to both products.
