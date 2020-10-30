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

namespace Flight\Routing\Services;

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
    private const PATTERN_REPLACES = ['/' => '\\/', '/[' => '/?(?:', '[' => '(?:', ']' => ')?', '.' => '\.'];

    private const SEGMENT_REPLACES = ['/' => '\\/', '.' => '\.'];

    /**
     * A matching requirement helper, to ease matching route pattern when found.
     */
    private const SEGMENT_TYPES = [
        'int'     => '\d+',
        'integer' => '\d+',
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

    /** @var string */
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
        $hostVariables = $variables = [];
        $hostRegex     = $hostTemplate = null;

        if ('' !== $host = $route->getDomain()) {
            $result = $this->compilePattern($route, $host);

            $hostVariables = $result['variables'];
            $variables     = $hostVariables;

            $hostRegex    = $result['regex'] . 'i';
            $hostTemplate = $result['template'];
        }

        $result        = $this->compilePattern($route, $route->getPath());
        $pathVariables = $result['variables'];

        $this->compiled      = $result['regex'];
        $this->template      = $result['template'];
        $this->pathVariables = $pathVariables;
        $this->hostRegex     = $hostRegex;
        $this->hostTemplate  = $hostTemplate;
        $this->hostVariables = $hostVariables;
        $this->variables     = \array_merge($variables, $pathVariables);

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
        if (true === $host) {
            return $this->hostTemplate;
        }

        return $this->template;
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
     *
     * @return array<string,mixed>
     */
    private function compilePattern(RouteInterface $route, string $uriPattern): array
    {
        $options = $replaces = [];

        $pattern     = \rtrim(\ltrim($uriPattern, ':/'), '/') ?? '/';
        $leadingChar = 0 === \substr_compare($uriPattern, $pattern, 0) ? '' : '/?';

        // correct [/ first occurrence]
        if (\strpos($pattern, '[/') === 0) {
            $pattern = '[' . \substr($pattern, 2);
        }

        // Add defaults and requirements found on given $pattern to $route
        $this->prepareRoute($route, $pattern);

        // Match all variables enclosed in "{}" and iterate over them...
        if (false !== \preg_match_all('#\{(\w+)\:?(.*?)?\}#', $pattern, $matches)) {
            [$options, $replaces] = $this->computePattern(
                (array) \array_combine($matches[1], $matches[2]),
                $route
            );
        }

        $template = \str_replace(['{', '}'], '', \preg_replace('#\{(\w+)\:?.*?\}#', '<\1>', $pattern));

        return [
            'template'  => \stripslashes(\str_replace('?', '', $template)),
            'regex'     => '#^' . $leadingChar . \strtr($template, $replaces) . '$#sD',
            'variables' => \array_fill_keys($options, null),
        ];
    }

    /**
     * Compute prepared pattern and return it's replacements and arguments.
     *
     * @param array<string,string> $variables
     * @param RouteInterface       $route
     *
     * @return array<int,array<int|string,string>>
     */
    private function computePattern(array $variables, RouteInterface $route): array
    {
        $options = $replaces = [];

        foreach ($variables as $key => $segment) {
            if (\strlen($key) > self::VARIABLE_MAXIMUM_LENGTH) {
                throw new UriHandlerException(
                    \sprintf(
                        'Variable name "%s" cannot be longer than %s characters in route pattern "%s".',
                        $key,
                        self::VARIABLE_MAXIMUM_LENGTH,
                        $route->getPath()
                    )
                );
            }
            $nested = null; // Match all nested variables enclosed in "{}"

            if (!empty($segment) && ('{' === $segment[0] && substr($segment, -1, 1) !== '}')) {
                [$key, $nested, $segment] = [\substr($segment, 1, \strlen($segment) - 1), $key, ''];
            }

            $segment            = $this->prepareSegment($key, $segment, $this->getRequirements($route->getPatterns()));
            $replaces["<$key>"] = \sprintf('(?P<%s>(?U)%s)', $key, $segment);
            $options[]          = $key;

            if (null !== $nested) {
                $replaces["<$nested>"] = $nested . $replaces["<$key>"];
            }
        }

        return [$options, \array_merge($replaces, self::PATTERN_REPLACES)];
    }

    /**
     * Prepare Route by adding defaults and requirements found,
     * on the given $pattern.
     *
     * @param RouteInterface $route
     * @param string         $pattern
     */
    private function prepareRoute(RouteInterface $route, string &$pattern): void
    {
        $path = '/(?:([a-zA-Z0-9_.-]+)=)?<([^> ]+) *([^>]*)>/';

        if (false !== \preg_match_all($path, $pattern, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as [$match, $parameter, $name, $regex]) { // $regex is not used
                $pattern = \str_replace($match, $parameter, $pattern);
                $route->setDefaults([$parameter => $name]);
            }
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
            // If PCRE subpattern name starts with a digit. Append the missing symbol "}"
            if (1 === \preg_match('#\{(\d+)#', $segment)) {
                $segment = $segment . '}';
            }

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
