<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Helpers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpExtractor
{
    public static function method(Request $request): string
    {
        return $request->getMethod();
    }

    public static function url(Request $request): string
    {
        // Only log the base URL without query parameters
        return $request->url();
    }


    public static function statusCode(Response $response): int
    {
        return $response->getStatusCode();
    }
}
