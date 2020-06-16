# Changelog

All notable changes to `divineniiquaye/flight-routing` will be documented in this file.

## 0.2.10 - 2020-06-16

- Added a call to *flush()* within the *HttpPublisher*, This provide response to the browser more quickly.
- Lifted php minnimum version to 7.2 to due new features prepared for router
- Updated coding standard to psr-12 to minimize breaks changes
- Updated *SimpleRouteCompiler* class for faster route compling and matching
- Updated php files header doc

## 0.2.7 - 2020-05-28

- Added psr-15 *RequestHandlerInterface* to *RouteCollectorInterface*
- Added methods from deleted *ArgumentsTrait* to *ControllersTrait*
- Improved code complexity to add some extra performance
- Removed two extra arguments from *Route* class
- Removed ['request'] arguments from *RouterProxy* and *RouteCollector* classes
- Removed some unnecessary methods from *RouteCollector* class
- Removed namespace key in *$groups* variable
- Renamed `dispatch()` method to `handle` method requiring psr-7 *ServerRequestInterface*
- Deleted *ArgumentsTrait* trait from Traits folder
- Fixed issues with setting and getting `$namespace` in *Route* class
- Fixed issues with performing a `route grouping`
- Updated *$namespace* parameter to public static
- Updated REAME.md file

## 0.2.4 - 2020-05-18

- Fixed minor issues with `route grouping`
- Fixed minor issues with `route uri generator`
- Improved code quality, applied psr fixtures, and routing performance
- Deleted *RouteMiddleware* class, and use *MiddlewareDispatcher* as replacements
- Deleted *RouteResource* class, it will be replaced in the future
- Deleted *ResourceController* interface
- Deleted *RouteRunnerMiddleware* middleware class
- Removed some `use function` statements from codebase
- Removed some methods from *Route* class into new traits
- Removed argument ['6'] from *Route* class
- Added *tests* for `route group`, `route uri generator` and more
- Added *PathsTrait* and *GroupsTrait* traits to *Route* class
- Updated *MethodNotAllowedException* class
- Updated `handle` method in *RouteResults* class

## 0.2.0 - 2020-05-16

- Improved how routes are handled and dispatched
- Improved performance of routing x1.5
- Renamed *SymfonyRouteCompiler* class to *SimpleRouteCompiler*
- Removed *RouteResource* class and support for resource routing
- Removed some methods from *Route* class to traits in `Traits` folder
- Added ability to match domain and scheme from path.
- Added ability to match controller and action on path
- Added serializable support for *Route* class
- Added new methods to some classes and interfaces
- Added phpunit testing Classes
- Added *schemes* to routing
- Added a new folder `Traits`
- Added *CallableHandler* class in `Concerns` folder
- Added *UriHandlerException* class in `Exceptions` folder
- Updated README.md file

## 0.1.1 - 2020-05-04

- Added improvement for routes handling
- Added support for php 7.1 to php 8.0
- Update README.md file

## 0.1.0 - 2020-02-21

- First release
