# UPGRADE FROM `v1.0.0` TO `v1.4.0`

* This upgrade comes with minimal dependencies of PHP 7.4. It's strongly recommended updating to new release.
* We've added, upgraded, and removed several packages this library depends on.
* Changes made to how routing is handled.

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

* Changes made to how route grouping is handled.

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


# UPGRADE FROM `v0.5.x` TO `v1.0.0`

Changes has been made to codebase which has affected how the library is meant to be used.

* Changes made to how routing is handled.

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

* Changes made to how route grouping is handled.

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

