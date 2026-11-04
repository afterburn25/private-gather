<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\PlatformContent;
use App\Support\Audit;
use Illuminate\Http\Request;

class PlatformContentController extends Controller
{
    public function edit(PlatformContent $content)
    {
        return view('admin.platform-content', ['content' => $content->all()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'brand_name' => 'required|string|max:120',
            'logo_url' => 'nullable|string|max:1000',
            'header_cta_label' => 'nullable|string|max:80',
            'header_cta_url' => 'nullable|string|max:500',
            'hero_eyebrow' => 'nullable|string|max:180',
            'hero_heading' => 'nullable|string|max:300',
            'hero_body' => 'nullable|string|max:3000',
            'hero_image_url' => 'nullable|string|max:1000',
            'hero_primary_label' => 'nullable|string|max:100',
            'hero_primary_url' => 'nullable|string|max:500',
            'hero_secondary_label' => 'nullable|string|max:100',
            'hero_secondary_url' => 'nullable|string|max:500',
            'featured_eyebrow' => 'nullable|string|max:180',
            'featured_heading' => 'nullable|string|max:240',
            'organizations_eyebrow' => 'nullable|string|max:180',
            'organizations_heading' => 'nullable|string|max:240',
            'domain_eyebrow' => 'nullable|string|max:180',
            'domain_heading' => 'nullable|string|max:300',
            'domain_body' => 'nullable|string|max:3000',
            'footer_text' => 'nullable|string|max:2000',
            'about_heading' => 'nullable|string|max:300',
            'about_body' => 'nullable|string|max:10000',
            'about_image_url' => 'nullable|string|max:1000',
            'hero_image' => 'nullable|image|max:10240',
            'about_image' => 'nullable|image|max:10240',
            'logo_image' => 'nullable|image|max:10240',
        ]);

        foreach (['show_featured_events', 'show_organizations', 'show_domain_section'] as $key) {
            $data[$key] = $request->boolean($key) ? '1' : '0';
        }

        foreach (['logo_image' => 'logo_url', 'hero_image' => 'hero_image_url', 'about_image' => 'about_image_url'] as $uploadKey => $settingKey) {
            if ($request->hasFile($uploadKey)) {
                $directory = public_path('uploads/platform');
                if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                    abort(500, 'Unable to create the platform media directory.');
                }
                $file = $request->file($uploadKey);
                $extension = strtolower((string) ($file->guessExtension() ?: 'bin'));
                $name = bin2hex(random_bytes(18)).'.'.$extension;
                $file->move($directory, $name);
                $data[$settingKey] = '/uploads/platform/'.$name;
            }
            unset($data[$uploadKey]);
        }

        foreach ($data as $key => $value) {
            PlatformSetting::updateOrCreate(
                ['key' => $key],
                ['group' => 'content', 'value' => $value, 'type' => str_starts_with($key, 'show_') ? 'boolean' : 'string', 'updated_by' => $request->user()->id]
            );
        }

        Audit::write('platform.content.updated', after: array_keys($data), request: $request);
        return back()->with('status', 'Private Gather website content updated.');
    }
}
