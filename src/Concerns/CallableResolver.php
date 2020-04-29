<?php /** @noinspection PhpComposerExtensionStubsInspection */

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

use Closure;
use RuntimeException;
use stdClass;
use TypeError;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Flight\Routing\Exceptions\InvalidControllerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use function is_object;
use function method_exists;
use function is_callable;
use function is_string;
use function get_class;
use function class_exists;
use function preg_match;
use function json_encode;
use function stripos;

/**
 * This class resolves a string of the format 'class:method', 'class::method'
 * and 'class@method' into a closure that can be dispatched.
 *
 * @final
 */
class CallableResolver implements CallableResolverInterface
{
    public const CALLABLE_PATTERN = '!^([^\:]+)(:|::|@)([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$!';

    /**
     * @var ContainerInterface|null
     */
    protected $container;

    /**
     * @var object|null
     */
    protected $instance;

    /**
     * @param ContainerInterface|null $container
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function addInstanceToClosure(object $instance): CallableResolverInterface
    {
        $this->instance = $instance;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($toResolve): callable
    {
        $resolved = $toResolve;

        if (is_string($resolved) && preg_match(self::CALLABLE_PATTERN, $toResolve, $matches)) {
            // check for slim callable as "class:method", "class::method" and "class@method"
            $resolved = $this->resolveCallable($matches[1], $matches[3]);
        }

        if (is_array($resolved) && !is_callable($resolved) && is_string($resolved[0])) {
            $resolved = $this->resolveCallable($resolved[0], $resolved[1]);
        }

        if (!$resolved instanceof Closure && method_exists($resolved, '__invoke')) {
            $resolved = $this->resolveCallable($toResolve);
        }

        // Checks if indeed what wwe want to return is a callable.
        $resolved = $this->assertCallable($resolved);

        // Bind new Instance or $this->container to \Closure
        if ($resolved instanceof Closure) {
            if (null !== $binded = $this->instance) {
                $resolved = $resolved->bindTo($binded);
            }

            if (null === $binded && $this->container instanceof ContainerInterface) {
                $resolved = $resolved->bindTo($this->container);
            }
        }

        return $resolved;
    }

    /**
     * {@inheritdoc}
     */
    public function returnType($controllerResponse, ResponseInterface $response): ResponseInterface
    {
        // Always return the response...
        if ($controllerResponse instanceof ResponseInterface) {
            return $controllerResponse;
        }

        if (is_string($controllerResponse) || is_numeric($controllerResponse)) {
            $response->getBody()->write((string) $controllerResponse);
        } elseif (is_array($controllerResponse) || $controllerResponse instanceof stdClass) {
            $response->getBody()->write(json_encode((array) $controllerResponse));
        }

        if ($this->isJson($response->getBody())) {
            return $response->withHeader('Content-Type', 'application/json');
        }

        if ($this->isXml($response->getBody())) {
            return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
        }

        // Set content-type to plain text if string doesn't contain <html> tag.
        if (
            !preg_match('/(<\/html[^>]*>)/i', (string) $response->getBody()) ||
            stripos((string) $response->getBody(), '<!doctype') === false
        ) {
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Check if string is something in the DIC
     * that's callable or is a class name which has an __invoke() method.
     *
     * @param string|object $class
     * @param string $method
     * @return callable
     *
     * @throws InvalidControllerException if the callable does not exist
     * @throws TypeError if does not return a callable
     */
    protected function resolveCallable($class, $method = '__invoke'): callable
    {
        $instance = $class;

        if ($this->container instanceof ContainerInterface && is_string($instance)) {
            $instance = $this->container->get($class);
        } else {
            if (!is_object($instance) && !class_exists($instance)) {
                throw new InvalidControllerException(sprintf('Callable %s does not exist', $class));
            }

            $instance = is_object($class) ? $class : new $class();
        }

        // For a class that implements RequestHandlerInterface, we will call handle()
        // if no method has been specified explicitly
        if ($instance instanceof RequestHandlerInterface) {
            $method = 'handle';
        }

        if (!class_exists(is_object($class) ? get_class($class) : $class)) {
            throw new InvalidControllerException(sprintf('Callable class %s does not exist', $class));
        }

        return [$instance, $method];
    }

    /**
     * @param Callable $callable
     *
     * @return Callable
     * @throws RuntimeException if the callable is not resolvable
     */
    protected function assertCallable($callable): callable
    {
        // Maybe could be a class object or RequestHandlerInterface instance
        if ((!$callable instanceof Closure && is_object($callable)) || is_string($callable)) {
            $callable = $this->resolveCallable($callable);
        }

        if (!is_callable($callable)) {
            throw new InvalidControllerException(sprintf(
                '%s is not resolvable',
                is_array($callable) || is_object($callable) ? json_encode($callable) : $callable
            ));
        }

        // Maybe could be an object
        return $callable;
    }

    private function isJson(StreamInterface $stream): bool
    {
        if (!function_exists('json_decode')) {
            return false;
        }
        $stream->rewind();

        json_decode($stream->getContents(), true);

        return JSON_ERROR_NONE === json_last_error();
    }

    private function isXml(StreamInterface $stream): bool
    {
        if (!function_exists('simplexml_load_string')) {
            return false;
        }
        $stream->rewind();

        $previousValue = libxml_use_internal_errors(true);
        $isXml = simplexml_load_string($stream->getContents());
        libxml_use_internal_errors($previousValue);

        return false !== $isXml;
    }
}
