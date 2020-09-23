# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## [Unreleased][unreleased]

### Added
- Added `Flight\Routing\RouteCollection` class for setting and retrieving `Flight\Routing\Route` class instance
- Added a `Flight\Routing\RouteFactory` class as default factory class to serve in `Flight\Routing\RouteCollector` class
- Added a `Flight\Routing\Interfaces\RouteFactoryInterface`, so it servers in `Flight\Routing\RouteCollector` class
- Added **405** error code to `Flight\Routing\Exceptions\MethodNotAllowedException` class when thrown
- Added new classes to `Flight\Routing\Tests\Fixtures` namespace, for improved phpunit testing
- Added improvement to `Flight\Routing\RouteCollector` class for a great performance usage
- Added improvement to `Flight\Routing\Route` class for a great performance usage
- Added `Flight\Routing\Router` class for dispatching routes instead of `Flight\Routing\RouteCollector` class
- Added phpunit tests for over 90% coverage
- Added branch for 0.5.x version, so to provide security fixtures, update, and maintenance
- Added **resource** method to `Flight\Routing\RouteCollector` class and its interface
- Added a **restful** method type of implementation for api usage with named prefix `__restful`
- Added `Flight\Routing\Traits\CastingTrait` to reduce code complexity in `Flight\Routing\Route` class
- Added `Flight\Routing\RouteLoader` class for loading annotations and file callable
- Added `Flight\Routing\Middlewares\PathMiddleware` class tof fix issues with right uri path

### Changed
- Made few changes to `README.md` file
- Made few changes to **composer.json** file
- Made few changes to **phpunit.xml.dist** file
- Made few changes to **phpstan.neon.dist** file
- Made few changes to **psalm.xml.dist** file
- Update `CHANGELOG-0.x.md` file
- Update `CHANGELOG.md` file
- Update `README.md` file
- Made few changes to `Flight\Routing\Middlewares\MiddlewareDispatcher` class
- Made few changes to `Flight\Routing\CallableResolver` class
- Renamed `Flight\Routing\Concerns\CallableResolver` class to `Flight\Routing\CallableResolver` with file location changes
- Renamed `Flight\Routing\Concerns\CallableHandler` class to `Flight\Routing\RouteHandler` with file location changes
- Renamed `Flight\Routing\Concerns\RouteValidation` class to `Flight\Routing\RouteValidation` with file location changes
- Made major changes to `Flight\Routing\RouteGroup` class and its interface `Flight\Routing\Interfaces\RouteGroupInterface`
- Made `Flight\Routing\Exceptions\RouteNotFoundException` class to implement `Flight\Routing\Interfaces\ExceptionInterface`
- Made `Flight\Routing\Exceptions\MethodNotAllowedException` class to extends to `RuntimeException` instead
- Update `Flight\Routing\Exceptions\DuplicateRouteException` to extend with `LogicException` instead of `DomainException`
- Update `Flight\Routing\Exceptions\UrlGenerationException` to extend with `RuntimeException` instead of `DomainException`
- Replaced `Flight\Routing\Interfaces\RouterInterface` with `Flight\Routing\Interfaces\RouteMatcherInterface` with changes
- Replace `Flight\Routing\Services\DefaultFlightRouter` class with `Flight\Routing\Services\SimpleRouteMatcher` with changes
- Updated `Flight\Routing\Services\SimpleRouteCompiler` class to use new `Flight\Routing\Route` class methods
- Updated `Flight\Routing\Services\SimpleRouteCompiler` class regex's used for matching urls
- Updated `Flight\Routing\Services\SimpleRouteCompiler` class for partly nested matching. This change is temporal
- Made few changes in `Flight\Routing\Services\SimpleRouteMatcher` class
- Updated `Flight\Routing\Router` class to use **divineniiquaye/php-invoker** library
- Moved and Renamed `Flight\Routing\RouteValidation` trait to `Flight\Routing\Traits\ValidationTrait`
- Moved **resolveController** and **mergeAttributes** methods in `Flight\Routing\Router` class to `Flight\Routing\Traits\ValidationTrait`
- Updated `Flight\Routing\Publisher::publish` method and it's interface second arguments default to null.
- Updated `Flight\Routing\Router` class constructor to add `Psr\Http\Message\UriFactoryInterface` to arguments

### Fixed
- Fixed validating request methods with strict array in `Flight\Routing\RouteValidation::compareMethod` method
- Fixed major and minor issues with phpstan and psalm
- Fixed major and minor coding standard issues
- Fixed minor issues with **PSR-15** middlewares usage
- Fixed cache control middleware response with IE < 9 in `Flight\RoutingMiddlewares\CacheControlMiddleware` class
- Fixed PSR-4 autoloading standard issues in tests directory: **Middlewares** and **Services**

### Removed
- Removed proxy usage, use `Flight\Routing\RouteCollector` class instead
- Removed **Traits** and **Concerns** folder from codebase
- Deleted `Flight\Routing\RouteProxy` class and its interface `Flight\Routing\Interfaces\RouteProxyInterface`
- Deleted `Flight\Routing\Concerns\HttpMethods` class, use `Flight\Routing\Interfaces\RouteCollectorInterface` instead
- Deleted two classes under `Flight\Routing\Tests\Fixtures` namespace
- Deleted `Flight\Routing\RouteResults` class, use `Flight\Routing\RouteHandler` instead
- Removed exception thrown on **_fragment** variable in `Flight\Routing\Services\SimpleRouteCompiler::compile` method
- Removed tests for php 8.0 due to phpunit's `PHPUnit\Framework\TestCase::createMock` method
- Deleted `Flight\Routing\CallableResolver` class and it's `Flight\Routing\Tests\CallableResolverTest` class
- Deleted `Flight\Routing\Interfaces\CallableResolverInterface` and implementation in `Flight\Routing\Router` class
- Deleted `Flight\RoutingMiddlewares\ContentLengthMiddleware` class
- Deleted `Flight\RoutingMiddlewares\ContentTypeMiddleware` class
- Deleted `Flight\RoutingMiddlewares\ContentTypeOptionsMiddleware` class
- Deleted `Flight\RoutingMiddlewares\MethodOverrideMiddleware` class

[unreleased]: https://github.com/divineniiquaye/flight-routing/compare/v0.5.2...master
