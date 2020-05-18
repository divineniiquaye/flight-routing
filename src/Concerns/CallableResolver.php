<?php

/** @noinspection PhpComposerExtensionStubsInspection */

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
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use TypeError;

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
     * @internal Used in ControllersTrait
     *
     * @return  ContainerInterface|null
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    public function addInstanceToClosure($instance): CallableResolverInterface
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

        if (is_array($resolved) && !$resolved instanceof Closure && is_string($toResolve[0])) {
            $resolved = $this->resolveCallable($resolved[0], $resolved[1]);
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
     * Check if string is something in the DIC
     * that's callable or is a class name which has an __invoke() method.
     *
     * @param string|object $class
     * @param string        $method
     *
     * @throws InvalidControllerException if the callable does not exist
     * @throws TypeError                  if does not return a callable
     *
     * @return callable
     */
    protected function resolveCallable($class, $method = '__invoke'): callable
    {
        $instance = $class;

        if ($this->container instanceof ContainerInterface && is_string($instance)) {
            $instance = $this->container->get($class);
        } else {
            if (!is_object($class) && !class_exists($class)) {
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
     * @param callable $callable
     *
     * @throws RuntimeException if the callable is not resolvable
     *
     * @return callable
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
}
