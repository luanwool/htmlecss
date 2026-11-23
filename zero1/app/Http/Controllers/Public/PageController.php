<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __invoke(Page $page): View
    {
        $user = request()->user();
        // Rascunhos só são visíveis para quem edita conteúdo (pré-visualização).
        abort_unless($page->is_published || $user?->can('content.manage'), 404);

        return view('public.page', ['page' => $page]);
    }
}
