<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.1 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Matchers;

use Flight\Routing\Interfaces\MatcherDumperInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\Traits\DumperTrait;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The routes dumper for any kind of route compiler.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class SimpleRouteDumper extends SimpleRouteMatcher implements MatcherDumperInterface
{
    use DumperTrait;

    /** @var string[] */
    private $dynamicRoutes = [];

    /** @var array<string,string|null> */
    private $staticRoutes = [];

    /** @var mixed[] */
    private $regexpList = [];

    /**
     * @param RouteCollection|string $collection
     */
    public function __construct($collection)
    {
        parent::__construct($collection);

        if (!$collection instanceof RouteCollection) {
            $this->export = false;
        }

        $this->warmCompiler($this->routes);
    }

    /**
     * {@inheritdoc}
     */
    public function dump()
    {
        return $this->generateCompiledRoutes();
    }

    /**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): ?Route
    {
        $requestUri    = $request->getUri();
        $requestMethod = $request->getMethod();
        $resolvedPath  = \rawurldecode($this->resolvePath($request));

        // To prevent breaks when routes are writing to cache file.
        if ($this->export) {
            $this->export = false;
            $this->warmCompiler($this->routes);
        }

        // FInd the matched route ...
        $matchedRoute = $this->getCompiledRoute($resolvedPath);

        if ($matchedRoute instanceof Route) {
            $matchDomain = $matchedRoute->getDefaults()['_domain'] ?? [[], []];

            return $this->matchRoute($matchedRoute, $requestUri, $requestMethod, $matchDomain);
        }

        return null;
    }

    /**
     * @return mixed[]
     */
    public function getCompiledRoutes()
    {
        return [$this->staticRoutes, $this->dynamicRoutes, $this->regexpList, $this->routes];
    }

    protected function getCompiledRoute(string $resolvedPath): ?Route
    {
        // Find static route ...
        if (isset($this->staticRoutes[$resolvedPath])) {
            return $this->routes[$this->staticRoutes[$resolvedPath]];
        }

        static $matchedRoute;
        $urlVariables = [];

        [$regexpList, $parameters] = $this->regexpList;

        // https://tools.ietf.org/html/rfc7231#section-6.5.5
        if ($this->compareUri($regexpList, $resolvedPath, $urlVariables)) {
            $routeId = $urlVariables['MARK'];
            unset($urlVariables[0], $urlVariables['MARK']);

            if (isset($this->dynamicRoutes[$routeId])) {
                $countVars    = 0;
                $matchedRoute = $this->routes[$this->dynamicRoutes[$routeId]];
                $parameters   = $parameters[$routeId];

                foreach ($matchedRoute->getArguments() as $key => $value) {
                    if (
                        \in_array($key, $parameters, true) &&
                        (null === $value && isset($urlVariables[$countVars]))
                    ) {
                        $matchedRoute->argument($key, $urlVariables[$countVars]);
                    }

                    $countVars++;
                }

                return $matchedRoute;
            }
        }

        return $matchedRoute;
    }

    /**
     * @param mixed[] $expressions
     * @param mixed[] $names
     *
     * @return mixed[]
     */
    private function generateExpressions(array $expressions, array $names)
    {
        // $namesCount For keeping track of the names for sub-matches.
        // $captureCount For re-adjust backreferences.
        $namesCount = $captureCount = 0;
        $variables  = [];
        $tree       = new ExpressionCollection();

        foreach ($expressions as $expression) {
            $name = $names[$namesCount++];

            // Get delimiters and vars:
            [$pattern, $vars] = $this->filterExpression($expression, $captureCount);

            if (false === $pattern) {
                return [[], []];
            }

            $tree->addRoute($pattern, [$name, $pattern, $vars]);
        }

        $code = $this->export ? '\'#^(?\'' : '#^(?';
        $code .= $this->compileExpressionCollection($tree, 0, $variables);
        $code .= $this->export ? "\n    .')/?$#sD'" : ')/?$#sD';

        return [$code, $variables];
    }

    /**
     * @param Route[]|string $routes
     */
    private function warmCompiler($routes): void
    {
        if (\is_string($routes)) {
            [$this->staticRoutes, $this->dynamicRoutes, $this->regexpList, $this->routes] = require $routes;

            return;
        }

        $regexpList = $newRoutes = [];

        foreach ($routes as $route) {
            $compiledRoute = clone $this->getCompiler()->compile($route);

            $routeName     = $route->getName();
            $pathVariables = $compiledRoute->getPathVariables();

            if (!empty($compiledRoute->getHostVariables())) {
                $route->default('_domain', [$compiledRoute->getHostsRegex(), $compiledRoute->getHostVariables()]);
            }

            if (empty($pathVariables)) {
                $url  = \rtrim($route->getPath(), '/') ?: '/';

                $this->staticRoutes[$url] = $routeName;
            } else {
                $route->arguments($pathVariables);

                $this->dynamicRoutes[] = $routeName;
                $regexpList[]          = $compiledRoute->getRegex();
            }

            $newRoutes[$routeName] = $route;
        }
        $this->routes     = $newRoutes; // Set the new routes.
        $this->regexpList = $this->generateExpressions($regexpList, \array_keys($this->dynamicRoutes));
    }

    /**
     * @return mixed[]
     */
    private function filterExpression(string $expression, int &$captureCount): array
    {
        \preg_match('/^(.)\^(.*)\$.([a-zA-Z]*$)/', $expression, $matches);

        if (empty($matches)) {
            return [false, []];
        }

        $modifiers = [];
        $delimeter = $matches[1];
        $pattern   = $matches[2];

        $pattern = \preg_replace_callback(
            '/\?P<([^>]++)>/',
            static function (array $matches) use (&$modifiers): string {
                $modifiers[] = $matches[1];

                return '';
            },
            $pattern
        );

        if ($delimeter !== '/') {
            // Replace occurrences by the escaped delimiter by its unescaped
            // version and escape new delimiter.
            $pattern = \str_replace("\\$delimeter", $delimeter, $pattern);
            $pattern = \str_replace('/', '\\/', $pattern);
        }

        // Re-adjust backreferences:
        // TODO What about \R backreferences (\0 isn't allowed, though)?

        // We assume that the expression is correct and therefore don't check
        // for matching parentheses.
        $captures = \preg_match_all('/\([^?]|\(\?[^:]/', $pattern);

        if ($captures > 0) {
            $backref = '/
                (?<!\\\\)        # Not preceded by a backslash,
                ((?:\\\\\\\\)*?) # zero or more escaped backslashes,
                \\\\ (\d+)       # followed by backslash plus digits.
            /x';
            $pattern = \preg_replace_callback(
                $backref,
                static function (array $m) use ($captureCount): string {
                    return $m[1] . '\\\\' . ((int) $m[2] + $captureCount);
                },
                $pattern
            );
            $captureCount += $captures;
        }

        return [$pattern, $modifiers];
    }
}
