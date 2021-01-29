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

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Provides ability to invoke any handler and write it's response into ResponseInterface.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class RouteHandler implements RequestHandlerInterface
{
    public const CONTENT_TYPE = 'Content-Type';

    /** @var callable */
    private $callable;

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /**
     * @param callable                 $callable
     * @param ResponseFactoryInterface $responseFactory
     */
    public function __construct(callable $callable, ResponseFactoryInterface $responseFactory)
    {
        $this->callable        = $callable;
        $this->responseFactory = $responseFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        static $result;

        \ob_start(); // Start buffering response output

        $response = $this->responseFactory->createResponse()
            ->withHeader(self::CONTENT_TYPE, 'text/html; charset=utf-8');

        try {
            $result = ($this->callable)($request, $response);
        } catch (\Throwable $e) {
            \ob_get_clean();

            throw $e;
        }

        return $this->wrapResponse($response, $result, (string) \ob_get_clean());
    }

    /**
     * Convert endpoint result into valid PSR 7 response.
     * content-type fallback is "text/html; charset=utf-8".
     *
     * @param ResponseInterface $response initial pipeline response
     * @param mixed             $result   generated endpoint output
     * @param string            $output   buffer output
     *
     * @return ResponseInterface
     */
    private function wrapResponse(ResponseInterface $response, $result = null, string $output = ''): ResponseInterface
    {
        // Always return the response...
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (\is_array($result) || ($result instanceof \JsonSerializable || $result instanceof \stdClass)) {
            $result = \json_encode($result);
        }

        $response->getBody()->write((string) $result . $output);

        //Always detect response anf glue buffered output
        return $this->detectResponse($response);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    private function detectResponse(ResponseInterface $response): ResponseInterface
    {
        $responseBody = $response->getBody();
        $responseBody->rewind();

        $contents = $responseBody->getContents();

        if ($this->isJson($contents)) {
            return $response->withHeader(self::CONTENT_TYPE, 'application/json');
        }

        if ($this->isXml($contents)) {
            return $response->withHeader(self::CONTENT_TYPE, 'application/xml; charset=utf-8');
        }

        // Set content-type to plain text if string doesn't contain </html> tag.
        if (0 === \preg_match('/(.*)(<\/html[^>]*>)/i', $contents)) {
            return $response->withHeader(self::CONTENT_TYPE, 'text/plain; charset=utf-8');
        }

        return $response;
    }

    /**
     * @param string $contents
     *
     * @return bool
     */
    private function isJson(string $contents): bool
    {
        \json_decode($contents, true);

        return \JSON_ERROR_NONE === \json_last_error();
    }

    /**
     * @param string $contents
     *
     * @return bool
     */
    private function isXml(string $contents): bool
    {
        $previousValue = \libxml_use_internal_errors(true);
        $isXml         = \simplexml_load_string($contents);
        \libxml_use_internal_errors($previousValue);

        return false !== $isXml && false !== \strpos($contents, '<?xml');
    }
}
