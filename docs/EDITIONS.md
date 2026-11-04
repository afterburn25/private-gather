# Private Gather Deployment Bases

Private Gather is distributed as two **separate deployment bases** with separate browser installers:

- **Hosted Base** — multi-organization Private Gather platform.
- **Self-Hosted Base** — single-organization installation for one club, organizer, or private host.

There is no edition selector in either deployable installer. The user downloads the base they intend to run.

Internally, compatible application code remains maintained from one shared Core so security fixes, migrations, and common product improvements do not have to be duplicated across two unrelated forks. Deployment identity, installer behavior, environment presets, and edition-only capabilities are separated at package-build time and verified in CI.

## Hosted Base

`PRIVATE_GATHER_EDITION=hosted`

Hosted is the multi-organization Private Gather platform. It includes tenant isolation, Create Website, My Sites, hosted tenant domains/subdomains, marketplace/discovery behavior, platform plans, and platform administration.

The Hosted package:

- has a Hosted-only `install/index.php`;
- has no edition selector;
- has no Self-Hosted organization/visibility/registration fields;
- presets `PRIVATE_GATHER_EDITION=hosted`;
- removes `SELF_HOSTED_*` settings from its packaged `.env.example`;
- identifies its installation base as `hosted-platform` in package metadata.

## Self-Hosted Base

`PRIVATE_GATHER_EDITION=self_hosted`

Self-Hosted is installed by one customer on hosting they control. Its dedicated installer creates one organization, assigns the first owner, and records that tenant in `SELF_HOSTED_TENANT_ID`. Requests resolve to that one organization rather than a Hosted wildcard-tenant control plane.

The Self-Hosted package:

- receives its own dedicated `install/index.php` during packaging;
- has no Hosted-platform option or edition selector;
- asks for organization name/type, site visibility, and member-registration policy during install;
- presets `PRIVATE_GATHER_EDITION=self_hosted`;
- disables Hosted wildcard mode in its packaged environment preset;
- removes Hosted wildcard target settings from its packaged `.env.example`;
- identifies its installation base as `self-hosted-organization` in package metadata.

Self-Hosted does not use Create Website or the Hosted multi-site switcher. Event, CMS, media, staff, branding, ticketing, membership, community, and analytics tools operate on the single local organization.

### Privacy

`SELF_HOSTED_VISIBILITY=private` is the default. Anonymous visitors are redirected to login before organization or event content is rendered. Authentication and password-recovery pages remain reachable. `public` can be selected when the owner wants a public-facing club site.

### Registration

`SELF_HOSTED_REGISTRATION=approval` is the default. New members remain pending until a local owner/administrator/manager approves them.

Other supported policies are:

- `open` — account becomes active immediately.
- `disabled` — public registration is unavailable.

### Local administration

The installer-created owner can use the installation administration tools appropriate to the Self-Hosted base. Hosted-only SaaS controls such as multi-organization platform management, platform plans, and central Hosted website administration remain unavailable.

## Automatic runtime provisioning

Both bases run the same safety-critical installer bootstrap before requirements are evaluated. It creates/repairs the Laravel runtime tree to mode `0775` and performs real write probes. This covers `storage/`, Laravel cache/session/view directories, logs, and `bootstrap/cache/`.

Neither base falls back to `0777`. If the operating system prevents the PHP/web-server account from modifying the uploaded tree, the installer fails closed.

## Installer deletion

After successful installation, both bases write the permanent installation lock first and then remove installer code automatically. The normal path recursively deletes `/install`. If a restrictive filesystem forces the safe rename fallback, a shutdown cleanup pass attempts to remove that fallback as well. CI includes a regression test that starts with restrictive file/directory modes and requires the installer tree to be gone after cleanup.

## Release and verification rule

Hosted and Self-Hosted packages are built from the same reviewed source commit, but they are no longer treated as one selectable installer artifact. Package verification requires:

- separate installer identities;
- no edition selector;
- no cross-edition installer fields;
- no cross-edition environment options;
- correct `BASE-PRESET` (`hosted-platform` or `self-hosted-organization`);
- protected deployment-state exclusion;
- exact shared-Core parity outside declared edition-specific files.

The Upgrade Center product ID remains `privategather/private-gather` so compatible shared-Core fixes can still be delivered consistently, while each deployment base retains its own installer and environment identity.
