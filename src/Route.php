<?php

/** @noinspection CallableParameterUseCaseInTypeContextInspection */

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

use Closure;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use RuntimeException;
use Serializable;
use Throwable;

/**
 * Value object representing a single route.
 *
 * Routes are a combination of path, middleware, and HTTP methods; two routes
 * representing the same path and overlapping HTTP methods are not allowed,
 * while two routes representing the same path and non-overlapping HTTP methods
 * can be used (and should typically resolve to different middleware).
 *
 * Internally, only those three properties are required. However, underlying
 * router implementations may allow or require additional information, such as
 * information defining how to generate a URL from the given route, qualifiers
 * for how segments of a route match, or even default values to use. These may
 * be provided after instantiation via the "defaults" property and related
 * addDefaults() method.
 */
class Route implements Serializable, RouteInterface, RequestHandlerInterface
{
    use Traits\ArgumentsTrait;
    use Traits\ControllersTrait;
    use Traits\DefaultsTrait;
    use Traits\DomainsTrait;
    use Traits\GroupsTrait;
    use Traits\MiddlewaresTrait;
    use Traits\PathsTrait;
    use Traits\PatternsTrait;

    /**
     * HTTP methods supported by this route.
     *
     * @var string[]
     */
    protected $methods = [];

    /**
     * Route name.
     *
     * @var null|string
     */
    protected $name;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var CallableResolverInterface
     */
    protected $callableResolver;

    /**
     * Create a new Route constructor.
     *
     * @param string[]                    $methods          The route HTTP methods
     * @param string                      $pattern          The route pattern
     * @param callable|string|object|null $callable         The route callable
     * @param callable                    $response         The HTTP response
     * @param CallableResolverInterface   $callableResolver
     * @param RouteGroup[]                $groups           The parent route groups
     */
    public function __construct(
        array $methods,
        string $pattern,
        $callable,
        callable $response,
        CallableResolverInterface $callableResolver,
        array $groups = []
    ) {
        $this->methods = $methods;

        // TODO: Use a different method of setting namespace before $callable...
        if (isset($groups['namespace'])) {
            $this->namespace = $groups['namespace'];
            unset($groups['namespace']);
        }

        $this->appendGroupToRoute($groups);
        $this->setController($callable);
        $this->setPath($pattern);

        $this->response = $response;
        $this->callableResolver = $callableResolver;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'path'          => $this->path,
            'prefix'        => $this->prefix,
            'host'          => $this->domain,
            'schemes'       => $this->schemes,
            'namespace'     => $this->namespace,
            'defaults'      => $this->defaults,
            'requirements'  => $this->patterns,
            'methods'       => $this->methods,
            'middlewares'   => $this->middlewares,
            'arguments'     => $this->arguments,
            'group'         => $this->groups,
            'group_append'  => $this->groupAppended,
            'response'      => $this->response,
            'callable'      => $this->callableResolver,
            'controller'    => $this->controller instanceof Closure ? [$this, 'getController'] : $this->controller,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @internal
     */
    final public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * @param array $data
     *
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->path = $data['path'];
        $this->prefix = $data['prefix'];
        $this->domain = $data['host'];
        $this->defaults = $data['defaults'];
        $this->schemes = $data['schemes'];
        $this->patterns = $data['requirements'];
        $this->methods = $data['methods'];
        $this->controller = $data['controller'];
        $this->response = $data['response'];
        $this->groupAppended = $data['group_append'];
        $this->callableResolver = $data['callable'];

        if (isset($data['middlewares'])) {
            $this->middlewares = $data['middlewares'];
        }
        if (isset($data['namespace'])) {
            $this->namespace = $data['namespace'];
        }
        if (isset($data['group'])) {
            $this->groups = $data['group'];
        }
        if (isset($data['arguments'])) {
            $this->arguments = $data['arguments'];
        }
    }

    /**
     * {@inheritdoc}
     *
     * @internal
     */
    final public function unserialize($serialized)
    {
        $this->__unserialize(unserialize($serialized, null));
    }

    /**
     * @param array $values
     *
     * @throws RuntimeException
     *
     * @internal
     */
    public function fromArray(array $values): void
    {
        try {
            foreach ($values as $key => $value) {
                if (null !== $value) {
                    if ('defaults' === $key) {
                        $this->addDefaults($value);
                    } elseif ('patterns' === $key) {
                        $this->whereArray($value);
                    } else {
                        $this->$key = $this->getValueFromKey($values, $key);
                    }
                }
            }
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Add or change the route name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName(?string $name): RouteInterface
    {
        if (null === $name) {
            return $this;
        }

        null !== $this->name ? $this->name .= $name : $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $callable = $this->callableResolver->resolve($this->controller);
        $request = $request->withAttribute('arguments', $this->arguments);

        // If controller is instance of RequestHandlerInterface
        if (!$callable instanceof Closure && $callable[0] instanceof RequestHandlerInterface) {
            return $callable($request);
        }

        return $this->handleController($callable, $request);
    }

    /**
     * @param array       $data
     * @param string      $key
     * @param string|null $message
     *
     * @return mixed
     */
    private function getValueFromKey(array $data, string $key, string $message = null)
    {
        if (isset($data[$key])) {
            return $data[$key];
        }

        if (null === $message) {
            $message = sprintf('Missing "%s" key in route collection', $key);
        }

        throw new RuntimeException($message);
    }
}
