<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\ImageSanitizer;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    private const MAX_IMAGE_PIXELS = 40_000_000;
    private const MAX_IMAGE_EDGE = 12_000;

    public function index(TenantContext $ctx)
    {
        $tenant = $ctx->requireTenant();

        return view('tenant.manage.media', [
            'tenant' => $tenant,
            'assets' => MediaAsset::where('tenant_id', $tenant->id)->latest()->paginate(48),
        ]);
    }

    public function store(Request $r, TenantContext $ctx, ImageSanitizer $sanitizer)
    {
        $tenant = $ctx->requireTenant();
        $d = $r->validate([
            // Do not use an extension-sensitive image/mime validation rule
            // here. The browser-supplied filename is untrusted and may be
            // deliberately misleading. The strict Fileinfo allowlist below,
            // followed by an actual image decode, is authoritative.
            'file' => 'required|file|max:15360',
            'visibility' => 'required|in:public,tenant,private',
            'alt_text' => 'nullable|string|max:255',
        ]);

        $file = $d['file'];
        $mime = strtolower((string) $file->getMimeType());
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };

        if ($extension === null) {
            throw ValidationException::withMessages(['file' => 'The uploaded file is not a supported image.']);
        }

        $disk = $d['visibility'] === 'public' ? 'public' : 'local';
        // Never trust the client-supplied filename or extension for a web-
        // reachable object. The server-generated extension is derived only
        // from Fileinfo's detected MIME type.
        $name = Str::uuid().'.'.$extension;
        $path = $file->storeAs('tenant/'.$tenant->id.'/media', $name, $disk);
        abort_unless($path, 500, 'Upload failed.');
        $absolute = Storage::disk($disk)->path($path);

        try {
            $image = @getimagesize($absolute);
            $width = (int) ($image[0] ?? 0);
            $height = (int) ($image[1] ?? 0);
            $decodedMime = strtolower((string) ($image['mime'] ?? ''));

            if ($width < 1 || $height < 1 || $decodedMime !== $mime) {
                throw ValidationException::withMessages(['file' => 'The uploaded image could not be validated safely.']);
            }
            if ($width > self::MAX_IMAGE_EDGE || $height > self::MAX_IMAGE_EDGE || ($width * $height) > self::MAX_IMAGE_PIXELS) {
                throw ValidationException::withMessages(['file' => 'The uploaded image dimensions are too large.']);
            }

            $stripped = $sanitizer->sanitize($absolute, $mime);

            // Re-validate after re-encoding so a failed/corrupt sanitizer can
            // never leave an invalid public object behind.
            $after = @getimagesize($absolute);
            if (! is_array($after) || strtolower((string) ($after['mime'] ?? '')) !== $mime) {
                throw ValidationException::withMessages(['file' => 'The uploaded image failed the safety processing step.']);
            }

            $asset = MediaAsset::create([
                'tenant_id' => $tenant->id,
                'uploaded_by' => $r->user()->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size' => filesize($absolute) ?: 0,
                'alt_text' => $d['alt_text'] ?? null,
                'visibility' => $d['visibility'],
                'sha256' => hash_file('sha256', $absolute),
                'metadata_stripped' => $stripped,
            ]);
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        return back()->with('status', 'Media uploaded.');
    }

    public function show(Request $r, TenantContext $ctx, MediaAsset $asset)
    {
        $tenant = $ctx->requireTenant();
        abort_unless($asset->tenant_id === $tenant->id, 404);
        if ($asset->visibility === 'private') {
            abort_unless($r->user() && $tenant->users()->where('users.id', $r->user()->id)->exists(), 403);
        }

        return Storage::disk($asset->disk)->response($asset->path, $asset->original_name, [
            'Content-Type' => $asset->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => $asset->visibility === 'public' ? 'public,max-age=86400' : 'private,no-store',
        ]);
    }

    public function destroy(TenantContext $ctx, MediaAsset $asset)
    {
        $tenant = $ctx->requireTenant();
        abort_unless($asset->tenant_id === $tenant->id, 404);
        Storage::disk($asset->disk)->delete($asset->path);
        $asset->delete();

        return back()->with('status', 'Media deleted.');
    }
}
