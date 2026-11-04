# Raster asset recovery

Private Gather 1.4 photographic showcase assets must be stored as valid binary images. Text-oriented repository writes can preserve a `.webp` filename while corrupting the payload, so release verification must decode every bundled showcase raster and verify canonical/public mirrors are byte-identical.

The release gate is `php tools/verify-raster-assets.php`.
