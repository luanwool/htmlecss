<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.content.pages.index', ['pages' => Page::orderBy('title')->get()]);
    }

    public function edit(Page $page): View
    {
        return view('admin.content.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page, AuditLogger $audit): RedirectResponse
    {
        $v = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:60000'],
            'meta_description' => ['nullable', 'string', 'max:300'],
        ]);
        $original = $page->getOriginal();
        $publish = $request->boolean('is_published');
        $page->fill($v + ['is_published' => $publish]);
        if ($publish && ! $page->published_at) {
            $page->published_at = now();
        }
        $page->save();
        // Versão anterior completa fica na auditoria (termos são contratuais).
        $audit->logChanges('page.updated', $page, $original);

        return redirect()->route('admin.pages.index')->with('status', 'Página salva.');
    }
}
