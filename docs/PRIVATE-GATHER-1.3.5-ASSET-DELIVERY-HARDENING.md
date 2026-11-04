# Private Gather 1.3.5 — Asset Delivery Hardening

Private Gather 1.3.5 hardens static asset delivery after a real deployment showed application HTML loading while styles and images were unavailable.

## Changes

- Canonical application assets remain under `public/assets/`.
- Hosted and Self-Hosted release packages now also contain a byte-identical physical root `assets/` compatibility mirror.
- The root mirror allows CSS, JavaScript, theme assets and branding to remain directly web-accessible on managed hosts that execute the PHP front controller but do not honor the intended static-asset rewrite mapping.
- Release packaging fails if `public/assets/` is absent or empty.
- Package verification requires the canonical and mirrored asset trees to have identical filenames and SHA-256 content hashes.
- Package verification explicitly requires the application CSS, redesign CSS, theme CSS, administration CSS, redesign JavaScript and official Private Gather logo in both locations.
- Package metadata records the static asset mirror and asset count.

## Canonical ownership

`public/assets/` remains the canonical maintained source. Root `assets/` is generated only in release packaging and must never become a separately maintained source tree.

## Security and state

No database schema, account, tenant, privacy or runtime-state behavior changes in this hotfix. Protected runtime-state exclusions remain unchanged.
