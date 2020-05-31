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

use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use Throwable;

/**
 * Provides ability to invoke any handler and write it's response into ResponseInterface.
 */
final class CallableHandler implements RequestHandlerInterface
{
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
        $this->callable = $callable;
        $this->responseFactory = $responseFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable
     */
    public function handle(Request $request): Response
    {
        $outputLevel = ob_get_level();
        ob_start();

        $output = $result = null;

        $response = $this->responseFactory
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        try {
            $result = ($this->callable)($request, $response);
        } catch (Throwable $e) {
            ob_get_clean();

            throw $e;
        } finally {
            while (ob_get_level() > $outputLevel + 1) {
                $output = ob_get_clean().$output;
            }
        }

        return $this->wrapResponse($response, $result, ob_get_clean().$output);
    }

    /**
     * Convert endpoint result into valid PSR 7 response.
     * content-type fallback is "text/html; charset=utf-8".
     *
     * @param Response $response Initial pipeline response.
     * @param mixed    $result   Generated endpoint output.
     * @param string   $output   Buffer output.
     *
     * @return Response
     */
    private function wrapResponse(Response $response, $result = null, string $output = ''): Response
    {
        // Always return the response...
        if ($result instanceof Response) {
            if (!empty($output) && $result->getBody()->isWritable()) {
                $result->getBody()->write($output);
            }

            return $result;
        }

        if (is_array($result) || $result instanceof JsonSerializable || $result instanceof stdClass) {
            $response->getBody()->write(json_encode($result));
        } else {
            $response->getBody()->write((string) $result);
        }


        //Always detect response anf glue buffered output
        return $this->detectResponse($response, $output);
    }

    private function detectResponse(ResponseInterface $response, $output)
    {
        $response->getBody()->write($output);

        if ($this->isJson($response->getBody())) {
            return $response->withHeader('Content-Type', 'application/json');
        }

        if ($this->isXml($response->getBody())) {
            return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
        }

        // Set content-type to plain text if string doesn't contain </html> tag.
        if (!preg_match('/(.*)(<\/html[^>]*>)/i', (string) $response->getBody())) {
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        return $response;
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
        $isXml = simplexml_load_string($contents = $stream->getContents());
        libxml_use_internal_errors($previousValue);

        return false !== $isXml && false !== strpos($contents, '<?xml');
    }
}
