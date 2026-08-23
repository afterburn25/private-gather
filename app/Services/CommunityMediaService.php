<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CommunityMediaService
{
    private const MAX_IMAGE_PIXELS = 40_000_000;
    private const MAX_IMAGE_EDGE = 12_000;

    public function __construct(private readonly ImageSanitizer $sanitizer)
    {
    }

    /** @return array{path:string,type:string,mime:string,size:int,duration_seconds:?int} */
    public function store(UploadedFile $file, int $tenantId, int $userId, string $scope): array
    {
        if ($tenantId < 1 || $userId < 1 || ! preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $scope)) {
            throw new \InvalidArgumentException('Invalid community media storage scope.');
        }

        $mime = strtolower((string) $file->getMimeType());
        [$type, $extension] = match ($mime) {
            'image/jpeg' => ['image', 'jpg'],
            'image/png' => ['image', 'png'],
            'image/webp' => ['image', 'webp'],
            'image/gif' => ['image', 'gif'],
            'video/mp4' => ['video', 'mp4'],
            'video/webm' => ['video', 'webm'],
            'video/quicktime' => ['video', 'mov'],
            default => [null, null],
        };

        if ($type === null || $extension === null) {
            throw ValidationException::withMessages(['media' => 'The uploaded file type is not supported.']);
        }

        $name = Str::uuid().'.'.$extension;
        $directory = 'tenant/'.$tenantId.'/community/'.$userId.'/'.$scope;
        $path = $file->storeAs($directory, $name, 'local');
        abort_unless($path, 500, 'Upload failed.');

        $absolute = Storage::disk('local')->path($path);

        try {
            if ($type === 'image') {
                $this->validateImage($absolute, $mime);
                $this->sanitizer->sanitize($absolute, $mime);
                $this->validateImage($absolute, $mime);
            }

            $size = filesize($absolute);
            if ($size === false || $size < 1) {
                throw ValidationException::withMessages(['media' => 'The uploaded file could not be stored safely.']);
            }

            return [
                'path' => $path,
                'type' => $type,
                'mime' => $mime,
                'size' => (int) $size,
                'duration_seconds' => null,
            ];
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    private function validateImage(string $absolute, string $expectedMime): void
    {
        $image = @getimagesize($absolute);
        $width = (int) ($image[0] ?? 0);
        $height = (int) ($image[1] ?? 0);
        $decodedMime = strtolower((string) ($image['mime'] ?? ''));

        if ($width < 1 || $height < 1 || $decodedMime !== $expectedMime) {
            throw ValidationException::withMessages(['media' => 'The uploaded image could not be validated safely.']);
        }

        if ($width > self::MAX_IMAGE_EDGE || $height > self::MAX_IMAGE_EDGE || ($width * $height) > self::MAX_IMAGE_PIXELS) {
            throw ValidationException::withMessages(['media' => 'The uploaded image dimensions are too large.']);
        }
    }
}
