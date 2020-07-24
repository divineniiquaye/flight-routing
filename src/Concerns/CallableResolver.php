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

namespace Flight\Routing\Concerns;

use Closure;
use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Interfaces\CallableResolverInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use TypeError;

/**
 * This class resolves a string of the format 'class:method',
 * and 'class@method' into a closure that can be dispatched.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @final
 */
class CallableResolver implements CallableResolverInterface
{
    public const CALLABLE_PATTERN = '!^([^\:]+)(:|@)([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$!';

    /**
     * @var null|ContainerInterface
     */
    protected $container;

    /**
     * @var null|object
     */
    protected $instance;

    /**
     * @param null|ContainerInterface $container
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @internal Used in ControllersTrait
     *
     * @return null|ContainerInterface
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
        if (\is_string($toResolve) && false !== \preg_match(self::CALLABLE_PATTERN, $toResolve, $matches)) {
            // check for slim callable as "class:method", and "class@method"
            $toResolve = $this->resolveCallable($matches[1], $matches[3]);
        }

        if (\is_array($toResolve) && \count($toResolve) === 2 && \is_string($toResolve[0])) {
            $toResolve = $this->resolveCallable($toResolve[0], $toResolve[1]);
        }

        // Checks if indeed what wwe want to return is a callable.
        $toResolve = $this->assertCallable($toResolve);

        // Bind new Instance or $this->container to \Closure
        if ($toResolve instanceof Closure) {
            $toResolve = $toResolve->bindTo($this->instance ?? $this->container);
        }

        return $toResolve;
    }

    /**
     * Check if string is something in the DIC
     * that's callable or is a class name which has an __invoke() method.
     *
     * @param object|string $class
     * @param string        $method
     *
     * @throws InvalidControllerException if the callable does not exist
     * @throws TypeError                  if does not return a callable
     *
     * @return callable
     */
    protected function resolveCallable($class, $method = '__invoke'): callable
    {
        if (\is_string($class) && $this->container instanceof ContainerInterface && $this->container->has($class)) {
            $class = $this->container->get($class);
        }

        // We do not need class as a string here
        $class = \is_object($class) ? $class : new $class();

        // For a class that implements RequestHandlerInterface, we will call handle()
        // if no method has been specified explicitly
        if ($class instanceof RequestHandlerInterface) {
            $method = 'handle';
        }

        if (\is_callable($callable = [$class, $method])) {
            return $callable;
        }

        throw new InvalidControllerException('Controller could not be resolved as callable');
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
        if (!$callable instanceof Closure && \is_object($callable)) {
            return $this->resolveCallable($callable);
        }

        if (!\is_callable($callable)) {
            throw new InvalidControllerException(
                \sprintf('%s is not resolvable', \json_encode($callable) ?? $callable)
            );
        }

        return $callable;
    }
}
