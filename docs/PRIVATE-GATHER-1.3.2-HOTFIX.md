# Private Gather 1.3.2 — Installer Filesystem Self-Heal

Private Gather 1.3.2 is a Hosted-installer stabilization release built from certified 1.3.1.

## Changes

- `storage/` and `bootstrap/cache/` repair still begins with safe `0775` create/chmod behavior.
- If an uploaded runtime tree is owned by another Unix account and cannot be repaired in place, the installer can rebuild that tree atomically through a writable parent directory so the active PHP/web-server account owns the replacement.
- Existing runtime/bootstrap files are copied into a staging tree before activation.
- Replacement is write-probed before the installer proceeds.
- Activation is rollback-safe if the new tree cannot be enabled.
- No `0777` fallback is used.
- Hosted successful-install cleanup now calls the hardened post-install deletion routine directly.
- The legacy `Private Gather Hosted Base · Multi-organization platform` footer has been removed from the Hosted installer.
- CI exercises ownership-neutral runtime rebuilding, preservation of existing files, write probes, and installer auto-deletion.

If both the affected directory and its parent are non-writable to PHP, the installer still fails closed because PHP cannot safely change Unix ownership by itself.
