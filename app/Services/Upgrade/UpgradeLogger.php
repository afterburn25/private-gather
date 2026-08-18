<?php

namespace App\Services\Upgrade;

final class UpgradeLogger
{
    private array $events = [];

    public function add(string $step, string $status, string $message, array $context = []): void
    {
        $this->events[] = [
            'time' => gmdate('c'),
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'context' => $context,
        ];
    }

    public function write(string $path): void
    {
        $parent = dirname($path);
        if (! is_dir($parent)) {
            @mkdir($parent, 0775, true);
        }
        @file_put_contents($path, json_encode(['events' => $this->events], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL, LOCK_EX);
    }
}
