<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing;

use Flight\Routing\Exceptions\{UriHandlerException, UrlGenerationException};
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
    private const PATTERN_REPLACES = ['/[' => '/?(?:', '[' => '(?:', ']' => ')?', '.' => '\.', '/$' => '/?$'];

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
    private const COMPILER_REGEX = '~\{(\w+)(?:\:(.*?\}?))?(?:\=(.*?))?\}~i';

    /**
     * This regex is used to reverse a pattern path, matching required and options vars.
     */
    private const REVERSED_REGEX = '#(?|\<(\w+)\>|\[(.*?\])\]|\[(.*?)\])#';

    /**
     * A matching requirement helper, to ease matching route pattern when found.
     */
    private const SEGMENT_TYPES = [
        'int' => '[0-9]+',
        'lower' => '[a-z]+',
        'upper' => '[A-Z]+',
        'alpha' => '[A-Za-z]+',
        'hex' => '[[:xdigit:]]+',
        'md5' => '[a-f0-9]{32}+',
        'sha1' => '[a-f0-9]{40}+',
        'year' => '[0-9]{4}',
        'month' => '0[1-9]|1[012]+',
        'day' => '0[1-9]|[12][0-9]|3[01]+',
        'date' => '[0-9]{4}-(?:0[1-9]|1[012])-(?:0[1-9]|[12][0-9]|(?<!02-)3[01])', // YYYY-MM-DD
        'slug' => '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*',
        'port' => '[0-9]{1,4}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5]',
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
    public function compile(string $route, array $placeholders = [], bool $reversed = false): array
    {
        $variables = $replaces = [];

        if (\strpbrk($route, '{')) {
            // Match all variables enclosed in "{}" and iterate over them...
            \preg_match_all(self::COMPILER_REGEX, $route, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

            foreach ($matches as [$placeholder, $varName, $segment, $default]) {
                if (1 === \preg_match('/\A\d+/', $varName)) {
                    throw new UriHandlerException(\sprintf('Variable name "%s" cannot start with a digit in route pattern "%s". Use a different name.', $varName, $route));
                }

                if (\strlen($varName) > self::VARIABLE_MAXIMUM_LENGTH) {
                    throw new UriHandlerException(\sprintf('Variable name "%s" cannot be longer than %s characters in route pattern "%s".', $varName, self::VARIABLE_MAXIMUM_LENGTH, $route));
                }

                if (\array_key_exists($varName, $variables)) {
                    throw new UriHandlerException(\sprintf('Route pattern "%s" cannot reference variable name "%s" more than once.', $route, $varName));
                }

                $segment = self::SEGMENT_TYPES[$segment] ?? $segment ?? self::prepareSegment($varName, $placeholders);
                [$variables[$varName], $replaces[$placeholder]] = !$reversed ? [$default, '(?P<'.$varName.'>'.$segment.')'] : [[$segment, $default], '<'.$varName.'>'];
            }
        }

        return !$reversed ? [\strtr('{^'.$route.'$}', $replaces + self::PATTERN_REPLACES), $variables] : [\strtr($route, $replaces), $variables];
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(array $route, array $parameters, int $referenceType = RouteUri::ABSOLUTE_PATH): RouteUri
    {
        [$pathRegex, $pathVars] = $this->compile($route['path'], reversed: true);

        $defaults = $route['defaults'] ?? [];
        $createUri = new RouteUri(self::interpolate($pathRegex, $pathVars, $parameters + $defaults), $referenceType);

        foreach (($route['hosts'] ?? []) as $host => $exists) {
            [$hostRegex, $hostVars] = $this->compile($host, reversed: true);
            $createUri->withHost(self::interpolate($hostRegex, $hostVars, $parameters + $defaults));
            break;
        }

        if (!empty($schemes = $route['schemes'] ?? [])) {
            $createUri->withScheme(isset($schemes['https']) ? 'https' : \array_key_last($schemes) ?? 'http');
        }

        return $createUri;
    }

    /**
     * Check for mandatory parameters then interpolate $uriRoute with given $parameters.
     *
     * @param array<string,array<int,string>> $uriVars
     * @param array<int|string,string>        $parameters
     */
    private static function interpolate(string $uriRoute, array $uriVars, array $parameters): string
    {
        $required = []; // Parameters required which are missing.
        $replaces = self::URI_FIXERS;

        // Fetch and merge all possible parameters + route defaults ...
        \preg_match_all(self::REVERSED_REGEX, $uriRoute, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

        if (isset($uriVars['*'])) {
            [$defaultPath, $required, $optional] = $uriVars['*'];
            $replaces = [];
        }

        foreach ($matches as $i => [$matched, $varName]) {
            if ('[' !== $matched[0]) {
                [$segment, $default] = $uriVars[$varName];
                $value = $parameters[$varName] ?? (isset($optional) ? $default : ($parameters[$i] ?? $default));

                if (!empty($value)) {
                    if (1 !== \preg_match("~^{$segment}\$~", (string) $value)) {
                        throw new UriHandlerException(
                            \sprintf('Expected route path "%s" placeholder "%s" value "%s" to match "%s".', $uriRoute, $varName, $value, $segment)
                        );
                    }
                    $optional = isset($optional) ? false : null;
                    $replaces[$matched] = $value;
                } elseif (isset($optional) && $optional) {
                    $replaces[$matched] = '';
                } else {
                    $required[] = $varName;
                }
                continue;
            }
            $replaces[$matched] = self::interpolate($varName, $uriVars + ['*' => [$uriRoute, $required, true]], $parameters);
        }

        if (!empty($required)) {
            throw new UrlGenerationException(\sprintf(
                'Some mandatory parameters are missing ("%s") to generate a URL for route path "%s".',
                \implode('", "', $required),
                $defaultPath ?? $uriRoute
            ));
        }

        return !empty(\array_filter($replaces)) ? \strtr($uriRoute, $replaces) : '';
    }

    private static function sanitizeRequirement(string $key, string $regex): string
    {
        if ('' !== $regex) {
            if ('^' === $regex[0]) {
                $regex = \substr($regex, 1);
            } elseif (\str_starts_with($regex, '\\A')) {
                $regex = \substr($regex, 2);
            }

            if (\str_ends_with($regex, '$')) {
                $regex = \substr($regex, 0, -1);
            } elseif (\strlen($regex) - 2 === \strpos($regex, '\\z')) {
                $regex = \substr($regex, 0, -2);
            }
        }

        if ('' === $regex) {
            throw new UriHandlerException(\sprintf('Routing requirement for "%s" cannot be empty.', $key));
        }

        return \strtr($regex, self::SEGMENT_REPLACES);
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
}
