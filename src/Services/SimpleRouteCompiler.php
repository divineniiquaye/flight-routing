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

use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Exceptions\UriHandlerException;
use Serializable;

use function substr;
use function strtr;
use function str_replace;
use function array_merge;
use function preg_match_all;
use function preg_replace;
use function sprintf;
use function strlen;
use function strpos;
use function rtrim;
use function ltrim;
use function implode;
use function array_map;
use function is_array;
use function stripslashes;
use function array_fill_keys;
use function substr_compare;
use function serialize;
use function unserialize;

/**
 * RouteCompiler compiles Route instances to regex.
 *
 * provides ability to match and generate uris based on given parameters.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class SimpleRouteCompiler implements Serializable
{
    private const DEFAULT_SEGMENT  = '[^\/]+';

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
        'uuid'    => '0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}'
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
    private $variables;
    private $pathVariables;
    private $hostVariables;
    /**
     * Get the route requirements.
     *
     * @param array $requirements
     * @return  array
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

        if ('' !== $host = $route->getDomain()) {
            $result = $this->compilePattern($route, $host, true);

            $hostVariables = $result['variables'];
            $variables = $hostVariables;

            $hostRegex = $result['regex'];
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
        $this->hostVariables = $hostVariables;
        $this->variables = array_merge($variables, $pathVariables);

        return $this;
    }

    /**
     * The template regex for matching.
     *
     * @return string The static regex
     */
    public function getStaticRegex(): string
    {
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
        $pos = 0;
        $pattern = rtrim(ltrim($uriPattern, ':/'), '/') ?: '/';
        $leadingChar = 0 === substr_compare($uriPattern, $pattern, $pos) ? '' : '/?';

        // correct [/ first occurrence]
        if (strpos($pattern, '[/') === 0) {
            $pattern = '[' . substr($pattern, 2);
        }

        if (preg_match_all('/(?:([a-zA-Z0-9_.-]+)=)?<([^> ]+) *([^>]*)>/', $pattern, $matches, PREG_SET_ORDER)) {
            foreach ($matches as [$match, $parameter, $name, $regex]) { // $regex is not used
                $pattern = str_replace($match, $parameter, $pattern);

                $route->addDefaults([$parameter => $name]);
                if (!empty($regex)) {
                    $route->addPattern($parameter, $regex);
                }
            }
        }

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
        $options = array_fill_keys($options, null);

        return [
            'template' => stripslashes(str_replace('?', '', $template)),
            'regex' => '{^'.$leadingChar.strtr($template, $replaces + self::PATTERN_REPLACES).'$}sD'.($isHost ? 'i' : ''),
            'variables' => $options,
        ];
    }

    /**
     * Prepares segment pattern with given constrains.
     *
     * @param string $name
     * @param string $segment
     * @param array $requirements
     *
     * @return string
     */
    private function prepareSegment(string $name, string $segment, array $requirements): string
    {
        if ($segment !== '') {
            // A PCRE subpattern name must start with a non-digit. Also a PHP variable cannot start with a digit so the
            // variable would not be usable as a Controller action argument.
            if (preg_match('#\{(\d+)#', $segment)) {
                $segment = $segment . '}';
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
     * @return string
     */
    private function filterSegment(string $segment): string
    {
        return strtr($segment, self::SEGMENT_REPLACES);
    }

    public function __serialize(): array
    {
        return [
            'vars' => $this->variables,
            'template_regex' => $this->template,
            'path_regex' => $this->compiled,
            'path_vars' => $this->pathVariables,
            'host_regex' => $this->hostRegex,
            'host_vars' => $this->hostVariables,
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
        $this->hostVariables = $data['host_vars'];
    }

    /**
     * @param $serialized
     * @internal
     */
    final public function unserialize($serialized): void
    {
        $this->__unserialize(unserialize($serialized, ['allowed_classes' => false]));
    }
}
