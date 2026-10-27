<?php

namespace App\Services;

use Illuminate\Http\Request;

class LicenseHostPolicy
{
    public function domainForRequest(Request $request): string
    {
        return rtrim(str_replace('/public', '', $request->getSchemeAndHttpHost()), '/');
    }

    public function isLocalDevelopment(string $domainUrl): bool
    {
        $host = parse_url($domainUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $host = parse_url('http://'.ltrim($domainUrl, '/'), PHP_URL_HOST);
        }

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = trim(strtolower(rtrim($host, '.')), '[]');

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
