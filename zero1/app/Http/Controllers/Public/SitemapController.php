<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [['loc' => route('home'), 'lastmod' => PageSection::max('updated_at')]];
        foreach (Page::published()->get() as $page) {
            $urls[] = ['loc' => route('pages.show', $page), 'lastmod' => $page->updated_at];
        }

        return response()->view('public.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
