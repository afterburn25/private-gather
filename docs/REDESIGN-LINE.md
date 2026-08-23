# Private Gather Redesign Line

This branch is the independent full-product redesign lineage for Private Gather.

## Source lineage

Recovered from the prior tenant/luxury redesign work and continued from `feature/tenant-websites-blueprint-next`.

## Hard rules

- This redesign has its own version train beginning at `0.1.0` and ending at `1.0.0`.
- Do not import release/certification rules from the separate Private Gather 1.2.x stabilization line automatically.
- Do not merge the 1.2.x repair branch wholesale into this redesign line.
- Preserve working Laravel/backend behavior unless a redesign requirement needs a backend adjustment.
- Keep the public repository `main` branch untouched until the redesign is intentionally released.
- Build for swingers/lifestyle clubs, organizers, private groups and their consenting adult members.
- Privacy, discretion and tenant isolation are product requirements, not decorative copy.
- Tenant organizations retain branded `example.privategather.com` sites with optional custom domains.
- Sensitive data must never become public merely because a screen is redesigned.
- Mobile is a first-class interface, not a shrunken desktop afterthought.

## Product direction

Private Gather becomes a unified social and operating platform for private lifestyle communities: discovery, events, clubs, groups, profiles, messaging, membership operations, check-in, branded tenant sites and mobile/PWA access.

## Version train

- 0.1.x — shell, navigation and design system
- 0.2.x — discovery and event presentation
- 0.3.x — clubs and groups
- 0.4.x — profiles, identity and trust presentation
- 0.5.x — messaging and community experience
- 0.6.x — Club OS / operator workspace
- 0.7.x — Door Mode and tenant-site presentation
- 0.8.x — mobile and PWA
- 0.9.x — consistency, accessibility and regression hardening
- 1.0.0 — completed redesign release candidate
