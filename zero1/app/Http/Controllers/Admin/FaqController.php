<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        return view('admin.content.faqs.index', ['faqs' => Faq::orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        return view('admin.content.faqs.form', ['faq' => new Faq]);
    }

    public function store(Request $request): RedirectResponse
    {
        Faq::create($this->validated($request));

        return redirect()->route('admin.faqs.index')->with('status', 'Pergunta salva.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.content.faqs.form', ['faq' => $faq]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validated($request));

        return redirect()->route('admin.faqs.index')->with('status', 'Pergunta atualizada.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('status', 'Pergunta removida.');
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'question' => ['required', 'string', 'max:200'],
            'answer' => ['nullable', 'string', 'max:3000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($request->boolean('is_published') && blank($v['answer'] ?? null)) {
            throw ValidationException::withMessages(['answer' => 'Escreva a resposta antes de publicar.']);
        }

        return $v + ['is_published' => $request->boolean('is_published'), 'sort_order' => (int) ($v['sort_order'] ?? 0)];
    }
}
