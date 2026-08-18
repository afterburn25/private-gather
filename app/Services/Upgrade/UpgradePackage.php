<?php

namespace App\Services\Upgrade;

use App\Support\UpgradePath;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class UpgradePackage
{
    public function __construct(
        public readonly string $zipPath,
        public readonly array $manifest,
    ) {
    }

    public static function open(string $zipPath): self
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive support is required for browser upgrades.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('The uploaded file is not a readable ZIP archive.');
        }

        try {
            $raw = $zip->getFromName('manifest.json');
            if ($raw === false) {
                throw new InvalidArgumentException('Upgrade package is missing manifest.json.');
            }

            $manifest = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            self::validateManifest($manifest, $zip);
        } finally {
            $zip->close();
        }

        return new self($zipPath, $manifest);
    }

    public function fromVersions(): array
    {
        return array_values($this->manifest['from_versions']);
    }

    public function toVersion(): string
    {
        return (string) $this->manifest['to_version'];
    }

    public function files(): array
    {
        return $this->manifest['files'];
    }

    public function migrations(): array
    {
        return array_values($this->manifest['migrations'] ?? []);
    }


    public function verifySignatureIfRequired(): void
    {
        if (! (bool) config('upgrades.require_signature', false)) {
            return;
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('Signed upgrades require the PHP Sodium extension.');
        }

        $publicKey = base64_decode((string) config('upgrades.ed25519_public_key'), true);
        $signature = base64_decode((string) ($this->manifest['signature'] ?? ''), true);

        if ($publicKey === false || strlen($publicKey) !== \SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('The configured upgrade signing public key is invalid.');
        }
        if ($signature === false || strlen($signature) !== \SODIUM_CRYPTO_SIGN_BYTES) {
            throw new InvalidArgumentException('The upgrade package signature is missing or invalid.');
        }

        $manifest = $this->manifest;
        unset($manifest['signature']);
        $canonical = self::canonicalJson($manifest);

        if (! sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey)) {
            throw new InvalidArgumentException('The upgrade package signature could not be verified.');
        }
    }

    private static function canonicalJson(mixed $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $isList = array_keys($item) === range(0, count($item) - 1);
            if ($isList) {
                return array_map($normalize, $item);
            }

            ksort($item, SORT_STRING);
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child);
            }
            return $item;
        };

        return json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function extractTo(string $stagingPath): void
    {
        if (! is_dir($stagingPath) && ! mkdir($stagingPath, 0775, true) && ! is_dir($stagingPath)) {
            throw new RuntimeException('Unable to create upgrade staging directory.');
        }

        $zip = new ZipArchive();
        if ($zip->open($this->zipPath) !== true) {
            throw new RuntimeException('Unable to reopen upgrade package.');
        }

        try {
            foreach ($this->files() as $file) {
                if (($file['action'] ?? 'replace') === 'delete') {
                    continue;
                }

                $path = UpgradePath::normalize((string) $file['path']);
                $entry = 'payload/'.$path;
                $content = $zip->getFromName($entry);
                if ($content === false) {
                    throw new InvalidArgumentException("Package payload is missing {$entry}.");
                }

                if (! hash_equals(strtolower((string) $file['sha256']), hash('sha256', $content))) {
                    throw new InvalidArgumentException("Checksum mismatch for {$path}.");
                }

                $target = $stagingPath.'/'.$path;
                $parent = dirname($target);
                if (! is_dir($parent) && ! mkdir($parent, 0775, true) && ! is_dir($parent)) {
                    throw new RuntimeException("Unable to create staging path for {$path}.");
                }

                if (file_put_contents($target, $content, LOCK_EX) === false) {
                    throw new RuntimeException("Unable to stage {$path}.");
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function validateManifest(array $manifest, ZipArchive $zip): void
    {
        $required = ['format', 'product', 'from_versions', 'to_version', 'files'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new InvalidArgumentException("Upgrade manifest is missing {$key}.");
            }
        }

        if ((int) $manifest['format'] !== 1) {
            throw new InvalidArgumentException('Unsupported upgrade package format.');
        }

        $acceptedProducts = array_values(array_unique(array_filter(array_merge(
            [(string) config('upgrades.product')],
            array_map('strval', (array) config('upgrades.legacy_products', []))
        ))));
        if (! in_array((string) $manifest['product'], $acceptedProducts, true)) {
            throw new InvalidArgumentException('This upgrade package belongs to a different product.');
        }

        if (! is_array($manifest['from_versions']) || $manifest['from_versions'] === []) {
            throw new InvalidArgumentException('Upgrade manifest must declare at least one supported source version.');
        }

        if (! is_string($manifest['to_version']) || trim($manifest['to_version']) === '') {
            throw new InvalidArgumentException('Upgrade target version is invalid.');
        }

        if (! is_array($manifest['files']) || count($manifest['files']) > (int) config('upgrades.max_files', 10000)) {
            throw new InvalidArgumentException('Upgrade file list is invalid or exceeds the configured limit.');
        }

        $seen = [];
        foreach ($manifest['files'] as $index => $file) {
            if (! is_array($file) || empty($file['path'])) {
                throw new InvalidArgumentException("Upgrade file entry {$index} is invalid.");
            }

            $path = UpgradePath::normalize((string) $file['path']);
            $seenKey = strtolower($path);
            if (isset($seen[$seenKey])) {
                throw new InvalidArgumentException("Duplicate upgrade path: {$path}");
            }
            $seen[$seenKey] = true;

            $action = (string) ($file['action'] ?? 'replace');
            if (! in_array($action, ['replace', 'delete'], true)) {
                throw new InvalidArgumentException("Unsupported action for {$path}.");
            }

            if ($action === 'replace') {
                $sha = strtolower((string) ($file['sha256'] ?? ''));
                if (! preg_match('/^[a-f0-9]{64}$/', $sha)) {
                    throw new InvalidArgumentException("Invalid SHA-256 for {$path}.");
                }
                if ($zip->locateName('payload/'.$path, ZipArchive::FL_NOCASE) === false) {
                    throw new InvalidArgumentException("Payload file is missing: {$path}");
                }
            }
        }

        foreach (($manifest['migrations'] ?? []) as $migration) {
            $path = UpgradePath::normalize((string) $migration);
            if (! str_starts_with($path, 'database/migrations/') || ! str_ends_with($path, '.php')) {
                throw new InvalidArgumentException("Invalid migration path: {$path}");
            }
            if (! isset($seen[strtolower($path)])) {
                throw new InvalidArgumentException("Migration {$path} must also appear in the file manifest.");
            }
        }

        $minimumPhp = (string) ($manifest['minimum_php'] ?? '8.3.0');
        if (version_compare(PHP_VERSION, $minimumPhp, '<')) {
            throw new InvalidArgumentException("This upgrade requires PHP {$minimumPhp} or newer.");
        }
    }
}
