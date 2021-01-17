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

use Flight\Routing\Exceptions\UriHandlerException;
use Flight\Routing\Interfaces\RouteInterface;
use Serializable;

/**
 * RouteCompiler compiles Route instances to regex.
 *
 * provides ability to match and generate uris based on given parameters.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class SimpleRouteCompiler implements Serializable
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
     * - /{var=<foo>} - A required variable with default value
     * - /{var}[.{format:(html|php)=<html>}] - A required variable with an optional variable, a rule & default
     */
    private const COMPILER_REGEX = '#\{(?<names>\w+)?(?:\:(?<rules>[^{}=]*(?:\{(?-1)\}[^{}]?)*))?(?:\=\<(?<defaults>[^>]+)\>)?\}#xi';

    /**
     * A matching requirement helper, to ease matching route pattern when found.
     */
    private const SEGMENT_TYPES = [
        'int'     => '\d+',
        'integer' => '\d+',
        'lower'   => '[a-z]+',
        'upper'   => '[A-Z]+',
        'alpha'   => '[A-Za-z]+',
        'alnum'   => '[A-Za-z0-9]+',
        'year'    => '[12][0-9]{3}',
        'month'   => '0[1-9]|1[012]',
        'day'     => '0[1-9]|[12][0-9]|3[01]',
        'uuid'    => '0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}',
    ];

    /**
     * The maximum supported length of a PCRE subpattern name
     * http://pcre.org/current/doc/html/pcre2pattern.html#SEC16.
     *
     * @internal
     */
    private const VARIABLE_MAXIMUM_LENGTH = 32;

    /** @var string */
    private $template;

    /** @var string */
    private $compiled;

    /** @var null|string */
    private $hostRegex;

    /** @var null|string */
    private $hostTemplate;

    /** @var array<int|string,mixed> */
    private $variables;

    /** @var array<int|string,mixed> */
    private $pathVariables;

    /** @var array<int|string,mixed> */
    private $hostVariables;

    /**
     * @return array<string,mixed>
     */
    public function __serialize(): array
    {
        return [
            'vars'           => $this->variables,
            'template_regex' => $this->template,
            'host_template'  => $this->hostTemplate,
            'path_regex'     => $this->compiled,
            'path_vars'      => $this->pathVariables,
            'host_regex'     => $this->hostRegex,
            'host_vars'      => $this->hostVariables,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->variables     = $data['vars'];
        $this->template      = $data['template_regex'];
        $this->compiled      = $data['path_regex'];
        $this->pathVariables = $data['path_vars'];
        $this->hostRegex     = $data['host_regex'];
        $this->hostTemplate  = $data['host_template'];
        $this->hostVariables = $data['host_vars'];
    }

    /**
     * Match the RouteInterface instance and compiles the current route instance.
     *
     * @param RouteInterface $route
     *
     * @return SimpleRouteCompiler
     */
    public function compile(RouteInterface $route): self
    {
        $hostVariables = [];
        $hostRegex     = $hostTemplate = null;

        if ('' !== $host = $route->getDomain()) {
            $result = $this->compilePattern($route, $host, true);

            $hostVariables = $result['variables'];
            $hostRegex     = $result['regex'] . 'i';
            $hostTemplate  = $result['template'];
        }

        $result        = $this->compilePattern($route, $route->getPath());
        $pathVariables = $result['variables'];

        $this->compiled      = $result['regex'] . 'u';
        $this->template      = $result['template'];
        $this->pathVariables = $pathVariables;
        $this->hostRegex     = $hostRegex;
        $this->hostTemplate  = $hostTemplate;
        $this->hostVariables = $hostVariables;
        $this->variables     = \array_merge($hostVariables, $pathVariables);

        return $this;
    }

    /**
     * The template regex for matching.
     *
     * @param bool $host either host or path template
     *
     * @return string The static regex
     */
    public function getRegexTemplate(bool $host = true): ?string
    {
        return $host ? $this->hostTemplate : $this->template;
    }

    /**
     * Returns the regex.
     *
     * @return string The regex
     */
    public function getRegex(): string
    {
        return $this->compiled;
    }

    /**
     * Returns the host regex.
     *
     * @return null|string The host regex or null
     */
    public function getHostRegex(): ?string
    {
        return $this->hostRegex;
    }

    /**
     * Returns the variables.
     *
     * @return array<int|string,string> The variables
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Returns the path variables.
     *
     * @return array<int|string,string> The variables
     */
    public function getPathVariables(): array
    {
        return $this->pathVariables;
    }

    /**
     * Returns the host variables.
     *
     * @return array<int|string,string> The variables
     */
    public function getHostVariables(): array
    {
        return $this->hostVariables;
    }

    /**
     * {@inheritdoc}
     *
     * @internal
     */
    final public function serialize(): string
    {
        return \serialize($this->__serialize());
    }

    /**
     * {@inheritdoc}
     *
     * @param string $serialized the string representation of the object
     *
     * @internal
     */
    final public function unserialize($serialized): void
    {
        $this->__unserialize(\unserialize($serialized, ['allowed_classes' => false]));
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
        if ('' !== $regex && \strpos($regex, '^') === 0) {
            $regex = \substr($regex, 1); // returns false for a single character
        }

        if ('$' === \substr($regex, -1)) {
            $regex = \substr($regex, 0, -1);
        }

        if ('' === $regex) {
            throw new UriHandlerException(\sprintf('Routing requirement for "%s" cannot be empty.', $key));
        }

        return $regex;
    }

    /**
     * @param RouteInterface $route
     * @param string         $uriPattern
     * @param bool           $isHost
     *
     * @throws UriHandlerException if a variable name starts with a digit or
     *                             if it is too long to be successfully used as a PCRE subpattern or
     *                             if a variable is referenced more than once
     *
     * @return array<string,mixed>
     */
    private function compilePattern(RouteInterface $route, string $uriPattern, $isHost = false): array
    {
        if (\strlen($uriPattern) > 1) {
            $uriPattern = \trim($uriPattern, '/');
        }

        // correct [/ first occurrence]
        if (\strpos($pattern = (!$isHost ? '/' : '') . $uriPattern, '[/') === 0) {
            $pattern = '[' . \substr($pattern, 2);
        }

        // Match all variables enclosed in "{}" and iterate over them...
        \preg_match_all(self::COMPILER_REGEX, $pattern, $matches);

        // Return only grouped named captures.
        $matches  = \array_filter($matches, 'is_string', \ARRAY_FILTER_USE_KEY);
        $template = (string) \preg_replace(self::COMPILER_REGEX, '<\1>', $pattern);

        list($variables, $replaces) = $this->computePattern($matches, $pattern, $route);

        return [
            'template'  => \stripslashes(\str_replace('?', '', $template)),
            'regex'     => '/^' . ($isHost ? '\/?' : '') . \strtr($template, $replaces) . '$/sD',
            'variables' => \array_fill_keys($variables, null),
        ];
    }

    /**
     * Compute prepared pattern and return it's replacements and arguments.
     *
     * @param array<string,string[]> $matches
     * @param string                 $pattern
     * @param RouteInterface         $route
     *
     * @return array<int,array<int|string,mixed>>
     */
    private function computePattern(array $matches, string $pattern, RouteInterface $route): array
    {
        $parameters   = $replaces = [];
        $requirements = $this->getRequirements($route->getPatterns());
        $varNames     = $this->filterVariableNames($matches['names'], $pattern);
        $variables    = \array_combine($varNames, $matches['rules']) ?: [];
        $defaults     = \array_combine($varNames, $matches['defaults']) ?: [];

        foreach ($variables as $key => $segment) {
            // A PCRE subpattern name must start with a non-digit. Also a PHP variable cannot start with a digit so the
            // variable would not be usable as a Controller action argument.
            if (\is_int($key)) {
                throw new UriHandlerException(
                    \sprintf(
                        'Variable name "%s" cannot start with a digit in route pattern "%s". Use a different name.',
                        $key,
                        $pattern
                    )
                );
            }

            if (\strlen($key) > self::VARIABLE_MAXIMUM_LENGTH) {
                throw new UriHandlerException(
                    \sprintf(
                        'Variable name "%s" cannot be longer than %s characters in route pattern "%s".',
                        $key,
                        self::VARIABLE_MAXIMUM_LENGTH,
                        $pattern
                    )
                );
            }

            // Add defaults found on given $pattern to $route
            if (isset($defaults[$key]) && !empty($default = $defaults[$key])) {
                $route->setDefaults([$key => $default]);
            }

            $replaces["<$key>"] = \sprintf('(?P<%s>(?U)%s)', $key, $this->prepareSegment($key, $segment, $requirements));
            $parameters[]       = $key;
        }

        return [$parameters, \array_merge($replaces, self::PATTERN_REPLACES)];
    }

    /**
     * Prevent variables with same name used more than once.
     *
     * @param string[] $names
     * @param string   $pattern
     *
     * @return string[]
     */
    private function filterVariableNames(array $names, string $pattern): array
    {
        $variables = [];

        foreach ($names as $varName) {
            if (\in_array($varName, $variables, true)) {
                throw new UriHandlerException(
                    \sprintf(
                        'Route pattern "%s" cannot reference variable name "%s" more than once.',
                        $pattern,
                        $varName
                    )
                );
            }

            $variables[] = $varName;
        }

        return $names;
    }

    /**
     * Prepares segment pattern with given constrains.
     *
     * @param string              $name
     * @param string              $segment
     * @param array<string,mixed> $requirements
     *
     * @return string
     */
    private function prepareSegment(string $name, string $segment, array $requirements): string
    {
        if ($segment !== '') {
            return self::SEGMENT_TYPES[$segment] ?? $segment;
        }

        if (!isset($requirements[$name])) {
            return self::DEFAULT_SEGMENT;
        }

        if (\is_array($requirements[$name])) {
            $values = \array_map([$this, 'filterSegment'], $requirements[$name]);

            return \implode('|', $values);
        }

        return $this->filterSegment((string) $requirements[$name]);
    }

    /**
     * @param string $segment
     *
     * @return string
     */
    private function filterSegment(string $segment): string
    {
        return \strtr($segment, self::SEGMENT_REPLACES);
    }
}
