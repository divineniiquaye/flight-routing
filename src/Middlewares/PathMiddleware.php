<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Middlewares;

use Flight\Routing\Router;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UriInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * This middleware increases SEO (search engine optimization) by preventing duplication
 * of content at different URLs including resolving sub-directory paths.
 *
 * The response status code is 302 if the permanent parameter is false (default),
 * and 301 if the redirection is permanent on redirection. If keep request method
 * parameter is true, response code 307, and if permanent is true status code is 308.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class PathMiddleware implements MiddlewareInterface
{
    /**
     * Slashes supported on browser when used.
     */
    public const SUB_FOLDER = __CLASS__.'::subFolder';

    /** @var array<string,string> */
    private array $uriSuffixes = [];

    /**
     * @param bool              $permanent         Whether the redirection is permanent
     * @param bool              $keepRequestMethod Whether redirect action should keep HTTP request method
     * @param array<int,string> $uriSuffixes       List of slashes to re-route, defaults to ['/']
     */
    public function __construct(
        private bool $permanent = false,
        private bool $keepRequestMethod = false,
        array $uriSuffixes = []
    ) {
        $this->permanent = $permanent;
        $this->keepRequestMethod = $keepRequestMethod;
        $this->uriSuffixes = empty($uriSuffixes) ? ['/' => '/'] : \array_combine($uriSuffixes, $uriSuffixes);
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestPath = ($requestUri = self::resolveUri($request))->getPath(); // Determine right request uri path.
        $response = $handler->handle($request);

        if (!empty($route = $request->getAttribute(Router::class, []))) {
            $this->uriSuffixes['/'] ??= '/';
            $routeEndTail = $this->uriSuffixes[$route['path'][-1]] ?? null;
            $requestEndTail = $this->uriSuffixes[$requestPath[-1]] ?? null;

            if ($requestEndTail === $requestPath || $routeEndTail === $requestEndTail) {
                return $response;
            }

            // Resolve request tail end to avoid conflicts and infinite redirection looping ...
            if (null === $requestEndTail && null !== $routeEndTail) {
                $requestPath .= $routeEndTail;
            } elseif (null === $routeEndTail && $requestEndTail) {
                $requestPath = \substr($requestPath, 0, -1);
            }

            $statusCode = $this->keepRequestMethod ? ($this->permanent ? 308 : 307) : ($this->permanent ? 301 : 302);
            $response = $response->withHeader('Location', (string) $requestUri->withPath($requestPath))->withStatus($statusCode);
        }

        return $response;
    }

    public static function resolveUri(ServerRequestInterface &$request): UriInterface
    {
        $requestUri = $request->getUri();
        $pathInfo = $request->getServerParams()['PATH_INFO'] ?? '';

        // Checks if the project is in a sub-directory, expect PATH_INFO in $_SERVER.
        if ('' !== $pathInfo && $pathInfo !== $requestUri->getPath()) {
            $request = $request->withAttribute(self::SUB_FOLDER, \substr($requestUri->getPath(), 0, -\strlen($pathInfo)));

            return $requestUri->withPath($pathInfo);
        }

        return $requestUri;
    }
}
