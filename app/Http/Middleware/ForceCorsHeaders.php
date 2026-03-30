<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceCorsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            $response = response()->noContent();
        } else {
            $response = $next($request);
        }

        $origin = (string) $request->headers->get('Origin', '');

        if ($origin === '' || !$this->isAllowedOrigin($origin)) {
            return $response;
        }

        $allowedHeaders = config('cors.allowed_headers', ['*']);
        $allowedMethods = config('cors.allowed_methods', ['*']);
        $requestedHeaders = $request->headers->get('Access-Control-Request-Headers');

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin', false);
        $response->headers->set(
            'Access-Control-Allow-Methods',
            implode(', ', $allowedMethods === ['*'] ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] : $allowedMethods)
        );
        $response->headers->set(
            'Access-Control-Allow-Headers',
            $allowedHeaders === ['*']
                ? ($requestedHeaders ?: 'Origin, Content-Type, Accept, Authorization, X-Requested-With')
                : implode(', ', $allowedHeaders)
        );

        if (config('cors.supports_credentials')) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $allowedOrigins = config('cors.allowed_origins', []);

        if (in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
            if (@preg_match($pattern, $origin) === 1) {
                return true;
            }
        }

        return false;
    }
}
