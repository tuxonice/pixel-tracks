<?php

namespace PixelTrack;

use DI\Container;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use PixelTrack\Routes\Web;
use PixelTrack\Service\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function FastRoute\simpleDispatcher;

class App
{
    /**
     * @var App
     */
    private static $instance;

    private Dispatcher $dispatcher;

    private Container $container;

    private function __construct()
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions(__DIR__ . '/di-config.php');
        $this->container = $builder->build();

        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
            Web::routes($r);
        });
    }

    public static function getInstance(): App
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @throws \Exception
     */
    public function route(Request $request): Response
    {
        $uri = $request->getRequestUri();
        $httpMethod = $request->getMethod();

        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return $this->container->call(['\PixelTrack\Controllers\NotFound','index']);
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                return $this->container->call(['\PixelTrack\Controllers\NotAllowed','index'], [$allowedMethods]);
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $parameters = $routeInfo[2];
                return $this->container->call($handler, $parameters);
        }

        throw new \Exception('Dispatcher error');
    }
}
