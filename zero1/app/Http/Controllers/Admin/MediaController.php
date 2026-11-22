<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Galeria do site: somente fotos reais da própria barbearia. */
class MediaController extends Controller
{
    public const CATEGORIES = ['ambiente' => 'Ambiente/infraestrutura', 'trabalhos' => 'Trabalhos/tratamentos', 'equipe' => 'Equipe', 'outros' => 'Outros'];

    public function __construct(private MediaUploader $uploader, private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.content.media', [
            'items' => MediaAsset::where('collection', 'gallery')->orderBy('sort_order')->latest()->get(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:12'],
            'files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('zero1.media.max_upload_kb')],
            'category' => ['nullable', 'in:'.implode(',', array_keys(self::CATEGORIES))],
            'confirm_own' => ['accepted'],
        ], ['confirm_own.accepted' => 'Confirme que as fotos são da própria Zero 1 Barbearia e que o uso está autorizado.']);

        foreach ($request->file('files') as $file) {
            $media = $this->uploader->store($file, 'gallery', ['category' => $request->input('category')]);
            $this->audit->log('media.uploaded', $media);
        }

        return back()->with('status', 'Fotos enviadas. Preencha o texto alternativo de cada uma.');
    }

    public function update(Request $request, MediaAsset $media): RedirectResponse
    {
        $v = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(self::CATEGORIES))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $media->update($v + ['is_published' => $request->boolean('is_published')]);

        return back()->with('status', 'Foto atualizada.');
    }

    public function destroy(MediaAsset $media): RedirectResponse
    {
        abort_unless($media->collection === 'gallery', 404);
        $this->audit->log('media.deleted', $media, $media->only(['path', 'original_name']));
        $this->uploader->delete($media);

        return back()->with('status', 'Foto removida.');
    }
}
