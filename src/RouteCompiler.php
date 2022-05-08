<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing;

use Flight\Routing\Exceptions\{UriHandlerException, UrlGenerationException};
use Flight\Routing\Generator\{GeneratedUri, RegexGenerator};
use Flight\Routing\Interfaces\RouteCompilerInterface;

/**
 * RouteCompiler compiles Route instances to regex.
 *
 * provides ability to match and generate uris based on given parameters.
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

    /**
     * Using the strtr function is faster than the preg_quote function.
     */
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
     * This regex is used to strip off a name attached to a group in a regex pattern.
     */
    private const STRIP_REGEX = '#\?(?|P<\w+>|<\w+>|\'\w+\')#';

    /**
     * A matching requirement helper, to ease matching route pattern when found.
     */
    private const SEGMENT_TYPES = [
        'int' => '[0-9]+',
        'lower' => '[a-z]+',
        'upper' => '[A-Z]+',
        'alpha' => '[A-Za-z]+',
        'year' => '[0-9]{4}',
        'month' => '0[1-9]|1[012]+',
        'day' => '0[1-9]|[12][0-9]|3[01]+',
        'date' => '[0-9]{4}-(?:0[1-9]|1[012])-(?:0[1-9]|[12][0-9]|(?<!02-)3[01])', // YYYY-MM-DD
        'slug' => '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*',
        'UID_BASE32' => '[0-9A-HJKMNP-TV-Z]{26}',
        'UID_BASE58' => '[1-9A-HJ-NP-Za-km-z]{22}',
        'UID_RFC4122' => '[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}',
        'ULID' => '[0-7][0-9A-HJKMNP-TV-Z]{25}',
        'UUID' => '[0-9a-f]{8}-[0-9a-f]{4}-[1-6][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
        'UUID_V1' => '[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
        'UUID_V3' => '[0-9a-f]{8}-[0-9a-f]{4}-3[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
        'UUID_V4' => '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
        'UUID_V5' => '[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
        'UUID_V6' => '[0-9a-f]{8}-[0-9a-f]{4}-6[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
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
    public function build(RouteCollection $routes): array
    {
        $tree = new RegexGenerator();
        $uriPrefixRegex = '#[^a-zA-Z0-9]+$#';
        $variables = $staticRegex = [];

        foreach ($routes->getRoutes() as $i => $route) {
            [$pathRegex, $hostsRegex, $compiledVars] = $this->compile($route);
            $pathRegex = self::resolveRegex($pathRegex);

            if (!empty($hostsRegex)) {
                $variables[$i] = [self::resolveRegex($hostsRegex), []];
            }

            if (!empty($compiledVars)) {
                $variables[$i] = [$variables[$i][0] ?? [], $compiledVars];
            }

            if ('?' === $pos = $pathRegex[-1]) {
                if (!\preg_match($uriPrefixRegex, $pathRegex[-2])) {
                    $pathRegex = \substr($pathRegex, 0, -1);
                }

                $tree->addRoute($pathRegex, [$pathRegex, $i]);
                continue;
            }

            if (\preg_match($uriPrefixRegex, $pos)) {
                $staticRegex[\substr($pathRegex, 0, -1)][] = $i;
            }

            $staticRegex[$pathRegex][] = $i;
        }

        if (!empty($compiledRegex = $tree->compile(0))) {
            $compiledRegex = '~^' . \substr($compiledRegex, 1) . '$~sDu';
        }

        return [$staticRegex, $compiledRegex ?: null, $variables];
    }

    /**
     * {@inheritdoc}
     */
    public function compile(Route $route): array
    {
        [$pathRegex, $variables] = self::compilePattern($route->getPath(), false, $rPs = $route->getPatterns());

        if ($hosts = $route->getHosts()) {
            $hostsRegex = [];

            foreach ($hosts as $host) {
                [$hostRegex, $hostVars] = self::compilePattern($host, false, $rPs);
                $variables += $hostVars;
                $hostsRegex[] = $hostRegex;
            }

            $hostsRegex = '{^' . \implode('|', $hostsRegex) . '$}ui';
        }

        if ('?' !== $pathRegex[-1]) {
            $pathRegex .= '?';
        }

        return ['{^' . $pathRegex . '$}u', $hostsRegex ?? null, $variables];
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(Route $route, array $parameters, int $referenceType = GeneratedUri::ABSOLUTE_PATH): GeneratedUri
    {
        [$pathRegex, $pathVariables] = self::compilePattern($route->getPath(), true);

        $defaults = $route->getDefaults();
        $createUri = new GeneratedUri(self::interpolate($pathRegex, $parameters, $defaults + $pathVariables), $referenceType);

        foreach ($route->getHosts() as $host) {
            [$hostRegex, $hostVariables] = self::compilePattern($host, true);

            break;
        }

        if (!empty($schemes = $route->getSchemes())) {
            $createUri->withScheme(\in_array('https', $schemes, true) ? 'https' : \end($schemes) ?? 'http');

            if (!isset($hostRegex)) {
                $createUri->withHost($_SERVER['HTTP_HOST'] ?? '');
            }
        }

        if (isset($hostRegex)) {
            $createUri->withHost(self::interpolate($hostRegex, $parameters, $defaults + ($hostVariables ?? [])));
        }

        return $createUri;
    }

    /**
     * Check for mandatory parameters then interpolate $uriRoute with given $parameters.
     *
     * @param array<int|string,mixed> $parameters
     * @param array<string,mixed>     $defaults
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

    private static function sanitizeRequirement(string $key, string $regex): string
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

        return \strtr($regex, self::SEGMENT_REPLACES);
    }

    /**
     * @param array<string,string|string[]> $requirements
     *
     * @throws UriHandlerException if a variable name starts with a digit or
     *                             if it is too long to be successfully used as a PCRE subpattern or
     *                             if a variable is referenced more than once
     */
    private static function compilePattern(string $uriPattern, bool $reversed = false, array $requirements = []): array
    {
        // A path which doesn't contain {}, should be ignored.
        if (!\str_contains($uriPattern, '{')) {
            return [\strtr($uriPattern, $reversed ? ['?' => ''] : self::SEGMENT_REPLACES), []];
        }

        $variables = []; // VarNames mapping to values use by route's handler.
        $replaces = $reversed ? ['?' => ''] : self::PATTERN_REPLACES;

        // correct [/ first occurrence]
        if (1 === \strpos($uriPattern, '[/')) {
            $uriPattern = '/[' . \substr($uriPattern, 3);
        }

        // Match all variables enclosed in "{}" and iterate over them...
        \preg_match_all(self::COMPILER_REGEX, $uriPattern, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

        foreach ($matches as [$placeholder, $varName, $segment, $default]) {
            // A PCRE subpattern name must start with a non-digit.
            if (1 === \preg_match('/\d/A', $varName)) {
                throw new UriHandlerException(\sprintf('Variable name "%s" cannot start with a digit in route pattern "%s". Use a different name.', $varName, $uriPattern));
            }

            if (\strlen($varName) > self::VARIABLE_MAXIMUM_LENGTH) {
                throw new UriHandlerException(\sprintf('Variable name "%s" cannot be longer than %s characters in route pattern "%s".', $varName, self::VARIABLE_MAXIMUM_LENGTH, $uriPattern));
            }

            if (\array_key_exists($varName, $variables)) {
                throw new UriHandlerException(\sprintf('Route pattern "%s" cannot reference variable name "%s" more than once.', $uriPattern, $varName));
            }

            $variables[$varName] = $default;
            $replaces[$placeholder] = !$reversed ? '(?P<' . $varName . '>' . (self::SEGMENT_TYPES[$segment] ?? $segment ?? self::prepareSegment($varName, $requirements)) . ')' : "<$varName>";
        }

        return [\strtr($uriPattern, $replaces), $variables];
    }

    /**
     * Prepares segment pattern with given constrains.
     *
     * @param array<string,mixed> $requirements
     */
    private static function prepareSegment(string $name, array $requirements): string
    {
        if (!\array_key_exists($name, $requirements)) {
            return self::DEFAULT_SEGMENT;
        }

        if (!\is_array($segment = $requirements[$name])) {
            return self::sanitizeRequirement($name, $segment);
        }

        return \implode('|', $segment);
    }

    /**
     * Strips starting and ending modifiers from a path regex.
     */
    private static function resolveRegex(string $pathRegex): string
    {
        $pos = (int) \strrpos($pathRegex, '$');
        $pathRegex = \substr($pathRegex, 1 + \strpos($pathRegex, '^'), -(\strlen($pathRegex) - $pos));

        if (\preg_match('/\\(\\?P\\<\w+\\>.*\\)/', $pathRegex)) {
            return \preg_replace(self::STRIP_REGEX, '', $pathRegex);
        }

        return \str_replace(['\\', '?'], '', $pathRegex);
    }
}
