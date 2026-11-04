# Private Gather — Foundation Architecture

## Product model

One installation serves three tenant types:

- Club / venue
- Independent event organizer
- Private host

Every tenant receives a platform subdomain immediately and may later attach one or more custom domains. The incoming HTTP `Host` is resolved to a `tenant_domains` row, then to exactly one tenant. Unknown non-central hosts fail closed with a 404 instead of falling through to another tenant.

## Domain model

Supported domain types:

- `platform_subdomain` — e.g. `sunset.privategather.com`
- `custom_domain` — e.g. `sunsetclub.com`
- `custom_subdomain` — e.g. `events.sunsetclub.com`

The application stores domain verification and TLS status but does not hard-code a DNS/TLS vendor. Infrastructure adapters can be added later for Caddy, Cloudflare for SaaS, or another provider.

## Tenant request flow

```text
Browser request
    ↓
Host header
    ↓
ResolveTenantByDomain middleware
    ├── central domain → marketplace context
    ├── known active tenant domain → tenant context
    └── unknown/inactive host → fail closed (404)
    ↓
Controller
    ↓
Tenant-scoped query
```

## Data boundaries

Every business-owned record that can differ by client must carry a tenant boundary. The initial foundation includes tenant IDs on events, CMS pages, settings, memberships, and audit logs. Future tables for orders, tickets, media, conversations, galleries, and staff permissions should follow the same rule.

## CMS foundation

A tenant homepage is stored as `cms_pages` + ordered `cms_sections`. New tenants receive editable starter sections:

1. Hero
2. Upcoming event grid
3. Membership CTA

The next CMS milestone adds an authenticated visual editor, media library, reusable blocks, page revisions, and placeholder/token validation.

## Security foundation

- Unknown customer domains fail closed.
- Exact event address has a separate field and visibility rule.
- Reserved platform subdomains cannot be provisioned.
- Tenant domain uniqueness is enforced by the database.
- Platform root/central domains cannot be claimed as custom tenant domains.
- Custom domains start `pending`, not active.
- Verification tokens and TLS state are first-class domain records.
- Authentication and authorization are intentionally not faked in this milestone; they are the next backend layer.
