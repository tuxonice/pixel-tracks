<?php

namespace PixelTrack\Middleware;

use PixelTrack\App;
use PixelTrack\Exception\CsrfTokenException;
use PixelTrack\Exception\PixelTrackException;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Monolog\Logger;

class ExceptionHandlerMiddleware implements MiddlewareInterface
{
    /**
     * @var mixed|\PixelTrack\Service\Twig
     */
    private mixed $twig;
    /**
     * @var mixed|\Monolog\Logger
     */
    private mixed $logger;

    public function __construct()
    {
        $app = App::getInstance();
        $this->twig = $app->getContainer()->get(Twig::class);
        $this->logger = $app->getContainer()->get(Logger::class);
    }

    public function handle(Request $request, callable|\Closure $next): Response
    {
        try {
            return $next($request);
        } catch (CsrfTokenException $e) {
            $this->logger->warning('CSRF token validation failed', [
                'exception' => $e,
                'request_uri' => $request->getRequestUri()
            ]);
            return new Response(
                $this->twig->getTwig()->render('Error/403.twig', ['message' => 'Invalid security token']),
                Response::HTTP_FORBIDDEN
            );
        } catch (PixelTrackException $e) {
            $this->logger->error('Application error', [
                'exception' => $e,
                'context' => $e->getContext(),
                'request_uri' => $request->getRequestUri()
            ]);
            return new Response(
                $this->twig->getTwig()->render('Error/500.twig', ['message' => $e->getMessage()]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (\Throwable $e) {
            $this->logger->critical('Unhandled exception', [
                'exception' => $e,
                'request_uri' => $request->getRequestUri()
            ]);

            return new Response(
                $this->twig->getTwig()->render('Error/500.twig', ['message' => 'An unexpected error occurred:' . $e->getMessage() . ' - ' .  $_ENV['DATABASE_DSN']]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
