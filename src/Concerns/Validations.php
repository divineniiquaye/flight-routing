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

namespace Flight\Routing\Concerns;

trait Validations
{
    /**
     * @var string[]string
     */
    private $parameters = [
        'id' => '\d+',
        'any' => '[^/]+',
        'all' => '.*',
        'string' => '\w+',
        'slug' => '[\w\-_]+',
    ];

    /**
     * {@inheritDoc}
     *
     * @return $this
     */
    public function addParameters(array $requirements)
    {
        foreach ($requirements as $key => $regex) {
            $this->parameters[$key] = $this->sanitizeRequirement($key, $regex);
        }

        return $this;
    }

    /**
     * Check if given request method matches given route method.
     *
     * @param string|array|null $routeMethod
     * @param string            $requestMethod
     *
     * @return bool
     */
    protected function compareMethod($routeMethod, string $requestMethod): bool
    {
        if (is_array($routeMethod)) {
            return in_array($requestMethod, $routeMethod);
        }

        return $routeMethod == $requestMethod;
    }

    /**
     * Check if given request domain matches given route domain.
     *
     * @param string|null $routeDomain
     * @param string      $requestDomain
     *
     * @return bool
     */
    protected function compareDomain(?string $routeDomain, string $requestDomain): bool
    {
        return $routeDomain == null || preg_match($routeDomain, $requestDomain);
    }

    /**
     * Check if given request uri matches given uri method.
     *
     * @param string $routeUri
     * @param string $requestUri
     * @param array  $parameters
     *
     * @return bool
     */
    protected function compareUri(string $routeUri, string $requestUri, array &$parameters)
    {
        return preg_match($routeUri, $requestUri, $parameters);
    }

    private function sanitizeRequirement(string $key, string $regex)
    {
        if ('' !== $regex && '^' === $regex[0]) {
            $regex = (string) mb_substr($regex, 1); // returns false for a single character
        }

        if ('$' === mb_substr($regex, -1)) {
            $regex = substr($regex, 0, -1);
        }

        if ('' === $regex) {
            throw new \InvalidArgumentException(sprintf('Routing requirement for "%s" cannot be empty.', $key));
        }

        return $regex;
    }

    /**
     * Get the value of parameters
     *
     * @return  array
     */
    public function getParameters()
    {
        return $this->parameters;
    }
}
