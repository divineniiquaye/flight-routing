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
        'uuid'    => '0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}',
    ];

    /**
     * The maximum supported length of a PCRE subpattern name
     * http://pcre.org/current/doc/html/pcre2pattern.html#SEC16.
     *
     * @internal
     */
    private const VARIABLE_MAXIMUM_LENGTH = 32;

    private $template;
    private $compiled;
    private $hostRegex;
    private $hostTemplate;
    private $variables;
    private $pathVariables;
    private $hostVariables;

    /**
     * Get the route requirements.
     *
     * @param array $requirements
     *
     * @return array
     */
    protected function getRouteRequirements(array $requirements): array
    {
        $newParamters = [];
        foreach ($requirements as $key => $regex) {
            $newParamters[$key] = $this->sanitizeRequirement($key, $regex);
        }

        return $newParamters;
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
        $variables = [];
        $hostRegex = null;
        $hostTemplate = null;

        if ('' !== $host = $route->getDomain()) {
            $result = $this->compilePattern($route, $host, true);

            $hostVariables = $result['variables'];
            $variables = $hostVariables;

            $hostRegex = $result['regex'];
            $hostTemplate = $result['template'];
        }

        $result = $this->compilePattern($route, $route->getPath(), false);
        $pathVariables = $result['variables'];

        foreach ($pathVariables as $pathParam) {
            if ('_fragment' === $pathParam) {
                throw new UriHandlerException(sprintf('Route pattern "%s" cannot contain "_fragment" as a path parameter.', $route->getPath()));
            }
        }

        $this->compiled = $result['regex'];
        $this->template = $result['template'];
        $this->pathVariables = $pathVariables;
        $this->hostRegex = $hostRegex;
        $this->hostTemplate = $hostTemplate;
        $this->hostVariables = $hostVariables;
        $this->variables = array_merge($variables, $pathVariables);

        return $this;
    }

    /**
     * The template regex for matching.
     *
     * @param bool $host Either host or path tempalte.
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
     * @return string|null The host regex or null
     */
    public function getHostRegex(): ?string
    {
        return $this->hostRegex;
    }

    /**
     * Returns the variables.
     *
     * @return array The variables
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Returns the path variables.
     *
     * @return array The variables
     */
    public function getPathVariables(): array
    {
        return $this->pathVariables;
    }

    /**
     * Returns the host variables.
     *
     * @return array The variables
     */
    public function getHostVariables(): array
    {
        return $this->hostVariables;
    }

    private function sanitizeRequirement(string $key, string $regex)
    {
        if ('' !== $regex && strpos($regex, '^') === 0) {
            $regex = (string) substr($regex, 1); // returns false for a single character
        }

        if ('$' === substr($regex, -1)) {
            $regex = substr($regex, 0, -1);
        }

        if ('' === $regex) {
            throw new UriHandlerException(sprintf('Routing requirement for "%s" cannot be empty.', $key));
        }

        return $regex;
    }

    private function compilePattern(RouteInterface $route, string $uriPattern, bool $isHost): array
    {
        $options = $replaces = [];
        $pattern = rtrim(ltrim($uriPattern, ':/'), '/') ?: '/';
        $leadingChar = 0 === substr_compare($uriPattern, $pattern, 0) ? '' : '/?';

        // correct [/ first occurrence]
        if (strpos($pattern, '[/') === 0) {
            $pattern = '['.substr($pattern, 2);
        }

        // Add defaults and requirements found on given $pattern to $route
        $this->prepareRoute($route, $pattern);

        // Match all variables enclosed in "{}" and iterate over them...
        if (preg_match_all('/{(\w+):?(.*?)?}/', $pattern, $matches)) {
            $variables = array_combine($matches[1], $matches[2]);

            foreach ($variables as $key => $segment) {
                if (strlen($key) > self::VARIABLE_MAXIMUM_LENGTH) {
                    throw new UriHandlerException(sprintf('Variable name "%s" cannot be longer than %s characters in route pattern "%s". Please use a shorter name.', $key, self::VARIABLE_MAXIMUM_LENGTH, $pattern));
                }

                $segment = $this->prepareSegment($key, $segment, $this->getRouteRequirements($route->getPatterns()));
                $replaces["<$key>"] = sprintf('(?P<%s>(?U)%s)', $key, $segment);
                $options[] = $key;
            }
        }

        $template = str_replace(['{', '}'], '', preg_replace('/{(\w+):?.*?}/', '<\1>', $pattern));

        return [
            'template'  => stripslashes(str_replace('?', '', $template)),
            'regex'     => '{^'.$leadingChar.strtr($template, $replaces + self::PATTERN_REPLACES).'$}sD'.($isHost ? 'i' : ''),
            'variables' => array_fill_keys($options, null),
        ];
    }

    /**
     * Prepare Route by adding defaults and requirements found,
     * on the given $pattern.
     *
     * @param RouteInterface $route
     * @param string         $pattern
     *
     * @return void
     */
    private function prepareRoute(RouteInterface $route, string &$pattern): void
    {
        if (preg_match_all('/(?:([a-zA-Z0-9_.-]+)=)?<([^> ]+) *([^>]*)>/', $pattern, $matches, PREG_SET_ORDER)) {
            foreach ($matches as [$match, $parameter, $name, $regex]) { // $regex is not used
                $pattern = str_replace($match, $parameter, $pattern);

                $route->addDefaults([$parameter => $name]);
                if (!empty($regex)) {
                    $route->addPattern($parameter, $regex);
                }
            }
        }
    }

    /**
     * Prepares segment pattern with given constrains.
     *
     * @param string $name
     * @param string $segment
     * @param array  $requirements
     *
     * @return string
     */
    private function prepareSegment(string $name, string $segment, array $requirements): string
    {
        if ($segment !== '') {
            // If PCRE subpattern name starts with a digit. Append the missing symbol "}"
            if (preg_match('#\{(\d+)#', $segment)) {
                $segment = $segment.'}';
            }

            return self::SEGMENT_TYPES[$segment] ?? $segment;
        }

        if (!isset($requirements[$name])) {
            return self::DEFAULT_SEGMENT;
        }

        if (is_array($requirements[$name])) {
            $values = array_map([$this, 'filterSegment'], $requirements[$name]);

            return implode('|', $values);
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
        return strtr($segment, self::SEGMENT_REPLACES);
    }

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
     * @internal
     */
    final public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function __unserialize(array $data): void
    {
        $this->variables = $data['vars'];
        $this->template = $data['template_regex'];
        $this->compiled = $data['path_regex'];
        $this->pathVariables = $data['path_vars'];
        $this->hostRegex = $data['host_regex'];
        $this->hostTemplate = $data['host_template'];
        $this->hostVariables = $data['host_vars'];
    }

    /**
     * @param $serialized
     *
     * @internal
     */
    final public function unserialize($serialized): void
    {
        $this->__unserialize(unserialize($serialized, ['allowed_classes' => false]));
    }
}
