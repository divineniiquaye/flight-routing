<?php

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

namespace Flight\Routing\Middlewares;

use ArrayAccess;
use function in_array;
use InvalidArgumentException;
use function is_array;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UriRedirectMiddleware implements MiddlewareInterface
{
    /**
     * @var array|ArrayAccess
     */
    protected $redirects = [];

    /**
     * @var array
     */
    protected const DEFAUTLS = [
        'permanent' => true,
        'query'     => true,
    ];

    /**
     * @var bool
     */
    private $permanent = true;

    /**
     * @var bool
     */
    private $query = true;

    /**
     * @param array|ArrayAccess $redirects [from => to]
     * @param array             $options
     */
    public function __construct($redirects = [], array $options = self::DEFAUTLS)
    {
        if (!is_array($redirects) && !($redirects instanceof ArrayAccess)) {
            throw new InvalidArgumentException(
                'The redirects argument must be an array or implement the ArrayAccess interface'
            );
        }

        if (!empty($redirects)) {
            $this->redirects = $redirects;
        }

        $this->allowQueries($options['query']);
        $this->permanentRedirection($options['permanent']);
    }

    /**
     * Whether return a permanent redirect.
     *
     * @param bool $permanent
     *
     * @return UriRedirectMiddleware
     */
    public function permanentRedirection(bool $permanent = true): self
    {
        $this->permanent = $permanent;

        return $this;
    }

    /**
     * Whether include the query to search the url.
     *
     * @param bool $query
     *
     * @return UriRedirectMiddleware
     */
    public function allowQueries(bool $query = true): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Process a request and return a response.
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $handler->handle($request);

        $uri = $request->getUri()->getPath();

        if ($this->query && $query = $request->getUri()->getQuery() !== '') {
            $uri .= '?'.$query;
        }

        if (!isset($this->redirects[$uri])) {
            return $response;
        }

        return $response
            ->withStatus($this->determineResponseCode($request))
            ->withAddedHeader('Location', $this->redirects[$uri]);
    }

    /**
     * Determine the response code according with the method and the permanent config.
     *
     * @param ServerRequestInterface $request
     *
     * @return int
     */
    private function determineResponseCode(ServerRequestInterface $request): int
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'CONNECT', 'TRACE', 'OPTIONS'])) {
            return $this->permanent ? 301 : 302;
        }

        return $this->permanent ? 308 : 307;
    }
}
