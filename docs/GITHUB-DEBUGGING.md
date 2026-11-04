# GitHub debugging baseline

Private Gather 1.0.8 uses GitHub Actions as an evidence gate before a change is copied to a live host.

The CI workflow checks:

- PHP syntax across the repository.
- Composer metadata validity.
- Installer, subdirectory routing, upgrade-center, branding, and product-structure assertions.
- Accidental committed `.env` or installed-state files.
- Dependency resolution and `composer audit`.
- Laravel boot and route registration.
- A fresh SQLite migration pass.
- PHPUnit tests.
- PHP 8.3 minimum-runtime compatibility in addition to the primary PHP 8.4 lane.

`composer.lock` is not present in the 1.0.8 deployment source. CI can resolve the declared constraints temporarily, but a reproducible production baseline should commit a reviewed lock file once dependencies have been resolved successfully.
