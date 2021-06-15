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

use Flight\Routing\CompiledRoute;
use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Interfaces\RouteCompilerInterface;
use Flight\Routing\Route;

/**
 * RouteCompiler compiles Route instances to regex.
 *
 * provides ability to match and generate uris based on given parameters.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class SimpleRouteCompiler implements RouteCompilerInterface
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
    private const COMPILER_REGEX = '#\{(\w+)(?:\:((?U).*\}|.*))?(?:\=(\w+))?\}#i';

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
     * The maximum supported length of a PCRE subpattern name
     * http://pcre.org/current/doc/html/pcre2pattern.html#SEC16.
     *
     * @internal
     */
    private const VARIABLE_MAXIMUM_LENGTH = 32;

    /**
     * {@inheritdoc}
     */
    public function compile(Route $route, bool $reversed = false): CompiledRoute
    {
        $requirements = $this->getRequirements($route->get('patterns'));
        $routePath = $route->get('path');

        if ('/' !== $routePath[0]) {
            $routePath = '/' . $routePath;
        }

        if (!empty($hosts = $route->get('domain'))) {
            $hostsRegex = $this->computeHosts($hosts, $reversed, $requirements);
        }

        [$pathRegex, $pathVariable] = '/' === $routePath
            ? [(!$reversed ? '\\' : '') . '/', []] // making homepage url much faster ...
            : $this->compilePattern($requirements, $routePath, $reversed);

        // Resolves $pathRegex and host and pattern variables.
        $pathRegex = !$reversed ? '/^' . $pathRegex . '$/sDu' : \stripslashes($pathRegex);
        $variables = isset($hostVariables) ? $hostVariables += $pathVariable : $pathVariable;

        return new CompiledRoute($pathRegex, $hostsRegex ?? [], $variables, empty($pathVariable) ? $routePath : null);
    }

    /**
     * Get the route requirements.
     *
     * @param array<string,string|string[]> $requirements
     *
     * @return array<string,string|string[]>
     */
    protected function getRequirements(array $requirements): array
    {
        $newParameters = [];

        foreach ($requirements as $key => $regex) {
            $newParameters[$key] = \is_array($regex) ? $regex : $this->sanitizeRequirement($key, $regex);
        }

        return $newParameters;
    }

    private function sanitizeRequirement(string $key, string $regex): string
    {
        if ('^' === @$regex[0]) {
            $regex = \substr($regex, 1); // returns false for a single character
        }

        if ('$' === @$regex[-1]) {
            $regex = \substr($regex, 0, -1);
        }

        if (empty($regex)) {
            throw new UriHandlerException(\sprintf('Routing requirement for "%s" cannot be empty.', $key));
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
    private function compilePattern(array $requirements, string $uriPattern, bool $reversed): array
    {
        // correct [/ first occurrence]
        if (0 === \strpos($uriPattern, '[/')) {
            $uriPattern = '[' . \substr($uriPattern, 3);
        }

        // Strip supported browser prefix of $uriPattern ...
        if (isset(Route::URL_PREFIX_SLASHES[$uriPattern[-1]])) {
            $uriPattern = \substr($uriPattern, 0, -1);
        }

        // Match all variables enclosed in "{}" and iterate over them...
        \preg_match_all(self::COMPILER_REGEX, $uriPattern, $matches, \PREG_UNMATCHED_AS_NULL);

        if (!empty($matches)) {
            [$variables, $replaces] = $this->computePattern($matches, $uriPattern, $reversed, $requirements);
        }

        // Resolves route pattern place holders ...
        $replaces = ($replaces ?? []) + (!$reversed ? self::PATTERN_REPLACES : ['?' => '']);

        return [\strtr($uriPattern, $replaces), $variables ?? []];
    }

    /**
     * Compile hosts from route and return computed hosts.
     *
     * @param array<string,string|string[]> $requirements
     *
     * @return string[]|string
     */
    private function computeHosts(array $hosts, bool $isReversed, array $requirements)
    {
        $hostVariables = $hostRegexps = [];
        $compliedHosts = '/^(?|';

        foreach ($hosts as $host) {
            [$hostRegex, $hostVariable] = $this->compilePattern($requirements, $host, $isReversed);
            $hostVariables += $hostVariable;

            if (1 === \count($hosts)) {
                $compliedHosts = !$isReversed ? '/^' . $hostRegex : \stripslashes($hostRegex);

                break;
            }

            if (!$isReversed) {
                $compliedHosts .= $hostRegex . '|';

                continue;
            }

            $hostRegexps[] = \stripslashes($hostRegex);
        }

        return empty($hostRegexps) ? $compliedHosts . ('|' === $compliedHosts[-1] ? ')' : '') . '$/sDi' : $hostRegexps;
    }

    /**
     * Compute prepared pattern and return it's replacements and arguments.
     *
     * @param array<string,string[]>        $matches
     * @param array<string,string|string[]> $requirements
     *
     * @return array<int,array<int|string,mixed>>
     */
    private function computePattern(array $matches, string $pattern, bool $isReversed, array $requirements): array
    {
        $variables = $replaces = [];
        [$placeholders, $names, $rules, $defaults] = $matches;

        $count = \count($names);

        foreach ($names as $index => $varName) {
            // Filter variable name to meet requirement
            $this->filterVariableName($varName, $pattern);

            if (\array_key_exists($varName, $variables)) {
                throw new UriHandlerException(
                    \sprintf(
                        'Route pattern "%s" cannot reference variable name "%s" more than once.',
                        $pattern,
                        $varName
                    )
                );
            }

            if (!$isReversed) {
                $replace = self::SEGMENT_TYPES[$rules[$index]] ?? $rules[$index] ?? $this->prepareSegment($varName, $requirements);

                // optimize the regex with a possessive quantifier.
                if (1 === $count && ('/' === $pattern[0] && '+' === @$replace[-1])) {
                    // This optimization cannot be applied when the next char is no real separator.
                    \preg_match('#\{.*\}(.+?)#', $pattern, $nextSeparator);

                    $replace .= !(isset($nextSeparator[1]) && (1 === \count($names) || '{' === $nextSeparator[1])) ? '+' : '';
                }

                $replace = \sprintf('(?P<%s>%s)', $varName, $replace);
            }

            $replaces[$placeholders[$index]] = $replace ?? "<$varName>";
            $variables[$varName] = $defaults[$index] ?? null;

            --$count;
        }

        return [$variables, $replaces];
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
                \sprintf(
                    'Variable name "%s" cannot start with a digit in route pattern "%s". Use a different name.',
                    $varName,
                    $pattern
                )
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
        if (!isset($requirements[$name])) {
            return self::DEFAULT_SEGMENT;
        }

        if (\is_array($requirements[$name])) {
            $values = \array_map([$this, 'filterSegment'], $requirements[$name]);

            return \implode('|', $values);
        }

        return $this->filterSegment((string) $requirements[$name]);
    }

    private function filterSegment(string $segment): string
    {
        return \strtr($segment, self::SEGMENT_REPLACES);
    }
}
