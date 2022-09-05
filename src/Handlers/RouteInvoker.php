<?php declare(strict_types=1);

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

namespace Flight\Routing\Handlers;

use Flight\Routing\Exceptions\InvalidControllerException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

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
    public function __construct(protected ?ContainerInterface $container = null)
    {
    }

    /**
     * Auto-configure route handler parameters.
     *
     * @param array<string,mixed> $arguments
     */
    public function __invoke(mixed $handler, array $arguments): mixed
    {
        if (\is_string($handler)) {
            $handler = \ltrim($handler, '\\');

            if ($this->container?->has($handler)) {
                $handler = $this->container->get($handler);
            } elseif (\str_contains($handler, '@')) {
                $handler = \explode('@', $handler, 2);
                goto maybe_callable;
            } elseif (\class_exists($handler)) {
                $handler = \is_callable($this->container) ? ($this->container)($handler) : new $handler();
            }
        } elseif (\is_array($handler) && ([0, 1] === \array_keys($handler) && \is_string($handler[0]))) {
            $handler[0] = \ltrim($handler[0], '\\');

            maybe_callable:
            if ($this->container?->has($handler[0])) {
                $handler[0] = $this->container->get($handler[0]);
            } elseif (\class_exists($handler[0])) {
                $handler[0] = \is_callable($this->container) ? ($this->container)($handler[0]) : new $handler[0]();
            }
        }

        if (!\is_callable($handler)) {
            if (!\is_object($handler)) {
                throw new InvalidControllerException(\sprintf('Route has an invalid handler type of "%s".', \gettype($handler)));
            }

            return $handler;
        }

        $handlerRef = new \ReflectionFunction(\Closure::fromCallable($handler));

        if ($handlerRef->getNumberOfParameters() > 0) {
            $resolvedParameters = $this->resolveParameters($handlerRef->getParameters(), $arguments);
        }

        return $handlerRef->invokeArgs($resolvedParameters ?? []);
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * Resolve route handler & parameters.
     *
     * @throws InvalidControllerException
     */
    public static function resolveRoute(
        ServerRequestInterface $request,
        callable $resolver,
        mixed $handler,
        callable $arguments,
    ): ResponseInterface|FileHandler|string|null {
        if ($handler instanceof RequestHandlerInterface) {
            return $handler->handle($request);
        }
        $printed = \ob_start(); // Start buffering response output

        try {
            if ($handler instanceof ResourceHandler) {
                $handler = $handler($request->getMethod(), true);
            }

            $response = ($resolver)($handler, $arguments($request));
        } catch (\Throwable $e) {
            \ob_get_clean();

            throw $e;
        } finally {
            while (\ob_get_level() > 1) {
                $printed = \ob_get_clean() ?: null;
            } // If more than one output buffers is set ...
        }

        if ($response instanceof ResponseInterface || \is_string($response = $printed ?: ($response ?? \ob_get_clean()))) {
            return $response;
        }

        if ($response instanceof RequestHandlerInterface) {
            return $response->handle($request);
        }

        if ($response instanceof \Stringable) {
            return $response->__toString();
        }

        if ($response instanceof FileHandler) {
            return $response;
        }

        if ($response instanceof \JsonSerializable || $response instanceof \iterable || \is_array($response)) {
            return \json_encode($response, \JSON_THROW_ON_ERROR) ?: null;
        }

        return null;
    }

    /**
     * @param array<int,\ReflectionParameter> $refParameters
     * @param array<string,mixed>             $arguments
     *
     * @return array<int,mixed>
     */
    protected function resolveParameters(array $refParameters, array $arguments): array
    {
        $parameters = [];
        $nullable = 0;

        foreach ($arguments as $k => $v) {
            if (\is_numeric($k) || !\str_contains($k, '&')) {
                continue;
            }

            foreach (\explode('&', $k) as $i) {
                $arguments[$i] = $v;
            }
        }

        foreach ($refParameters as $index => $parameter) {
            $typeHint = $parameter->getType();

            if ($nullable > 0) {
                $index = $parameter->getName();
            }

            if ($typeHint instanceof \ReflectionUnionType || $typeHint instanceof \ReflectionIntersectionType) {
                foreach ($typeHint->getTypes() as $unionType) {
                    if ($unionType->isBuiltin()) {
                        continue;
                    }

                    if (isset($arguments[$unionType->getName()])) {
                        $parameters[$index] = $arguments[$unionType->getName()];
                        continue 2;
                    }

                    if ($this->container?->has($unionType->getName())) {
                        $parameters[$index] = $this->container->get($unionType->getName());
                        continue 2;
                    }
                }
            } elseif ($typeHint instanceof \ReflectionNamedType && !$typeHint->isBuiltin()) {
                if (isset($arguments[$typeHint->getName()])) {
                    $parameters[$index] = $arguments[$typeHint->getName()];
                    continue;
                }

                if ($this->container?->has($typeHint->getName())) {
                    $parameters[$index] = $this->container->get($typeHint->getName());
                    continue;
                }
            }

            if (isset($arguments[$parameter->getName()])) {
                $parameters[$index] = $arguments[$parameter->getName()];
            } elseif ($parameter->isDefaultValueAvailable()) {
                ++$nullable;
            } elseif (!$parameter->isVariadic() && ($parameter->isOptional() || $parameter->allowsNull())) {
                $parameters[$index] = null;
            }
        }

        return $parameters;
    }
}
