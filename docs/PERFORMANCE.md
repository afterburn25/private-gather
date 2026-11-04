# Performance and Scale Baseline

The 0.9.8 train adds compound indexes around tenant/event/RSVP/order hot paths and a short-lived hostname lookup cache.

Production recommendations:
- Redis for cache, sessions, queues and rate-limit state once more than one web node is used.
- Queue email, image and bulk notification work.
- S3-compatible object storage + CDN for public media at scale.
- Keep database backups and upgrade backups outside the web document root.
- Profile database queries before increasing cache duration.
