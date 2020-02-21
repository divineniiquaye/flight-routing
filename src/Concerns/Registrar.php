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

use Flight\Routing\RouteCollector;

trait Registrar
{
    /** @var string|null */
    private $name;

    /** @var string */
    private $uri;

    /** @var RouteCollector */
    private $router;

    /** @var string|array|null */
    private $method;

    /** @var string|callable */
    private $controller;

    /** @var string[]|callable[] */
    private $middleware = [];

    /** @var string|null */
    private $domain;

    /** @var string|null */
    private $namespace;

    /** @var array */
    private $defaults = [];

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @return string|array|null
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return callable|string
     */
    public function getController()
    {
        $controller = $this->controller;
        $namespace =   $this->namespace;

        if (
            is_string($controller) &&
            ! is_null($namespace) &&
            false === mb_strpos($controller, $namespace)
        ) {
            $controller = $namespace . $controller;
        }
        return $controller;
    }

    /**
     * @return callable[]|string[]
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * @return string|null
     */
    public function getDomain()
    {
        return str_replace(['http://', 'https://'], '', $this->domain);
    }

    /**
     * Sets the pattern for the path.
     *
     * Based on https://github.com/symfony/routing/blob/master/Route.php by Fabien
     *
     * @param string $pattern The path pattern
     *
     * @return $this
     */
    protected function setPath($pattern)
    {
        if (false !== strpbrk($pattern, '?<')) {
            $pattern = preg_replace_callback('#\{(\w++)(<.*?>)?(\?[^\}]*+)?\}#', function ($match) {
                if (isset($match[3][0])) {
                    $this->addDefaults([$match[1] => '?' !== $match[3] ? substr($match[3], 1) : null]);
                }
                if (isset($match[2][0])) {
                    $this->define($match[1], substr($match[2], 1, -1));
                }

                return '{'.$match[1].'}';
            }, $pattern);
        }

        $this->uri = $pattern;
    }

    /**
     * Adds defaults.
     *
     * This method implements a fluent interface.
     *
     * @param array $defaults The defaults
     *
     * @return $this
     */
    public function addDefaults(array $defaults)
    {
        foreach ($defaults as $name => $default) {
            $this->defaults[$name] = $default;
        }

        return $this;
    }

    /**
     * Gets a default value.
     *
     * @param string $name A variable name
     *
     * @return mixed The default value or defaults when not given
     */
    public function getDefault($name)
    {
        return isset($this->defaults[$name]) ? $this->defaults[$name] : $this->defaults;
    }

    /**
     * Checks if a default value is set for the given variable.
     *
     * @param string $name A variable name
     *
     * @return bool true if the default value is set, false otherwise
     */
    public function hasDefault($name)
    {
        return \array_key_exists($name, $this->defaults);
    }
}
