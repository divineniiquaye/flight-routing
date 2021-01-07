## UPGRADE FROM 0.5.x to 1.0.0

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
    use Flight\Routing\{RouteCollector, Router};
    use Biurad\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;
    use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;

    $collector = new RouteCollector();
    $collector->get('phpinfo', '/phpinfo', 'phpinfo'); // Will create a phpinfo route.

    $factory = new Psr17Factory();
    $router = new Router($factory, $factory);

    $router->addRoute(...$collector->getCollection());

    // Start the routing
    (new SapiStreamEmitter())->emit($router->handle($factory::fromGlobalRequest()));
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

## UPGRADE FROM 1.0.0 to 1.x.x

---

- Removed `Flight\Routing\RouteCollector` class (BR Changes)
- Removed `Flight\Routing\RouteFactory` class (BR Changes)
- Changed how routes are handled and dispatched

    _Before_

    ```php
    use Flight\Routing\{RouteCollector, Router};
    use Biurad\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;
    use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;

    $collector = new RouteCollector();
    $collector->get('phpinfo', '/phpinfo', 'phpinfo'); // Will create a phpinfo route.

    $factory = new Psr17Factory();
    $router = new Router($factory, $factory);

    $router->addRoute(...$collector->getCollection());

    // Start the routing
    (new SapiStreamEmitter())->emit($router->handle($factory::fromGlobalRequest()));
    ```

    _After_

    ```php
    use Flight\Routing\{RouteList, Route, Router};
    use Biurad\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;
    use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;

    $collector = new RouteList();
    $collector->add(Route::get('phpinfo', '/phpinfo', 'phpinfo')); // Will create a phpinfo route.

    $factory = new Psr17Factory();
    $router = new Router($factory, $factory);

    $router->addRoute(...$collector->getRoutes());

    // Start the routing
    (new SapiStreamEmitter())->emit($router->handle($factory::fromGlobalRequest()));
    ```

-   Changed how route grouping is handled

    _Before_

    ```php
    use Flight\Routing\RouteCollector;
    use Flight\Routing\Interfaces\RouteCollectorInterface;

    $collector = new RouteCollector();

    $collector->group(
        function (RouteCollectorInterface $route) {
            // Define your routes using $route...
        }
    );
    ```

    _After_

    ```php
    $collector = new RouteList();

    $collector->group(
        function (RouteListInterface $group) {
            // Define your routes using $route...
        }
    );

    $collector->addForeach(...);
    ```

