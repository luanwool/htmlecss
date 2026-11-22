<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageSection;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectionController extends Controller
{
    /** Campos extras editáveis por seção (rótulos de CTA etc.). */
    public const EXTRA_FIELDS = [
        'hero' => ['primary_cta' => 'Texto do botão principal', 'secondary_cta' => 'Texto do botão secundário'],
        'testimonials' => ['google_cta' => 'Texto do botão do Google Maps'],
        'cta' => ['primary_cta' => 'Texto do botão'],
    ];

    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.content.index', ['sections' => PageSection::orderBy('sort_order')->get()]);
    }

    public function edit(PageSection $section): View
    {
        return view('admin.content.section', [
            'section' => $section->load('media'),
            'extraFields' => self::EXTRA_FIELDS[$section->key] ?? [],
        ]);
    }

    public function update(Request $request, PageSection $section, MediaUploader $uploader): RedirectResponse
    {
        $extra = array_keys(self::EXTRA_FIELDS[$section->key] ?? []);
        $rules = [
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:600'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'media' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('zero1.media.max_upload_kb')],
            'media_alt' => ['nullable', 'string', 'max:150'],
        ];
        foreach ($extra as $field) {
            $rules["content.{$field}"] = ['nullable', 'string', 'max:60'];
        }
        $v = $request->validate($rules);
        $original = $section->getOriginal();

        $section->fill([
            'eyebrow' => $v['eyebrow'] ?? null,
            'title' => $v['title'] ?? null,
            'subtitle' => $v['subtitle'] ?? null,
            'sort_order' => (int) ($v['sort_order'] ?? $section->sort_order),
            'is_visible' => $request->boolean('is_visible'),
            'content' => array_merge($section->content ?? [], collect($v['content'] ?? [])->only($extra)->all()),
        ]);

        if ($request->boolean('remove_media') && $section->media) {
            $old = $section->media;
            $section->media_id = null;
            $section->save();
            $uploader->delete($old);
        }
        if ($request->hasFile('media')) {
            $old = $section->media;
            $media = $uploader->store($request->file('media'), 'section', ['alt_text' => $v['media_alt'] ?? null]);
            $section->media_id = $media->id;
            $section->save();
            if ($old) {
                $uploader->delete($old);
            }
        } elseif ($section->media && isset($v['media_alt'])) {
            $section->media->update(['alt_text' => $v['media_alt']]);
        }

        $section->save();
        $this->audit->logChanges('content.section_updated', $section, $original);

        return redirect()->route('admin.content.index')->with('status', 'Seção "'.$section->label().'" atualizada.');
    }
}
