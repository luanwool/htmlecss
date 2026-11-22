<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Depoimentos reais. Publicação exige registro de autorização do autor. */
class ReviewController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.content.reviews.index', ['reviews' => Review::orderBy('sort_order')->latest()->get()]);
    }

    public function create(): View
    {
        return view('admin.content.reviews.form', ['review' => new Review]);
    }

    public function store(Request $request): RedirectResponse
    {
        $review = Review::create($this->validated($request, new Review));
        $this->audit->log('review.created', $review, [], $review->toArray());

        return redirect()->route('admin.reviews.index')->with('status', 'Depoimento salvo.');
    }

    public function edit(Review $review): View
    {
        return view('admin.content.reviews.form', ['review' => $review]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $original = $review->getOriginal();
        $review->update($this->validated($request, $review));
        $this->audit->logChanges('review.updated', $review, $original);

        return redirect()->route('admin.reviews.index')->with('status', 'Depoimento atualizado.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->audit->log('review.deleted', $review, $review->toArray());
        $review->delete();

        return back()->with('status', 'Depoimento removido.');
    }

    private function validated(Request $request, Review $review): array
    {
        $v = $request->validate([
            'author_name' => ['required', 'string', 'max:80'],
            'content' => ['required', 'string', 'max:1200'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'source' => ['nullable', 'in:google,presencial,instagram,whatsapp,outro'],
            'reviewed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'authorization_note' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $authorized = $request->boolean('authorization_confirmed');
        if ($request->boolean('is_published') && ! $authorized) {
            throw ValidationException::withMessages([
                'authorization_confirmed' => 'Só é possível publicar depoimentos com autorização do autor registrada.',
            ]);
        }

        $v['is_published'] = $request->boolean('is_published');
        $v['sort_order'] = (int) ($v['sort_order'] ?? 0);
        if ($authorized && ! $review->authorization_confirmed_at) {
            $v['authorization_confirmed_at'] = now();
            $v['authorization_confirmed_by'] = $request->user()->id;
        } elseif (! $authorized) {
            $v['authorization_confirmed_at'] = null;
            $v['authorization_confirmed_by'] = null;
        }

        return $v;
    }
}
