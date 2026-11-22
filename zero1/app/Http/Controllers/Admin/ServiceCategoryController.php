<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.services.categories', ['categories' => ServiceCategory::withCount('services')->orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        $slug = Str::slug($data['name']);
        abort_if(ServiceCategory::where('slug', $slug)->exists(), 422, 'Categoria já existe.');
        ServiceCategory::create(['name' => $data['name'], 'slug' => $slug, 'sort_order' => $data['sort_order'] ?? 0]);

        return back()->with('status', 'Categoria criada.');
    }

    public function update(Request $request, ServiceCategory $category): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        $category->update(['name' => $data['name'], 'sort_order' => $data['sort_order'] ?? 0]);

        return back()->with('status', 'Categoria atualizada.');
    }

    public function destroy(ServiceCategory $category): RedirectResponse
    {
        $category->delete(); // serviços ficam sem categoria (nullOnDelete)

        return back()->with('status', 'Categoria removida.');
    }
}
