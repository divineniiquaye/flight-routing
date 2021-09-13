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

namespace Flight\Routing;

use Flight\Routing\Routes\{FastRoute as Route, Route as BaseRoute};
use Flight\Routing\Exceptions\{UriHandlerException, UrlGenerationException};
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Interfaces\RouteCompilerInterface;

/**
 * RouteCompiler compiles Route instances to regex.
 *
 * provides ability to match and generate uris based on given parameters.
 *
 * @final This class is final and recommended not to be extended unless special cases
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class RouteCompiler implements RouteCompilerInterface
{
    private const DEFAULT_SEGMENT = '[^\/]+';

    /**
     * This string defines the characters that are automatically considered separators in front of
     * optional placeholders (with default and no static text following). Such a single separator
     * can be left out together with the optional placeholder from matching and generating URLs.
     */
    private const PATTERN_REPLACES = ['/' => '\\/', '/[' => '\/?(?:', '[' => '(?:', ']' => ')?', '.' => '\.'];

    private const SEGMENT_REPLACES = ['/' => '\\/', '.' => '\.'];

    /**
     * This regex is used to match a certain rule of pattern to be used for routing.
     *
     * List of string patterns that regex matches:
     * - /{var} - A required variable pattern
     * - /[{var}] - An optional variable pattern
     * - /foo[/{var}] - A path with an optional sub variable pattern
     * - /foo[/{var}[.{format}]] - A path with optional nested variables
     * - /{var:[a-z]+} - A required variable with lowercase rule
     * - /{var=foo} - A required variable with default value
     * - /{var}[.{format:(html|php)=html}] - A required variable with an optional variable, a rule & default
     */
    private const COMPILER_REGEX = '~\{(\w+)(?:\:(.*?\}?))?(?:\=(\w+))?\}~iu';

    /**
     * This regex is used to reverse a pattern path, matching required and options vars.
     */
    private const REVERSED_REGEX = '#(?|\<(\w+)\>|(\[(.*)]))#';

    /**
     * A matching requirement helper, to ease matching route pattern when found.
     */
    private const SEGMENT_TYPES = [
        'int' => '\d+',
        'lower' => '[a-z]+',
        'upper' => '[A-Z]+',
        'alpha' => '[A-Za-z]+',
        'alnum' => '[A-Za-z0-9]+',
        'year' => '[12][0-9]{3}',
        'month' => '0[1-9]|1[012]+',
        'day' => '0[1-9]|[12][0-9]|3[01]+',
        'uuid' => '0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}',
    ];

    /**
     * A helper in reversing route pattern to URI.
     */
    private const URI_FIXERS = [
        '[]' => '',
        '[/]' => '',
        '[' => '',
        ']' => '',
        '://' => '://',
        '//' => '/',
        '/..' => '/%2E%2E',
        '/.' => '/%2E',
    ];

    /**
     * The maximum supported length of a PCRE subpattern name
     * http://pcre.org/current/doc/html/pcre2pattern.html#SEC16.
     *
     * @internal
     */
    private const VARIABLE_MAXIMUM_LENGTH = 32;

    /**
     * {@inheritdoc}
     */
    public function compile(Route $route): array
    {
        $requirements = $route->get('patterns');
        $routePath = \ltrim($route->get('path'), '/');
        $variables = []; // The vars found in path and hosts regex.

        // Strip supported browser prefix of $routePath ...
        if (isset(BaseRoute::URL_PREFIX_SLASHES[@$routePath[-1]])) {
            $routePath = \substr($routePath, 0, -1);
        }

        // A path containing {} is a Dynamic route.
        if (\str_contains($routePath, '{')) {
            [$pathRegex, $variables] = self::compilePattern('/' . $routePath, false, $requirements);
        } else {
            $pathRegex = \strtr('/' . $routePath, self::SEGMENT_REPLACES);
        }

        if ($route instanceof Routes\DomainRoute) {
            $hosts = $route->get('hosts');

            if (!empty($hosts)) {
                $hostsRegex = self::compileHosts($hosts, $requirements, $variables);
            }
        }

        return [$pathRegex, $hostsRegex ?? null, $variables];
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(Route $route, array $parameters): GeneratedUri
    {
        [$pathRegex, $pathVariables] = self::compilePattern('/' . \ltrim($route->get('path'), '/'), true);

        $defaults = $route->get('defaults');
        $createUri = new GeneratedUri(self::interpolate($pathRegex, $parameters, $defaults + $pathVariables));

        foreach ($route->get('hosts') as $host) {
            $compiledHost = self::compilePattern($host, true);

            if (!empty($compiledHost)) {
                [$hostRegex, $hostVariables] = $compiledHost;

                break;
            }
        }

        if (!empty($schemes = $route->get('schemes'))) {
            $createUri->withScheme(\in_array('https', $schemes, true) ? 'https' : \end($schemes) ?? 'http');

            if (!isset($hostRegex)) {
                $createUri->withHost($_SERVER['HTTP_HOST'] ?? '');
            }
        }

        if (isset($hostRegex)) {
            $createUri->withHost(self::interpolate($hostRegex, $parameters, $defaults + $hostVariables));
        }

        return $createUri;
    }

    /**
     * Check for mandatory parameters then interpolate $uriRoute with given $parameters.
     *
     * @param array<string,mixed> $parameters
     * @param array<string,mixed> $defaults
     */
    private static function interpolate(string $uriRoute, array $parameters, array $defaults): string
    {
        $required = []; // Parameters required which are missing.
        $replaces = self::URI_FIXERS;

        // Fetch and merge all possible parameters + route defaults ...
        \preg_match_all(self::REVERSED_REGEX, $uriRoute, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

        foreach ($matches as $matched) {
            if (3 === \count($matched) && isset($matched[2])) {
                \preg_match_all('#\<(\w+)\>#', $matched[2], $optionalVars, \PREG_SET_ORDER);

                foreach ($optionalVars as [$type, $var]) {
                    $replaces[$type] = $parameters[$var] ?? $defaults[$var] ?? null;
                }

                continue;
            }

            $replaces[$matched[0]] = $parameters[$matched[1]] ?? $defaults[$matched[1]] ?? null;

            if (null === $replaces[$matched[0]]) {
                $required[] = $matched[1];
            }
        }

        if (!empty($required)) {
            throw new UrlGenerationException(\sprintf('Some mandatory parameters are missing ("%s") to generate a URL for route path "%s".', \implode('", "', $required), $uriRoute));
        }

        return \strtr($uriRoute, $replaces);
    }

    private static function sanitizeRequirement(string $key, ?string $regex): string
    {
        if ('' !== $regex) {
            if ('^' === $regex[0]) {
                $regex = \substr($regex, 1);
            } elseif (0 === \strpos($regex, '\\A')) {
                $regex = \substr($regex, 2);
            }
        }

        if (\str_ends_with($regex, '$')) {
            $regex = \substr($regex, 0, -1);
        } elseif (\strlen($regex) - 2 === \strpos($regex, '\\z')) {
            $regex = \substr($regex, 0, -2);
        }

        if ('' === $regex) {
            throw new \InvalidArgumentException(\sprintf('Routing requirement for "%s" cannot be empty.', $key));
        }

        return null !== $regex ? \strtr($regex, self::SEGMENT_REPLACES) : self::DEFAULT_SEGMENT;
    }

    /**
     * @param array<string,string|string[]> $requirements
     *
     * @throws UriHandlerException if a variable name starts with a digit or
     *                             if it is too long to be successfully used as a PCRE subpattern or
     *                             if a variable is referenced more than once
     *
     * @return array<string,mixed>
     */
    private static function compilePattern(string $uriPattern, bool $reversed = false, array $requirements = []): array
    {
        $variables = []; // VarNames mapping to values use by route's handler.
        $replaces = $reversed ? ['?' => ''] : self::PATTERN_REPLACES;

        // correct [/ first occurrence]
        if (1 === \strpos($uriPattern, '[/')) {
            $uriPattern = '/[' . \substr($uriPattern, 3);
        }

        // Match all variables enclosed in "{}" and iterate over them...
        \preg_match_all(self::COMPILER_REGEX, $uriPattern, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

        foreach ($matches as [$placeholder, $varName, $segment, $default]) {
            // Filter variable name to meet requirement
            self::filterVariableName($varName, $uriPattern);

            if (\array_key_exists($varName, $variables)) {
                throw new UriHandlerException(\sprintf('Route pattern "%s" cannot reference variable name "%s" more than once.', $uriPattern, $varName));
            }

            $variables[$varName] = $default;
            $replaces[$placeholder] = !$reversed ? '(?P<' . $varName . '>' . (self::SEGMENT_TYPES[$segment] ?? $segment ?? self::prepareSegment($varName, $requirements)) . ')' : "<$varName>";
        }

        return [\strtr($uriPattern, $replaces), $variables];
    }

    /**
     * @param string[]                      $hosts
     * @param array<string,string|string[]> $requirements
     */
    private static function compileHosts(array $hosts, array $requirements, array &$variables): ?string
    {
        $hostsRegex = [];

        foreach ($hosts as $host) {
            [$hostRegex, $hostVars] = self::compilePattern($host, false, $requirements);

            $variables += $hostVars;
            $hostsRegex[] = $hostRegex;
        }

        return 1 === \count($hostsRegex) ? $hostsRegex[0] : '(?|' . \implode('|', $hostsRegex) . ')';
    }

    /**
     * Filter variable name to meet requirements.
     *
     * @param array<string,string|null> $varNames
     */
    private static function filterVariableName(string $varName, string $pattern): void
    {
        // A PCRE subpattern name must start with a non-digit. Also a PHP variable cannot start with a digit so the
        // variable would not be usable as a Controller action argument.
        if (1 === \preg_match('/^\d/', $varName)) {
            throw new UriHandlerException(\sprintf('Variable name "%s" cannot start with a digit in route pattern "%s". Use a different name.', $varName, $pattern));
        }

        if (\strlen($varName) > self::VARIABLE_MAXIMUM_LENGTH) {
            throw new UriHandlerException(
                \sprintf(
                    'Variable name "%s" cannot be longer than %s characters in route pattern "%s".',
                    $varName,
                    self::VARIABLE_MAXIMUM_LENGTH,
                    $pattern
                )
            );
        }
    }

    /**
     * Prepares segment pattern with given constrains.
     *
     * @param array<string,mixed> $requirements
     */
    private static function prepareSegment(string $name, array $requirements): string
    {
        if (!isset($requirements[$name])) {
            return self::DEFAULT_SEGMENT;
        }

        if (!\is_array($segment = $requirements[$name])) {
            return self::sanitizeRequirement($name, $segment);
        }

        return \implode('|', \array_map(
            static function (string $segment) use ($name): string {
                return self::sanitizeRequirement($name, $segment);
            },
            $segment
        ));
    }
}
