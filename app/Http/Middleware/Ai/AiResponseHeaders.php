<?php

namespace App\Http\Middleware\Ai;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AiResponseHeaders
{
    public const REQUEST_ID = 'request_id';

    public static function requestId(Request $request): string
    {
        $id = $request->attributes->get(self::REQUEST_ID);
        if (! is_string($id)) {
            $id = 'req_'.Str::random(16);
            $request->attributes->set(self::REQUEST_ID, $id);
        }

        return $id;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = self::requestId($request);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
