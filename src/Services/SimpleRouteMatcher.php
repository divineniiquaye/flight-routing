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

use Closure;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;

class SimpleRouteMatcher implements RouteMatcherInterface
{
    private const URI_FIXERS = [
        '[]'  => '',
        '[/]' => '',
        '['   => '',
        ']'   => '',
        '://' => '://',
        '//'  => '/',
    ];

    /** @var SimpleRouteCompiler */
    private $compiler;

    public function __construct()
    {
        $this->compiler = new SimpleRouteCompiler();
    }

    /**
     * {@inheritdoc}
     */
    public function compileRoute(RouteInterface $route): RouteMatcherInterface
    {
        $this->compiler = $this->compiler->compile($route);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function buildPath(RouteInterface $route, array $substitutions): string
    {
        $this->compileRoute($route);

        $parameters = \array_merge(
            $this->getVariables(),
            $route->getDefaults(),
            $this->fetchOptions($substitutions, \array_keys($this->getVariables()))
        );

        // If we have s secured scheme, it should be served
        $schemes = \array_map(function ($scheme) {
            return 'https' === $scheme ? 'https' : 'http';
        }, $route->getSchemes() ?? ['http']);
        $path = '';

        //Uri without empty blocks (pretty stupid implementation)
        if (null !== $this->compiler->getRegexTemplate()) {
            $path = \sprintf(
                '%s://%s/',
                \current($schemes),
                \trim($this->interpolate($this->compiler->getRegexTemplate(), $parameters), '.')
            );
        }

        return $path .= $this->interpolate($this->compiler->getRegexTemplate(false), $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getRegex(bool $domain = false): string
    {
        if (false !== $domain) {
            return (string) $this->compiler->getHostRegex();
        }

        return $this->compiler->getRegex();
    }

    /**
     * {@inheritdoc}
     */
    public function getVariables(): array
    {
        return $this->compiler->getVariables();
    }

    /**
     * Interpolate string with given values.
     *
     * @param null|string             $string
     * @param array<int|string,mixed> $values
     *
     * @return string
     */
    private function interpolate(?string $string, array $values): string
    {
        $replaces = [];

        foreach ($values as $key => $value) {
            $replaces["<{$key}>"] = (\is_array($value) || $value instanceof Closure) ? '' : $value;
        }

        return \strtr((string) $string, $replaces + self::URI_FIXERS);
    }

    /**
     * Fetch uri segments and query parameters.
     *
     * @param array<int|string,mixed> $parameters
     * @param array<int|string,mixed> $allowed
     *
     * @return array<int|string,mixed>
     */
    private function fetchOptions($parameters, array $allowed): array
    {
        $result = [];

        foreach ($parameters as $key => $parameter) {
            if (\is_numeric($key) && isset($allowed[$key])) {
                // this segment fetched keys from given parameters either by name or by position
                $key = $allowed[$key];
            }

            // TODO: String must be normalized here
            $result[$key] = $parameter;
        }

        return $result;
    }
}
