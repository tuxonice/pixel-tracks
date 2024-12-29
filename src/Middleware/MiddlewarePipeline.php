<?php

namespace PixelTrack\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MiddlewarePipeline
{
    /**
     * @var array<string>
     */
    protected array $middlewares = [];

    public function add(string $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function process(Request $request, \Closure $handler): Response
    {
        $next = $handler;

        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = function ($request) use ($middleware, $next) {
                return (new $middleware())->handle($request, $next);
            };
        }

        return $next($request);
    }
}
