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

namespace Flight\Routing;

use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use Throwable;

/**
 * Provides ability to invoke any handler and write it's response into ResponseInterface.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class RouteHandler implements RequestHandlerInterface
{
    private const CONTENT_TYPE = 'Content-Type';
    /** @var callable */
    private $callable;

    /** @var ResponseInterface */
    private $responseFactory;

    /**
     * @param callable          $callable
     * @param ResponseInterface $responseFactory
     */
    public function __construct(callable $callable, ResponseInterface $responseFactory)
    {
        $this->callable        = $callable;
        $this->responseFactory = $responseFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $outputLevel = \ob_get_level();
        \ob_start();

        $output = '';
        $result = null;

        $response = $this->responseFactory
            ->withHeader(self::CONTENT_TYPE, 'text/html; charset=utf-8');

        try {
            $result = ($this->callable)($request, $response);
        } catch (Throwable $e) {
            \ob_get_clean();

            throw $e;
        } finally {
            while (\ob_get_level() > $outputLevel + 1) {
                $output = \ob_get_clean() . $output;
            }
        }

        return $this->wrapResponse($response, $result, \ob_get_clean() . $output);
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
            if (!empty($output) && $result->getBody()->isWritable()) {
                $result->getBody()->write($output);
            }

            return $result;
        }

        if (\is_array($result) || $result instanceof JsonSerializable || $result instanceof stdClass) {
            $result = \json_encode($result);
        }

        $response->getBody()->write((string) $result);
        $response->getBody()->write($output);

        //Always detect response anf glue buffered output
        return $this->detectResponse($response);
    }

    private function detectResponse(ResponseInterface $response): ResponseInterface
    {
        if ($this->isJson($response->getBody())) {
            return $response->withHeader(self::CONTENT_TYPE, 'application/json');
        }

        if ($this->isXml($response->getBody())) {
            return $response->withHeader(self::CONTENT_TYPE, 'application/xml; charset=utf-8');
        }

        // Set content-type to plain text if string doesn't contain </html> tag.
        if (0 === \preg_match('/(.*)(<\/html[^>]*>)/i', (string) $response->getBody())) {
            return $response->withHeader(self::CONTENT_TYPE, 'text/plain; charset=utf-8');
        }

        return $response;
    }

    private function isJson(StreamInterface $stream): bool
    {
        if (!\function_exists('json_decode')) {
            return false;
        }
        $stream->rewind();

        \json_decode($stream->getContents(), true);

        return \JSON_ERROR_NONE === \json_last_error();
    }

    private function isXml(StreamInterface $stream): bool
    {
        if (!\function_exists('simplexml_load_string')) {
            return false;
        }
        $stream->rewind();

        $previousValue = \libxml_use_internal_errors(true);
        $isXml         = \simplexml_load_string($contents = $stream->getContents());
        \libxml_use_internal_errors($previousValue);

        return false !== $isXml && false !== \strpos($contents, '<?xml');
    }
}
