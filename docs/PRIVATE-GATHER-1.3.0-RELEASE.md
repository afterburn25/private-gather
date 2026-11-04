# Private Gather 1.3.0 — Product Completion Release

Private Gather 1.3.0 is the product-completion milestone for the redesign lineage based on the certified Private Gather Redesign 1.0.0 checkpoint.

## Release scope
This release brings the redesigned marketplace, member experience, event experience, Club OS, tenant website tools, commerce operations, trust/safety operations, mobile/PWA behavior, production readiness, and failure-state polish into one synchronized source tree.

Key release areas include advanced discovery; complete club profiles; member onboarding, privacy, favorites, connections and community-scoped member discovery; rich event hosts/schedules/galleries/FAQ/updates; explicit browser push enrollment; membership/subscription lifecycle; CRM; split-fee accounting, refunds and payouts; domain workflow; accessible website-builder ordering; tenant and platform theme previews; consent-aware growth tooling; Trust & Safety escalation; showcase data; hardened error states; system health; smoke tooling; and production operations documentation.

## Privacy and safety boundaries
- Member discovery is tenant/community scoped rather than a global people directory.
- Marketing consent is channel-specific and explicit; manually entered contacts do not become consented automatically.
- Push enrollment is explicit per browser and stored subscription material is encrypted at rest.
- Lock-screen push content is intentionally generic.
- Private event coordinates and exact venue data are not exposed outside configured access conditions.
- Trust escalation does not copy raw identity-verification evidence into trust cases.
- Hosted and Self-Hosted packages never intentionally include a live `.env`, installed lock, or mutable deployment state.

## Deployment presets
Private Gather remains one product. `hosted` and `self_hosted` are deployment presets used to construct synchronized release archives from the same exact Core commit; they are not separate customer-facing product editions.

## Production requirements
Before a production deployment, follow `docs/PRODUCTION-OPERATIONS.md` and validate database backups/restore, environment secrets, HTTPS/session security, queues, mail, payment provider state, domain/TLS automation, monitoring/observability, object storage/CDN as applicable, worker supervision, capacity, and incident procedures.

## Certification rule
Only artifacts produced from the exact 1.3.0 source commit after every required CI/package gate succeeds should be distributed as certified 1.3.0 archives. SHA-256 values are generated and independently checked after artifact download.
