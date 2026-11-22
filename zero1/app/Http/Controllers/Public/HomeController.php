<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Faq;
use App\Models\MediaAsset;
use App\Models\MembershipPlan;
use App\Models\OpeningHour;
use App\Models\PageSection;
use App\Models\Review;
use App\Models\Service;
use App\Services\Seo\LocalBusinessSchema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(LocalBusinessSchema $schema): View
    {
        $sections = PageSection::query()->with('media')->where('is_visible', true)->orderBy('sort_order')->get()->keyBy('key');

        $services = Service::query()->publiclyListed()->with('category')
            ->orderBy('sort_order')->orderBy('name')->get();

        $plans = MembershipPlan::query()->published()
            ->with(['services' => fn ($q) => $q->active()])
            ->orderBy('sort_order')->orderBy('price_cents')->get();

        $barbers = Barber::query()->visibleOnSite()->with(['photo', 'services' => fn ($q) => $q->publiclyListed()])
            ->orderBy('sort_order')->orderBy('display_name')->get();

        $gallery = MediaAsset::query()->where('collection', 'gallery')->published()
            ->orderBy('sort_order')->latest()->limit(12)->get();

        return view('public.home', [
            'sections' => $sections,
            'services' => $services,
            'plans' => $plans,
            'barbers' => $barbers,
            'gallery' => $gallery,
            'reviews' => Review::query()->published()->orderBy('sort_order')->latest('reviewed_on')->limit(9)->get(),
            'faqs' => Faq::query()->published()->orderBy('sort_order')->get(),
            'hours' => OpeningHour::query()->orderBy('weekday')->get(),
            'schema' => $schema->build(),
        ]);
    }
}
