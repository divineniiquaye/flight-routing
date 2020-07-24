# Change Log

All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## [0.5.1] - 2020-07-25
### Fixed
- Fixed failing tests on github action

## [0.5.0] - 2020-07-24
### Added
- Added changelog for 0.x versions
- Added Dependabot to make changes to **composer.json** file
- Added **.editorconfig** and **.gitattributes** files
- Added tests for **psalm**, **github actions**, **phpstan** and **phpcs** for PSR-12 coding standing

### Changed
- Made changes to `README.md` file
- Made changes to `CHANGELOG.md` file
- Updated `CHANGELOG.md` file for [unreleased] version
- Replaced `Support_us.md` with `FUNDING.yml` in **.github** folder
- Made changes to `CONTRIBUTING.md` file and moved it to **.github** folder
- Made changes to **composer.json** file
- Updated php version to 7.1 for stable release and dev release
- Updated psalm and phpunit config file to be prefixed .dist
- Marked `Flight\Routing\Exceptions\UrlGenerationException` class as final

### Fixed
- Fixed minor documentation bug on RouteGroup class
- Fixed minor issues with parsing static method as string

### Removed
- Deleted `.scrutinizer.yml` file by @biustudio
- Removed unassigned variable from `Flight\Routing\Concerns\CallableHandler` class
- Removed `Countable` implementation from `Flight\Routing\Interfaces\RouterInterface`
- Delete tests bootstrap file. used composer's autoload file

## [0.2.9] - 2020-06-22
### Added
- Added a call to `flush()` method within `Flight\Routing\Services\HttpPublisher` class, sending response to the browser more quickly.
- Added calendar (year, month, day) segment types to `Flight\Routing\Services\SimpleRouteCompiler` class

### Changed
- Made changes to `README.md` file
- Made changes to `CHANGELOG.md` file
- Made changes to `CONTRIBUTING.md` file
- Made changes to **.gitignore** file
- Made changes to **.travis.yml** file
- Updated php files header doc
- Updated coding standard to psr-12 to minimize breaks changes

### Fixed
- Fixed issues with `Flight\Routing\Services\SimpleRouteCompiler` class for faster route compiling and matching
- Fixed minor issues with php files end of line to "\n"
- Fixed major issues with failing tests on `Flight\Routing\Services\SimpleRouteCompiler` class

## [0.2.8] - 2020-06-11
### Fixed
- Apply fixes from StyleCI
- Fixed minor issues with docs comments
- Fixed minor issues appending namespace to class

## [0.2.7] - 2020-06-03
### Added
- Added psr-15 `RequestHandlerInterface` to `Flight\Routing\Interfaces\RouteCollectorInterface`

### Changed
- Renamed `dispatch()` method to `handle` in `Flight\Routing\RouteCollector` class requiring psr-7 `ServerRequestInterface`
- Made changes to `README.md` file
- Made changes to `CHANGELOG.md` file
- Made changes to most classes to improve code complexity to add extra performance

### Fixed
- Applied Scrutinizer Auto-Fixes
- Fixed minor issues with setting and getting `$namespace` in `Flight\Routing\Route` class
- Fixed major issues with weak route grouping
- Fixed error dispatching middlewares in `Flight\Routing\RouteCollector` class on first run

### Removed
- Deleted `Flight\Routing\Traits\ArgumentsTrait` and moved it's methods to `Flight\Routing\Traits\ControllersTrait`
- Removed two extra arguments from `Flight\Routing\Route` class
- Removed ['request'] arguments from `Flight\Routing\RouterProxy` and `Flight\Routing\RouteCollector` classes

## [0.2.5] - 2020-05-21
### Added
- Added phpunit config file **phpunit.xml**

## [0.2.4] - 2020-05-21
### Added
- Added `Flight\Routing\Traits\GroupsTrait` and `Flight\Routing\Traits\PathsTrait`
- Added tests for `Flight\Routing\RouteGroup`, `Flight\Routing\RouteCollector::generateUri()` and more

### Changed
- Made changes to `CHANGELOG.md` file
- Made changes to `Flight\Routing\RouteResults::handle()`
- Moved a few methods from `Flight\Routing\Route` to new paths and groups traits

### Fixed
- Fixed major issues with weak route grouping
- Fixed major issues with faulty route uri generation
- Improved code quality, applied PSR fixtures, and routing performance

### Removed
- Deleted deprecated class `Flight\Routing\Middlewares\RouteRunnerMiddleware`
- Deleted deprecated class `Flight\Routing\RouteResource`
- Deleted `Flight\Routing\RouteMiddleware` class, in favor of `Flight\Routing\Middlewares\MiddlewareDisptcher` class
- Deleted `Flight\Routing\Interfaces\ResourceController` interface, since `Flight\Routing\RouteResource` doesn't exists
- Removed argument ['6'] from `Flight\Routing\Route` class

## [0.2.2] - 2020-05-17
### Changed
- Made changes to **composer.json** file

### Fixed
- Applied fixes from StyleCI

## [0.2.1] - 2020-05-16
### Changed
- Made changes to **composer.json** file
- Made changes to **.travis.yml** file
- Made changes to `README.md` file

### Fixed
- Fixed major issues with `Psr\Log\LoggerInterface` class not found

## [0.2.0] - 2020-05-16
### Added
- Added `Flight\Routing\Concerns\CallableHandler` class for content-type detection
- Added `Flight\Routing\Exceptions\UriHandlerException` class
- Added serializable support for `Flight\Routing\Route` class
- Added `Flight\Routing\Middlewares\MiddlewareDisptcher` class for handling middleware
- Added ability to match domain and scheme from path.
- Added ability to match controller and method on a path
- Added phpunit tests

### Changed
- Renamed `Flight\Routing\Services\SymfonyRouteCompiler` class to `Flight\Routing\Services\SimpleRouteCompiler`
- Improved how routes are handled and dispatched (has breaking changes)
- Made changes to several classes for new route dispatching
- Made changes to `CHANGELOG.md` file

### Fixed
- Improved performance of routing x1.5
- Fixed minor issues with handling routes

### Removed
- Moved most methods from `Flight\Routing\Route` class to traits in `Traits` folder
- Marked `Flight\Routing\RouteResource` class as deprecated

## [0.1.1] - 2020-05-06
### Changed
- Made changes to `README.md` file
- Made changes to `CHANGELOG.md` file
- Made changes to **.scrutinizer.yml** file

### Fixed
- Fixed major issues with `Flight\Routing\Concerns\CallableResolver::addInstanceToClosure()` for PHP 7.1
- Fixed major issues with `Flight\Routing\Interfaces\CallableResolverInterface::addInstanceToClosure()` for PHP 7.1

## [0.1.0] - 2020-05-01
### Added
- Added license scan report and status to `README.md` file
- Added `Flight\Routing\Exceptions\MethodNotAllowedException` class
- Added extra support for route grouping
- Added new methods to most classes and minimal complexity and performance

### Changed
- Changed how routes are dispatched. (has breaking changes)
- Made changes to several classes for new route dispatching
- Made changes to **composer.json** file

### Fixed
- Fixed major issues breaking routing of urls to handlers
- Fixed major issues with type-hints due to **declare(strict_types=1);** found in php files
- Fixed major issues with array not able to convert into callable
- Fixed major issues generating and parsing url patterns
- Fixed major issues with classes, methods, properties and variables documentation

### Removed
- Marked `Flight\Routing\Middlewares\RouteRunnerMiddleware` class as deprecated

## [0.1-beta] - 2020-04-27
### Added
- Initial commit (made major fixtures and changes)

[0.5.1]: https://github.com/divineniiquaye/flight-routing/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/divineniiquaye/flight-routing/compare/v0.2.9...v0.5.0
[0.2.9]: https://github.com/divineniiquaye/flight-routing/compare/v0.2.8...v0.2.9
[0.2.8]: https://github.com/divineniiquaye/flight-routing/compare/v0.2.5...v0.2.8
[0.2.7]: https://github.com/divineniiquaye/flight-routing/compare/v0.2.5...v0.2.7
[0.2.5]: https://github.com/divineniiquaye/flight-routing/compare/v0.2.4...v0.2.5
[0.2.4]: https://github.com/divineniiquaye/flight-routing/compare/v0.2.2...v0.2.4
[0.2.2]: https://github.com/divineniiquaye/flight-routing/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/divineniiquaye/flight-routing/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/divineniiquaye/flight-routing/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/divineniiquaye/flight-routing/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/divineniiquaye/flight-routing/compare/v0.1-beta...v0.1.0
