<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Controllers\FeedController;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;
use Tempest\Support\Priority;

/**
 * Tempest route matching treats {@slug}.xml as [^/]++.xml, which never matches real paths.
 * Handle the legacy audio feed URL before MatchRouteMiddleware returns 404.
 */
#[Priority(Priority::FRAMEWORK - 32)]
final readonly class LegacyAudioFeedMiddleware implements HttpMiddleware
{
    public function __construct(
        private FeedController $feeds,
    ) {
    }

    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        if ($request->method !== Method::GET && $request->method !== Method::HEAD) {
            return $next($request);
        }

        if (preg_match('#^/feeds/(?P<slug>[^/]+)\.xml$#', $request->path, $matches) !== 1) {
            return $next($request);
        }

        return $this->feeds->audio($matches['slug'], $request);
    }
}
