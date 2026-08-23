<?php

declare(strict_types=1);

$base = rtrim((string)($argv[1] ?? getenv('PRIVATE_GATHER_SMOKE_URL') ?: ''), '/');
if (!filter_var($base, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($base, PHP_URL_SCHEME)), ['http','https'], true)) {
    fwrite(STDERR, "Usage: php tools/http-smoke.php https://your-private-gather.example\n");
    exit(2);
}

$paths = ['/' => [200], '/health' => [200], '/login' => [200,302], '/events' => [200,302]];
$failures = [];
$times = [];
foreach ($paths as $path => $allowed) {
    $url = $base.$path;
    $start = microtime(true);
    $headers = @get_headers($url, true);
    $elapsed = (microtime(true)-$start)*1000;
    $times[$path] = $elapsed;
    if (!$headers || !isset($headers[0])) { $failures[] = $path.' could not be reached'; continue; }
    $statusLine = is_array($headers[0]) ? end($headers[0]) : $headers[0];
    preg_match('/\s(\d{3})\s/', (string)$statusLine, $match);
    $status = (int)($match[1] ?? 0);
    if (!in_array($status, $allowed, true)) $failures[] = $path.' returned HTTP '.$status;
    printf("%-18s HTTP %-3d %7.1f ms\n", $path, $status, $elapsed);
}

// Small sequential read-only burst catches obvious timeout/worker exhaustion
// without pretending to be a real capacity benchmark.
$burstFailures = 0;
for ($i=0; $i<20; $i++) {
    $start = microtime(true);
    $headers = @get_headers($base.'/health');
    $elapsed = (microtime(true)-$start)*1000;
    if (!$headers || !preg_match('/\s200\s/', (string)$headers[0])) $burstFailures++;
    $times['burst-'.$i] = $elapsed;
}
if ($burstFailures) $failures[] = $burstFailures.' / 20 read-only health burst requests failed';

$sorted = array_values($times); sort($sorted);
$p95 = $sorted[(int)floor((count($sorted)-1)*0.95)] ?? 0;
printf("P95 observed response: %.1f ms\n", $p95);

if ($failures) {
    fwrite(STDERR, "HTTP SMOKE: FAIL\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}
echo "HTTP SMOKE: PASS\n";
echo "This is a non-destructive deployment smoke check, not a substitute for staged load testing.\n";
