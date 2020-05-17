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

use function array_merge;
use Closure;
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Interfaces\RouteGroupInterface;
use Flight\Routing\Interfaces\RouteInterface;
use function in_array;
use function ltrim;
use function preg_match;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use function rtrim;
use RuntimeException;
use Serializable;
use function serialize;
use function sprintf;
use function strpbrk;
use function strpos;
use Throwable;
use function unserialize;

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
    use Traits\MiddlewaresTrait;
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
     * Route path pattern.
     *
     * @var string
     */
    protected $path;

    /**
     * @var bool
     */
    protected $groupAppended = false;

    /**
     * Parent route groups.
     *
     * @var RouteGroupInterface[]
     */
    protected $groups;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * Container.
     *
     * @var ContainerInterface|null
     */
    protected $container;

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
     * @param ContainerInterface|null     $container
     * @param RouteGroup[]                $groups           The parent route groups
     */
    public function __construct(
        array $methods,
        string $pattern,
        $callable,
        callable $response,
        CallableResolverInterface $callableResolver,
        ContainerInterface $container = null,
        array $groups = []
    ) {
        $this->methods = $methods;
        $this->appendGroupToRoute($groups);
        $this->setController($callable);
        $this->setPath($pattern);

        $this->response = $response;
        $this->callableResolver = $callableResolver;
        $this->container = $container;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'path'         => $this->path,
            'host'         => $this->domain,
            'schemes'      => $this->schemes,
            'namespace'    => $this->namespace,
            'defaults'     => $this->defaults,
            'requirements' => $this->patterns,
            'methods'      => $this->methods,
            'middlewares'  => $this->middlewares,
            'arguments'    => $this->arguments,
            'group'        => $this->groups,
            'response'     => $this->response,
            'callable'     => $this->callableResolver,
            'controller'   => $this->controller instanceof Closure ? [$this, 'getController'] : $this->controller,
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
        $this->domain = $data['host'];
        $this->defaults = $data['defaults'];
        $this->schemes = $data['schemes'];
        $this->patterns = $data['requirements'];
        $this->methods = $data['methods'];
        $this->controller = $data['controller'];
        $this->response = $data['response'];
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
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     *
     * @param string $uri
     * @param mixed  $prefix
     *
     * @return mixed|string
     */
    private function normalizePrefix(string $uri, $prefix)
    {
        // Allow homepage uri on prefix just like python dgango url style.
        if (in_array($uri, ['', '/'], true)) {
            return rtrim($prefix, '/').$uri;
        }

        $urls = [];
        foreach (['&', '-', '_', '~', '@'] as $symbols) {
            if (strpos($prefix, $symbols) !== false) {
                $urls[] = rtrim($prefix, '/').$uri;
            }
        }

        return $urls ? $urls[0] : rtrim($prefix, '/').'/'.ltrim($uri, '/');
    }

    /**
     * Sets the pattern for the path.
     *
     * @param string $pattern The path pattern
     *
     * @return void
     */
    protected function setPath(string $pattern): void
    {
        if (isset($this->groups[RouteGroupInterface::PREFIX])) {
            $pattern = $this->normalizePrefix($pattern, $this->groups[RouteGroupInterface::PREFIX]);
        }

        // Match for a domain
        if (preg_match('@^(?:(https?):)?(//[^/]+)@i', $pattern, $matches)) {
            $this->addDomain(isset($matches[1]) ? $matches[0] : $matches[2]);
            $pattern = preg_replace('@^(?:(https?):)?(//[^/]+)@i', '', $pattern);
        }

        if (
            strpbrk($pattern, '<*') !== false &&
            preg_match(
                '/^(?:(?P<route>[^(.*)]+)\*<)?(?:(?P<controller>[^@]+)@+)?(?P<action>[a-z_\-]+)\>$/i',
                $pattern,
                $matches
            )
        ) {
            if (!isset($matches['route'])) {
                throw new InvalidControllerException("Unable to locate route candidate for `{$pattern}`");
            }

            $pattern = $matches['route'];
            if (isset($matches['controller'], $matches['action'])) {
                $this->setController([$matches['controller'] ?: $this->controller, $matches['action']]);
            }
        }

        $this->path = (empty($pattern) || '/' === $pattern) ? '/' : $pattern;
    }

    /**
     * @param array $groups
     *
     * @return void
     */
    protected function appendGroupToRoute(array $groups): void
    {
        if (empty($groups)) {
            return;
        }

        $this->groups = current($groups)->getOptions();
        if (isset($this->groups[RouteGroupInterface::MIDDLEWARES])) {
            $this->middlewares = array_merge($this->middlewares, $this->groups[RouteGroupInterface::MIDDLEWARES]);
        }

        if (isset($this->groups[RouteGroupInterface::DOMAIN])) {
            $this->domain = $this->groups[RouteGroupInterface::DOMAIN] ?? '';
        }

        $this->groupAppended = true;
    }

    /**
     * {@inheritdoc}
     */
    public function hasGroup(): bool
    {
        return $this->groupAppended;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupId(): ?string
    {
        if (!$this->hasGroup()) {
            return null;
        }

        return md5(serialize($this->groups));
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->path;
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

        $current = $this->groups[RouteGroupInterface::NAME] ?? null;
        $this->name = null !== $current ? $current.$name : $name;

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
