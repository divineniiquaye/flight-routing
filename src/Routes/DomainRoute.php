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

namespace Flight\Routing\Routes;

use Flight\Routing\Exceptions\UriHandlerException;
use Psr\Http\Message\UriInterface;

/**
 * Value object representing a single route.
 *
 * The default support for this route class includes FastRoute support:
 * - hosts binding
 * - schemes binding
 * - domain and schemes casting from route pattern
 *
 * @method string[] getSchemes() Gets the route hosts schemes.
 * @method string[] getHosts()   Gets the route hosts.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class DomainRoute extends FastRoute
{
    /**
     * A Pattern to match protocol, host and port from a url.
     *
     * Examples of urls that can be matched: http://en.example.domain, {sub}.example.domain, https://example.com:34, example.com, etc.
     *
     * @var string[]
     */
    public const URL_PATTERN = ['#^(?:([a-z]+)\:\/{2})?([^\/]+)?$#u', '#^(?:([a-z]+)\:)?(?:\/{2}([^\/]+))?(?:(\/.*))?$#u'];

    protected static array $getter = [
        'name' => 'name',
        'path' => 'path',
        'methods' => 'methods*',
        'schemes' => 'schemes*',
        'hosts' => 'hosts*',
        'handler' => 'handler',
        'arguments' => 'arguments*',
        'patterns' => 'patterns*',
        'defaults' => 'defaults*',
    ];

    public function __construct(string $pattern, $methods = self::DEFAULT_METHODS, $handler = null)
    {
        parent::__construct('', $methods, $handler);

        // Resolve route pattern ...
        $this->data['path'] .= $this->resolvePattern($pattern);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UriHandlerException
     */
    public function match(string $method, UriInterface $uri)
    {
        $matched = parent::match($method, $uri);

        if (isset($this->data['schemes'])) {
            if (\in_array($uri->getScheme(), $this->get('schemes'), true)) {
                return $matched;
            }

            throw new UriHandlerException(\sprintf('Unfortunately current scheme "%s" is not allowed on requested uri [%s].', $uri->getScheme(), $uri->getPath()), 400);
        }

        return $matched;
    }

    /**
     * {@inheritdoc}
     */
    public function path(string $pattern)
    {
        return parent::path($this->resolvePattern($pattern));
    }

    /**
     * Sets the requirement of host on this Route.
     *
     * @param string $hosts The host for which this route should be enabled
     *
     * @return static
     */
    public function domain(string ...$hosts)
    {
        foreach ($hosts as $host) {
            \preg_match(self::URL_PATTERN[0], $host, $matches, \PREG_UNMATCHED_AS_NULL);

            if (isset($matches[1])) {
                $this->data['schemes'][] = $matches[1];
            }

            if (isset($matches[2])) {
                $this->data['hosts'][] = $matches[2];
            }
        }

        return $this;
    }

    /**
     * Sets the requirement of domain scheme on this Route.
     *
     * @param string ...$schemes
     *
     * @return static
     */
    public function scheme(string ...$schemes)
    {
        foreach ($schemes as $scheme) {
            $this->data['schemes'][] = \strtolower($scheme);
        }

        return $this;
    }

    protected function resolvePattern(string $pattern): string
    {
        \preg_match(self::URL_PATTERN[1], $pattern, $matches, \PREG_UNMATCHED_AS_NULL);

        if (!empty($matches)) {
            if (isset($matches[1])) {
                $this->data['schemes'][] = $matches[1];
            }

            if (isset($matches[2])) {
                $this->data['hosts'][] = $matches[2];
            }

            if (!isset($matches[3])) {
                throw new UriHandlerException('A route path not could not be found, Did you forget include one.');
            }
        }

        return $matches[3] ?? $pattern;
    }
}
