<?php

declare(strict_types=1);

// Compatibility entry point. Normal clean requests are sent directly to
// private-gather.php by .htaccess so a stale cached index.php can never control
// the application-root request.
require __DIR__.'/private-gather.php';
