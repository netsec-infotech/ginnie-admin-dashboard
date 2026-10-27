<?php

namespace App\Http\Middleware;

use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCatalogLocale
{
    public function __construct(
        protected CatalogLanguageService $catalogLanguageService,
        protected LanguageService $languageService,
    ) {
    }

    /**
     * Handle an incoming request and set the application locale for catalog resolution.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawLocale = $request->header('X-Locale');

        if (empty($rawLocale)) {
            $rawLocale = $request->query('locale');
        }

        $locale = is_string($rawLocale) ? $this->languageService->normalizeCode($rawLocale) : '';

        if ($this->catalogLanguageService->isValidCatalogLocale($locale)) {
            app()->setLocale($locale);
        } else {
            app()->setLocale($this->catalogLanguageService->defaultLocale());
        }

        return $next($request);
    }
}
