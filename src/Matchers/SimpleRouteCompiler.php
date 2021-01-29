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
use Flight\Routing\Route;

/**
 * RouteCompiler compiles Route instances to regex.
 *
 * provides ability to match and generate uris based on given parameters.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class SimpleRouteCompiler implements \Serializable
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
    private const COMPILER_REGEX = '#\{(\w+)?(?:\:([^{}=]*(?:\{(?-1)\}[^{}]?)*))?(?:\=\<([^>]+)\>)?\}#xi';

    /**
     * A matching requirement helper, to ease matching route pattern when found.
     */
    private const SEGMENT_TYPES = [
        'int'     => '\d+',
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

    /** @var string[] */
    private $hostRegex;

    /** @var string[] */
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
     * @param Route $route
     *
     * @return SimpleRouteCompiler
     */
    public function compile(Route $route): self
    {
        $hostVariables = $hostRegex = $hostTemplate = [];
        $requirements  = $this->getRequirements($route->getPatterns());

        if ([] !== $hosts = $route->getDomain()) {
            foreach (\array_keys($hosts) as $host) {
                $result = $this->compilePattern($requirements, $host, true);

                $hostVariables += $result['variables'];

                $hostRegex[]    = $result['regex'] . 'i';
                $hostTemplate[] = $result['template'];
            }
        }

        $result        = $this->compilePattern($requirements, $route->getPath());
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
     * The path template regex for matching.
     *
     * @param bool $host either host or path template
     *
     * @return string The static regex
     */
    public function getPathTemplate(): string
    {
        return $this->template;
    }

    /**
     * The hosts template regex for matching.
     *
     * @param bool $host either host or path template
     *
     * @return string[] The static regexps
     */
    public function getHostTemplate(): array
    {
        return $this->hostTemplate;
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
     * @return array The hosts regex
     */
    public function getHostsRegex(): array
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
     * @param array<string,string|string[]> $requirements
     * @param string                        $uriPattern
     * @param bool                          $isHost
     *
     * @throws UriHandlerException if a variable name starts with a digit or
     *                             if it is too long to be successfully used as a PCRE subpattern or
     *                             if a variable is referenced more than once
     *
     * @return array<string,mixed>
     */
    private function compilePattern(array $requirements, string $uriPattern, $isHost = false): array
    {
        if (\strlen($uriPattern) > 1) {
            $uriPattern = \trim($uriPattern, '/');
        }

        // correct [/ first occurrence]
        if (\strpos($uriPattern, '[/') === 0) {
            $uriPattern = '[' . \substr($uriPattern, 2);
        }

        // Match all variables enclosed in "{}" and iterate over them...
        \preg_match_all(self::COMPILER_REGEX, $pattern = (!$isHost ? '/' : '') . $uriPattern, $matches);

        list($variables, $replaces) = $this->computePattern($matches, $pattern, $requirements);

        // Return only grouped named captures.
        $template = (string) \preg_replace(self::COMPILER_REGEX, '<\1>', $pattern);

        return [
            'template'  => \stripslashes(\str_replace('?', '', $template)),
            'regex'     => '/^' . ($isHost ? '\/?' : '') . \strtr($template, $replaces) . '$/sD',
            'variables' => $variables,
        ];
    }

    /**
     * Compute prepared pattern and return it's replacements and arguments.
     *
     * @param array<string,string[]>        $matches
     * @param string                        $pattern
     * @param array<string,string|string[]> $requirements
     *
     * @return array<int,array<int|string,mixed>>
     */
    private function computePattern(array $matches, string $pattern, array $requirements): array
    {
        $variables = $replaces = [];

        list(, $names, $rules, $defaults) = $matches;

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

            if (isset($rules[$index])) {
                $replace = $this->prepareSegment($varName, $rules[$index], $requirements);

                $replaces["<$varName>"] = \sprintf('(?P<%s>(?U)%s)', $varName, $replace);
            }

            $variables[$varName] = !empty($default = $defaults[$index]) ? $default : null;
        }

        return [$variables, $replaces + self::PATTERN_REPLACES];
    }

    /**
     * Prevent variables with same name used more than once.
     *
     * @param int|string $varName
     * @param string     $pattern
     */
    private function filterVariableName($varName, string $pattern): void
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
