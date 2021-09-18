## UPGRADE FROM 0.5.x to 1.0.0

---

-   `Flight\Routing\RouteCollector` now takes one argument, which is an instance of `Flight\Routing\Interfaces\RouteFactoryInterface`
-   Added new `Flight\Routing\Router` class for dispatching routes and middlewares
-   `Flight\Routing\Route` class can be constructed with only four arguments, which are all mandatory
-   Added a compulsory **name** string argument as first parameter of `Flight\Routing\Route` constructor
-   Changed `Flight\Routing\Interfaces\RouterInterface` to `Flight\Routing\Interfaces\RouteMatcherInterface` for performance
-   Removed `Flight\Routing\RouteResults` class, use `Flight\Routing\Handlers\RouteHandler` class instead
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

- Renamed `Flight\Routing\RouteCollector` to `Flight\Routing\RouteCollection` class (BR Changes)
- Removed `Flight\Routing\RouteFactory` class (BR Changes)
- Added `Flight\Routing\RouteMatcher` class
- Replaced **handle** to **process** in the `Flight\Routing\Router` class
- Added a default `Flight\Routing\RouteHandler` class for dispatching matched route
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
    use Flight\Routing\{Handlers\RouteHandler, RouteCollection, Router};
    use Biurad\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;
    use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;

    $router = new Router();
    $router->setCollection(static function (RouteCollection $collector): void {
        $collector->get('/phpinfo', 'phpinfo'); // Will create a phpinfo route.
    });

    $psr17Factory = new Psr17Factory();
    $response = $router->process($psr17Factory->fromGlobalRequest(), new RouteHandler($psr17Factory));

    // Start the routing
    (new SapiStreamEmitter())->emit($response);
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
    $collection = new RouteCollection();

    // callable grouping
    $group1 = function (RouteCollection $group) {
        // Define your routes using $group...
    };

    // or collection grouping
    $group2 = new RouteCollection();
    $group2->addRoute('/phpinfo', ['GET', 'HEAD'], 'phpinfo');

    $collection->group('group_name', $group1);
    $collection->group('group_name', $group2);

    //or dsl
    $collection->group('group_name')
        ->addRoute('/phpinfo', ['GET', 'HEAD'], 'phpinfo')->end()
        // ... More can be added including nested grouping
    ->end();
    ```

