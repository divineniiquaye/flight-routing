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

use Flight\Routing\{GeneratedUri, Route};
use Flight\Routing\Exceptions\{UriHandlerException, UrlGenerationException};
use Flight\Routing\Interfaces\RouteCompilerInterface;

/**
 * RouteCompiler compiles Route instances to regex.
 *
 * provides ability to match and generate uris based on given parameters.
 *
 * @final This class is final and recommended not to be extended unless special cases
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteCompiler implements RouteCompilerInterface
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
    private const COMPILER_REGEX = '~\\{(\\w+)(?:\\:([^{}=]+(?:\\{[\\w,^{}]+)?))?(?:\\=((?2)))?\\}~i';

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
        $hostVariables = $hostsRegex = [];
        $requirements = $this->getRequirements($route->get('patterns'));
        $routePath = \ltrim($route->get('path'), '/');

        // Strip supported browser prefix of $routePath ...
        if (!empty($routePath) && isset(Route::URL_PREFIX_SLASHES[$routePath[-1]])) {
            $routePath = \substr($routePath, 0, -1);
        }

        if (!empty($hosts = $route->get('domain'))) {
            foreach ($hosts as $host) {
                [$hostRegex, $hostVariable] = $this->compilePattern($host, false, $requirements);

                $hostVariables += $hostVariable;
                $hostsRegex[] = $hostRegex;
            }
        }

        if (!\str_contains($routePath, '{')) {
            return ['/' . $routePath, $hostsRegex, $hostVariables];
        }

        [$pathRegex, $pathVariables] = $this->compilePattern($routePath, false, $requirements);

        return ['\\/' . $pathRegex, $hostsRegex, empty($hostVariables) ? $pathVariables : $hostVariables += $pathVariables];
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(Route $route, array $parameters, array $defaults = []): GeneratedUri
    {
        [$pathRegex, $pathVariables] = $this->compilePattern(\ltrim($route->get('path'), '/'), true);

        $pathRegex = '/' . \stripslashes(\str_replace('?', '', $pathRegex));
        $createUri = new GeneratedUri($this->interpolate($pathRegex, $parameters, $defaults, $pathVariables));

        foreach ($route->get('domain') as $host) {
            $compiledHost = $this->compilePattern($host, true);

            if (!empty($compiledHost)) {
                [$hostRegex, $hostVariables] = $compiledHost;

                break;
            }
        }

        if (!empty($schemes = $route->get('schemes'))) {
            $createUri->withScheme(isset($schemes['https']) ? 'https' : \key($schemes) ?? 'http');

            if (!isset($hostRegex)) {
                $createUri->withHost($_SERVER['HTTP_HOST'] ?? '');
            }
        }

        if (isset($hostRegex)) {
            $createUri->withHost($this->interpolate($hostRegex, $parameters, $defaults, $hostVariables));
        }

        return $createUri;
    }

    /**
     * Check for mandatory parameters then interpolate $uriRoute with given $parameters.
     *
     * @param array<int|string,mixed> $parameters
     */
    private function interpolate(string $uriRoute, array $parameters, array $defaults, array $allowed): string
    {
        \preg_match_all('#\[\<(\w+).*?\>\]#', $uriRoute, $optionalVars, \PREG_UNMATCHED_AS_NULL);

        if (isset($optionalVars[1])) {
            foreach ($optionalVars[1] as $optional) {
                $defaults[$optional] = null;
            }
        }

        // Fetch and merge all possible parameters + route defaults ...
        $parameters += $defaults;

        // all params must be given
        if ($diff = \array_diff_key($allowed, $parameters)) {
            throw new UrlGenerationException(\sprintf('Some mandatory parameters are missing ("%s") to generate a URL for route path "%s".', \implode('", "', \array_keys($diff)), $uriRoute));
        }

        $replaces = self::URI_FIXERS;

        foreach ($parameters as $key => $value) {
            $replaces["<{$key}>"] = (\is_array($value) || $value instanceof \Closure) ? '' : $value;
        }

        return \strtr($uriRoute, $replaces);
    }

    /**
     * Get the route requirements.
     *
     * @param array<string,string|string[]> $requirements
     *
     * @return array<string,string|string[]>
     */
    private function getRequirements(array $requirements): array
    {
        $newParameters = [];

        foreach ($requirements as $key => $regex) {
            $newParameters[$key] = \is_array($regex) ? $regex : $this->sanitizeRequirement($key, $regex);
        }

        return $newParameters;
    }

    private function sanitizeRequirement(string $key, string $regex): string
    {
        if ('' !== $regex) {
            if ('^' === $regex[0]) {
                $regex = \substr($regex, 1);
            } elseif (0 === \strpos($regex, '\\A')) {
                $regex = \substr($regex, 2);
            }

            if ('$' === $regex[-1]) {
                $regex = \substr($regex, 0, -1);
            } elseif (\strlen($regex) - 2 === \strpos($regex, '\\z')) {
                $regex = \substr($regex, 0, -2);
            }
        }

        if ('' === $regex) {
            throw new \InvalidArgumentException(\sprintf('Routing requirement for "%s" cannot be empty.', $key));
        }

        return $regex;
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
    private function compilePattern(string $uriPattern, bool $reversed = false, array $requirements = []): array
    {
        $variables = $replaces = [];

        // correct [/ first occurrence]
        if (0 === \strpos($uriPattern, '[/')) {
            $uriPattern = '[' . \substr($uriPattern, 3);
        }

        // Match all variables enclosed in "{}" and iterate over them...
        \preg_match_all(self::COMPILER_REGEX, $uriPattern, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

        foreach ($matches as [$placeholder, $varName, $segment, $default]) {
            // Filter variable name to meet requirement
            $this->filterVariableName($varName, $uriPattern);

            if (\array_key_exists($varName, $variables)) {
                throw new UriHandlerException(\sprintf('Route pattern "%s" cannot reference variable name "%s" more than once.', $uriPattern, $varName));
            }

            $variables[$varName] = $default;
            $replaces[$placeholder] = !$reversed ? '(?P<' . $varName . '>' . (self::SEGMENT_TYPES[$segment] ?? $segment ?? $this->prepareSegment($varName, $requirements)) . ')' : "<$varName>";
        }

        return [\strtr($uriPattern, !$reversed ? self::PATTERN_REPLACES + $replaces : $replaces), $variables];
    }

    /**
     * Prevent variables with same name used more than once.
     */
    private function filterVariableName(string $varName, string $pattern): void
    {
        // A PCRE subpattern name must start with a non-digit. Also a PHP variable cannot start with a digit so the
        // variable would not be usable as a Controller action argument.
        if (1 === \preg_match('/^\d/', $varName)) {
            throw new UriHandlerException(
                \sprintf('Variable name "%s" cannot start with a digit in route pattern "%s". Use a different name.', $varName, $pattern)
            );
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
    private function prepareSegment(string $name, array $requirements): string
    {
        if (null === $segment = $requirements[$name] ?? null) {
            return self::DEFAULT_SEGMENT;
        }

        return \is_array($segment) ? \implode('|', \array_map([$this, 'filterSegment'], $segment)) : $this->filterSegment((string) $segment);
    }

    private function filterSegment(string $segment): string
    {
        return \strtr($segment, self::SEGMENT_REPLACES);
    }
}
