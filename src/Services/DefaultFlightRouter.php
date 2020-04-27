<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing\Services;

use Flight\Routing\Concerns\RouteValidation;
use Flight\Routing\Exceptions\UrlGenerationException;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouterInterface;
use Psr\Http\Message\ServerRequestInterface;
use Flight\Routing\RouteResults;

use function preg_replace_callback;
use function array_key_exists;
use function dirname;
use function substr;
use function strlen;
use function rawurldecode;
use function array_replace;
use function trim;

class DefaultFlightRouter implements RouterInterface
{
    use RouteValidation;

    /**
     * Symfony RouteCompiler.
     *
     * @var SymfonyRouteCompiler
     */
    private $compiler;

    /**
     * Routes to inject into the underlying RouteCollector.
     *
     * @var RouteInterface[]
     */
    private $routesToInject = [];

    /**
     * Constructor.
     *
     * Accepts optionally a FastRoute RouteCollector and a callable factory
     * that can return a FastRoute dispatcher.
     *
     * If either is not provided defaults will be used:
     *
     * - A SymfonyRouteCompiler instance will parse the routes and return
     *   the absolute matched route.
     *
     * @param SymfonyRouteCompiler|null $compiler    if not provided, a default
     *                                               implementation will be used
     */
    public function __construct(SymfonyRouteCompiler $compiler = null)
    {
        $this->compiler = $compiler ?? new SymfonyRouteCompiler;
    }

    /**
     * Add a route to the collection.
     *
     * Uses Symfony routing style. Since it has been adopted
     * by many projects and framework including laravel framework.
     */
    public function addRoute(RouteInterface $route): void
    {
        $this->routesToInject[] = $route;
    }

    /**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): RouteResults
    {
        $method = $request->getMethod();
        $domain = $request->getUri()->getHost();

        // HEAD and GET are equivalent as per RFC
        if ('HEAD' === $method = $request->getMethod()) {
            $method = 'GET';
        }

        $basepath = dirname($request->getServerParams()['SCRIPT_NAME'] ?? '');

        // For phpunit testing to be smooth.
        if ('cli' === PHP_SAPI) {
            $basepath = '';
        }

        $finalisedPath = substr($request->getUri()->getPath(), strlen($basepath));
        $matched = $this->marshalMatchedRoute($method, $domain, rawurldecode($finalisedPath));

        // Get the request matching format.
        [$status, $parameters, $route] = $matched;
        $finalised = new RouteResults($method, $finalisedPath, $status, $parameters, $route);

        // A feature adopted from Symfony routing, workaround fix.
        if (
            RouteResults::FOUND === $status &&
            null !== $redirectedPath = $this->compareRedirection($route->getPath(), $finalisedPath)
        ) {
            $prefix = strlen($basepath) > 1 ? $basepath . '' : '/';
            $finalised->shouldRedirect($prefix . $redirectedPath);
        }

        return $finalised;
    }

    /**
     * Marshals a route result based on the results of matching URL from set of routes.
     *
     * @param string $method The current request method
     * @param string $host The domain to be parsed
     * @param string $path The path info to be parsed
     *
     * @return array An array of results.
     */
    private function marshalMatchedRoute(string $method, string $host, string $path): array
    {
        $path = trim($path, '/') ?: '/';

        foreach ($this->routesToInject as $index => $route) {
            // Let's match the routes
            $match = $this->compiler->compile($route);
            [$parameters, $HostParameters] = [[], []];

            if (
                $this->compareDomain($match->getHostRegex(), $host, $HostParameters) &&
                $this->compareUri($match->getRegex(), $path, $parameters)
            ) {
                // Throw and exception if url is not found no request method.
                if (!$this->compareMethod($route->getMethods(), $method)) {
                    return [RouteResults::METHOD_NOT_ALLOWED, [], null];
                }

                return [RouteResults::FOUND, array_replace($parameters, $HostParameters), $route];
            }
        }

        return [RouteResults::NOT_FOUND, [], null];
    }

    /**
     * Generate a URI based on a given route.
     *
     * Replacements in FlightCaption are written as `{name}` or `{name<pattern>}`;
     * this method will automatedly search for the best route
     * match based on the available substitutions and generates a uri.
     *
     * {@inheritdoc}
     *
     * @return string URI path generated
     */
    public function generateUri(RouteInterface $route, array $substitutions = []): string
    {
        $routePath = $this->compiler->compile($route);
        $defaults  = $route->getDefaults();

        // One route pattern can correspond to multiple routes if it has optional parts or defaults
        $uri = preg_replace_callback('/\??\{(.*?)\??\}/', function ($match) use ($substitutions, $defaults) {
            if (isset($substitutions[$match[1]])) {
                return $substitutions[$match[1]];
            } elseif (array_key_exists($match[1], $defaults)) {
                return $defaults[$match[1]];
            }

            return  $match[0];
        }, $routePath->getStaticRegex());

        // We'll make sure we don't have any missing $substitutions or we
        // will need to throw the exception to let the developers know one was not given.
        $path = preg_replace_callback('/\{(.*?)(\?)?\}/', function ($match) use (&$defaults, $route) {
            if (! array_key_exists($match[1], $defaults)) {
                throw UrlGenerationException::forMissingParameters($route);
            }

            return '';
        }, $uri);

        return $path; // Return generated path
    }
}
