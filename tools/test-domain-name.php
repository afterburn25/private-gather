<?php

declare(strict_types=1);

require dirname(__DIR__).'/app/Support/DomainName.php';

use App\Support\DomainName;

$cases = [
    ['Example.COM', 'example.com'],
    ['https://Club.Example.com/path', 'club.example.com'],
    ['club.example.com:443', 'club.example.com'],
    ['club.example.com.', 'club.example.com'],
];

foreach ($cases as [$input, $expected]) {
    $actual = DomainName::normalize($input);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$input} => {$actual}, expected {$expected}\n");
        exit(1);
    }
}

if (DomainName::platformSubdomain('sunset-club', 'Example.COM') !== 'sunset-club.example.com') {
    fwrite(STDERR, "FAIL: platformSubdomain\n");
    exit(1);
}

fwrite(STDOUT, "DOMAIN NAME TESTS: PASS\n");
