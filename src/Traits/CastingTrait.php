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

namespace Flight\Routing\Traits;

use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Handlers\ResourceHandler;
use Flight\Routing\Route;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

trait CastingTrait
{
    /** @var string|null */
    private $name;

    /** @var string */
    private $path;

    /** @var array<string,bool> */
    private $methods = [];

    /** @var string[] */
    private $domain = [];

    /** @var array<string,bool> */
    private $schemes = [];

    /** @var array<string,mixed> */
    private $defaults = [];

    /** @var array<string,string|string[]> */
    private $patterns = [];

    /** @var MiddlewareInterface[] */
    private $middlewares = [];

    /** @var mixed */
    private $controller;

    /**
     * Locates appropriate route by name. Support dynamic route allocation using following pattern:
     * Pattern route:   `pattern/*<controller@action>`
     * Default route: `*<controller@action>`
     * Only action:   `pattern/*<action>`.
     */
    private function castRoute(string $route): string
    {
        if (!(\strpbrk($route, ':*{') || 0 === \strpos($route, '//'))) {
            return $route ?: '/';
        }

        $pattern = \preg_replace_callback(Route::RCA_PATTERN, function (array $matches): string {
            if (isset($matches[1])) {
                $this->schemes[$matches[1]] = true;
            }

            if (isset($matches[2])) {
                $this->domain[] = $matches[2];
            }

            // Match controller from route pattern.
            $handler = $matches[4] ?? $this->controller;

            if (isset($matches[5])) {
                $this->controller = !empty($handler) ? [$handler, $matches[5]] : $matches[5];
            }

            return $matches[3];
        }, $route, -1, $count, \PREG_UNMATCHED_AS_NULL);

        return $pattern ?? $route;
    }

    /**
     * @internal skip throwing an exception and return exisitng $controller
     *
     * @param callable|object|string|string[] $controller
     *
     * @return mixed
     */
    private function castNamespace(string $namespace, $controller)
    {
        if ($controller instanceof ResourceHandler) {
            return $controller->namespace($namespace);
        }

        if (\is_string($controller) && (!\str_starts_with($controller, $namespace) && '\\' === $controller[0])) {
            return $namespace . $controller;
        }

        if ((\is_array($controller) && \array_keys($controller) === [0, 1]) && \is_string($controller[0])) {
            $controller[0] = $this->castNamespace($namespace, $controller[0]);
        }

        return $controller;
    }

    /**
     * Resolves route handler to return a response.
     *
     * @param null|callable(mixed,array) $handlerResolver
     * @param mixed                      $handler
     *
     * @throws InvalidControllerException if invalid response stream contents
     *
     * @return ResponseInterface|string
     */
    private function castHandler(ServerRequestInterface $request, ?callable $handlerResolver, $handler)
    {
        \ob_start(); // Start buffering response output

        $response = null !== $handlerResolver ? $handlerResolver($handler, $this->arguments) : $this->resolveHandler($handler);

        // If response was returned using an echo expression ...
        $echoedResponse = \ob_get_clean();

        if (!$response instanceof ResponseInterface) {
            switch (true) {
                case $response instanceof RequestHandlerInterface:
                    return $response->handle($request);

                case (null === $response || true === $response) && false !== $echoedResponse:
                    $response = $echoedResponse;

                    break;

                case \is_string($response) || \is_int($response):
                    $response = (string) $response;

                    break;

                case \is_array($response) || $response instanceof \JsonSerializable || $response instanceof \Traversable:
                    $response = \json_encode($response);

                    break;

                default:
                    throw new InvalidControllerException(\sprintf('Response type for route "%s" is not allowed in PSR7 response body stream.', $this->name));
            }
        }

        return $result ?? $response;
    }

    /**
     * Auto-configure route handler parameters.
     *
     * @param mixed $handler
     *
     * @return mixed
     */
    private function resolveHandler($handler)
    {
        if ((\is_array($handler) && [0, 1] === \array_keys($handler)) && \is_string($handler[0])) {
            $handler[0] = (new \ReflectionClass($handler[0]))->newInstanceArgs();
        }

        if (\is_callable($handler)) {
            $handlerRef = new \ReflectionFunction(\Closure::fromCallable($handler));
        } elseif (\is_object($handler) || (\is_string($handler) && \class_exists($handler))) {
            $handlerRef = new \ReflectionClass($handler);

            if ($handlerRef->hasMethod('__invoke')) {
                return $this->resolveHandler([$handlerRef->newInstance(), '__invoke']);
            }

            if (null !== $constructor = $handlerRef->getConstructor()) {
                $constructorParameters = $constructor->getParameters();
            }
        }

        if (!isset($handlerRef)) {
            return $handler;
        }

        $parameters = [];
        $arguments = $this->defaults['_arguments'] ?? [];

        foreach ($constructorParameters ?? $handlerRef->getParameters() as $index => $parameter) {
            $typeHint = $parameter->getType();

            if ($typeHint instanceof \ReflectionUnionType) {
                foreach ($typeHint->getTypes() as $unionType) {
                    if (isset($arguments[$unionType->getName()])) {
                        $parameters[$index] = $arguments[$unionType->getName()];

                        break;
                    }
                }
            } elseif ($typeHint instanceof \ReflectionNamedType && isset($arguments[$typeHint->getName()])) {
                $parameters[$index] = $arguments[$typeHint->getName()];
            }

            if (isset($arguments[$parameter->getName()])) {
                $parameters[$index] = $arguments[$parameter->getName()];
            } elseif (\PHP_VERSION_ID < 80000) {
                if ($parameter->isOptional() && $parameter->isDefaultValueAvailable()) {
                    $parameters[$index] = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $parameters[$index] = null;
                }
            }
        }

        if ($handlerRef instanceof \ReflectionFunction) {
            return $handlerRef->invokeArgs($parameters);
        }

        return $handlerRef->newInstanceArgs($parameters);
    }

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     */
    private function castPrefix(string $uri, string $prefix): string
    {
        // This is not accepted, but we're just avoiding throwing an exception ...
        if (empty($prefix)) {
            return $uri;
        }

        if (isset(Route::URL_PREFIX_SLASHES[$prefix[-1]], Route::URL_PREFIX_SLASHES[$uri[0]])) {
            return $prefix . \ltrim($uri, implode('', Route::URL_PREFIX_SLASHES));
        }

        // browser supported slashes ...
        $slashExist = Route::URL_PREFIX_SLASHES[$prefix[-1]] ?? Route::URL_PREFIX_SLASHES[$uri[0]] ?? null;

        if (null === $slashExist) {
            $prefix .= '/';
        }

        return $prefix . $uri;
    }
}
