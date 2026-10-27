<?php

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private readonly LanguageService $languages) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isPanelRequest($request)) {
            $locale = $request->hasSession() ? $request->session()->get('locale') : null;
            if (is_string($locale) && $locale !== '') {
                app()->setLocale($this->languages->normalizeCode($locale));
            }

            return $next($request);
        }

        $language = $this->languages->resolveForRequest($request);
        $this->languages->apply($language);

        $request->attributes->set('language', $language);
        view()->share('availableLanguages', $this->languages->active());
        view()->share('textDirection', $language->direction);

        return $next($request);
    }

    private function isPanelRequest(Request $request): bool
    {
        return $request->is('admin', 'admin/*', 'seller', 'seller/*')
            || $request->routeIs('admin.*', 'seller.*');
    }
}
