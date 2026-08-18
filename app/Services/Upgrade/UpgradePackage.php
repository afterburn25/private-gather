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
            self::validateArchiveEnvelope($zip);

            $manifestStat = $zip->statName('manifest.json');
            if ($manifestStat === false) {
                throw new InvalidArgumentException('Upgrade package is missing manifest.json.');
            }

            $maxManifestBytes = max(1, (int) config('upgrades.max_manifest_kb', 1024)) * 1024;
            if ((int) ($manifestStat['size'] ?? 0) > $maxManifestBytes) {
                throw new InvalidArgumentException('Upgrade manifest exceeds the configured size limit.');
            }

            $raw = $zip->getFromName('manifest.json');
            if ($raw === false || strlen($raw) > $maxManifestBytes) {
                throw new InvalidArgumentException('Upgrade package manifest could not be read safely.');
            }

            $manifest = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($manifest)) {
                throw new InvalidArgumentException('Upgrade manifest must be a JSON object.');
            }
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

            $isList = $item === [] || array_keys($item) === range(0, count($item) - 1);
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
            self::validateArchiveEnvelope($zip);
            $maxFileBytes = max(1, (int) config('upgrades.max_file_mb', 64)) * 1024 * 1024;

            foreach ($this->files() as $file) {
                if (($file['action'] ?? 'replace') === 'delete') {
                    continue;
                }

                $path = UpgradePath::normalize((string) $file['path']);
                $entry = 'payload/'.$path;
                $index = $zip->locateName($entry);
                if ($index === false) {
                    throw new InvalidArgumentException("Package payload is missing {$entry}.");
                }

                $stat = $zip->statIndex($index);
                if ($stat === false) {
                    throw new InvalidArgumentException("Package payload metadata is unreadable: {$entry}.");
                }
                $expectedSize = (int) ($stat['size'] ?? -1);
                if ($expectedSize < 0 || $expectedSize > $maxFileBytes) {
                    throw new InvalidArgumentException("Package payload exceeds the configured per-file size limit: {$path}.");
                }

                $target = $stagingPath.'/'.$path;
                $parent = dirname($target);
                if (! is_dir($parent) && ! mkdir($parent, 0775, true) && ! is_dir($parent)) {
                    throw new RuntimeException("Unable to create staging path for {$path}.");
                }

                $input = $zip->getStream($entry);
                if (! is_resource($input)) {
                    throw new InvalidArgumentException("Unable to stream package payload {$entry}.");
                }
                $output = @fopen($target, 'wb');
                if (! is_resource($output)) {
                    fclose($input);
                    throw new RuntimeException("Unable to stage {$path}.");
                }

                try {
                    // Read at most one byte beyond the declared uncompressed
                    // size. A mismatch indicates a corrupt/manipulated entry and
                    // avoids materializing the file in PHP memory.
                    $written = stream_copy_to_stream($input, $output, $expectedSize + 1);
                    if ($written === false || $written !== $expectedSize) {
                        throw new InvalidArgumentException("Expanded size mismatch for {$path}.");
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }

                if (! hash_equals(strtolower((string) $file['sha256']), hash_file('sha256', $target))) {
                    @unlink($target);
                    throw new InvalidArgumentException("Checksum mismatch for {$path}.");
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function validateArchiveEnvelope(ZipArchive $zip): void
    {
        $maxEntries = max(10, (int) config('upgrades.max_archive_entries', 20250));
        if ($zip->numFiles > $maxEntries) {
            throw new InvalidArgumentException('Upgrade archive contains too many entries.');
        }

        $maxManifestBytes = max(1, (int) config('upgrades.max_manifest_kb', 1024)) * 1024;
        $maxFileBytes = max(1, (int) config('upgrades.max_file_mb', 64)) * 1024 * 1024;
        $maxExpandedBytes = max(1, (int) config('upgrades.max_expanded_mb', 1024)) * 1024 * 1024;
        $expandedPayloadBytes = 0;
        $seen = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                throw new InvalidArgumentException('Upgrade archive contains an unreadable entry.');
            }

            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            if ($name === '' || str_contains($name, "\0")) {
                throw new InvalidArgumentException('Upgrade archive contains an invalid entry name.');
            }

            $key = strtolower($name);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Upgrade archive contains duplicate or case-colliding entry: {$name}");
            }
            $seen[$key] = true;

            $size = (int) ($stat['size'] ?? 0);
            if ($size < 0) {
                throw new InvalidArgumentException("Upgrade archive entry has an invalid size: {$name}");
            }

            if ($name === 'manifest.json' && $size > $maxManifestBytes) {
                throw new InvalidArgumentException('Upgrade manifest exceeds the configured size limit.');
            }

            if (str_starts_with($key, 'payload/') && ! str_ends_with($name, '/')) {
                if ($size > $maxFileBytes) {
                    throw new InvalidArgumentException("Upgrade payload entry exceeds the configured per-file size limit: {$name}");
                }
                $expandedPayloadBytes += $size;
                if ($expandedPayloadBytes > $maxExpandedBytes) {
                    throw new InvalidArgumentException('Upgrade archive expanded payload exceeds the configured size limit.');
                }
            }
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
        foreach ($manifest['from_versions'] as $version) {
            if (! is_string($version) || trim($version) === '' || strlen($version) > 64) {
                throw new InvalidArgumentException('Upgrade manifest contains an invalid source version.');
            }
        }

        if (! is_string($manifest['to_version']) || trim($manifest['to_version']) === '' || strlen($manifest['to_version']) > 64) {
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
                // Exact-case lookup avoids ambiguous ZIP entries and guarantees
                // validation and extraction address the same archive member.
                if ($zip->locateName('payload/'.$path) === false) {
                    throw new InvalidArgumentException("Payload file is missing: {$path}");
                }
            }
        }

        if (isset($manifest['migrations']) && ! is_array($manifest['migrations'])) {
            throw new InvalidArgumentException('Upgrade migrations list must be an array.');
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
        if ($minimumPhp === '' || strlen($minimumPhp) > 32) {
            throw new InvalidArgumentException('Upgrade minimum PHP version is invalid.');
        }
        if (version_compare(PHP_VERSION, $minimumPhp, '<')) {
            throw new InvalidArgumentException("This upgrade requires PHP {$minimumPhp} or newer.");
        }
    }
}
