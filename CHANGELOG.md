CHANGELOG
=========

1.4
---

* [BC Break] Refactored route matching algorithm
* [BC Break] Updated and Renamed `RouteCollector` class to `RouteCollection`
* Added benchmark to compare performance over time
* Added custom route matcher support to the router class
* Updated routes cache from serialization to var_export
* Updated PHP minimum version to 7.4 and added PHP 8.1 support
* Updated README file with documentation of new changes
* Improved PSR-15 middleware performance
* Improved route's handler class code complexity and performance
* Improved overall performance of adding and matching routes

1.0
---

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

0.5 - 2020-07-24
---

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
