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

namespace Flight\Routing\Handlers;

use Flight\Routing\Exceptions\InvalidControllerException;
use Psr\Container\ContainerInterface;

/**
 * Invokes a route's handler with arguments.
 *
 * If you're using this library with Rade-DI, Yii Inject, DivineNii PHP Invoker, or Laravel DI,
 * instead of using this class as callable, use the call method from the container's class.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteInvoker
{
    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Auto-configure route handler parameters.
     *
     * @param mixed               $handler
     * @param array<string,mixed> $arguments
     *
     * @return mixed
     */
    public function __invoke($handler, array $arguments)
    {
        if (\is_string($handler)) {
            if (null !== $this->container && $this->container->has($handler)) {
                $handler = $this->container->get($handler);
            } elseif (\str_contains($handler, '@')) {
                $handler = \explode('@', $handler, 2);

                goto maybe_callable;
            } elseif (\class_exists($handler)) {
                $handlerRef = new \ReflectionClass($handler);

                if ($handlerRef->hasMethod('__invoke')) {
                    $handler = [$handler, '__invoke'];

                    goto maybe_callable;
                }

                if (null === $constructor = $handlerRef->getConstructor()) {
                    return $handlerRef->newInstanceWithoutConstructor();
                }

                return $handlerRef->newInstanceArgs($this->resolveParameters($constructor->getParameters(), $arguments));
            }
        }

        if ((\is_array($handler) && [0, 1] === \array_keys($handler)) && \is_string($handler[0])) {
            maybe_callable:
            if (null !== $this->container && $this->container->has($handler[0])) {
                $handler[0] = $this->container->get($handler[0]);
            } elseif (\class_exists($handler[0])) {
                $handler[0] = (new \ReflectionClass($handler[0]))->newInstanceArgs([]);
            }
        }

        if (\is_callable($handler)) {
            $handlerRef = new \ReflectionFunction(\Closure::fromCallable($handler));
        } elseif (\is_object($handler)) {
            return $handler;
        } else {
            throw new InvalidControllerException(\sprintf('Route has an invalid handler type of "%s".', \gettype($handler)));
        }

        return $handlerRef->invokeArgs($this->resolveParameters($handlerRef->getParameters(), $arguments));
    }

    /**
     * @param array<int,\ReflectionParameter> $refParameters
     * @param array<string,mixed> $arguments
     *
     * @return array<int,mixed>
     */
    private function resolveParameters(array $refParameters, array $arguments): array
    {
        $parameters = [];

        foreach ($refParameters as $index => $parameter) {
            $typeHint = $parameter->getType();

            if ($typeHint instanceof \ReflectionUnionType) {
                foreach ($typeHint->getTypes() as $unionType) {
                    if (isset($arguments[$unionType->getName()])) {
                        $parameters[$index] = $arguments[$unionType->getName()];

                        continue 2;
                    }

                    if (null !== $this->container && $this->container->has($unionType->getName())) {
                        $parameters[$index] = $this->container->get($unionType->getName());

                        continue 2;
                    }
                }
            } elseif ($typeHint instanceof \ReflectionNamedType) {
                if (isset($arguments[$typeHint->getName()])) {
                    $parameters[$index] = $arguments[$typeHint->getName()];

                    continue;
                }

                if (null !== $this->container && $this->container->has($typeHint->getName())) {
                    $parameters[$index] = $this->container->get($typeHint->getName());

                    continue;
                }
            }

            if (isset($arguments[$parameter->getName()])) {
                $parameters[$index] = $arguments[$parameter->getName()];
            } elseif (null !== $this->container && $this->container->has($parameter->getName())) {
                $parameters[$index] = $this->container->get($parameter->getName());
            } elseif ($parameter->allowsNull() && !$parameter->isDefaultValueAvailable()) {
                $parameters[$index] = null;
            }
        }

        return $parameters;
    }
}
