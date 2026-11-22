<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\SiteSettings;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(SiteSettings $settings): Response
    {
        $indexable = app()->environment('production') && $settings->get('seo.indexable', true);

        $lines = $indexable
            ? ['User-agent: *', 'Disallow: /painel', 'Disallow: /barbeiro', 'Disallow: /minha-conta', 'Disallow: /entrar',
                'Disallow: /cadastro', 'Disallow: /redefinir-senha', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
