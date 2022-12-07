CHANGELOG
=========

2.1
===

* [BC BREAK] Replaced `array_merge_recursive` in route to allow total replacement of route vars
* Added support to the router's `setCollection` method to strictly accept type of closure or route collection
* Improved route match performance tremendously nearly over 30% faster than previous version

2.0
===

* [BC BREAK] Removed the route class to use array instead of object
* [BC BREAK] Removed the route matcher class to use only the `Flight\Routing\Router` class for route matching
* [BC BREAK] Removed the `buildRoutes` method from the route collection class, use the `getRoutes` method directly
* [BC BREAK] Removed the `getRoute` method from the route collection class, use the `offGet` method instead
* [BC BREAK] Removed the `routes` method from the route collection class with no replacement
* [BC BREAK] Removed the `addRoute` method from the route collection class, use the `add` method instead
* [BC BREAK] Removed the `isCached` and `addRoute` methods from the default router class
* [BC BREAK] Removed classes, traits and class methods which are unnecessary or affects performance of routing
* [BC BREAK] Improved the route collection class to use array based routes instead of objects
* [BC BREAK] Improved how the default route handler handles array like callable handlers
* [BC BREAK] Replaced the route matcher implementation in the router class for compiler's implementation instead
* [BC BREAK] Replaced unmatched route host exception to instead return null and a route not found exception
* [BC BREAK] Renamed the `Flight\Routing\Generator\GeneratedUri` class to `Flight\Routing\RouteUri`
* Removed `symfony/var-exporter` library support from caching support, using PHP `var-export` function instead
* Added a new `FileHandler` handler class to return contents from a valid file as PSR-7 response
* Added new sets of requirements to the `Flight\Routing\RouteCompiler::SEGMENT_TYPES` constant
* Added a `offGet` method to the route collection class for finding route by it index number
* Added PHP `Countable` support to the route collection class, for faster routes count
* Added PHP `ArrayAccess` support to the route collection class for easier access to routes
* Added support for the default route compiler placeholder's default rule from `\w+` to `.*?`
* Added a static `export` method to the default router class to export php values in a well formatted way for caching
* Improved the route annotation's listener and attribute class for better performance
* Improved the default route matcher's `generateUri` method reversing a route path and strictly matching parameters
* Improved the default route matcher's class `Flight\Routing\Router::match()` method for better performance
* Improved the default route handler's class for easier extending of route handler and arguments rendering
* Improved the default route handler's class ability to detect content type of string
* Improved and fixed route namespacing issues
* Improved thrown exceptions messages for better debugging of errors
* Improved the sorting of routes in the route's collection class
* Improved the `README.md` doc file for better understanding on how to use this library
* Improved coding standard in making the codebase more readable
* Improved benchmarking scenarios for better performance comparison
* Improved performance tremendously, see [Benchmark Results](./BENCHMARK.txt)
* Updated all tests units rewritten with `pestphp/pest` for easier maintenance and improved benchmarking
* Updated minimum requirement for installing this library to PHP 8.0

1.6
===

* Added four public constants to modify the returned value of the `Flight\Routing\Generator\GeneratedUri` class
* Added a third parameter to the router matcher and route's compiler interface `generateUri` method
* Added `symfony/var-exporter` support in providing better performance for cached routes
* Added a `setData` method to the default route class for custom setting of additional data
* Added new sets of requirements to the `Flight\Routing\RouteCompiler::SEGMENT_TYPES` constant
* Improved the default's route class `__set_state` method to work properly with the `var_export` function
* Improved the `Flight\Routing\Generator\GeneratedUri` class in generating reversed route path
* Improved matching cached duplicated dynamic route pattern as unique to avoid regex error
* [BR Break] Replaced the `RouteGeneratorInterface` interface with a `UrlGeneratorInterface` implementation
* [BC Break] Updated the MRM feature on non-cached routes as optional as it affects performance
* Removed the group trait from the route collection class and merge method into the route's collection class
* Removed `PSR-6` cache support from the default router class
* Removed travis CI build support, GitHub Action is the preferred CI

1.5
===

* Added an `attributes` parameter to attributed route class
* Added hasMethod and hasScheme public methods to the route class
* Updated the Route's public const `PRIORITY_REGEX` value
* Updated `biurad/annotation` library to `^1.0` and improved support
* Removed custom route matcher support in the default route matcher class
* Improved priority route's match regex pattern
* Improved static routes generated by the default compiler's build method
* Improved performance matching compiled and non-compiled routes

1.4
===

* [BC Break] Refactored route matching algorithm
* [BC Break] Updated and Renamed `RouteCollector` class to `RouteCollection`
* Added benchmark to compare performance over time
* Added custom route matcher support to the router class
* Added attributes parameter to the route's annotation class
* Updated routes cache from serialization to var_export
* Updated PHP minimum version to 7.4 and added PHP 8.1 support
* Updated README file with documentation of new changes
* Improved PSR-15 middleware performance
* Improved route's handler class code complexity and performance
* Improved overall performance of adding and matching routes

1.0
===

* [BC Break] Refactored route matching algorithm
* [BC Break] Renamed, Added, and Removed unused classes and methods
* Added and reverted debug mode with routes profiling
* Added PHP 7.4 and 8 support to codebase
* Updated README file with documentation of new changes
* Updated phpunit tests coverage to 90+
* Fixed PSR-4 autoloading standard issues
* Fixed coding standard and static analysers issues
* Improved restful routes implementation
* Improved overall performance of adding and matching routes

0.5
===

* [BC Break] Removed PHP countable interface from route collection's interface
* [BC Break] Renamed missed spelled middleware dispatcher class
* [BC Break] Renamed missed spelled content type middleware class
* Added .github and git community health files
* Added static types analysers (PHPStan, PHPCS, PSalm)
* Updated and fixed PHP types issues and doc commenting
* Updated and fixed phpunit tests cases
* Fixed issue parsing static method as string
* Improved overall performance of adding and matching routes
* Marked `Flight\Routing\Exceptions\UrlGenerationException` class as final
