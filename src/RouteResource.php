<?php /** @noinspection PhpUnusedParameterInspection */

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

namespace Flight\Routing;

use Flight\Routing\Interfaces\RouteCollectorInterface;

use function array_intersect;
use function ucfirst;
use function array_diff;
use function mb_strpos;
use function str_replace;
use function explode;
use function array_map;
use function implode;
use function trim;
use function array_merge;

class RouteResource
{
    /**
     * The router instance.
     *
     * @var RouteCollector
     */
    protected $router;

    /**
     * The default actions for a resourceful controller.
     *
     * @var array
     */
    protected $resourceDefaults = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

    /**
     * The parameters set for this resource instance.
     *
     * @var array|string
     */
    protected $parameters;

    /**
     * The global parameter mapping.
     *
     * @var array
     */
    protected static $parameterMap = [];

    /**
     * Singular global parameters.
     *
     * @var bool
     */
    protected static $singularParameters = true;

    /**
     * The verbs used in the resource URIs.
     *
     * @var array
     */
    protected static $verbs = [
        'create' => 'create',
        'destroy' => null,
        'edit' => 'edit',
    ];

    /**
     * Create a new resource registrar instance.
     *
     * @param RouteCollectorInterface $router
     */
    public function __construct(RouteCollectorInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Route a resource to a controller.
     *
     * @param string $name
     * @param string $controller
     * @param array  $options
     */
    public function register($name, $controller, array $options = []): void
    {
        if (isset($options['parameters']) && !isset($this->parameters)) {
            $this->parameters = $options['parameters'];
        }

        // We need to extract the base resource from the resource name. Nested resources
        // are supported in the framework, but we need to know what name to use for a
        // place-holder on the route parameters, which should be the base resources.
        $base = $this->getResourceWildcard($name);

        $defaults = $this->resourceDefaults;
        foreach ($this->getResourceMethods($defaults, $options) as $m) {
            $this->{'addResource'.ucfirst($m)}($name, $base, $controller, $options);
        }
    }

    /**
     * Get the applicable resource methods.
     *
     * @param array $defaults
     * @param array $options
     *
     * @return array
     */
    protected function getResourceMethods($defaults, $options): array
    {
        if (isset($options['only'])) {
            return array_intersect($defaults, (array) $options['only']);
        }

        if (isset($options['except'])) {
            return array_diff($defaults, (array) $options['except']);
        }

        return $defaults;
    }

    /**
     * Get the base resource URI for a given resource.
     *
     * @param string $resource
     *
     * @return string
     */
    public function getResourceUri($resource): string
    {
        if (!mb_strpos($resource, '.')) {
            return $resource;
        }

        // Once we have built the base URI, we'll remove the parameter holder for this
        // base resource name so that the individual route adders can suffix these
        // paths however they need to, as some do not have any parameters at all.
        $segments = explode('.', $resource);

        $uri = $this->getNestedResourceUri($segments);

        return str_replace('/{'.$this->getResourceWildcard(end($segments)).'}', '', $uri);
    }

    /**
     * Get the URI for a nested resource segment array.
     *
     * @param array $segments
     *
     * @return string
     */
    protected function getNestedResourceUri(array $segments): string
    {
        // We will spin through the segments and create a place-holder for each of the
        // resource segments, as well as the resource itself. Then we should get an
        // entire string for the resource URI that contains all nested resources.
        return implode('/', array_map(function ($s) {
            return $s.'/{'.$this->getResourceWildcard($s).'}';
        }, $segments));
    }

    /**
     * Get the action array for a resource route.
     *
     * @param string $resource
     * @param string $controller
     * @param string $method
     * @param array  $options
     *
     * @return array
     */
    protected function getResourceAction($resource, $controller, $method, $options): array
    {
        $name = $this->getResourceName($resource, $method, $options);

        return ['name' => $name, 'controller' => [$controller, $method]];
    }

    /**
     * Get the name for a given resource.
     *
     * @param string $resource
     * @param string $method
     * @param array  $options
     *
     * @return string
     */
    protected function getResourceName($resource, $method, $options): string
    {
        // If a global prefix has been assigned to all names for this resource, we will
        // grab that so we can prepend it onto the name when we create this name for
        // the resource action. Otherwise we'll just use an empty string for here.
        $prefix = isset($options['name']) ? $options['name'].'.' : '';

        return $this->getGroupResourceName($prefix, $resource, $method);
    }

    /**
     * Get the resource name for a grouped resource.
     *
     * @param string $prefix
     * @param string $resource
     * @param string $method
     *
     * @return string
     */
    protected function getGroupResourceName($prefix, $resource, $method): string
    {
        return trim("{$prefix}{$resource}.{$method}", '.');
    }

    /**
     * Format a resource parameter for usage.
     *
     * @param string $value
     *
     * @return string
     */
    public function getResourceWildcard($value): string
    {
        if (isset($this->parameters[$value])) {
            $value = $this->parameters[$value];
        } elseif (isset(static::$parameterMap[$value])) {
            $value = static::$parameterMap[$value];
        }

        return str_replace('-', '_', $value);
    }

    /**
     * Add the index method for a resourceful route.
     *
     * @param string $name
     * @param string $base
     * @param string $controller
     * @param array  $options
     *
     * @return RouteCollector
     */
    protected function addResourceIndex($name, $base, $controller, $options): RouteCollector
    {
        $uri = $this->getResourceUri($name);
        $action = $this->getResourceAction($name, $controller, 'index', $options);

        return $this->router->get($uri, $action['controller'])->setName($action['name']);
    }

    /**
     * Add the create method for a resourceful route.
     *
     * @param string $name
     * @param string $base
     * @param string $controller
     * @param array  $options
     *
     * @return RouteCollector
     */
    protected function addResourceCreate($name, $base, $controller, $options): RouteCollector
    {
        $uri = $this->getResourceUri($name).'/'.static::$verbs['create'];
        $action = $this->getResourceAction($name, $controller, 'create', $options);

        return $this->router->get($uri, $action['controller'])->setName($action['name']);
    }

    /**
     * Add the store method for a resourceful route.
     *
     * @param string $name
     * @param string $base
     * @param string $controller
     * @param array  $options
     *
     * @return RouteCollector
     */
    protected function addResourceStore($name, $base, $controller, $options): RouteCollector
    {
        $uri = $this->getResourceUri($name);
        $action = $this->getResourceAction($name, $controller, 'store', $options);

        return $this->router->post($uri, $action['controller'])->setName($action['name']);
    }

    /**
     * Add the show method for a resourceful route.
     *
     * @param string $name
     * @param string $base
     * @param string $controller
     * @param array  $options
     *
     * @return RouteCollector
     */
    protected function addResourceShow($name, $base, $controller, $options): RouteCollector
    {
        $uri = $this->getResourceUri($name).'/{'.$base.'}';
        $action = $this->getResourceAction($name, $controller, 'show', $options);

        return $this->router->get($uri, $action['controller'])->setName($action['name']);
    }

    /**
     * Add the edit method for a resourceful route.
     *
     * @param string $name
     * @param string $base
     * @param string $controller
     * @param array  $options
     *
     * @return RouteCollector
     */
    protected function addResourceEdit($name, $base, $controller, $options): RouteCollector
    {
        $uri = $this->getResourceUri($name).'/{'.$base.'}/'.static::$verbs['edit'];
        $action = $this->getResourceAction($name, $controller, 'edit', $options);

        return $this->router->get($uri, $action['controller'])->setName($action['name']);
    }

    /**
     * Add the update method for a resourceful route.
     *
     * @param string $name
     * @param string $base
     * @param string $controller
     * @param array  $options
     *
     * @return Interfaces\RouteInterface
     */
    protected function addResourceUpdate($name, $base, $controller, $options): Interfaces\RouteInterface
    {
        $uri = $this->getResourceUri($name).'/{'.$base.'}';
        $action = $this->getResourceAction($name, $controller, 'update', $options);

        return $this->router->map(['PUT', 'PATCH'], $uri, $action['controller'])->setName($action['name']);
    }

    /**
     * Add the destroy method for a resourceful route.
     *
     * @param string $name
     * @param string $base
     * @param string $controller
     * @param array  $options
     *
     * @return RouteCollector
     */
    protected function addResourceDestroy($name, $base, $controller, $options): RouteCollector
    {
        $uri = $this->getResourceUri($name).'/{'.$base.'}';

        if (static::$verbs['destroy']) {
            $uri .= '/' . static::$verbs['destroy'];
        }

        $action = $this->getResourceAction($name, $controller, 'destroy', $options);

        return $this->router->delete($uri, $action['controller'])->setName($action['name']);
    }

    /**
     * Get the global parameter map.
     *
     * @return array
     */
    public static function getParameters(): array
    {
        return static::$parameterMap;
    }

    /**
     * Set the global parameter mapping.
     *
     * @param array $parameters
     */
    public static function setParameters(array $parameters = []): void
    {
        static::$parameterMap = $parameters;
    }

    /**
     * Get or set the action verbs used in the resource URIs.
     *
     * @param array $verbs
     *
     * @return array
     */
    public static function verbs(array $verbs = []): ?array
    {
        if (empty($verbs)) {
            return static::$verbs;
        }

        static::$verbs = array_merge(static::$verbs, $verbs);
    }
}
