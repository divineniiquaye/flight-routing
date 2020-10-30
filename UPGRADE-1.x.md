## UPGRADE FROM 0.5.x to 1.x.x

---

-   `Flight\Routing\RouteCollector` now takes one argument, which is an instance of `Flight\Routing\Interfaces\RouteFactoryInterface`
-   Added new `Flight\Routing\Router` class for dispatching routes and middlewares
-   `Flight\Routing\Route` class can be constructed with only four arguments, which are all mandatory
-   Added a compulsory **name** string argument as first parameter of `Flight\Routing\Route` constructor
-   Changed `Flight\Routing\Interfaces\RouterInterface` to `Flight\Routing\Interfaces\RouteMatcherInterface` for performance
-   Removed `Flight\Routing\RouteResults` class, use `Flight\Routing\RouteHandler` class instead
-   Changed how routes are handled and dispatched

    _Before_

    ```php
    <?php

    use Flight\Routing\Publisher;
    use Flight\Routing\RouteCollector as Router;
    use BiuradPHP\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;

    $router = new Router(new Psr17Factory());

    $router->get('/phpinfo', 'phpinfo'); // Will create a phpinfo route.

    // Start the routing
    (new Publisher)->publish($router->handle(Psr17Factory::fromGlobalRequest()));
    ```

    _After_

    ```php
    use Flight\Routing\{RouteCollector, Router, Publisher};
    use Biurad\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;

    $collector = new RouteCollector();

    $collector->get('phpinfo', '/phpinfo', 'phpinfo'); // Will create a phpinfo route.

    $factory = new Psr17Factory();
    $router = new Router($factory, $factory);

    $router->addRoute(...$collector->getCollection());

    // Start the routing
    (new Publisher)->publish($router->handle($factory::fromGlobalRequest()));
    ```

-   Adding PSR-15 middlewares to routes has been improved

    _Before_

    ```php
    $response = $router->handle(Psr17Factory::fromGlobalRequest());
    ```

    _After_

    ```php
    use Flight\Routing\RoutePipeline;

    $pipeline = (new RoutePipeline())->withRouter($router);

    // If you want to add global middlewares, use the $pipeline, addMiddleware method.

    $response = $pipeline->handle(Psr17Factory::fromGlobalRequest());
    ```

    OR

    ```php
    use Flight\Routing\RoutePipeline;

    $pipeline = new RoutePipeline();

    // If you want to add global middlewares, use the $pipeline, addMiddleware method.

    $response = $pipeline->process(Psr17Factory::fromGlobalRequest(), $router);
    ```

-   Changed how route grouping is handled

    _Before_

    ```php
    use Flight\Routing\Interfaces\RouterProxyInterface;

    $router->group(
        [...], // Add your group attributes
        function (RouterProxyInterface $route) {
            // Define your routes using $route...
        }
    );
    ```

    _After_

    ```php
    use Flight\Routing\Interfaces\RouteCollectorInterface;

    $collector->group(
        function (RouteCollectorInterface $route) {
            // Define your routes using $route...
        }
    );
    ```
