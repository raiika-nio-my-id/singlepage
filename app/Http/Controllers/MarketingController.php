<?php

namespace App\Http\Controllers;

use App\Services\MarketingSite;
use Illuminate\Http\Response;

class MarketingController extends Controller
{
    public function show(): Response
    {
        return response(MarketingSite::html(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }

    public function robots(): Response
    {
        return response(MarketingSite::robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    public function sitemap(): Response
    {
        return response(MarketingSite::sitemapXml(), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}
